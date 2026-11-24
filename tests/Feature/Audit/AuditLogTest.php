<?php

namespace Tests\Feature\Audit;

use App\Actions\Payment\RecordCashPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_package_price_change_is_recorded_with_old_and_new_values_and_the_actor(): void
    {
        $admin = $this->admin();
        $package = Package::factory()->create(['price' => 150000]);
        Activity::query()->delete();

        $this->actingAs($admin)->put("/packages/{$package->id}", [
            'name' => $package->name, 'mikrotik_profile' => $package->mikrotik_profile, 'price' => 175000, 'is_active' => true,
        ]);

        $activity = Activity::sole();
        $this->assertSame('Paket', $activity->log_name);
        $this->assertSame('updated', $activity->event);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(175000, $activity->attribute_changes['attributes']['price']);
        $this->assertSame(150000, $activity->attribute_changes['old']['price']);
    }

    public function test_deleting_a_customer_is_recorded(): void
    {
        $admin = $this->admin();
        $customer = Customer::factory()->create();
        Activity::query()->delete();

        $this->actingAs($admin)->delete("/customers/{$customer->id}");

        $activity = Activity::where('event', 'deleted')->sole();
        $this->assertSame('Pelanggan dihapus', $activity->description);
        $this->assertTrue($activity->subject_id === $customer->id);
        $this->assertSame($admin->id, $activity->causer_id);
    }

    public function test_changes_made_by_the_system_have_no_causer(): void
    {
        $customer = Customer::factory()->create();
        Activity::query()->delete();

        $customer->update(['status' => 'isolated']);

        $this->assertNull(Activity::sole()->causer_id);
    }

    public function test_secrets_never_reach_the_audit_log(): void
    {
        $router = Router::factory()->create(['password' => 'rahasia-router', 'webhook_token' => 'token-rahasia']);
        $customer = Customer::factory()->create(['pppoe_password' => 'rahasia-pppoe', 'nik' => '3201010101010001']);
        PaymentGateway::factory()->create(['credentials' => ['api_key' => 'rahasia-gateway']]);
        $router->update(['password' => 'rahasia-baru', 'name' => 'Router Baru']);
        $customer->update(['pppoe_password' => 'rahasia-baru', 'name' => 'Nama Baru']);

        $dump = Activity::all()->toJson();

        foreach (['rahasia-router', 'token-rahasia', 'rahasia-pppoe', 'rahasia-gateway', 'rahasia-baru', '3201010101010001'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    public function test_router_status_heartbeats_do_not_flood_the_log(): void
    {
        $router = Router::factory()->create();
        Activity::query()->delete();

        $router->forceFill(['status' => 'online', 'last_checked_at' => now(), 'last_error' => null])->save();

        $this->assertSame(0, Activity::count());
    }

    public function test_gateway_credential_change_logs_key_names_only(): void
    {
        $owner = $this->owner();
        $gateway = PaymentGateway::factory()->create(['driver' => 'tripay', 'credentials' => ['merchant_code' => 'T1', 'api_key' => 'lama', 'private_key' => 'p']]);
        Activity::query()->delete();

        $this->actingAs($owner)->put("/settings/payment-gateways/{$gateway->id}", [
            'name' => $gateway->name, 'is_active' => false, 'is_sandbox' => true, 'fee_bearer' => 'merchant', 'sort_order' => 0,
            'enabled_channels' => [], 'credentials' => ['merchant_code' => '', 'api_key' => 'sangat-rahasia-baru', 'private_key' => ''],
        ]);

        $activity = Activity::where('log_name', 'Payment Gateway')->where('description', 'like', 'Kredensial%')->sole();
        $this->assertSame(['api_key'], $activity->properties['keys']);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertStringNotContainsString('sangat-rahasia-baru', Activity::all()->toJson());
    }

    public function test_unchanged_credentials_do_not_create_a_credential_log(): void
    {
        $owner = $this->owner();
        $gateway = PaymentGateway::factory()->create(['driver' => 'tripay', 'credentials' => ['merchant_code' => 'T1', 'api_key' => 'k', 'private_key' => 'p']]);
        Activity::query()->delete();

        $this->actingAs($owner)->put("/settings/payment-gateways/{$gateway->id}", [
            'name' => 'Tripay Baru', 'is_active' => false, 'is_sandbox' => true, 'fee_bearer' => 'merchant', 'sort_order' => 0,
            'enabled_channels' => [], 'credentials' => ['merchant_code' => '', 'api_key' => '', 'private_key' => ''],
        ]);

        $this->assertSame(0, Activity::where('description', 'like', 'Kredensial%')->count());
    }

    public function test_cash_payment_is_recorded_with_cashier_and_invoice(): void
    {
        $cashier = $this->admin();
        $invoice = Invoice::factory()->create(['total' => 200000]);
        Activity::query()->delete();

        $this->app->make(RecordCashPayment::class)->handle($invoice, 200000, $cashier);

        $activity = Activity::where('log_name', 'Pembayaran')->sole();
        $this->assertSame($cashier->id, $activity->causer_id);
        $this->assertSame($invoice->invoice_number, $activity->properties['invoice']);
        $this->assertStringContainsString('200.000', $activity->description);
    }

    public function test_invoices_created_by_the_scheduler_are_not_logged_but_status_changes_are(): void
    {
        $invoice = Invoice::factory()->create();
        $this->assertSame(0, Activity::where('log_name', 'Invoice')->count());

        $invoice->update(['status' => 'cancelled']);

        $this->assertSame(1, Activity::where('log_name', 'Invoice')->count());
    }

    public function test_integration_settings_log_changed_keys_but_not_values(): void
    {
        $owner = $this->owner();
        $payload = ['settings' => ['whatsapp' => ['base_url' => 'http://wa.local:3000', 'api_key' => 'token-wa-rahasia'], 'company' => ['name' => 'Wifi Teslatech'], 'isolir' => ['title' => 'T', 'message' => ''], 'billing' => ['reminder_days_before' => '3', 'extension_admin_max_days' => '14', 'extension_owner_max_days' => '62', 'vpn_invoice_days_before' => '5'], 'genieacs' => ['rx_power_paths' => 'a', 'ssid_path' => 'b', 'wifi_password_path' => 'c']]];
        Activity::query()->delete();

        $this->actingAs($owner)->put('/settings/integrations', $payload)->assertSessionDoesntHaveErrors();

        $activity = Activity::where('log_name', 'Pengaturan')->sole();
        $this->assertContains('whatsapp.api_key', $activity->properties['keys']);
        $this->assertStringNotContainsString('token-wa-rahasia', Activity::all()->toJson());
    }

    public function test_user_account_changes_are_recorded_without_the_password(): void
    {
        $user = User::factory()->create();
        Activity::query()->delete();

        $user->update(['password' => 'password-baru-rahasia', 'is_active' => false]);

        $activity = Activity::sole();
        $this->assertSame(['is_active'], array_keys($activity->attribute_changes['attributes']));
        $this->assertStringNotContainsString('password-baru-rahasia', Activity::all()->toJson());
    }
}
