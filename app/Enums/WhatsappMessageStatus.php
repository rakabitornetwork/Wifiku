<?php

namespace App\Enums;

/**
 * Status pengiriman pesan WhatsApp.
 */
enum WhatsappMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
