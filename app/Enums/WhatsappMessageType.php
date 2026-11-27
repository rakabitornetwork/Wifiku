<?php

namespace App\Enums;

/**
 * Jenis pesan WhatsApp keluar.
 */
enum WhatsappMessageType: string
{
    case Invoice = 'invoice';
    case Reminder = 'reminder';
    case Receipt = 'receipt';
    case Outage = 'outage';
    case Isolir = 'isolir';
    case Test = 'test';
    case PasswordReset = 'password_reset';
    case AccountSecurity = 'account_security';
    case Chat = 'chat';
}
