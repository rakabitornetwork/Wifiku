<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Jobs\Network\ReopenCustomerJob;
use App\Jobs\Network\SyncPppoeSecretJob;
use App\Models\Customer;

class UpdateCustomer
{
    /**
     * Perbarui pelanggan, jaga penanda waktu isolir konsisten dengan status, lalu sinkronkan ke router.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $status = CustomerStatus::from($data['status']);

        if ($status === CustomerStatus::Isolated) {
            $data['isolated_at'] = $customer->isolated_at ?? now();
        } else {
            $data['isolated_at'] = null;
        }

        $previousUsername = $customer->pppoe_username;
        $previousRouterId = $customer->router_id;

        $customer->update($data);

        if ($customer->wasChanged(['router_id', 'package_id', 'pppoe_username', 'pppoe_password', 'status', 'name'])) {
            SyncPppoeSecretJob::dispatch(
                $customer,
                $customer->wasChanged('pppoe_username') ? $previousUsername : null,
                $customer->wasChanged('router_id') ? $previousRouterId : null,
            );
        }

        // Janji Bayar baru untuk pelanggan terisolir membuka layanannya; job memeriksa ulang masa berlakunya.
        if ($status === CustomerStatus::Isolated && $customer->wasChanged('promise_to_pay_until') && $customer->promise_to_pay_until !== null) {
            ReopenCustomerJob::dispatch($customer);
        }

        return $customer;
    }
}
