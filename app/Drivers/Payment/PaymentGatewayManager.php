<?php

namespace App\Drivers\Payment;

use App\Drivers\Payment\Contracts\PaymentGatewayDriver as PaymentGatewayDriverContract;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentGateway;

/**
 * Factory driver payment gateway berbasis konfigurasi di database (bukan .env).
 */
class PaymentGatewayManager
{
    /**
     * Buat instance driver untuk satu baris konfigurasi gateway.
     */
    public function driver(PaymentGateway $gateway): PaymentGatewayDriverContract
    {
        $class = self::driverClass($gateway->driver);

        return new $class($gateway);
    }

    /**
     * Kelas driver untuk satu jenis gateway.
     *
     * @return class-string<PaymentGatewayDriverContract>
     */
    public static function driverClass(PaymentGatewayDriver $driver): string
    {
        return match ($driver) {
            PaymentGatewayDriver::Duitku => DuitkuDriver::class,
            PaymentGatewayDriver::Tripay => TripayDriver::class,
            PaymentGatewayDriver::Midtrans => MidtransDriver::class,
        };
    }

    /**
     * Driver untuk gateway tertentu, misalnya saat memproses callback.
     */
    public function for(PaymentGatewayDriver $driver): PaymentGatewayDriverContract
    {
        $gateway = PaymentGateway::where('driver', $driver)->first()
            ?? throw new PaymentGatewayException("Gateway {$driver->value} belum terdaftar.");

        return $this->driver($gateway);
    }

    /**
     * Driver gateway aktif dengan prioritas tertinggi untuk transaksi baru.
     */
    public function default(): PaymentGatewayDriverContract
    {
        return $this->driver($this->activeGateway());
    }

    /**
     * Baris konfigurasi gateway aktif dengan prioritas tertinggi.
     */
    public function activeGateway(): PaymentGateway
    {
        return PaymentGateway::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first()
            ?? throw new PaymentGatewayException('Tidak ada payment gateway yang aktif.');
    }
}
