<?php

namespace App\Enums;

/**
 * Jenis baris item tagihan.
 */
enum InvoiceItemType: string
{
    case Package = 'package';
    case Prorata = 'prorata';
    case Installation = 'installation';
    case Adjustment = 'adjustment';
    case Vpn = 'vpn';
}
