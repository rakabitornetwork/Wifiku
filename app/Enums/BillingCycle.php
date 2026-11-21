<?php

namespace App\Enums;

/**
 * Tipe siklus tagihan pelanggan.
 */
enum BillingCycle: string
{
    case Fixed = 'fixed';
    case Anniversary = 'anniversary';
}
