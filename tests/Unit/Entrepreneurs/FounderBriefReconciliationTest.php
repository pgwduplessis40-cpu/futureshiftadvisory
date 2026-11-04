<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\FounderBriefReconciliation;
use App\Services\Entrepreneurs\FounderBriefReconciliationTopics;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class FounderBriefReconciliationTest extends TestCase
{
    public function test_current_complete_review_preserves_sources_and_distinguishes_cash_from_buffer(): void
    {
        [$plan, $profile] = $this->fixture();
        $service = app(FounderBriefReconciliation::class);
        $initial = $service->evaluate($plan, $profile);
        $this->assertFalse($initial['complete']);
        $this->assertCount(11, $initial['topics']);
        $this->assertStringContainsString('MISSION', $initial['sources'][0]['body']);
        $this->assertStringContainsString('Last complete source sentence.', $initial['sources'][0]['body']);
        $facts = collect($initial['topics'])->keyBy('key');
        $this->assertStringContainsString('$875.00 x 1, weekly', implode(' ', $facts['owner_pay']['budget_facts']));
        $this->assertStringContainsString('cash-deficit cover: $0.00', implode(' ', $facts['cash_funding']['budget_facts']));
        $this->assertStringContainsString('Reserve-target shortfall: $2,300.00', implode(' ', $facts['cash_funding']['budget_facts']));
        $this->assertStringContainsString('Proposed external request: Not recorded', implode(' ', $facts['cash_funding']['budget_facts']));
        $this->assertStringContainsString('operating cost', implode(' ', $facts['asset_treatment']['budget_facts']));
        $this->complete($plan, $initial['source_token']);
        $this->assertTrue($service->evaluate($plan, $profile)['complete']);
    }

    public function test_source_edits_require_new_decisions_but_summary_generation_does_not(): void
    {
        foreach (['body', 'evidence', 'empty_response_evidence', 'budget', 'summary'] as $change) {
            [$plan, $profile] = $this->fixture();
            $service = app(FounderBriefReconciliation::class);
            $this->complete($plan, $service->evaluate($plan, $profile)['source_token']);
            match ($change) {
                'body' => $plan->sections[0]->body .= ' Revised assumptions.',
                'evidence' => $plan->sections[0]->attached_document_ids = ['new-record'],
                'empty_response_evidence' => $plan->sections->push((new PlanSection(['key' => 'industry-context', 'body' => '', 'attached_document_ids' => ['new-record']]))->setRelation('phase', null)),
                'budget' => $plan->budgetRunway->forecast_years = 3,
                'summary' => $plan->sections->push((new PlanSection(['key' => BusinessPlanExecutiveSummary::SECTION_KEY, 'title' => 'Executive summary', 'body' => 'New derived summary.']))->setRelation('phase', null)),
            };
            $status = $service->evaluate($plan, $profile);
            $this->assertSame($change === 'summary', $status['complete'], $change);
            if ($change !== 'summary') {
                $this->assertSame(['unresolved'], array_values(array_unique(array_column($status['topics'], 'state'))));
                $this->assertStringContainsString('Dated records', $status['topics'][0]['note']);
            }
        }
    }

    public function test_missing_decisions_and_short_explanations_cannot_complete_a_review(): void
    {
        [$plan, $profile] = $this->fixture();
        $service = app(FounderBriefReconciliation::class);
        $token = $service->evaluate($plan, $profile)['source_token'];
        foreach ([['state' => 'explained', 'note' => 'OK'], ['state' => 'unresolved', 'note' => 'Dated records still need checking.']] as $decision) {
            $this->complete($plan, $token);
            $scope = $plan->assessments[0]->scoring_scope;
            $scope['financial_reconciliation']['decisions']['revenue_scope'] = $decision;
            $plan->assessments[0]->scoring_scope = $scope;
            $this->assertFalse($service->evaluate($plan, $profile)['complete']);
        }
        $scope['financial_reconciliation']['decisions'] = [];
        $plan->assessments[0]->scoring_scope = $scope;
        $this->assertFalse($service->evaluate($plan, $profile)['complete']);
    }

    private function complete(BusinessPlan $plan, string $token): void
    {
        $plan->assessments[0]->scoring_scope = ['financial_reconciliation' => [
            'source_token' => $token, 'as_of' => '2026-11-03', 'saved_at' => '2026-11-03T10:00:00Z',
            'decisions' => array_fill_keys(array_keys(FounderBriefReconciliationTopics::all()), ['state' => 'aligned', 'note' => 'Dated records support these agreed assumptions.']),
        ]];
    }

    /** @return array{BusinessPlan,EntrepreneurProfile} */
    private function fixture(): array
    {
        $plan = (new BusinessPlan)->setRelation('sections', new Collection([
            (new PlanSection(['key' => 'financial-assumptions', 'body' => '𝗠𝗜𝗦𝗦𝗜𝗢𝗡 '.str_repeat('A complete source sentence. ', 80).'Last complete source sentence.']))->setRelation('phase', null),
        ]))->setRelation('assessments', new Collection([new PlanAssessment(['round' => 1])]))
            ->setRelation('budgetRunway', new EntrepreneurBudget([
                'forecast_years' => 1, 'expected_runway_months' => 1,
                'monthly_fixed_costs' => [['label' => 'Owner pay', 'amount' => 875, 'cadence' => 'weekly']],
                'future_costs' => [['label' => 'Vehicle', 'amount' => 18000, 'year' => 2, 'classification' => 'operating']],
                'computed' => ['monthly_fixed_costs' => 1000, 'opening_cash_balance' => 1000, 'total_funding' => 0, 'monthly_detail' => [['month' => 1, 'revenue' => 3000, 'cumulative_cash' => 2000]]],
            ]));

        return [$plan, new EntrepreneurProfile(['name' => 'Example founder'])];
    }
}
