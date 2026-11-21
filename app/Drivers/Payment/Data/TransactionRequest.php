<?php

namespace App\Drivers\Payment\Data;

final readonly class TransactionRequest
{
    public function __construct(
        public string $merchantRef,
        public int $amount,
        public string $channel,
        public string $customerName,
        public string $customerPhone,
        public string $description,
        public string $callbackUrl,
        public string $returnUrl,
        public ?string $customerEmail = null,
        public int $expiresInMinutes = 1440,
    ) {}
}
