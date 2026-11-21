<?php

namespace App\Enums;

/**
 * Status gangguan jaringan pada satu ODP.
 */
enum OutageStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
