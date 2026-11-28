<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Jobs\Network\RemovePppoeSecretJob;
use App\Models\Customer;
use App\Models\Odp;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Router $router, Package $package, array $overrides = []): array
    {
        return [
            'router_id' => $router->id,
            'package_id' => $package->id,
            'name' => 'Budi Santoso',
            'whatsapp' => '081234567890',
            'pppoe_username' => 'budi.santoso',
            'pppoe_password' => 'rahasia-pppoe',
            'billing_cycle' => 'fixed',
            'billing_day' => 5,
            'due_days' => 7,
            'installed_at' => '2026-11-15',
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_lists_customers_with_router_package_and_odp_loaded(): void
    {
        Customer::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/customers')->assertInertia(
            fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 2)
                ->has('customers.data.0.router')
                ->has('customers.data.0.package')
                ->missing('customers.data.0.pppoe_password'),
        );
    }

    public function test_filters_customers_by_status_router_and_search(): void
    {
        $router = Router::factory()->create();
        Customer::factory()->for($router)->create(['name' => 'Siti Aminah', 'status' => CustomerStatus::Isolated]);
        Customer::factory()->for($router)->create(['name' => 'Siti Rahma']);
        Customer::factory()->create(['name' => 'Siti Lain']);

        $this->actingAs($this->admin())->get("/customers?search=Siti&status=isolated&router_id={$router->id}")->assertInertia(
            fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Siti Aminah'),
        );
    }

    public function test_creates_customer_with_generated_code_and_encrypted_password(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package))->assertRedirect('/customers');

        $customer = Customer::sole();
        $this->assertSame('TT-'.now()->format('ym').'-0001', $customer->customer_code);
        $this->assertSame('rahasia-pppoe', $customer->pppoe_password);
        $this->assertNotSame('rahasia-pppoe', DB::table('customers')->value('pppoe_password'));
    }

    public function test_generated_codes_increment_per_month(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();

        $admin = $this->admin();

        $this->actingAs($admin)->post('/customers', $this->validPayload($router, $package));
        $this->actingAs($admin)->post('/customers', $this->validPayload($router, $package, ['pppoe_username' => 'siti.aminah']));

        $this->assertSame(
            ['TT-'.now()->format('ym').'-0001', 'TT-'.now()->format('ym').'-0002'],
            Customer::orderBy('id')->pluck('customer_code')->all(),
        );
    }

    #[DataProvider('whatsappFormats')]
    public function test_normalizes_whatsapp_number_to_62_format(string $input): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, Package::factory()->create(), ['whatsapp' => $input]));

        $this->assertSame('6281234567890', Customer::sole()->whatsapp);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function whatsappFormats(): array
    {
        return [
            'leading zero' => ['081234567890'],
            'country code with plus and spaces' => ['+62 812-3456-7890'],
            'already normalized' => ['6281234567890'],
        ];
    }

    public function test_anniversary_cycle_uses_install_day_up_to_31(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, [
            'billing_cycle' => 'anniversary', 'billing_day' => null, 'installed_at' => '2026-10-31',
        ]));

        $this->assertSame(31, Customer::sole()->billing_day);
    }

    public function test_fixed_cycle_accepts_billing_day_up_to_31_but_not_32(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, ['billing_day' => 32]))
            ->assertSessionHasErrors('billing_day');

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, ['billing_day' => 31]))
            ->assertSessionHasNoErrors();

        $this->assertSame(31, Customer::sole()->billing_day);
    }

    public function test_fixed_cycle_requires_billing_day(): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())
            ->post('/customers', $this->validPayload($router, Package::factory()->create(), ['billing_day' => null]))
            ->assertSessionHasErrors('billing_day');
    }

    public function test_rejects_package_bound_to_another_router(): void
    {
        $router = Router::factory()->create();
        $otherRoutersPackage = Package::factory()->for(Router::factory())->create();

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $otherRoutersPackage))->assertSessionHasErrors('package_id');
    }

    public function test_accepts_package_available_on_all_routers(): void
    {
        $router = Router::factory()->create();

        $this->actingAs($this->admin())
            ->post('/customers', $this->validPayload($router, Package::factory()->create(['router_id' => null])))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_rejects_duplicate_pppoe_username_on_same_router_only(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();
        Customer::factory()->for($router)->create(['pppoe_username' => 'budi.santoso']);

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package))->assertSessionHasErrors('pppoe_username');
        $this->actingAs($this->admin())->post('/customers', $this->validPayload(Router::factory()->create(), $package))->assertSessionDoesntHaveErrors();
    }

    public function test_rejects_odp_from_another_router(): void
    {
        $router = Router::factory()->create();
        $odp = Odp::factory()->create();

        $this->actingAs($this->admin())
            ->post('/customers', $this->validPayload($router, Package::factory()->create(), ['odp_id' => $odp->id]))
            ->assertSessionHasErrors('odp_id');
    }

    public function test_rejects_odp_port_beyond_capacity_or_already_used(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->create();
        $odp = Odp::factory()->for($router)->create(['total_ports' => 4]);
        Customer::factory()->for($router)->create(['odp_id' => $odp->id, 'odp_port' => 2]);

        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, ['odp_id' => $odp->id, 'odp_port' => 5]))->assertSessionHasErrors('odp_port');
        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, ['odp_id' => $odp->id, 'odp_port' => 2]))->assertSessionHasErrors('odp_port');
        $this->actingAs($this->admin())->post('/customers', $this->validPayload($router, $package, ['odp_id' => $odp->id, 'odp_port' => 3]))->assertSessionDoesntHaveErrors();
    }

    public function test_customer_may_keep_their_own_odp_port_on_update(): void
    {
        $router = Router::factory()->create();
        $odp = Odp::factory()->for($router)->create();
        $customer = Customer::factory()->for($router)->create(['odp_id' => $odp->id, 'odp_port' => 2]);

        $this->actingAs($this->admin())
            ->put("/customers/{$customer->id}", $this->validPayload($router, $customer->package, [
                'pppoe_username' => $customer->pppoe_username, 'pppoe_password' => '', 'odp_id' => $odp->id, 'odp_port' => 2,
            ]))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_update_keeps_password_when_blank_and_tracks_isolation_time(): void
    {
        $customer = Customer::factory()->create(['pppoe_password' => 'lama']);
        $payload = $this->validPayload($customer->router, $customer->package, ['pppoe_username' => $customer->pppoe_username, 'pppoe_password' => '']);

        $this->actingAs($this->admin())->put("/customers/{$customer->id}", [...$payload, 'status' => 'isolated']);

        $customer->refresh();
        $this->assertSame('lama', $customer->pppoe_password);
        $this->assertSame(CustomerStatus::Isolated, $customer->status);
        $this->assertNotNull($customer->isolated_at);

        $this->actingAs($this->admin())->put("/customers/{$customer->id}", [...$payload, 'status' => 'active']);

        $this->assertNull($customer->fresh()->isolated_at);
    }

    public function test_edit_form_never_exposes_the_pppoe_password(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())->get("/customers/{$customer->id}/edit")->assertInertia(
            fn (Assert $page) => $page->component('Customers/Form')->where('customer.id', $customer->id)->missing('customer.pppoe_password'),
        );
    }

    public function test_edit_form_keeps_inactive_current_package_selectable(): void
    {
        $customer = Customer::factory()->for(Package::factory()->create(['is_active' => false]))->create();

        $this->actingAs($this->admin())->get("/customers/{$customer->id}/edit")->assertInertia(
            fn (Assert $page) => $page->has('packages', 1)->where('packages.0.id', $customer->package_id),
        );
    }

    public function test_deleting_customer_archives_the_record_and_queues_router_secret_removal(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['pppoe_username' => 'budi']);

        $this->actingAs($this->admin())->delete("/customers/{$customer->id}");

        $this->assertSoftDeleted($customer);
        Queue::assertPushed(RemovePppoeSecretJob::class, fn (RemovePppoeSecretJob $job) => $job->routerId === $customer->router_id && $job->username === 'budi');
    }

    public function test_deleting_customer_releases_the_username_for_reuse(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['pppoe_username' => 'budi']);

        $this->actingAs($this->admin())->delete("/customers/{$customer->id}");

        $replacement = Customer::factory()->create(['router_id' => $customer->router_id, 'pppoe_username' => 'budi']);
        $this->assertNotSame($customer->id, $replacement->id);
        $this->assertSame("budi~dihapus-{$customer->id}", Customer::withTrashed()->find($customer->id)->pppoe_username);
        Queue::assertPushed(RemovePppoeSecretJob::class, fn (RemovePppoeSecretJob $job) => $job->username === 'budi');
    }

    public function test_bulk_delete_removes_only_selected_customers_and_queues_router_cleanup(): void
    {
        Queue::fake();
        [$first, $second, $kept] = Customer::factory()->count(3)->create();

        $this->actingAs($this->admin())->delete('/customers', ['ids' => [$first->id, $second->id]])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($kept);
        Queue::assertPushed(RemovePppoeSecretJob::class, 2);
    }

    public function test_bulk_delete_select_all_applies_the_list_filters(): void
    {
        Queue::fake();
        $router = Router::factory()->create();
        Customer::factory()->count(20)->for($router)->create(['status' => CustomerStatus::Isolated]);
        $active = Customer::factory()->for($router)->create(['status' => CustomerStatus::Active]);
        $otherRouter = Customer::factory()->create(['status' => CustomerStatus::Isolated]);

        $this->actingAs($this->admin())->delete('/customers', ['select_all' => true, 'status' => 'isolated', 'router_id' => $router->id]);

        $this->assertSame(2, Customer::count());
        $this->assertNotSoftDeleted($active);
        $this->assertNotSoftDeleted($otherRouter);
        Queue::assertPushed(RemovePppoeSecretJob::class, 20);
    }

    public function test_bulk_delete_requires_a_selection_and_the_manage_permission(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin())->delete('/customers', ['ids' => []])->assertSessionHasErrors('ids');
        $this->actingAs($this->userWithRole('Teknisi'))->delete('/customers', ['ids' => [$customer->id]])->assertForbidden();

        $this->assertNotSoftDeleted($customer);
        Queue::assertNothingPushed();
    }

    public function test_whatsapp_number_is_optional_but_must_be_valid_when_given(): void
    {
        $router = Router::factory()->create();
        $package = Package::factory()->for($router)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/customers', $this->validPayload($router, $package, ['whatsapp' => '', 'pppoe_username' => 'tanpa-wa']))->assertSessionHasNoErrors();
        $this->assertSame('', Customer::where('pppoe_username', 'tanpa-wa')->firstOrFail()->whatsapp);

        $this->actingAs($admin)->post('/customers', $this->validPayload($router, $package, ['whatsapp' => null, 'pppoe_username' => 'null-wa']))->assertSessionHasNoErrors();
        $this->assertSame('', Customer::where('pppoe_username', 'null-wa')->firstOrFail()->whatsapp);

        $this->actingAs($admin)->post('/customers', $this->validPayload($router, $package, ['whatsapp' => '12345', 'pppoe_username' => 'salah']))->assertSessionHasErrors('whatsapp');
    }

    public function test_whatsapp_number_can_be_cleared_on_update(): void
    {
        $customer = Customer::factory()->create(['whatsapp' => '6281234567890']);

        $this->actingAs($this->admin())->put("/customers/{$customer->id}", $this->validPayload($customer->router, $customer->package, ['whatsapp' => '', 'pppoe_username' => $customer->pppoe_username]))->assertSessionHasNoErrors();

        $this->assertSame('', $customer->fresh()->whatsapp);
    }

    public function test_page_size_can_be_chosen_and_invalid_values_fall_back_to_default(): void
    {
        Customer::factory()->count(30)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/customers')->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 15)->has('customers.data', 15));
        $this->actingAs($admin)->get('/customers?per_page=25')->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 25)->has('customers.data', 25));
        $this->actingAs($admin)->get('/customers?per_page=10')->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 10)->has('customers.data', 10));
        $this->actingAs($admin)->get('/customers?per_page=100000')->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 15));
        $this->actingAs($admin)->get('/customers?per_page=abc')->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 15));
    }

    public function test_search_endpoint_returns_matches_and_ignores_short_terms(): void
    {
        Customer::factory()->create(['name' => 'Budi Santoso']);
        Customer::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($this->admin())->getJson('/customers/search?q=bud')->assertJsonCount(1)->assertJsonPath('0.name', 'Budi Santoso');
        $this->actingAs($this->admin())->getJson('/customers/search?q=b')->assertExactJson([]);
    }
}
