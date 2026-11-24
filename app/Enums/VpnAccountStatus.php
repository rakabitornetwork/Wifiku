<?php

namespace App\Enums;

/**
 * Status akun VPN; `disabled` dipakai untuk akun yang ditangguhkan atau kedaluwarsa,
 * `pending` untuk akun buatan member yang belum dibayar (belum dipasang di router).
 */
enum VpnAccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Pending = 'pending';
}
