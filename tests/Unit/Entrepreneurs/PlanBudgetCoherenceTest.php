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

    public function test_it_keeps_unevidenced_cost_and_revenue_estimates_as_non_blocking_advisories(): void
    {
        $result = (new PlanBudgetCoherence)->evaluate([
            'budget' => [
                'status' => 'complete',
                'assessment_evidence' => [
                    'assumptions' => [
                        'opening_cash_balance' => 0,
                    ],
                    'computed' => [
                        'input_count' => 0,
                    ],
                    'flags' => [
                        [
                            'key' => 'fixed_cost_sources_need_verification',
                            'message' => 'Record the source and reference for every fixed-cost line before external issue: Software subscription.',
                        ],
                        [
                            'key' => 'revenue_sources_need_verification',
                            'message' => 'Record the pipeline, contract, or pricing source for every revenue line before external issue: Advisory package.',
                        ],
                    ],
                    'funding_readiness' => [
                        'warnings' => [
                            'Fixed-cost sources need verification: Record the source and reference for every fixed-cost line before external issue: Software subscription.',
                            'Verify the source for every fixed cost before external issue: Software subscription.',
                            'Revenue sources need verification: Record the pipeline, contract, or pricing source for every revenue line before external issue: Advisory package.',
                            'Verify the pipeline, contract, or pricing source for every revenue line before external issue: Advisory package.',
                        ],
                    ],
                ],
            ],
            'phases' => [[
                'sections' => [[
                    'requirement_key' => 'financial-assumptions',
                    'body' => 'Starting cash is $0.',
                ]],
            ]],
        ]);

        $this->assertTrue($result['approval_available']);
        $this->assertSame('met', $result['status']);
        $this->assertSame([], $result['findings']);
        $this->assertSame(['fixed_cost_sources', 'revenue_sources'], array_column($result['advisories'], 'key'));
    }

    public function test_it_groups_repeated_diagnostics_into_three_specific_founder_actions(): void
    {
        $result = (new PlanBudgetCoherence)->evaluate([
            'budget' => [
                'status' => 'complete',
                'assessment_evidence' => [
                    'expected_runway_months' => 12,
                    'monthly_fixed_costs' => [
                        [
                            'label' => 'Commercial kitchen or site cost',
                            'amount' => 850,
                            'cadence' => 'monthly',
                            'cadence_confirmed' => false,
                        ],
                        [
                            'label' => 'Insurance',
                            'amount' => 120,
                            'cadence' => 'monthly',
                            'cadence_confirmed' => false,
                        ],
                    ],
                    'computed' => [
                        'monthly_fixed_costs' => 5_718,
                        'runway_months' => 5,
                        'input_count' => 1,
                    ],
                    'flags' => [
                        [
                            'key' => 'fixed_cost_cadences_need_confirmation',
                            'message' => 'The model is using a monthly equivalent, but each of these costs still needs its billing cadence confirmed: Commercial kitchen or site cost, Insurance.',
                        ],
                        [
                            'key' => 'cash_timing_needs_verification',
                            'message' => 'Verify current records for: forecast_start_month.',
                        ],
                    ],
                    'funding_readiness' => [
                        'required_additional_funding' => 4_686,
                        'warnings' => [
                            'Fixed-cost cadences need confirmation: The model is using a monthly equivalent, but each of these costs still needs its billing cadence confirmed: Commercial kitchen or site cost, Insurance.',
                            'Cash timing needs verification: Verify current records for: forecast_start_month.',
                            'Runway needs checking: The expected runway is more than 2 months away from the budget calculation. Check the assumptions or explain the difference.',
                        ],
                    ],
                ],
            ],
            'phases' => [[
                'sections' => [[
                    'requirement_key' => 'financial-assumptions',
                    'body' => 'Essential operating costs are approximately $600 per month, excluding my own wages of an estimated $3,698 per month. Professional indemnity insurance and trademark registration are required before launch.',
                ]],
            ]],
        ]);

        $this->assertSame(3, $result['unresolved_count']);
        $this->assertSame(
            ['regular_costs', 'forecast_calendar', 'funding_and_runway'],
            array_column($result['actions'], 'key'),
        );
        $this->assertSame(
            'Set the billing cadence for: Commercial kitchen or site cost, Insurance.',
            $result['actions'][0]['steps'][0],
        );
        $this->assertStringContainsString('professional indemnity insurance', $result['actions'][0]['steps'][1]);
        $this->assertStringContainsString('trademark registration and protection', implode(' ', $result['actions'][0]['steps']));
        $this->assertStringContainsString('$5,718', implode(' ', $result['actions'][0]['steps']));
        $this->assertSame(
            'Set and confirm the forecast start month',
            $result['actions'][1]['title'],
        );
        $this->assertStringContainsString('First save the cost and forecast-date changes above', $result['actions'][2]['steps'][0]);
        $this->assertCount(11, $result['findings']);
    }
}
