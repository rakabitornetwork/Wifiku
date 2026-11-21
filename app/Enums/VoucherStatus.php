<?php

namespace App\Enums;

/**
 * Status voucher hotspot.
 */
enum VoucherStatus: string
{
    case Unused = 'unused';
    case Active = 'active';
    case Expired = 'expired';
    case Disabled = 'disabled';
}
