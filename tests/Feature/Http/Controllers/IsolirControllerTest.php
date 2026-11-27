<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IsolirControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_reachable_without_authentication(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Isolated]);

        $this->get("/isolir/{$customer->customer_code}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Public/Isolir')->where('customer.customer_code', $customer->customer_code)->where('customer.status', 'isolated'),
        );
    }

    public function test_unknown_or_archived_customer_code_returns_404(): void
    {
        $archived = Customer::factory()->create();
        $archived->delete();

        $this->get('/isolir/TT-0000-0000')->assertNotFound();
        $this->get("/isolir/{$archived->customer_code}")->assertNotFound();
    }

    public function test_shows_only_unpaid_and_partial_invoices_with_outstanding_total(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['period_start' => '2026-09-01', 'due_date' => '2026-09-08', 'total' => 200000, 'status' => InvoiceStatus::Unpaid]);
        Invoice::factory()->for($customer)->create(['period_start' => '2026-10-01', 'due_date' => '2026-10-08', 'total' => 200000, 'amount_paid' => 50000, 'status' => InvoiceStatus::Partial]);
        Invoice::factory()->for($customer)->create(['period_start' => '2026-08-01', 'status' => InvoiceStatus::Paid]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid]);

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(
            fn (Assert $page) => $page
                ->has('invoices', 2)
                ->where('invoices.0.outstanding', 200000)
                ->where('invoices.1.outstanding', 150000)
                ->where('outstanding', 350000),
        );
    }

    public function test_flags_overdue_invoices(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['due_date' => now()->subDay(), 'status' => InvoiceStatus::Unpaid]);

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(fn (Assert $page) => $page->where('invoices.0.is_overdue', true));
    }

    public function test_masks_the_customer_name_and_hides_contact_details(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi Santoso Wijaya', 'whatsapp' => '6281234567890', 'address' => 'Jl. Mawar 12']);

        $response = $this->get("/isolir/{$customer->customer_code}");

        $response->assertInertia(fn (Assert $page) => $page->where('customer.name', 'Budi S*** W***'));
        $this->assertStringNotContainsString('6281234567890', $response->getContent());
        $this->assertStringNotContainsString('Jl. Mawar 12', $response->getContent());
        $this->assertStringNotContainsString('Santoso', $response->getContent());
    }

    public function test_builds_whatsapp_link_from_company_setting(): void
    {
        $customer = Customer::factory()->create();
        Setting::create(['group' => 'company', 'key' => 'cs_whatsapp', 'value' => '+62 812-0000-1111']);

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(
            fn (Assert $page) => $page->where('whatsappUrl', fn (string $url) => str_starts_with($url, 'https://wa.me/628120000111') && str_contains($url, $customer->customer_code)),
        );
    }

    public function test_omits_whatsapp_link_when_cs_number_is_not_configured(): void
    {
        $customer = Customer::factory()->create();

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(fn (Assert $page) => $page->where('whatsappUrl', null));
    }

    public function test_lists_channels_of_the_active_gateway_with_highest_priority(): void
    {
        $customer = Customer::factory()->create();
        PaymentGateway::factory()->create(['driver' => 'midtrans', 'is_active' => true, 'sort_order' => 2, 'enabled_channels' => ['GOPAY']]);
        PaymentGateway::factory()->create(['driver' => 'tripay', 'is_active' => true, 'sort_order' => 1, 'enabled_channels' => ['QRIS', 'BRIVA']]);
        PaymentGateway::factory()->create(['driver' => 'duitku', 'is_active' => false, 'sort_order' => 0, 'enabled_channels' => ['OVO']]);

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(
            fn (Assert $page) => $page->where('payment.available', true)->where('payment.channels.0.code', 'QRIS')->where('payment.channels.1.code', 'BRIVA')->where('payment.channels.1.label', 'BRI')->where('payment.channels.1.group', 'Transfer Bank (Virtual Account)'),
        );
    }

    public function test_payment_is_unavailable_without_an_active_gateway(): void
    {
        $customer = Customer::factory()->create();

        $this->get("/isolir/{$customer->customer_code}")->assertInertia(fn (Assert $page) => $page->where('payment.available', false)->where('payment.channels', []));
    }
}
