<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentAuthorityRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $clientId,
        public string $proposalId,
        public string $type,
        public string $gateway,
        public array $payload,
    ) {}
}
