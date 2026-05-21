<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Models\Client;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Documents\DocumentVerificationGate;

final class Gate
{
    public function __construct(
        private readonly DataQualityScorer $scorer,
        private readonly DocumentVerificationGate $documentGate,
    ) {}

    public function assertSufficient(Client|string $client, string $requirement = Client::DATA_QUALITY_MEDIUM): DataQualityScore
    {
        $client = $client instanceof Client
            ? $client
            : Client::query()->findOrFail($client);

        $score = $this->scorer->score($client);

        try {
            $this->documentGate->ensureClear($client);
        } catch (DocumentVerificationBlockedException $e) {
            throw DataQualityInsufficientException::forDocumentReview($score, $e);
        }

        if ($this->rank($score->level) < $this->rank($requirement)) {
            throw new DataQualityInsufficientException($score, $requirement);
        }

        return $score;
    }

    private function rank(string $level): int
    {
        return match ($level) {
            Client::DATA_QUALITY_HIGH => 3,
            Client::DATA_QUALITY_MEDIUM => 2,
            Client::DATA_QUALITY_LOW => 1,
            default => 0,
        };
    }
}
