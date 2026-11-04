<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\User;

final readonly class AssessmentGate
{
    public function __construct(private ReleaseGate $gate) {}

    /** @return array{run_id:string,source_hash:string,configuration_hash:string,status:string} */
    public function capture(BusinessPlan $plan, ?User $actor): array
    {
        $report = $this->gate->evaluate($plan, 'plan');
        $run = $this->gate->record($plan, $report, $actor);

        return ['run_id' => (string) $run->getKey(), 'source_hash' => $report->context->source->hash(),
            'configuration_hash' => $report->configuration->hash(), 'status' => 'draft'];
    }
}
