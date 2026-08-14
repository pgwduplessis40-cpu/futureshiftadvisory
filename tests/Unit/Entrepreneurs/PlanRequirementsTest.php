<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Services\Entrepreneurs\PlanRequirements;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class PlanRequirementsTest extends TestCase
{
    public function test_lender_readability_requirements_are_explicit(): void
    {
        $definitions = PlanRequirements::definitions();

        $this->assertContains('competitor-comparison', array_column($definitions['market']['requirements'], 'key'));
        $this->assertContains('organisation-management', array_column($definitions['strategy']['requirements'], 'key'));
        $this->assertContains('executive-summary', array_column($definitions['financial']['requirements'], 'key'));
    }

    public function test_empty_plan_completion_is_not_complete(): void
    {
        $plan = new BusinessPlan;
        $plan->setRelation('sections', new Collection);
        $plan->setRelation('budgetRunway', null);

        $completion = PlanRequirements::completion($plan);

        $this->assertFalse($completion['complete']);
        $this->assertSame(0, $completion['completed']);
    }
}
