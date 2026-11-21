<?php

namespace Tests\Feature\Actions\Customer;

use App\Actions\Customer\UpdateCustomer;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\Network\SyncPppoeSecretJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakesRouterOs;
use Tests\TestCase;

class UpdateCustomerTest extends TestCase
{
    use FakesRouterOs;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function data(Customer $customer, array $overrides = []): array
    {
        return [...$customer->only(['router_id', 'package_id', 'name', 'pppoe_username', 'status']), 'status' => $customer->status->value, ...$overrides];
    }

    public function test_changing_username_renames_the_router_secret(): void
    {
        $customer = Customer::factory()->create(['pppoe_username' => 'budi']);
        $fake = $this->fakeRouterOs($customer->router);
        $fake->seed('/ppp/secret', ['name' => 'budi', 'profile' => $customer->package->mikrotik_profile]);

        $this->app->make(UpdateCustomer::class)->handle($customer, $this->data($customer, ['pppoe_username' => 'budi.baru']));

        $this->assertSame(['budi.baru'], array_column($fake->rows('/ppp/secret'), 'name'));
        $this->assertNotNull($customer->fresh()->router_synced_at);
    }

    public function test_moving_customer_to_another_router_removes_the_old_secret(): void
    {
        $customer = Customer::factory()->create(['pppoe_username' => 'budi']);
        $oldRouter = $customer->router;
        $newRouter = Router::factory()->create();
        $this->fakeRouterOs($oldRouter)->seed('/ppp/secret', ['name' => 'budi']);

        $this->app->make(UpdateCustomer::class)->handle($customer, $this->data($customer, ['router_id' => $newRouter->id]));

        $this->assertSame([], $this->fakeRouterOs($oldRouter)->rows('/ppp/secret'));
        $this->assertSame(['budi'], array_column($this->fakeRouterOs($newRouter)->rows('/ppp/secret'), 'name'));
    }

    public function test_unrelated_change_does_not_touch_the_router(): void
    {
        Queue::fake([SyncPppoeSecretJob::class]);
        $customer = Customer::factory()->create();

        $this->app->make(UpdateCustomer::class)->handle($customer, $this->data($customer, ['village' => 'Sukamaju']));

        Queue::assertNotPushed(SyncPppoeSecretJob::class);
    }

    public function test_new_promise_to_pay_reopens_isolated_customer(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Invoice::factory()->for($customer)->create(['due_date' => now()->subDays(3), 'status' => InvoiceStatus::Unpaid]);

        $this->app->make(UpdateCustomer::class)->handle($customer, $this->data($customer, ['promise_to_pay_until' => now()->addDays(3)->toDateString()]));

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
    }

    public function test_expired_promise_does_not_reopen(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Invoice::factory()->for($customer)->create(['due_date' => now()->subDays(10), 'status' => InvoiceStatus::Unpaid]);

        $this->app->make(UpdateCustomer::class)->handle($customer, $this->data($customer, ['promise_to_pay_until' => now()->subDay()->toDateString()]));

        $this->assertSame(CustomerStatus::Isolated, $customer->fresh()->status);
        $this->assertSame([], $this->fakeRouterOs($customer->router)->commands);
    }
}
