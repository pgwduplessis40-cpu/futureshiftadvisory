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

    public function test_blocks_cross_document_scale_pricing_and_fixed_cost_trace_mismatches(): void
    {
        $plan = new BusinessPlan;
        $plan->setRelation('sections', new EloquentCollection([
            new PlanSection([
                'body' => 'The 2026 targets are 8-12 Intensives and $24-36k revenue, with capacity capped at 2-3 Intensives/month. Historical rates were $1,000-1,500/day. Current pricing is $1,500+GST/day. The plan repeats strategic thinking, creative problem-solving and practical implementation. The next section repeats strategic thinking, creative problem-solving and practical implementation.',
            ]),
        ]));
        $plan->setRelation('budgetRunway', new EntrepreneurBudget([
            'computed' => [
                'monthly_fixed_costs' => 51_573,
                'monthly_detail' => [
                    ['month' => 12, 'revenue' => 142_609, 'cumulative_cash' => 80_000],
                    ['month' => 13, 'revenue' => 142_609, 'cumulative_cash' => 90_000],
                ],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Software subscriptions', 'amount' => 8_400, 'quantity' => 1],
                ['label' => 'Owners Compensation- current $550 wk $45,500', 'amount' => 0, 'quantity' => 1],
            ],
        ]));

        $review = (new ExternalIssueReview)->evaluate($plan);

        $blocking = implode("\n", $review['blocking_reasons']);
        $warnings = implode("\n", $review['warnings']);

        $this->assertStringContainsString('Fixed-cost trace does not reconcile', $blocking);
        $this->assertStringContainsString('owner compensation row', $blocking);
        $this->assertStringContainsString('written annual revenue target', $blocking);
        $this->assertStringContainsString('monthly capacity and pricing evidence', $blocking);
        $this->assertStringContainsString('Reconcile historical rates with current pricing', $blocking);
        $this->assertStringContainsString('Repeated narrative phrasing', $warnings);
    }
}
