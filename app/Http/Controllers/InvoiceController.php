<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Jobs\Billing\GenerateMonthlyInvoicesJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Toast;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $status = InvoiceStatus::tryFrom((string) $request->query('status'));

        $invoices = Invoice::query()
            ->with(['customer:id,customer_code,name', 'vpnAccount:id,name,username,portal_token'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%"))
                    ->orWhereHas('vpnAccount', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->tap(fn ($query) => $this->applySort($query, $this->sortColumns()))
            ->paginate($this->perPage(15))
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search, 'status' => $status?->value ?? ''],
        ]);
    }

    /**
     * Jalankan generator invoice harian sekarang (biasanya otomatis pukul 00:10).
     */
    public function generate(): RedirectResponse
    {
        GenerateMonthlyInvoicesJob::dispatch();

        Toast::success('Generate invoice siklus berjalan sedang diproses di antrean.');

        return back();
    }

    /**
     * @return array<string, string|Closure|Expression>
     */
    private function sortColumns(): array
    {
        return [
            'invoice_number' => 'invoice_number',
            'customer_code' => fn () => Customer::query()->select('customer_code')->whereColumn('customers.id', 'invoices.customer_id'),
            'customer' => fn () => Customer::query()->select('name')->whereColumn('customers.id', 'invoices.customer_id'),
            'period' => 'period_start',
            'due_date' => 'due_date',
            'total' => 'total',
            'remaining' => DB::raw('total - amount_paid'),
            'status' => 'status',
        ];
    }
}
