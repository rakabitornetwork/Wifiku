<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Jobs\Billing\GenerateMonthlyInvoicesJob;
use App\Models\Invoice;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $status = InvoiceStatus::tryFrom((string) $request->query('status'));

        $invoices = Invoice::query()
            ->with('customer:id,customer_code,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
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
}
