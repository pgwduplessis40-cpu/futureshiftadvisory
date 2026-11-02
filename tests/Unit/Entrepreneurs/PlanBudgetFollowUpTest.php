<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\PlanBudgetFollowUp;
use Tests\TestCase;

final class PlanBudgetFollowUpTest extends TestCase
{
    public function test_review_clears_only_advisor_findings_and_is_bound_to_current_inputs(): void
    {
        $service = new PlanBudgetFollowUp;
        $coherence = ['approval_available' => false, 'findings' => [
            ['category' => 'budget_support', 'severity' => 'review', 'message' => 'Runway needs checking.', 'next_action' => 'Review.'],
        ]];
        $before = $service->present($coherence);
        $review = ['note' => 'The planned reserve covers the reviewed cash exposure.', 'reviewed_at' => '2026-11-02', 'reviewed_by_user_id' => 1,
            'findings_hash' => $service->findingsHash($before['advisor_reviews']), 'inputs_hash' => 'current'];
        $approved = $service->present($coherence, $review, 'current');
        $this->assertFalse($before['approval_available']);
        $this->assertTrue($approved['approval_available']);
        $this->assertTrue($approved['review_completed']);
        $this->assertSame(0, $approved['unresolved_count']);
        $this->assertSame('met', $approved['budget_support']['status']);
        $this->assertFalse($service->present($coherence, $review, 'changed')['approval_available']);

        $coherence['findings'][] = ['category' => 'plan_correlation', 'severity' => 'missing', 'message' => 'The plan refers to trademark registration and protection, but the budget does not name a matching cost row.', 'next_action' => 'Add.'];
        $coherence['findings'][] = ['category' => 'budget_support', 'severity' => 'review', 'message' => 'Fixed-cost trace mismatch: $500 versus $100.', 'next_action' => 'Recalculate.'];
        $stillBlocked = $service->present($coherence, $review, 'current');
        $this->assertTrue($stillBlocked['review_completed']);
        $this->assertFalse($stillBlocked['approval_available']);
        $this->assertSame(2, $stillBlocked['unresolved_count']);
        $this->assertCount(1, $stillBlocked['actions']);
        $this->assertCount(1, $stillBlocked['calculation_issues']);

        $coherence['findings'][0]['message'] = 'Different runway risk.';
        $this->assertFalse($service->present($coherence, $review, 'current')['review_completed']);
    }

    public function test_advisories_do_not_prevent_progression_but_unexplained_blocks_need_reassessment(): void
    {
        $service = new PlanBudgetFollowUp;
        $ready = $service->present(['approval_available' => true, 'advisories' => [
            ['key' => 'fixed_cost_sources', 'message' => 'Verify before external issue.', 'next_action' => 'Advisor check.'],
        ]]);
        $this->assertTrue($ready['approval_available']);
        $this->assertSame([], $ready['actions']);
        $this->assertSame(0, $ready['unresolved_count']);

        $historical = $service->present(['approval_available' => false, 'actions' => [['title' => 'Reconcile the regular business costs']]]);
        $this->assertFalse($historical['approval_available']);
        $this->assertSame([], $historical['actions']);
        $this->assertCount(1, $historical['calculation_issues']);
    }
}
