<?php

namespace App\Enums;

/**
 * Status layanan pelanggan PPPoE.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Isolated = 'isolated';
    case Inactive = 'inactive';
}
