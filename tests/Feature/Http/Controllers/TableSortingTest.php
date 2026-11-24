<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class TableSortingTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * Halaman dan kunci `sort` yang diterima judul tabelnya.
     *
     * @return array<string, array{string, string}>
     */
    public static function sortableColumns(): array
    {
        $pages = [
            '/hotspot/profiles' => ['name', 'router', 'mikrotik_profile', 'price', 'validity', 'vouchers', 'status'],
            '/hotspot/resellers' => ['name', 'phone', 'discount', 'batches', 'status'],
            '/invoices' => ['invoice_number', 'customer', 'period', 'due_date', 'total', 'remaining', 'status'],
            '/monitoring' => ['serial_number', 'model', 'customer', 'rx_power', 'last_seen'],
            '/expenses' => ['date', 'category', 'description', 'router', 'amount', 'creator'],
            '/outages' => ['odp', 'title', 'notified', 'started', 'resolved', 'status'],
            '/assets' => ['serial_number', 'type', 'mac_address', 'ownership', 'customer', 'rx_power', 'status'],
            '/packages' => ['name', 'mikrotik_profile', 'rate_limit', 'router', 'price', 'customers', 'status'],
            '/hotspot/batches' => ['batch_code', 'profile', 'router', 'reseller', 'vouchers', 'used', 'created', 'status'],
            '/routers' => ['name', 'host', 'status', 'customers', 'packages', 'hotspot_profiles', 'last_checked'],
            '/users' => ['name', 'email', 'phone', 'role', 'last_login', 'status'],
        ];

        $cases = [];

        foreach ($pages as $url => $columns) {
            foreach ($columns as $column) {
                $cases["{$url} {$column}"] = [$url, $column];
            }
        }

        return $cases;
    }

    #[DataProvider('sortableColumns')]
    public function test_every_sortable_column_returns_the_page_in_both_directions(string $url, string $column): void
    {
        $owner = $this->userWithRole('Owner');

        foreach (['asc', 'desc'] as $direction) {
            $this->actingAs($owner)->get("{$url}?sort={$column}&direction={$direction}")->assertOk();
        }
    }

    public function test_unknown_sort_key_is_ignored(): void
    {
        $this->actingAs($this->userWithRole('Owner'))->get('/packages?sort=password;drop')->assertOk();
    }

    public function test_voucher_batch_detail_vouchers_can_be_sorted(): void
    {
        $batch = VoucherBatch::factory()->create();

        foreach (['code', 'price', 'first_login', 'expires', 'status'] as $column) {
            $this->actingAs($this->userWithRole('Owner'))->get("/hotspot/batches/{$batch->id}?sort={$column}&direction=desc")->assertOk();
        }
    }

    public function test_invoices_are_sorted_by_remaining_balance(): void
    {
        $small = Invoice::factory()->for(Customer::factory())->create(['total' => 100000, 'amount_paid' => 90000]);
        $large = Invoice::factory()->for(Customer::factory())->create(['total' => 100000, 'amount_paid' => 0]);

        $this->actingAs($this->userWithRole('Owner'))
            ->get('/invoices?sort=remaining&direction=desc')
            ->assertInertia(fn ($page) => $page->where('invoices.data.0.id', $large->id)->where('invoices.data.1.id', $small->id));
    }
}
