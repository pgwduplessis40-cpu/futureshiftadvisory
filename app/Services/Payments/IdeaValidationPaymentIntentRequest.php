<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class IdeaValidationPaymentIntentRequest
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $purchaseId,
        public string $paymentId,
        public string $clientId,
        public string $customerEmail,
        public string $customerName,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}
}
