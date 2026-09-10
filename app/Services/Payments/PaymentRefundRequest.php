<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentRefundRequest
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $paymentReference,
        public string $idempotencyKey,
        public int|float|string $amount,
        public string $currency,
        public array $metadata = [],
    ) {}
}
