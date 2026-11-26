<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Legacy\LegacyBillingContactSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyBillingContactSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyPath = tempnam(sys_get_temp_dir(), 'legacy').'.sqlite';
        touch($this->legacyPath);
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => $this->legacyPath, 'foreign_key_constraints' => false]]);
        Schema::connection('legacy')->create('pppoe_customers', function ($table) {
            $table->integer('id')->primary();
            $table->string('username');
            $table->string('phone')->nullable();
            $table->integer('billing_day');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('legacy');
        @unlink($this->legacyPath);
        parent::tearDown();
    }

    private function legacy(int $id, string $username, ?string $phone, int $billingDay): void
    {
        DB::connection('legacy')->table('pppoe_customers')->insert(['id' => $id, 'username' => $username, 'phone' => $phone, 'billing_day' => $billingDay]);
    }

    private function sync(string $today = '2026-11-27'): array
    {
        return (new LegacyBillingContactSync(DB::connection('legacy'), CarbonImmutable::parse($today)))->sync();
    }

    public function test_fills_blank_whatsapp_and_moves_billing_day_and_start_to_the_next_occurrence(): void
    {
        $customer = Customer::factory()->create(['pppoe_username' => 'Budi@Desa', 'whatsapp' => '', 'billing_day' => 1, 'installed_at' => '2026-12-01']);
        $this->legacy(77, 'budi@desa ', '0812-3456-7890', 20);

        $report = $this->sync();

        $customer->refresh();
        $this->assertSame('6281234567890', $customer->whatsapp);
        $this->assertSame(20, $customer->billing_day);
        $this->assertSame('2026-12-20', $customer->installed_at->toDateString());
        $this->assertSame(1, $report['whatsapp_diisi']);
        $this->assertSame(1, $report['tanggal_tagihan_diubah']);
    }

    public function test_a_billing_day_later_this_month_starts_this_month_and_matching_is_by_username_not_id(): void
    {
        $customer = Customer::factory()->create(['id' => 5, 'pppoe_username' => 'andi', 'whatsapp' => '', 'billing_day' => 1, 'installed_at' => '2026-12-01']);
        $this->legacy(5, 'orang-lain', '081111111111', 3);
        $this->legacy(9, 'andi', null, 28);

        $this->sync();

        $customer->refresh();
        $this->assertSame('', $customer->whatsapp);
        $this->assertSame(28, $customer->billing_day);
        $this->assertSame('2026-11-28', $customer->installed_at->toDateString());
    }

    public function test_existing_whatsapp_is_kept_and_unchanged_billing_day_is_left_alone(): void
    {
        $customer = Customer::factory()->create(['pppoe_username' => 'siti', 'whatsapp' => '6285555555555', 'billing_day' => 11, 'installed_at' => '2026-12-11']);
        $this->legacy(1, 'siti', '089999999999', 11);

        $report = $this->sync();

        $customer->refresh();
        $this->assertSame('6285555555555', $customer->whatsapp);
        $this->assertSame('2026-12-11', $customer->installed_at->toDateString());
        $this->assertSame(1, $report['whatsapp_tetap_beda']);
        $this->assertSame(1, $report['tanggal_tagihan_sama']);
    }

    public function test_customers_with_invoices_duplicates_and_unknown_usernames_are_skipped(): void
    {
        $billed = Customer::factory()->create(['pppoe_username' => 'sudah-ditagih', 'billing_day' => 1, 'installed_at' => '2026-10-01']);
        Invoice::factory()->create(['customer_id' => $billed->id]);
        $twinA = Customer::factory()->create(['pppoe_username' => 'kembar', 'billing_day' => 1, 'whatsapp' => '']);
        Customer::factory()->create(['pppoe_username' => 'Kembar', 'billing_day' => 1, 'whatsapp' => '']);
        Customer::factory()->create(['pppoe_username' => 'tidak-ada']);
        $this->legacy(1, 'sudah-ditagih', null, 15);
        $this->legacy(2, 'kembar', '081234567891', 15);

        $report = $this->sync();

        $this->assertSame(1, $billed->fresh()->billing_day);
        $this->assertSame('', $twinA->fresh()->whatsapp);
        $this->assertSame(1, $report['dilewati_sudah_punya_invoice']);
        $this->assertSame(2, $report['dilewati_username_ganda']);
        $this->assertSame(1, $report['tidak_ada_di_database_lama']);
    }

    public function test_the_command_dry_run_changes_nothing_and_a_real_run_records_the_audit_log(): void
    {
        $customer = Customer::factory()->create(['pppoe_username' => 'budi', 'whatsapp' => '', 'billing_day' => 1, 'installed_at' => '2026-12-01']);
        $this->legacy(1, 'budi', '081234567890', 15);

        $this->artisan('legacy:sync-billing-contacts', ['path' => $this->legacyPath, '--dry-run' => true])->assertSuccessful();
        $this->assertSame('', $customer->fresh()->whatsapp);

        $this->artisan('legacy:sync-billing-contacts', ['path' => $this->legacyPath])->assertSuccessful();
        $this->assertSame('6281234567890', $customer->fresh()->whatsapp);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'Pelanggan', 'description' => 'Pelanggan diubah', 'subject_id' => $customer->id]);
    }
}
