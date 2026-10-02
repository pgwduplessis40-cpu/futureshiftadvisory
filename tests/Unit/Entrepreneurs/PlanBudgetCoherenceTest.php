<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\PlanBudgetCoherence;
use Tests\TestCase;

final class PlanBudgetCoherenceTest extends TestCase
{
    public function test_it_reconciles_total_costs_when_the_plan_explicitly_excludes_owner_compensation(): void
    {
        $result = (new PlanBudgetCoherence)->evaluate([
            'budget' => [
                'status' => 'complete',
                'assessment_evidence' => [
                    'monthly_fixed_costs' => [
                        [
                            'label' => 'Operating subscriptions',
                            'amount' => 506,
                            'quantity' => 1,
                            'cadence' => 'monthly',
                        ],
                        [
                            'label' => 'Owner compensation',
                            'amount' => 875,
                            'quantity' => 1,
                            'cadence' => 'weekly',
                        ],
                    ],
                    'computed' => [
                        'monthly_fixed_costs' => 4_298,
                        'input_count' => 0,
                    ],
                ],
            ],
            'phases' => [[
                'sections' => [[
                    'requirement_key' => 'financial-assumptions',
                    'body' => 'Essential operating costs are approximately $600 per month, excluding my own wages of an estimated $3,698 per month.',
                ]],
            ]],
        ]);

        $this->assertSame('met', $result['plan_correlation']['status']);
        $this->assertSame('met', $result['status']);
        $this->assertNotContains(
            'The plan states monthly operating costs of $600, but the budget uses $4,298 of monthly fixed costs.',
            array_column($result['findings'], 'message'),
        );
    }

    public function test_it_prefers_a_formatted_cost_claim_that_explicitly_excludes_owner_wages(): void
    {
        $result = (new PlanBudgetCoherence)->evaluate([
            'budget' => [
                'status' => 'complete',
                'assessment_evidence' => [
                    'monthly_fixed_costs' => [],
                    'computed' => [
                        'monthly_fixed_costs' => 4_298,
                    ],
                ],
            ],
            'phases' => [[
                'sections' => [[
                    'requirement_key' => 'financial-assumptions',
                    'body' => '<p>Earlier note: operating costs are $600 per month.</p><p>Essential operating costs are approximately <strong>$600</strong> per month, excluding my own <em>wages</em> of an estimated <strong>$3,698</strong> per month.</p>',
                ]],
            ]],
        ]);

        $this->assertSame('met', $result['plan_correlation']['status']);
        $this->assertNotContains(
            'The plan states monthly operating costs of $600, but the budget uses $4,298 of monthly fixed costs.',
            array_column($result['findings'], 'message'),
        );
    }
}
