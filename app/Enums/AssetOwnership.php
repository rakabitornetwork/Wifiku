<?php

namespace App\Enums;

/**
 * Status kepemilikan perangkat di rumah pelanggan.
 */
enum AssetOwnership: string
{
    case Company = 'company';
    case Customer = 'customer';
}
