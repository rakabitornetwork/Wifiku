<?php

namespace App\Enums;

/**
 * Status tagihan pelanggan.
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
