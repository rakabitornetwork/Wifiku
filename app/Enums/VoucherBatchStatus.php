<?php

namespace App\Enums;

/**
 * Status proses generate batch voucher di Queue.
 */
enum VoucherBatchStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
