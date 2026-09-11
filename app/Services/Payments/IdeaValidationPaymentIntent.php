<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class IdeaValidationPaymentIntent
{
    public function __construct(
        public string $publishableKey,
        public string $clientSecret,
        public string $paymentIntentRef,
        public bool $fixture = false,
    ) {}
}
