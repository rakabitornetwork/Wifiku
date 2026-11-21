<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Services\Settings\SettingService;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IsolirController extends Controller
{
    /**
     * Halaman publik tanpa login: hanya menampilkan data minimum yang dibutuhkan pelanggan.
     */
    public function __invoke(Customer $customer, SettingService $settings): Response
    {
        $customer->load('package:id,name');

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('due_date')
            ->get(['id', 'invoice_number', 'period_start', 'period_end', 'due_date', 'total', 'amount_paid', 'status'])
            ->map(fn (Invoice $invoice): array => [
                'invoice_number' => $invoice->invoice_number,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total' => $invoice->total,
                'outstanding' => $invoice->total - $invoice->amount_paid,
                'status' => $invoice->status->value,
                'is_overdue' => $invoice->due_date->isPast(),
            ]);

        $gateway = PaymentGateway::where('is_active', true)->orderBy('sort_order')->first();
        $csNumber = preg_replace('/\D+/', '', (string) $settings->get('company', 'cs_whatsapp'));

        return Inertia::render('Public/Isolir', [
            'company' => $settings->get('company', 'name', config('app.name')),
            'title' => $settings->get('isolir', 'title', 'Layanan Internet Anda Sedang Diisolir'),
            'message' => $settings->get('isolir', 'message'),
            'customer' => [
                'customer_code' => $customer->customer_code,
                'name' => $this->maskName($customer->name),
                'package' => $customer->package->name,
                'status' => $customer->status->value,
            ],
            'invoices' => $invoices,
            'outstanding' => $invoices->sum('outstanding'),
            'payment' => [
                'available' => $gateway !== null && ! empty($gateway->enabled_channels),
                'channels' => $gateway?->enabled_channels ?? [],
            ],
            'whatsappUrl' => $csNumber !== ''
                ? 'https://wa.me/'.$csNumber.'?text='.rawurlencode("Halo, saya pelanggan {$customer->customer_code}. Mohon bantuan terkait layanan internet saya.")
                : null,
        ]);
    }

    /**
     * "Budi Santoso" menjadi "Budi S***" agar nama lengkap tidak terbuka bagi yang menebak kode.
     */
    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts);

        return trim($first.' '.collect($parts)->map(fn (string $part): string => Str::substr($part, 0, 1).'***')->implode(' '));
    }
}
