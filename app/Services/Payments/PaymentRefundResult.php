<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentRefundResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gateway,
        public string $gatewayRef,
        public string $status,
        public int|float|string $amount,
        public string $currency,
        public array $metadata = [],
    ) {}
}
