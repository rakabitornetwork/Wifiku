<?php

namespace App\Drivers\Payment\Data;

use App\Enums\PaymentStatus;

final readonly class CallbackResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $merchantRef,
        public PaymentStatus $status,
        public int $amount,
        public ?string $gatewayRef = null,
        public array $raw = [],
    ) {}
}
