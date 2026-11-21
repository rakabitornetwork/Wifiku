<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentGatewayControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function tripay(array $attributes = []): PaymentGateway
    {
        return PaymentGateway::factory()->create([
            'driver' => PaymentGatewayDriver::Tripay,
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'api-lama', 'private_key' => 'private-lama'],
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Tripay',
            'is_active' => false,
            'is_sandbox' => true,
            'fee_bearer' => 'merchant',
            'sort_order' => 0,
            'enabled_channels' => ['QRIS', 'BRIVA'],
            'credentials' => ['merchant_code' => '', 'api_key' => '', 'private_key' => ''],
            ...$overrides,
        ];
    }

    public function test_admin_cannot_open_or_change_gateway_settings(): void
    {
        $gateway = $this->tripay();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/settings/payment-gateways')->assertForbidden();
        $this->actingAs($admin)->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload())->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/settings/payment-gateways')->assertRedirect('/login');
    }

    public function test_page_exposes_only_whether_each_credential_is_set(): void
    {
        $this->tripay(['credentials' => ['merchant_code' => 'T0001', 'api_key' => 'rahasia-api-key']]);

        $response = $this->actingAs($this->owner())->get('/settings/payment-gateways');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/PaymentGateways')
                ->where('gateways.0.credentials', [
                    ['key' => 'merchant_code', 'label' => 'Merchant Code', 'is_set' => true],
                    ['key' => 'api_key', 'label' => 'Api Key', 'is_set' => true],
                    ['key' => 'private_key', 'label' => 'Private Key', 'is_set' => false],
                ]),
        );
        $this->assertStringNotContainsString('rahasia-api-key', $response->getContent());
    }

    public function test_blank_credentials_keep_stored_values_while_other_fields_update(): void
    {
        $gateway = $this->tripay();

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload(['name' => 'Tripay Utama', 'sort_order' => 3]))
            ->assertSessionDoesntHaveErrors();

        $gateway->refresh();
        $this->assertSame('Tripay Utama', $gateway->name);
        $this->assertSame(3, $gateway->sort_order);
        $this->assertSame(['merchant_code' => 'T0001', 'api_key' => 'api-lama', 'private_key' => 'private-lama'], $gateway->credentials);
    }

    public function test_submitting_the_mask_placeholder_does_not_overwrite_a_credential(): void
    {
        $gateway = $this->tripay();

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload([
            'credentials' => ['merchant_code' => '', 'api_key' => '••••••••', 'private_key' => ''],
        ]));

        $this->assertSame('api-lama', $gateway->fresh()->credentials['api_key']);
    }

    public function test_new_credential_replaces_only_that_key_and_is_encrypted_at_rest(): void
    {
        $gateway = $this->tripay();

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload([
            'credentials' => ['merchant_code' => '', 'api_key' => 'api-baru', 'private_key' => ''],
        ]));

        $this->assertSame(['merchant_code' => 'T0001', 'api_key' => 'api-baru', 'private_key' => 'private-lama'], $gateway->fresh()->credentials);
        $this->assertStringNotContainsString('api-baru', DB::table('payment_gateways')->value('credentials'));
    }

    public function test_unknown_credential_keys_are_ignored(): void
    {
        $gateway = $this->tripay();

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload([
            'credentials' => ['merchant_code' => '', 'api_key' => '', 'private_key' => '', 'server_key' => 'bukan-milik-tripay'],
        ]));

        $this->assertArrayNotHasKey('server_key', $gateway->fresh()->credentials);
    }

    public function test_cannot_activate_gateway_with_missing_credentials(): void
    {
        $gateway = $this->tripay(['credentials' => ['merchant_code' => 'T0001']]);

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload(['is_active' => true]))
            ->assertSessionHasErrors(['credentials.api_key', 'credentials.private_key']);

        $this->assertFalse($gateway->fresh()->is_active);
    }

    public function test_can_activate_gateway_when_credentials_arrive_in_the_same_request(): void
    {
        $gateway = $this->tripay(['credentials' => null]);

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload([
            'is_active' => true,
            'credentials' => ['merchant_code' => 'T0001', 'api_key' => 'k', 'private_key' => 'p'],
        ]))->assertSessionDoesntHaveErrors();

        $this->assertTrue($gateway->fresh()->is_active);
    }

    public function test_channels_are_deduplicated(): void
    {
        $gateway = $this->tripay();

        $this->actingAs($this->owner())->put("/settings/payment-gateways/{$gateway->id}", $this->validPayload(['enabled_channels' => ['QRIS', 'QRIS', 'BRIVA']]));

        $this->assertSame(['QRIS', 'BRIVA'], $gateway->fresh()->enabled_channels);
    }
}
