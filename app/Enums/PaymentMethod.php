<?php

namespace App\Enums;

/**
 * Metode pembayaran tagihan.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Gateway = 'gateway';
    case Transfer = 'transfer';
}
