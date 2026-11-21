<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_second_invoice_for_same_customer_and_period(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Invoice::factory()->for($invoice->customer)->create(['period_start' => $invoice->period_start]);
    }

    public function test_allows_same_pppoe_username_on_different_routers(): void
    {
        Customer::factory()->for(Router::factory())->create(['pppoe_username' => 'budi']);
        Customer::factory()->for(Router::factory())->create(['pppoe_username' => 'budi']);

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_rejects_duplicate_pppoe_username_on_same_router(): void
    {
        $router = Router::factory()->create();
        Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);

        $this->expectException(UniqueConstraintViolationException::class);

        Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);
    }
}
