<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\Client;
use App\Models\GovernanceReviewFinding;
use Illuminate\Support\Collection;

/**
 * @phpstan-type GovernanceFindings Collection<int, GovernanceReviewFinding>
 */
final readonly class NpoGovernanceReviewInputs
{
    /**
     * @param  GovernanceFindings  $findings
     */
    public function __construct(
        public Client $client,
        public Collection $findings,
    ) {}
}
