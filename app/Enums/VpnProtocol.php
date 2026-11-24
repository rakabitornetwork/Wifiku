<?php

namespace App\Enums;

/**
 * Protokol tunnel yang dijual. Nilainya sama dengan `service` pada PPP secret RouterOS.
 */
enum VpnProtocol: string
{
    case L2tp = 'l2tp';
    case Sstp = 'sstp';

    public function label(): string
    {
        return match ($this) {
            self::L2tp => 'L2TP',
            self::Sstp => 'SSTP',
        };
    }
}
