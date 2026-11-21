<?php

namespace App\Enums;

/**
 * Pihak yang menanggung biaya transaksi payment gateway.
 */
enum FeeBearer: string
{
    case Merchant = 'merchant';
    case Customer = 'customer';
}
