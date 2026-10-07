<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\PlanBudgetActionMessage;
use App\Services\Entrepreneurs\PlanBudgetActionMapper;
use Tests\TestCase;

final class PlanBudgetActionMessageTest extends TestCase
{
    public function test_it_normalises_and_formats_a_canonical_action_payload(): void
    {
        $assessment = new PlanAssessment;
        $assessment->scoring_scope = [
            'plan_budget_coherence' => [
                'approval_available' => false,
                'actions' => [
                    'not-an-action',
                    [
                        'key' => 'forecast_calendar',
                        'severity' => 'review',
                        'title' => 'Set the forecast start month',
                        'destination' => 'Budget > Financial assumptions',
                        'steps' => ['Choose the first forecast month.', '', 12],
                        'completion' => 'Save the financial assumptions.',
                    ],
                    [
                        'key' => 'FORECAST_CALENDAR',
                        'severity' => 'review',
                        'title' => 'Duplicate action',
                        'destination' => 'Budget > Financial assumptions',
                        'steps' => ['This action must be removed as a duplicate.'],
                        'completion' => 'Save the financial assumptions.',
                    ],
                    [
                        'key' => 'incomplete',
                        'title' => '',
                        'destination' => 'Budget > Financial assumptions',
                        'steps' => ['This action is incomplete.'],
                        'completion' => 'Save the financial assumptions.',
                    ],
                ],
            ],
        ];

        $messages = new PlanBudgetActionMessage(new PlanBudgetActionMapper);
        $actions = $messages->actions($assessment);
        $message = $messages->format($actions);

        $this->assertSame(['forecast_calendar'], array_column($actions, 'key'));
        $this->assertSame(['Choose the first forecast month.', '12'], $actions[0]['steps']);
        $this->assertStringContainsString('1. Set the forecast start month', $message);
        $this->assertStringContainsString('- Choose the first forecast month.', $message);
        $this->assertStringContainsString('- 12', $message);
        $this->assertStringNotContainsString('Duplicate action', $message);
        $this->assertStringNotContainsString('This action is incomplete.', $message);
    }

    public function test_it_returns_no_actions_when_the_assessment_is_approved_or_its_payload_is_not_actionable(): void
    {
        $messages = new PlanBudgetActionMessage(new PlanBudgetActionMapper);

        $noCoherence = new PlanAssessment;
        $noCoherence->scoring_scope = [];
        $this->assertSame([], $messages->actions($noCoherence));

        $approved = new PlanAssessment;
        $approved->scoring_scope = [
            'plan_budget_coherence' => [
                'approval_available' => true,
                'actions' => [],
            ],
        ];
        $this->assertSame([], $messages->actions($approved));

        $invalidActions = new PlanAssessment;
        $invalidActions->scoring_scope = [
            'plan_budget_coherence' => [
                'approval_available' => false,
                'actions' => 'invalid action payload',
            ],
        ];
        $this->assertSame([], $messages->actions($invalidActions));
    }

    public function test_it_maps_saved_legacy_findings_when_the_canonical_actions_are_unavailable(): void
    {
        $assessment = new PlanAssessment;
        $assessment->scoring_scope = [
            'plan_budget_coherence' => [
                'approval_available' => false,
                'actions' => 'invalid action payload',
                'findings' => [
                    ['category' => 'budget_support', 'severity' => 'review', 'message' => 'Verify current records for: forecast_start_month.', 'next_action' => 'Set the forecast month.'],
                    ['category' => 'budget_support', 'severity' => 'review', 'message' => 'Verify current records for: forecast_start_month.', 'next_action' => 'Set the forecast month.'],
                    ['category' => 'other', 'severity' => 'review', 'message' => 'Ignore this unsupported finding.', 'next_action' => 'Ignore it.'],
                    'not-a-finding',
                ],
            ],
        ];

        $messages = new PlanBudgetActionMessage(new PlanBudgetActionMapper);
        $actions = $messages->actions($assessment);

        $this->assertSame(['forecast_calendar'], array_column($actions, 'key'));
        $this->assertSame('Set and confirm the forecast start month', $actions[0]['title']);
        $this->assertStringContainsString('Tick “I have checked Month 1', implode(' ', $actions[0]['steps']));
    }
}
