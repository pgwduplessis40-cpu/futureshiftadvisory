<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\Client;
use App\Models\ClientFunderRecord;

/**
 * Resolved, ownership-checked sources for a Funder Accountability report.
 */
final readonly class NpoFunderAccountabilityInputs
{
    public function __construct(
        public Client $client,
        public ClientFunderRecord $record,
    ) {}
}
