<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\Client;
use App\Models\NpoDimensionScore;
use Illuminate\Support\Collection;

/**
 * @phpstan-type NpoDimensionScores Collection<int, NpoDimensionScore>
 */
final readonly class NpoReportInputs
{
    /**
     * @param  NpoDimensionScores  $scores
     */
    public function __construct(
        public Client $client,
        public Collection $scores,
    ) {}
}
