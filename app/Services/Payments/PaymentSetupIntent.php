<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentSetupIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $publishableKey,
        public string $clientSecret,
        public string $setupIntentRef,
        public string $customerRef,
        public array $metadata = [],
    ) {}
}
