<?php

namespace Tests\Feature\Database;

use App\Models\PaymentGateway;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_owner_account_with_default_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'amon@teslatech.my.id')->sole();

        $this->assertSame('Amon', $owner->name);
        $this->assertTrue(Hash::check('gantengmax', $owner->password));
        $this->assertTrue($owner->hasRole('Owner'));
    }

    public function test_running_twice_does_not_duplicate_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('payment_gateways', 3);
    }

    public function test_registers_every_payment_gateway_as_inactive_without_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $gateways = PaymentGateway::all();

        $this->assertEqualsCanonicalizing(['duitku', 'tripay', 'midtrans'], $gateways->pluck('driver.value')->all());
        $this->assertTrue($gateways->every(fn (PaymentGateway $gateway): bool => ! $gateway->is_active && $gateway->credentials === null));
    }
}
