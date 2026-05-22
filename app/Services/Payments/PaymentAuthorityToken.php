<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentAuthorityToken
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $token,
        public ?string $customerRef,
        public array $metadata = [],
    ) {}
}
