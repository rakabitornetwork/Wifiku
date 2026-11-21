<?php

namespace Tests\Feature\Services\Legacy;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Services\Legacy\LegacyDatabaseImporter;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LegacyDatabaseImporterTest extends TestCase
{
    use RefreshDatabase;

    private Encrypter $legacyEncrypter;

    private Connection $legacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyEncrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => false]]);
        $this->legacy = DB::connection('legacy');
        $this->createLegacySchema();
    }

    private function createLegacySchema(): void
    {
        $schema = $this->legacy->getSchemaBuilder();

        $schema->create('mikrotik_routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port');
            $table->string('username');
            $table->text('password');
            $table->boolean('use_ssl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_message')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price');
            $table->string('mikrotik_profile')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('mikrotik_router_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('pppoe_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mikrotik_router_id');
            $table->unsignedBigInteger('subscription_package_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('username');
            $table->text('password');
            $table->string('service_profile')->nullable();
            $table->string('overdue_action')->default('isolir');
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_synced_at')->nullable();
            $table->date('start_date')->nullable();
            $table->integer('billing_day')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('grace_until')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->unsignedBigInteger('pppoe_customer_id');
            $table->string('type')->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->integer('amount');
            $table->integer('discount')->default(0);
            $table->integer('total');
            $table->string('status');
            $table->dateTime('paid_at')->nullable();
            $table->string('package_name')->nullable();
            $table->text('notes')->nullable();
            $table->integer('billing_months')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->integer('amount');
            $table->string('method');
            $table->dateTime('paid_at');
            $table->string('reference')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    private function seedLegacy(): void
    {
        $secret = fn (string $plain) => $this->legacyEncrypter->encryptString($plain);
        $now = '2026-08-02 17:09:54';

        $this->legacy->table('mikrotik_routers')->insert(['id' => 1, 'name' => 'POP A', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'api', 'password' => $secret('router-pw'), 'last_status' => 'online', 'created_at' => $now, 'updated_at' => $now]);
        $this->legacy->table('subscription_packages')->insert(['id' => 5, 'name' => '10 Mbps', 'price' => 150000, 'mikrotik_profile' => '10M', 'mikrotik_router_id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->insertRows('pppoe_customers', [
            ['id' => 1, 'mikrotik_router_id' => 1, 'subscription_package_id' => 5, 'name' => 'Budi', 'phone' => '0812-3456-7890', 'username' => 'budi', 'password' => $secret('ppp-pw'), 'start_date' => '2026-07-03', 'billing_day' => 3, 'status' => 'isolated', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'mikrotik_router_id' => 1, 'subscription_package_id' => null, 'name' => 'Siti', 'phone' => null, 'username' => 'siti', 'password' => $secret('x'), 'service_profile' => 'legacy-profile', 'start_date' => '2026-07-10', 'billing_day' => 31, 'status' => 'disabled', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->insertRows('invoices', [
            ['id' => 1, 'number' => 'INV/2026/08/0001', 'pppoe_customer_id' => 1, 'type' => 'prorata', 'period_start' => '2026-07-03', 'period_end' => '2026-08-03', 'due_date' => '2026-08-03', 'amount' => 150000, 'total' => 150000, 'status' => 'paid', 'paid_at' => '2026-08-03 03:00:00', 'package_name' => '10 Mbps', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'number' => 'INV/2026/08/0002', 'pppoe_customer_id' => 1, 'type' => 'monthly', 'period_start' => '2026-07-03', 'period_end' => '2026-08-03', 'due_date' => '2026-08-03', 'amount' => 150000, 'total' => 150000, 'status' => 'void', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'number' => 'INV/2026/09/0001', 'pppoe_customer_id' => 1, 'type' => 'monthly', 'period_start' => '2026-08-03', 'period_end' => '2026-09-03', 'due_date' => '2026-09-03', 'amount' => 150000, 'discount' => 10000, 'total' => 140000, 'status' => 'unpaid', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'number' => 'INV/2026/09/0002', 'pppoe_customer_id' => 1, 'type' => 'adjustment', 'period_start' => '2026-08-03', 'period_end' => '2026-09-03', 'due_date' => '2026-09-03', 'amount' => 5000, 'total' => 5000, 'status' => 'unpaid', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->insertRows('payments', [
            ['id' => 1, 'invoice_id' => 1, 'amount' => 150000, 'method' => 'duitku', 'paid_at' => '2026-08-03 03:00:00', 'reference' => 'D123', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'invoice_id' => 3, 'amount' => 50000, 'method' => 'cash', 'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $this->legacy->table($table)->insert($row);
        }
    }

    private function importer(?Encrypter $encrypter = null): LegacyDatabaseImporter
    {
        return new LegacyDatabaseImporter($this->legacy, $encrypter ?? $this->legacyEncrypter);
    }

    public function test_imports_data_keeping_ids_and_reencrypting_secrets(): void
    {
        $this->seedLegacy();

        $this->importer()->import();

        $router = Router::findOrFail(1);
        $this->assertSame('router-pw', $router->password);
        $this->assertNotSame('router-pw', $router->getRawOriginal('password'));
        $this->assertSame(8728, $router->api_port);

        $budi = Customer::findOrFail(1);
        $this->assertSame('ppp-pw', $budi->pppoe_password);
        $this->assertSame('6281234567890', $budi->whatsapp);
        $this->assertSame('isolated', $budi->status->value);
        $this->assertSame('2026-07-03', $budi->installed_at->toDateString());
        $this->assertSame('TT-2607-0001', $budi->customer_code);
        $this->assertSame('TT-2607-0002', Customer::findOrFail(2)->customer_code);
    }

    public function test_customer_without_phone_package_or_active_flag_is_handled(): void
    {
        $this->seedLegacy();

        $report = $this->importer()->import();

        $siti = Customer::findOrFail(2);
        $this->assertSame('', $siti->whatsapp);
        $this->assertSame('inactive', $siti->status->value);
        $this->assertSame(28, $siti->billing_day);
        $this->assertSame('Tanpa Paket (migrasi)', $siti->package->name);
        $this->assertFalse($siti->package->is_active);
        $this->assertSame(1, $report['customers_tanpa_whatsapp']);
        $this->assertSame(1, $report['customers_tanpa_paket']);
    }

    public function test_invoices_resolve_period_collisions_and_map_status_and_payments(): void
    {
        $this->seedLegacy();

        $report = $this->importer()->import();

        $this->assertNull(Invoice::find(2), 'void duplikat dibuang');
        $this->assertSame(1, $report['invoice_void_duplikat_dilewati']);
        $this->assertSame('paid', Invoice::findOrFail(1)->status->value);
        $this->assertSame(150000, Invoice::findOrFail(1)->amount_paid);
        $this->assertTrue(Invoice::findOrFail(1)->is_prorated);

        $partial = Invoice::findOrFail(3);
        $this->assertSame('partial', $partial->status->value);
        $this->assertSame(50000, $partial->amount_paid);
        $this->assertSame(10000, $partial->discount);

        $shifted = Invoice::findOrFail(4);
        $this->assertSame('2026-08-04', $shifted->period_start->toDateString());
        $this->assertSame('2026-09-04', $shifted->period_end->toDateString());
        $this->assertSame(1, $report['invoice_periode_digeser']);
        $this->assertStringContainsString('digeser', $shifted->notes);
        $this->assertSame(1, $shifted->items()->count());
        $this->assertSame('adjustment', $shifted->items()->first()->type->value);

        $this->assertSame('gateway', Payment::findOrFail(1)->method->value);
        $this->assertSame('D123', Payment::findOrFail(1)->gateway_ref);
        $this->assertSame('LEGACY-2', Payment::findOrFail(2)->merchant_ref);
        $this->assertSame(3, $report['invoices']);
    }

    public function test_timestamps_are_converted_from_legacy_timezone_to_app_timezone(): void
    {
        $this->seedLegacy();

        $this->importer()->import();

        $this->assertSame('2026-08-03', Invoice::findOrFail(1)->issue_date->toDateString());
        $this->assertSame('2026-08-03 00:09:54', DB::table('invoices')->where('id', 1)->value('created_at'));
    }

    public function test_import_is_idempotent(): void
    {
        $this->seedLegacy();

        $this->importer()->import();
        $this->importer()->import();

        $this->assertSame(1, Router::count());
        $this->assertSame(2, Customer::count());
        $this->assertSame(3, Invoice::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(3, DB::table('invoice_items')->count());
        $this->assertSame(1, DB::table('packages')->where('name', 'Tanpa Paket (migrasi)')->count());
        $this->assertSame('TT-2607-0001', Customer::findOrFail(1)->customer_code);
    }

    public function test_secrets_encrypted_with_a_previous_legacy_key_are_decrypted(): void
    {
        $this->seedLegacy();
        $previous = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $this->legacy->table('pppoe_customers')->where('id', 2)->update(['password' => $previous->encryptString('lama-pw')]);
        $current = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $current->previousKeys([$this->legacyEncrypter->getKey(), $previous->getKey()]);

        $this->importer($current)->import();

        $this->assertSame('lama-pw', Customer::findOrFail(2)->pppoe_password);
        $this->assertSame('ppp-pw', Customer::findOrFail(1)->pppoe_password);
    }

    public function test_wrong_legacy_key_aborts_with_clear_message(): void
    {
        $this->seedLegacy();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY lama tidak cocok');

        $this->importer(new Encrypter(random_bytes(32), 'AES-256-CBC'))->import();
    }
}
