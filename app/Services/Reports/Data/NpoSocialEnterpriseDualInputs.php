<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\Client;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;

/**
 * Typed source records required to compose a Social Enterprise Dual Impact report.
 */
final readonly class NpoSocialEnterpriseDualInputs
{
    public function __construct(
        public Client $client,
        public NpoSocialEnterpriseScorecard $scorecard,
        public NpoTensionAnalysis $analysis,
    ) {}
}
