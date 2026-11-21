<?php

namespace App\Enums;

/**
 * Jenis aset/perangkat inventaris.
 */
enum AssetType: string
{
    case Ont = 'ont';
    case Router = 'router';
    case Stb = 'stb';
    case Other = 'other';
}
