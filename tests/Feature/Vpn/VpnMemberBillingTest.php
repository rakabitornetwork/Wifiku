<?php

namespace Tests\Feature\Vpn;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\VpnAccount;
use App\Models\VpnMember;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VpnMemberBillingTest extends TestCase
{
    use RefreshDatabase;

    private VpnMember $member;

    private VpnAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = VpnMember::factory()->create();
        $this->account = VpnAccount::factory()->for(VpnServer::factory()->create(), 'server')->create(['vpn_member_id' => $this->member->id, 'name' => 'Router Rumah']);
    }

    private function invoice(VpnAccount $account, array $attributes = []): Invoice
    {
        return Invoice::factory()->create(['customer_id' => null, 'vpn_account_id' => $account->id, 'total' => 25000, ...$attributes]);
    }

    private function gateway(bool $respond = true): void
    {
        PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'is_active' => true,
            'enabled_channels' => ['QRIS', 'BRIVA'],
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-key', 'private_key' => 'private-key'],
        ]);
        if (! $respond) {
            return;
        }

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => [
            'reference' => 'T0001-REF', 'checkout_url' => 'https://tripay.co.id/checkout/T0001-REF', 'expired_time' => now()->addDay()->timestamp,
        ]])]);
    }

    public function test_billing_page_requires_a_member_login(): void
    {
        $this->get('/vpn-tunnel/tagihan')->assertRedirect('/portal?layanan=vpn');
        $this->post('/vpn-tunnel/tagihan/bayar', [])->assertRedirect('/portal?layanan=vpn');
    }

    public function test_lists_open_invoices_and_history_of_only_the_members_own_accounts(): void
    {
        $open = $this->invoice($this->account, ['period_start' => now()->startOfMonth()]);
        $paid = $this->invoice($this->account, ['status' => InvoiceStatus::Paid, 'amount_paid' => 25000, 'paid_at' => now(), 'period_start' => now()->subMonth()->startOfMonth()]);
        $foreign = $this->invoice(VpnAccount::factory()->create(['vpn_member_id' => VpnMember::factory()->create()->id]));
        Payment::create(['invoice_id' => $paid->id, 'customer_id' => null, 'method' => PaymentMethod::Cash, 'merchant_ref' => 'CASH-1', 'amount' => 25000, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($this->member, 'member')->get('/vpn-tunnel/tagihan')->assertInertia(
            fn (Assert $page) => $page->component('Public/Vpn/Billing')
                ->has('open', 1)
                ->where('open.0.invoice_number', $open->invoice_number)
                ->where('open.0.account', 'Router Rumah')
                ->where('outstanding', 25000)
                ->has('history.data', 1)
                ->where('history.data.0.invoice_number', $paid->invoice_number)
                ->where('history.data.0.paid_via', 'Tunai')
                ->where('payment.available', false),
        );
        $this->assertNotSame($foreign->invoice_number, $open->invoice_number);
    }

    public function test_outstanding_counts_only_the_remaining_amount_of_partial_invoices(): void
    {
        $this->invoice($this->account, ['status' => InvoiceStatus::Partial, 'amount_paid' => 10000]);

        $this->actingAs($this->member, 'member')->get('/vpn-tunnel/tagihan')->assertInertia(fn (Assert $page) => $page->where('outstanding', 15000));
    }

    public function test_open_invoice_exposes_a_still_valid_checkout_to_continue(): void
    {
        $invoice = $this->invoice($this->account);
        Payment::create(['invoice_id' => $invoice->id, 'customer_id' => null, 'method' => PaymentMethod::Gateway, 'channel' => 'QRIS', 'merchant_ref' => 'INV-1', 'amount' => 25000, 'status' => PaymentStatus::Pending, 'checkout_url' => 'https://tripay.co.id/checkout/ABC', 'expires_at' => now()->addHours(5)]);
        Payment::create(['invoice_id' => $invoice->id, 'customer_id' => null, 'method' => PaymentMethod::Gateway, 'channel' => 'QRIS', 'merchant_ref' => 'INV-0', 'amount' => 25000, 'status' => PaymentStatus::Pending, 'checkout_url' => 'https://tripay.co.id/checkout/OLD', 'expires_at' => now()->subHour()]);

        $this->actingAs($this->member, 'member')->get('/vpn-tunnel/tagihan')->assertInertia(
            fn (Assert $page) => $page->where('open.0.checkout_url', 'https://tripay.co.id/checkout/ABC'),
        );
    }

    public function test_member_can_pay_an_own_invoice_online(): void
    {
        $this->gateway();
        $invoice = $this->invoice($this->account);

        $this->actingAs($this->member, 'member')->post('/vpn-tunnel/tagihan/bayar', ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])
            ->assertRedirect('https://tripay.co.id/checkout/T0001-REF');

        $payment = Payment::sole();
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertNull($payment->customer_id);
        $this->assertSame(25000, $payment->amount);
    }

    public function test_member_cannot_pay_another_members_invoice(): void
    {
        $this->gateway();
        $foreign = $this->invoice(VpnAccount::factory()->create(['vpn_member_id' => VpnMember::factory()->create()->id]));

        $this->actingAs($this->member, 'member')->post('/vpn-tunnel/tagihan/bayar', ['invoice_number' => $foreign->invoice_number, 'channel' => 'QRIS'])->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_paid_invoice_and_disabled_channel_are_rejected(): void
    {
        $this->gateway();
        $paid = $this->invoice($this->account, ['status' => InvoiceStatus::Paid, 'amount_paid' => 25000]);
        $open = $this->invoice($this->account, ['period_start' => now()->addMonth()->startOfMonth()]);
        $this->actingAs($this->member, 'member');

        $this->post('/vpn-tunnel/tagihan/bayar', ['invoice_number' => $paid->invoice_number, 'channel' => 'QRIS'])->assertSessionHasErrors('invoice_number');
        $this->post('/vpn-tunnel/tagihan/bayar', ['invoice_number' => $open->invoice_number, 'channel' => 'OVO'])->assertSessionHasErrors('channel');
        $this->assertSame(0, Payment::count());
    }

    public function test_gateway_failure_returns_with_an_error_and_marks_the_attempt_failed(): void
    {
        $this->gateway(respond: false);
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'down'], 500)]);
        $invoice = $this->invoice($this->account);

        $this->actingAs($this->member, 'member')->post('/vpn-tunnel/tagihan/bayar', ['invoice_number' => $invoice->invoice_number, 'channel' => 'QRIS'])->assertRedirect();

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_history_is_paginated(): void
    {
        foreach (range(1, 12) as $month) {
            $this->invoice($this->account, ['status' => InvoiceStatus::Paid, 'period_start' => now()->subMonths($month)->startOfMonth()]);
        }

        $this->actingAs($this->member, 'member')->get('/vpn-tunnel/tagihan')->assertInertia(
            fn (Assert $page) => $page->has('history.data', 10)->where('history.total', 12),
        );
    }
}
