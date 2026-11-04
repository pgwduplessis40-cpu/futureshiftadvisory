<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Entrepreneurs\BusinessPlanSnapshot;
use App\Services\Entrepreneurs\FounderBriefRelease;
use App\Services\Entrepreneurs\PlanBudgetFollowUp;
use Database\Seeders\RatingFrameworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PlanBudgetApprovalContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_233_decision_is_recognised_after_reassessment_and_warning_copy_changes(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $saved = $original->refresh()->scoring_scope['plan_budget_review'];
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);

        $this->assertTrue($result['review_completed']);
        $this->assertTrue($result['approval_available']);
        $this->assertSame(0, $result['unresolved_count']);
        $this->assertSame($saved['note'], $result['review_note']);
        $this->assertSame($saved['reviewed_at'], $result['reviewed_at']);
        $this->assertSame(1, $result['review_assessment_round']);
        $this->assertSame($saved, $original->refresh()->scoring_scope['plan_budget_review']);
        $this->assertArrayNotHasKey('plan_budget_review', $latest->refresh()->scoring_scope);
        $this->assertEquals(232.73, data_get($plan->budgetRunway->computed, 'monthly_detail.0.cumulative_cash') * -1);

        // Recognition is not completion, a passing assessment, or permission to generate a brief.
        $release = app(FounderBriefRelease::class)->evaluate($plan->refresh(), $plan->entrepreneurProfile);
        $this->assertFalse($release['can_generate']);
        $this->assertFalse($release['active']);
        $latest->forceFill(['finalised_at' => now(), 'overall_grade' => 'strong'])->save();
        $release = app(FounderBriefRelease::class)->evaluate($plan->refresh(), $plan->entrepreneurProfile);
        $this->assertFalse($release['can_generate']);
        $this->assertFalse($release['active']);
    }

    public function test_legacy_decision_on_the_current_assessment_survives_generated_warning_changes(): void
    {
        [, $assessment] = $this->approvedFundingDecision();
        $this->assertTrue(app(PlanBudgetFollowUp::class)->forAssessment($assessment)['review_completed']);
    }

    public function test_corrected_cash_wording_preserves_an_identical_legacy_funding_decision_but_not_a_changed_amount(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $saved = $original->refresh()->scoring_scope['plan_budget_review'];
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $scope = $latest->scoring_scope;
        $scope['plan_budget_coherence']['findings'][0]['message'] = str_replace(' and planned operating buffer', '', $scope['plan_budget_coherence']['findings'][0]['message']);
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $this->assertTrue(app(PlanBudgetFollowUp::class)->forAssessment($latest)['review_completed']);
        $scope['plan_budget_coherence']['findings'][0]['message'] = str_replace('$233', '$234', $scope['plan_budget_coherence']['findings'][0]['message']);
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $this->assertFalse(app(PlanBudgetFollowUp::class)->forAssessment($latest)['review_completed']);
        $this->assertSame($saved, $original->refresh()->scoring_scope['plan_budget_review']);
    }

    /** @return iterable<string,array{string,mixed}> */
    public static function financialChanges(): iterable
    {
        yield 'a cent changes although both amounts display as $233' => ['computed.monthly_detail.0.cumulative_cash', -232.74];
        yield 'opening cash' => ['assumptions.opening_cash_balance', 7532];
        yield 'owner pay' => ['monthly_fixed_costs.0.amount', 875];
        yield 'revenue ramp' => ['revenue_forecast.0.amount', 6000];
        yield 'payment timing' => ['assumptions.receivables_days', 30];
        yield 'funding source' => ['funding_sources.0.amount', 233];
        yield 'forecast horizon' => ['forecast_years', 3];
    }

    #[DataProvider('financialChanges')]
    public function test_changed_financial_basis_reopens_the_decision(string $path, mixed $value): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $budget = $plan->budgetRunway;
        $attributes = $budget->toArray();
        data_set($attributes, $path, $value);
        $field = explode('.', $path)[0];
        $budget->forceFill([$field => $attributes[$field]])->save();
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);
        $this->assertFalse($result['review_completed']);
        $this->assertFalse($result['approval_available']);
        $this->assertStringContainsString('inputs have changed', $result['review_status_message']);
    }

    public function test_a_changed_assessed_revision_cannot_reuse_a_decision_even_if_live_inputs_were_reverted(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $snapshot = app(BusinessPlanSnapshot::class)->capture($plan);
        data_set($snapshot, 'budget.assessment_evidence.assumptions.opening_cash_balance', 9000);
        $latest = $this->nextAssessment($original, $snapshot);
        $this->assertFalse(app(PlanBudgetFollowUp::class)->forAssessment($latest)['review_completed']);
    }

    public function test_new_findings_and_client_corrections_are_not_waived(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $scope = $latest->scoring_scope;
        $scope['plan_budget_coherence']['findings'][] = ['category' => 'plan_correlation', 'severity' => 'missing',
            'message' => 'Update the forecast start month.', 'next_action' => 'Set the forecast calendar.', 'input_key' => 'funding_and_runway'];
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);
        $this->assertTrue($result['review_completed']);
        $this->assertFalse($result['approval_available']);
        $scope['plan_budget_coherence']['findings'][0]['message'] = 'A different funding exposure needs review.';
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);
        $this->assertFalse($result['review_completed']);
        $this->assertStringContainsString('findings have changed', $result['review_status_message']);
    }

    public function test_an_unverifiable_newer_decision_does_not_fall_back_to_an_older_approval(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $scope = $latest->scoring_scope;
        $scope['plan_budget_review'] = [...$original->scoring_scope['plan_budget_review'], 'inputs_hash' => 'unverifiable'];
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);
        $this->assertFalse($result['review_completed']);
        $this->assertStringContainsString('cannot be matched', $result['review_status_message']);
    }

    public function test_approval_is_never_inferred_from_finalisation_or_another_business_plan(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $otherPlan = $plan->replicate();
        $otherPlan->save();
        $assessment = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($otherPlan));
        $assessment->forceFill(['business_plan_id' => $otherPlan->id, 'finalised_at' => now()])->save();
        $this->assertFalse(app(PlanBudgetFollowUp::class)->forAssessment($assessment->refresh())['review_completed']);
    }

    public function test_a_malformed_current_decision_does_not_resurrect_an_earlier_approval(): void
    {
        [$plan, $original] = $this->approvedFundingDecision();
        $latest = $this->nextAssessment($original, app(BusinessPlanSnapshot::class)->capture($plan));
        $scope = $latest->scoring_scope;
        $scope['plan_budget_review'] = 'invalid record';
        $latest->forceFill(['scoring_scope' => $scope])->save();
        $result = app(PlanBudgetFollowUp::class)->forAssessment($latest);
        $this->assertFalse($result['review_completed']);
        $this->assertStringContainsString('saved decision is incomplete', $result['review_status_message']);
    }

    /** @return array{BusinessPlan,PlanAssessment} */
    private function approvedFundingDecision(): array
    {
        $this->seed(RatingFrameworkSeeder::class);
        $advisor = User::factory()->create();
        $profile = EntrepreneurProfile::query()->create(['user_id' => $advisor->id, 'assigned_advisor_id' => $advisor->id, 'name' => 'Approval fixture', 'email' => $advisor->email]);
        $plan = BusinessPlan::query()->create(['entrepreneur_profile_id' => $profile->id, 'title' => 'Approval fixture', 'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR, 'created_by_user_id' => $advisor->id]);
        EntrepreneurBudget::query()->create([
            'business_plan_id' => $plan->id, 'status' => EntrepreneurBudget::STATUS_COMPLETE, 'forecast_years' => 1,
            'expected_runway_months' => 1, 'assumptions' => ['opening_cash_balance' => 7531],
            'monthly_fixed_costs' => [['label' => 'Owner pay', 'amount' => 550]],
            'revenue_forecast' => [['label' => 'Intensives', 'amount' => 3000]],
            'computed' => ['opening_cash_balance' => 7531, 'monthly_fixed_costs' => 550,
                'monthly_detail' => [['month' => 1, 'cumulative_cash' => -232.73]]],
        ]);
        $snapshot = app(BusinessPlanSnapshot::class)->capture($plan);
        // A real legacy decision used the warning copy from the release at approval time.
        data_set($snapshot, 'budget.assessment_evidence.funding_readiness.warnings', ['Earlier source evidence guidance.']);
        $coherence = ['approval_available' => false, 'findings' => [['category' => 'budget_support', 'severity' => 'review',
            'message' => 'The forecast requires $233 of additional funding to cover the modelled cash trough and planned operating buffer.',
            'next_action' => 'Confirm the cash position and funding requirement.']]];
        $followUp = app(PlanBudgetFollowUp::class);
        $assessment = PlanAssessment::query()->create(['business_plan_id' => $plan->id, 'rating_framework_id' => RatingFramework::query()->firstOrFail()->id, 'round' => 1, 'plan_snapshot' => $snapshot, 'ai_scores' => [], 'overall_grade' => 'strong',
            'scoring_scope' => ['plan_budget_coherence' => $coherence, 'plan_budget_review' => [
                'note' => 'Accepted the additional $233 requirement; the founder can cover it.',
                'reviewed_at' => now()->subWeek()->toIso8601String(), 'reviewed_by_user_id' => $advisor->id,
                'findings_hash' => $followUp->findingsHash($followUp->present($coherence)['advisor_reviews']),
                'inputs_hash' => $followUp->snapshotInputsHash($snapshot),
            ]]]);

        return [$plan, $assessment];
    }

    /** @param array<string,mixed> $snapshot */
    private function nextAssessment(PlanAssessment $original, array $snapshot): PlanAssessment
    {
        return PlanAssessment::query()->create(['business_plan_id' => $original->business_plan_id, 'rating_framework_id' => $original->rating_framework_id, 'round' => $original->round + 1, 'ai_scores' => [], 'overall_grade' => 'strong',
            'plan_snapshot' => $snapshot, 'scoring_scope' => ['plan_budget_coherence' => $original->scoring_scope['plan_budget_coherence']]]);
    }
}
