<?php

namespace App\Enums;

/**
 * Status akun VPN; `disabled` dipakai untuk akun yang ditangguhkan atau kedaluwarsa.
 */
enum VpnAccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
