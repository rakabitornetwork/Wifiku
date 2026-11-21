<?php

namespace Database\Seeders;

use App\Enums\PaymentGatewayDriver;
use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Daftarkan seluruh driver gateway dalam kondisi nonaktif tanpa kredensial.
     * Kredensial diisi Owner melalui Panel Admin, tidak pernah melalui .env.
     */
    public function run(): void
    {
        foreach (PaymentGatewayDriver::cases() as $order => $driver) {
            PaymentGateway::firstOrCreate(
                ['driver' => $driver],
                [
                    'name' => ucfirst($driver->value),
                    'is_active' => false,
                    'is_sandbox' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
