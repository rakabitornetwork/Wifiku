<?php

namespace App\Drivers\Payment\Data;

use Carbon\CarbonImmutable;

final readonly class TransactionResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $gatewayRef,
        public ?string $checkoutUrl = null,
        public ?string $qrString = null,
        public ?string $vaNumber = null,
        public int $fee = 0,
        public ?CarbonImmutable $expiresAt = null,
        public array $raw = [],
    ) {}
}
