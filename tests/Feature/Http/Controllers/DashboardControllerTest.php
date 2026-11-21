<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RouterStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_summarizes_customers_routers_and_outstanding_invoices(): void
    {
        Customer::factory()->count(2)->create();
        Customer::factory()->create(['status' => CustomerStatus::Isolated]);
        Router::factory()->create(['status' => RouterStatus::Online]);
        Invoice::factory()->create(['total' => 200000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid]);

        $this->actingAs($this->admin())->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.customers_active', 4)
                ->where('stats.customers_isolated', 1)
                ->where('stats.routers_online', 1)
                ->where('stats.invoices_unpaid', 1)
                ->where('stats.outstanding', 150000),
        );
    }
}
