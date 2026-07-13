<?php

declare(strict_types=1);

namespace App\Services\QuoteSources;

use App\Models\IntegrationScope;
use App\Models\QuoteSourceExtraction;
use InvalidArgumentException;

final class IntegrationScopeQuoteSourceGate
{
    public function assertReady(IntegrationScope $scope): void
    {
        $extractions = QuoteSourceExtraction::query()
            ->where('scopeable_type', $scope->getMorphClass())
            ->where('scopeable_id', $scope->getKey())
            ->get();

        foreach ($extractions as $extraction) {
            if ($extraction->status === QuoteSourceExtraction::STATUS_PENDING) {
                throw new InvalidArgumentException('The external implementation plan is still being prepared for quote review.');
            }

            if ($extraction->status === QuoteSourceExtraction::STATUS_BLOCKED) {
                throw new InvalidArgumentException($extraction->blocked_reason ?: 'Resolve the external implementation plan before creating a fee calculation.');
            }

            $pendingRows = collect($extraction->extracted_rows ?? [])
                ->contains(static fn (mixed $row): bool => is_array($row) && ($row['review_status'] ?? 'pending') === 'pending');

            if ($pendingRows) {
                throw new InvalidArgumentException('Review the extracted implementation-plan scope rows before creating a fee calculation.');
            }
        }
    }
}
