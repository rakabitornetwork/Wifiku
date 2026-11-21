<?php

namespace App\Actions\Customer;

use App\Models\Customer;

class GenerateCustomerCode
{
    /**
     * Nomor pelanggan berikutnya pada bulan ini (TT-YYMM-0001).
     */
    public function handle(): string
    {
        $prefix = 'TT-'.now()->format('ym').'-';

        $last = Customer::withTrashed()->where('customer_code', 'like', $prefix.'%')->max('customer_code');
        $sequence = $last === null ? 1 : (int) substr($last, -4) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
