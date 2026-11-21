<?php

namespace App\Actions\Customer;

use App\Jobs\Network\RemovePppoeSecretJob;
use App\Models\Customer;

class DeleteCustomer
{
    /**
     * Arsipkan pelanggan, lepaskan username PPPoE-nya agar bisa dipakai lagi, dan hapus secret-nya dari router lewat antrean.
     */
    public function handle(Customer $customer): void
    {
        $routerId = $customer->router_id;
        $username = $customer->pppoe_username;

        // Unik per (router, username) tetap berlaku pada data yang diarsipkan, jadi namanya diganti.
        $customer->forceFill(['pppoe_username' => "{$username}~dihapus-{$customer->id}"])->saveQuietly();
        $customer->delete();

        RemovePppoeSecretJob::dispatch($routerId, $username);
    }
}
