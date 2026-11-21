<?php

namespace App\Services\Legacy;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memindahkan data aplikasi lama (router, paket, pelanggan PPPoE, tagihan, pembayaran) ke skema aplikasi ini.
 * ID lama dipertahankan sehingga relasi tetap utuh dan impor aman dijalankan ulang.
 * Pemanggil bertanggung jawab atas transaksi pada koneksi aplikasi ini.
 */
class LegacyDatabaseImporter
{
    private const PLACEHOLDER_PACKAGE = 'Tanpa Paket (migrasi)';

    /**
     * @var array<string, int>
     */
    private array $report = [];

    /**
     * @var array<int, int>
     */
    private array $placeholderPackages = [];

    public function __construct(
        private readonly ConnectionInterface $legacy,
        private readonly Encrypter $legacyEncrypter,
        private readonly string $legacyTimezone = 'UTC',
    ) {}

    /**
     * @return array<string, int>
     */
    public function import(): array
    {
        $this->importRouters();
        $this->importPackages();
        $this->importCustomers();
        $this->importInvoices();

        return $this->report;
    }

    private function importRouters(): void
    {
        foreach ($this->legacy->table('mikrotik_routers')->orderBy('id')->get() as $router) {
            $this->upsert('routers', $router->id, [
                'name' => $router->name,
                'host' => $router->host,
                'api_port' => $router->port,
                'use_ssl' => $router->use_ssl,
                'username' => $router->username,
                'password' => Crypt::encryptString($this->decrypt($router->password, "router #{$router->id}")),
                'isolir_profile' => 'ISOLIR',
                'status' => in_array($router->last_status, ['online', 'offline'], true) ? $router->last_status : 'unknown',
                'last_checked_at' => $this->timestamp($router->last_checked_at),
                'last_error' => $router->last_message === null ? null : Str::limit($router->last_message, 250, ''),
                'notes' => $router->notes,
                'created_at' => $this->timestamp($router->created_at),
                'updated_at' => $this->timestamp($router->updated_at),
            ], 'routers');
        }
    }

    private function importPackages(): void
    {
        foreach ($this->legacy->table('subscription_packages')->orderBy('id')->get() as $package) {
            $this->upsert('packages', $package->id, [
                'router_id' => $package->mikrotik_router_id,
                'name' => $package->name,
                'mikrotik_profile' => $package->mikrotik_profile ?: 'default',
                'rate_limit' => null,
                'price' => $package->price,
                'is_active' => $package->is_active,
                'created_at' => $this->timestamp($package->created_at),
                'updated_at' => $this->timestamp($package->updated_at),
            ], 'packages');
        }
    }

    private function importCustomers(): void
    {
        $existingCodes = DB::table('customers')->pluck('customer_code', 'id');
        $sequences = [];

        foreach ($existingCodes as $code) {
            $sequences[substr($code, 0, -4)] = max($sequences[substr($code, 0, -4)] ?? 0, (int) substr($code, -4));
        }

        foreach ($this->legacy->table('pppoe_customers')->orderBy('id')->get() as $customer) {
            $prefix = 'TT-'.Carbon::parse($customer->start_date ?? $customer->created_at)->format('ym').'-';
            $code = $existingCodes[$customer->id] ?? $prefix.str_pad((string) ($sequences[$prefix] = ($sequences[$prefix] ?? 0) + 1), 4, '0', STR_PAD_LEFT);

            $status = match (true) {
                ! $customer->is_active, $customer->status === 'disabled' => 'inactive',
                $customer->status === 'isolated' => 'isolated',
                default => 'active',
            };

            $this->upsert('customers', $customer->id, [
                'customer_code' => $code,
                'router_id' => $customer->mikrotik_router_id,
                'package_id' => $customer->subscription_package_id ?? $this->placeholderPackage($customer->mikrotik_router_id, $customer->service_profile),
                'odp_id' => null,
                'odp_port' => null,
                'nik' => null,
                'name' => $customer->name,
                'whatsapp' => $this->normalizePhone($customer->phone),
                'address' => $customer->address,
                'village' => null,
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
                'pppoe_username' => $customer->username,
                'pppoe_password' => Crypt::encryptString($this->decrypt($customer->password, "pelanggan #{$customer->id}")),
                'billing_cycle' => 'fixed',
                'billing_day' => min(28, max(1, (int) ($customer->billing_day ?: 1))),
                'due_days' => 7,
                'installed_at' => $this->date($customer->start_date ?? $customer->created_at),
                'status' => $status,
                'promise_to_pay_until' => $this->date($customer->grace_until),
                'isolated_at' => $status === 'isolated' ? $this->timestamp($customer->updated_at) : null,
                'router_synced_at' => $this->timestamp($customer->last_synced_at),
                'created_at' => $this->timestamp($customer->created_at),
                'updated_at' => $this->timestamp($customer->updated_at),
            ], 'customers');

            $this->count('customers_tanpa_whatsapp', blank($customer->phone) ? 1 : 0);
            $this->count('customers_tanpa_paket', $customer->subscription_package_id === null ? 1 : 0);
            $this->count('customers_bypass_isolir_tidak_dibawa', $customer->overdue_action === 'bypass' ? 1 : 0);
        }
    }

    private function importInvoices(): void
    {
        $paidByInvoice = $this->legacy->table('payments')->selectRaw('invoice_id, sum(amount) as paid')->groupBy('invoice_id')->pluck('paid', 'invoice_id');
        $customerByInvoice = [];
        $keptInvoices = [];
        $usedKeys = [];

        $invoices = $this->legacy->table('invoices')->orderBy('id')->get()->groupBy(fn ($invoice) => $invoice->pppoe_customer_id.'|'.$this->date($invoice->period_start));

        foreach ($invoices as $group) {
            // Unique (customer, period_start) di aplikasi ini: tagihan aktif didahulukan, void duplikat dibuang.
            $ordered = $group->sortBy(fn ($invoice) => [$invoice->status === 'void' ? 1 : 0, $invoice->id])->values();
            $activeCount = 0;

            foreach ($ordered as $position => $invoice) {
                if ($invoice->status === 'void' && $position > 0) {
                    $this->count('invoice_void_duplikat_dilewati');

                    continue;
                }

                $periodStart = Carbon::parse($this->date($invoice->period_start));
                $periodEnd = Carbon::parse($this->date($invoice->period_end));
                $shifted = false;

                if ($invoice->status !== 'void' && $activeCount++ > 0) {
                    $shifted = true;
                }

                // Aplikasi ini mewajibkan unique (customer, period_start) dan (customer, period_end).
                while (isset($usedKeys['start|'.$invoice->pppoe_customer_id.'|'.$periodStart->toDateString()]) || isset($usedKeys['end|'.$invoice->pppoe_customer_id.'|'.$periodEnd->toDateString()])) {
                    $periodStart->addDay();
                    $periodEnd->addDay();
                    $shifted = true;
                }

                $usedKeys['start|'.$invoice->pppoe_customer_id.'|'.$periodStart->toDateString()] = true;
                $usedKeys['end|'.$invoice->pppoe_customer_id.'|'.$periodEnd->toDateString()] = true;
                $this->storeInvoice($invoice, $periodStart, $periodEnd, $shifted, (int) ($paidByInvoice[$invoice->id] ?? 0));
                $customerByInvoice[$invoice->id] = $invoice->pppoe_customer_id;
                $keptInvoices[$invoice->id] = true;
            }
        }

        foreach ($this->legacy->table('payments')->orderBy('id')->get() as $payment) {
            if (! isset($keptInvoices[$payment->invoice_id])) {
                $this->count('pembayaran_dilewati');

                continue;
            }

            $this->upsert('payments', $payment->id, [
                'invoice_id' => $payment->invoice_id,
                'customer_id' => $customerByInvoice[$payment->invoice_id],
                'payment_gateway_id' => null,
                'method' => match ($payment->method) {
                    'cash' => 'cash',
                    'duitku' => 'gateway',
                    default => 'transfer',
                },
                'channel' => in_array($payment->method, ['duitku', 'other'], true) ? $payment->method : null,
                'merchant_ref' => "LEGACY-{$payment->id}",
                'gateway_ref' => $payment->method === 'duitku' ? $payment->reference : null,
                'amount' => $payment->amount,
                'fee' => 0,
                'status' => 'paid',
                'paid_at' => $this->timestamp($payment->paid_at),
                'received_by' => null,
                'created_at' => $this->timestamp($payment->created_at),
                'updated_at' => $this->timestamp($payment->updated_at),
            ], 'payments');
        }
    }

    private function storeInvoice(object $invoice, Carbon $periodStart, Carbon $periodEnd, bool $shifted, int $amountPaid): void
    {
        $this->count('invoice_periode_digeser', $shifted ? 1 : 0);

        $status = match (true) {
            $invoice->status === 'void' => 'cancelled',
            $invoice->status === 'paid' => 'paid',
            $amountPaid > 0 => 'partial',
            default => 'unpaid',
        };

        $notes = collect([$invoice->notes, in_array($invoice->type, ['adjustment', 'vpn_router', 'multi_month'], true) ? "[Migrasi] tipe lama: {$invoice->type}" : null, $shifted ? '[Migrasi] periode mulai digeser agar unik' : null])->filter()->implode("\n");

        $this->upsert('invoices', $invoice->id, [
            'invoice_number' => $invoice->number,
            'customer_id' => $invoice->pppoe_customer_id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'issue_date' => Carbon::parse($this->timestamp($invoice->created_at))->toDateString(),
            'due_date' => $this->date($invoice->due_date),
            'is_prorated' => $invoice->type === 'prorata',
            'prorata_days' => null,
            'period_days' => Carbon::parse($this->date($invoice->period_start))->diffInDays(Carbon::parse($this->date($invoice->period_end))),
            'subtotal' => $invoice->amount,
            'discount' => $invoice->discount,
            'tax' => 0,
            'total' => $invoice->total,
            'amount_paid' => $amountPaid,
            'status' => $status,
            'paid_at' => $this->timestamp($invoice->paid_at),
            'reminder_sent_at' => null,
            'notes' => $notes === '' ? null : $notes,
            'created_at' => $this->timestamp($invoice->created_at),
            'updated_at' => $this->timestamp($invoice->updated_at),
        ], 'invoices');

        DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id,
            'type' => match ($invoice->type) {
                'prorata' => 'prorata',
                'adjustment' => 'adjustment',
                default => 'package',
            },
            'description' => ($invoice->package_name ?: Str::headline($invoice->type)).($invoice->billing_months > 1 ? " ({$invoice->billing_months} bulan)" : ''),
            'quantity' => 1,
            'unit_price' => $invoice->amount,
            'amount' => $invoice->amount,
            'created_at' => $this->timestamp($invoice->created_at),
            'updated_at' => $this->timestamp($invoice->updated_at),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsert(string $table, int $id, array $row, string $label): void
    {
        DB::table($table)->updateOrInsert(['id' => $id], $row);
        $this->count($label);
    }

    private function count(string $key, int $by = 1): void
    {
        $this->report[$key] = ($this->report[$key] ?? 0) + $by;
    }

    private function placeholderPackage(int $routerId, ?string $profile): int
    {
        return $this->placeholderPackages[$routerId] ??= DB::table('packages')->where('router_id', $routerId)->where('name', self::PLACEHOLDER_PACKAGE)->value('id')
            ?? DB::table('packages')->insertGetId([
                'router_id' => $routerId,
                'name' => self::PLACEHOLDER_PACKAGE,
                'mikrotik_profile' => $profile ?: 'default',
                'price' => 0,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function decrypt(string $payload, string $context): string
    {
        try {
            return $this->legacyEncrypter->decryptString($payload);
        } catch (DecryptException $exception) {
            throw new RuntimeException("Gagal mendekripsi {$context}: APP_KEY lama tidak cocok.", previous: $exception);
        }
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            $digits === '' => '',
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    private function date(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }

    private function timestamp(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, $this->legacyTimezone)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
