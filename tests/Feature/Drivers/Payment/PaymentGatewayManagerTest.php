<?php

namespace Tests\Feature\Drivers\Payment;

use App\Drivers\Payment\DuitkuDriver;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Drivers\Payment\MidtransDriver;
use App\Drivers\Payment\PaymentGatewayManager;
use App\Drivers\Payment\TripayDriver;
use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{PaymentGatewayDriver, class-string}>
     */
    public static function drivers(): array
    {
        return [
            'duitku' => [PaymentGatewayDriver::Duitku, DuitkuDriver::class],
            'tripay' => [PaymentGatewayDriver::Tripay, TripayDriver::class],
            'midtrans' => [PaymentGatewayDriver::Midtrans, MidtransDriver::class],
        ];
    }

    /**
     * @param  class-string  $expectedClass
     */
    #[DataProvider('drivers')]
    public function test_resolves_driver_class_for_gateway(PaymentGatewayDriver $driver, string $expectedClass): void
    {
        PaymentGateway::factory()->create(['driver' => $driver]);

        $this->assertInstanceOf($expectedClass, (new PaymentGatewayManager)->for($driver));
    }

    public function test_default_returns_active_gateway_with_lowest_sort_order(): void
    {
        PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Duitku, 'is_active' => false, 'sort_order' => 0]);
        PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Midtrans, 'is_active' => true, 'sort_order' => 2]);
        PaymentGateway::factory()->create(['driver' => PaymentGatewayDriver::Tripay, 'is_active' => true, 'sort_order' => 1]);

        $this->assertInstanceOf(TripayDriver::class, (new PaymentGatewayManager)->default());
    }

    public function test_default_throws_when_no_gateway_is_active(): void
    {
        PaymentGateway::factory()->create(['is_active' => false]);

        $this->expectException(PaymentGatewayException::class);

        (new PaymentGatewayManager)->default();
    }
}
