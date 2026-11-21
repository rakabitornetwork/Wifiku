<?php

namespace App\Enums;

/**
 * Driver payment gateway yang didukung PaymentGatewayManager.
 */
enum PaymentGatewayDriver: string
{
    case Duitku = 'duitku';
    case Tripay = 'tripay';
    case Midtrans = 'midtrans';
}
