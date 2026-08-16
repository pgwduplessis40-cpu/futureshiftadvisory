<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\ExternalIssueReview;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

final class ExternalIssueReviewTest extends TestCase
{
    public function test_blocks_unresolved_merge_fields_dated_patches_revenue_cliffs_and_conflicting_funding_claims(): void
    {
        $plan = new BusinessPlan;
        $plan->setRelation('sections', new EloquentCollection([
            new PlanSection([
                'body' => "{{ business_type }}\n\nUpdate Aug 14th 2026\n\nThe business is self-funded and no external capital is anticipated.",
            ]),
        ]));
        $plan->setRelation('budgetRunway', new EntrepreneurBudget([
            'computed' => [
                'monthly_detail' => [
                    ['month' => 12, 'revenue' => 100_000, 'cumulative_cash' => 5_000],
                    ['month' => 13, 'revenue' => 40_000, 'cumulative_cash' => -20_000],
                ],
            ],
        ]));

        $review = (new ExternalIssueReview)->evaluate($plan);

        $this->assertCount(4, $review['blocking_reasons']);
        $this->assertStringContainsString('merge fields', $review['blocking_reasons'][0]);
        $this->assertStringContainsString('dated update', $review['blocking_reasons'][1]);
        $this->assertStringContainsString('Month 13 revenue', $review['blocking_reasons'][2]);
        $this->assertStringContainsString('conflicts with a negative', $review['blocking_reasons'][3]);
    }
}
