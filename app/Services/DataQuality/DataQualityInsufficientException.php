<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Models\Client;
use Throwable;

final class DataQualityInsufficientException extends \RuntimeException
{
    public function __construct(
        public readonly DataQualityScore $score,
        public readonly string $requirement,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? sprintf(
                'Improve data first: data quality is %s; %s is required.',
                $score->label(),
                $this->labelFor($requirement),
            ),
            previous: $previous,
        );
    }

    public static function forDocumentReview(DataQualityScore $score, Throwable $previous): self
    {
        return new self(
            score: $score,
            requirement: Client::DATA_QUALITY_MEDIUM,
            message: 'Improve data first: resolve document verification flags before analysis runs.',
            previous: $previous,
        );
    }

    private function labelFor(string $level): string
    {
        return match ($level) {
            Client::DATA_QUALITY_HIGH => 'High',
            Client::DATA_QUALITY_MEDIUM => 'Medium',
            Client::DATA_QUALITY_LOW => 'Low',
            default => 'Insufficient',
        };
    }
}
