<?php

namespace App\Enums;

/**
 * Status inventaris perangkat.
 */
enum AssetStatus: string
{
    case InStock = 'in_stock';
    case Installed = 'installed';
    case Broken = 'broken';
    case Returned = 'returned';
}
