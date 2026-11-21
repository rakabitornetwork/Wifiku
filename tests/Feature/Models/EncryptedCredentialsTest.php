<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\PaymentGateway;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptedCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_password_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $router = Router::factory()->create(['password' => 'rahasia-router']);

        $stored = DB::table('routers')->where('id', $router->id)->value('password');

        $this->assertNotSame('rahasia-router', $stored);
        $this->assertSame('rahasia-router', Crypt::decryptString($stored));
        $this->assertSame('rahasia-router', $router->fresh()->password);
        $this->assertArrayNotHasKey('password', $router->toArray());
    }

    public function test_customer_pppoe_password_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $customer = Customer::factory()->create(['pppoe_password' => 'rahasia-pppoe']);

        $stored = DB::table('customers')->where('id', $customer->id)->value('pppoe_password');

        $this->assertSame('rahasia-pppoe', Crypt::decryptString($stored));
        $this->assertArrayNotHasKey('pppoe_password', $customer->toArray());
    }

    public function test_payment_gateway_credentials_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $credentials = ['merchant_code' => 'T12345', 'api_key' => 'api-key', 'private_key' => 'private-key'];
        $gateway = PaymentGateway::factory()->create(['credentials' => $credentials]);

        $stored = DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials');

        $this->assertStringNotContainsString('private-key', $stored);
        $this->assertSame($credentials, $gateway->fresh()->credentials);
        $this->assertArrayNotHasKey('credentials', $gateway->toArray());
    }
}
