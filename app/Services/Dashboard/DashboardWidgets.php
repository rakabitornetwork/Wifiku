<?php

namespace App\Services\Dashboard;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OutageStatus;
use App\Enums\WhatsappMessageStatus;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Reports\ProfitLossReport;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

/**
 * Data widget informasi dashboard admin. Setiap widget hanya dihitung dan dikirim bila pengguna punya
 * permission yang relevan, sehingga angka keuangan tidak sampai ke browser teknisi.
 */
class DashboardWidgets
{
    /** Batas redaman ONT (dBm), sama dengan halaman Monitoring. */
    public const WARNING_DBM = -25;

    public const CRITICAL_DBM = -27;

    public function __construct(private readonly ProfitLossReport $profitLoss) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $widgets = [];

        if ($user->can(Permissions::REPORTS_VIEW)) {
            $widgets['finance'] = $this->finance($now);
        }

        if ($user->can(Permissions::INVOICES_VIEW)) {
            $widgets['collection'] = $this->collection($now);
            $widgets['overdue'] = $this->overdue($now);
        }

        if ($user->can(Permissions::ASSETS_MANAGE)) {
            $widgets['weak_signal'] = $this->weakSignal();
        }

        $attention = $this->attention($user, $now);

        if ($attention !== []) {
            $widgets['attention'] = $attention;
        }

        if ($user->can(Permissions::AUDIT_VIEW)) {
            $widgets['activity'] = $this->activity();
        }

        return $widgets;
    }

    /**
     * Pendapatan dan pengeluaran 6 bulan terakhir beserta perbandingan laba bulan ini dengan bulan lalu.
     *
     * @return array{trend: list<array{month: string, revenue: int, expenses: int, net_profit: int}>, current: array{revenue: int, expenses: int, net_profit: int}, revenue_change: float|null}
     */
    private function finance(CarbonImmutable $now): array
    {
        $trend = $this->profitLoss->trend($now, 6);
        $current = $trend[count($trend) - 1];
        $previous = $trend[count($trend) - 2] ?? null;

        return [
            'trend' => $trend,
            'current' => ['revenue' => $current['revenue'], 'expenses' => $current['expenses'], 'net_profit' => $current['net_profit']],
            'revenue_change' => $previous !== null && $previous['revenue'] > 0 ? round(($current['revenue'] - $previous['revenue']) / $previous['revenue'] * 100, 1) : null,
        ];
    }

    /**
     * Seberapa banyak tagihan yang terbit bulan ini sudah tertagih.
     *
     * @return array{billed: int, collected: int, invoices: int, paid_invoices: int, percent: int}
     */
    private function collection(CarbonImmutable $now): array
    {
        $rows = Invoice::query()
            ->whereNotNull('customer_id')
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->whereBetween('issue_date', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
            ->selectRaw('count(*) as invoices, coalesce(sum(total), 0) as billed, coalesce(sum(amount_paid), 0) as collected')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as paid_invoices', [InvoiceStatus::Paid->value])
            ->first();

        $billed = (int) $rows->billed;
        $collected = min((int) $rows->collected, $billed);

        return [
            'billed' => $billed,
            'collected' => $collected,
            'invoices' => (int) $rows->invoices,
            'paid_invoices' => (int) $rows->paid_invoices,
            'percent' => $billed > 0 ? (int) round($collected / $billed * 100) : 0,
        ];
    }

    /**
     * Tagihan lewat jatuh tempo yang paling lama menunggak.
     *
     * @return array{count: int, total: int, items: list<array{invoice_number: string, customer: string, customer_code: string, outstanding: int, days_late: int}>}
     */
    private function overdue(CarbonImmutable $now): array
    {
        $query = fn () => Invoice::query()
            ->whereNotNull('customer_id')
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->whereDate('due_date', '<', $now->toDateString());

        $summary = $query()->selectRaw('count(*) as total, coalesce(sum(total - amount_paid), 0) as outstanding')->first();

        $items = $query()
            ->with('customer:id,name,customer_code')
            ->orderBy('due_date')
            ->limit(6)
            ->get(['id', 'invoice_number', 'customer_id', 'due_date', 'total', 'amount_paid'])
            ->map(fn (Invoice $invoice): array => [
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? '-',
                'customer_code' => $invoice->customer?->customer_code ?? '-',
                'outstanding' => max(0, $invoice->total - $invoice->amount_paid),
                'days_late' => (int) $invoice->due_date->startOfDay()->diffInDays($now->startOfDay()),
            ])->all();

        return ['count' => (int) $summary->total, 'total' => (int) $summary->outstanding, 'items' => $items];
    }

    /**
     * ONT terpasang dengan redaman terburuk (di bawah batas peringatan).
     *
     * @return array{count: int, critical: int, items: list<array{customer: string, serial_number: string, rx_power: float, level: 'warning'|'critical'}>}
     */
    private function weakSignal(): array
    {
        $weak = fn () => Asset::query()
            ->where('type', AssetType::Ont)
            ->where('status', AssetStatus::Installed)
            ->whereNotNull('last_rx_power')
            ->where('last_rx_power', '<', self::WARNING_DBM);

        $items = $weak()
            ->with('customer:id,name')
            ->orderBy('last_rx_power')
            ->limit(6)
            ->get(['id', 'customer_id', 'serial_number', 'last_rx_power'])
            ->map(fn (Asset $asset): array => [
                'customer' => $asset->customer?->name ?? 'Belum terhubung ke pelanggan',
                'serial_number' => $asset->serial_number,
                'rx_power' => (float) $asset->last_rx_power,
                'level' => (float) $asset->last_rx_power < self::CRITICAL_DBM ? 'critical' : 'warning',
            ])->all();

        return [
            'count' => $weak()->count(),
            'critical' => $weak()->where('last_rx_power', '<', self::CRITICAL_DBM)->count(),
            'items' => $items,
        ];
    }

    /**
     * Hal-hal yang menunggu tindakan; tiap butir hanya muncul untuk yang berhak menanganinya.
     *
     * @return list<array{key: string, label: string, value: int, tone: 'red'|'amber'|'blue'|'green', href: string, hint: string|null}>
     */
    private function attention(User $user, CarbonImmutable $now): array
    {
        $items = [];

        if ($user->can(Permissions::OUTAGES_MANAGE)) {
            $open = Outage::query()->where('status', OutageStatus::Open);
            $latest = (clone $open)->latest()->value('title');
            $items[] = ['key' => 'outages', 'label' => 'Gangguan aktif', 'value' => (clone $open)->count(), 'tone' => 'red', 'href' => '/outages', 'hint' => $latest];
        }

        if ($user->can(Permissions::CUSTOMERS_VIEW)) {
            $items[] = [
                'key' => 'promises',
                'label' => 'Janji bayar jatuh tempo',
                'value' => Customer::query()->where('status', CustomerStatus::Active)->whereNotNull('promise_to_pay_until')->whereDate('promise_to_pay_until', '<=', $now->toDateString())->count(),
                'tone' => 'amber',
                'href' => '/customers',
                'hint' => 'Hari ini atau terlewat, belum diisolir',
            ];
            $items[] = [
                'key' => 'new_customers',
                'label' => 'Pemasangan baru bulan ini',
                'value' => Customer::query()->whereBetween('installed_at', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])->count(),
                'tone' => 'green',
                'href' => '/customers',
                'hint' => null,
            ];
        }

        if ($user->can(Permissions::SETTINGS_MANAGE)) {
            $items[] = [
                'key' => 'whatsapp',
                'label' => 'Pesan WhatsApp gagal (24 jam)',
                'value' => WhatsappMessage::query()->where('status', WhatsappMessageStatus::Failed)->where('created_at', '>=', $now->subDay())->count(),
                'tone' => 'blue',
                'href' => '/settings/integrations',
                'hint' => null,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{description: string, causer: string, log: string|null, at: string}>
     */
    private function activity(): array
    {
        return Activity::query()
            ->with('causer')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $activity): array => [
                'description' => (string) $activity->description,
                'causer' => $activity->causer?->name ?? 'Sistem',
                'log' => $activity->log_name,
                'at' => $activity->created_at->toIso8601String(),
            ])->all();
    }
}
