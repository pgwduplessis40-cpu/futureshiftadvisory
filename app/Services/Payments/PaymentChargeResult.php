<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentChargeResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gateway,
        public string $gatewayRef,
        public string $status,
        public string $amount,
        public string $currency,
        public ?string $failoverFrom = null,
        public array $metadata = [],
    ) {}

    public function withFailoverFrom(string $gateway): self
    {
        return new self(
            gateway: $this->gateway,
            gatewayRef: $this->gatewayRef,
            status: $this->status,
            amount: $this->amount,
            currency: $this->currency,
            failoverFrom: $gateway,
            metadata: $this->metadata,
        );
    }
}
