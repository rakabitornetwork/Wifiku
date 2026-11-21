<?php

namespace App\Enums;

/**
 * Status transaksi pembayaran.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}
