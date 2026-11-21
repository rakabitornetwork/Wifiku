<?php

namespace App\Enums;

/**
 * Status koneksi API router MikroTik.
 */
enum RouterStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Unknown = 'unknown';
}
