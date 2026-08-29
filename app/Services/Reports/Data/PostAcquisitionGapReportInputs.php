<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\PostAcquisitionMigration;
use Illuminate\Support\Collection;

/**
 * Resolved persisted sources for a post-acquisition gap report.
 */
final readonly class PostAcquisitionGapReportInputs
{
    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     * @param  list<string>  $completeRequirements
     * @param  list<string>  $missingRequirements
     */
    public function __construct(
        public PostAcquisitionMigration $migration,
        public Client $client,
        public DdEngagement $engagement,
        public ?BusinessPlan $plan,
        public Collection $risks,
        public Collection $integrationPlan,
        public array $completeRequirements,
        public array $missingRequirements,
    ) {}

    public function planIsComplete(): bool
    {
        return $this->missingRequirements === [];
    }
}
