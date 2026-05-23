<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentChargeRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $clientId,
        public string $proposalId,
        public string $authorityId,
        public string $token,
        public ?string $customerRef,
        public string $amount,
        public string $currency,
        public string $gateway,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}
}
