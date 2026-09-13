<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * The original, customer-agreed Idea Validation quote recovered during a
 * staff-reviewed payment reconciliation. Values are fixed-decimal strings so
 * historical money is never reconstructed from the current service rate.
 */
final readonly class IdeaValidationHistoricalQuote
{
    public function __construct(
        public string $amountExGst,
        public string $gstAmount,
        public string $amountIncludingGst,
        public string $currency,
    ) {}
}
