<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Entrepreneurs\BusinessPlanSnapshot;
use App\Services\Entrepreneurs\PlanBudgetFollowUp;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Tests\TestCase;

final class EntrepreneurNavigationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_growth_does_not_repeat_current_release_checks_or_invalidate_saved_reviews(): void
    {
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $advisor = User::factory()->withTwoFactor()->create(['user_type' => 'advisor', 'primary_role' => 'advisor']);
        $advisor->assignRole('advisor');
        $profile = EntrepreneurProfile::query()->create(['name' => 'History navigation fixture', 'email' => 'history@example.test', 'assigned_advisor_id' => $advisor->id, 'stage' => 'idea_validation']);
        $plan = BusinessPlan::query()->create(['entrepreneur_profile_id' => $profile->id, 'title' => 'History performance plan', 'source_type' => 'entrepreneur']);
        $phase = PlanPhase::query()->create(['business_plan_id' => $plan->id, 'key' => 'financial', 'title' => 'Financial', 'position' => 1]);
        for ($index = 0; $index < 16; $index++) {
            PlanSection::query()->create(['business_plan_id' => $plan->id, 'plan_phase_id' => $phase->id,
                'key' => 'section-'.$index, 'title' => 'Operating plan '.$index, 'source_type' => 'entrepreneur',
                'body' => str_repeat('The business records customer demand, delivery commitments, operating capacity and evidence for this service. ', 16).$index]);
        }
        EntrepreneurBudget::query()->create(['business_plan_id' => $plan->id, 'forecast_years' => 1, 'status' => 'partial']);
        $framework = RatingFramework::query()->create(['version' => 1, 'status' => RatingFramework::STATUS_PUBLISHED,
            'production_ready' => true, 'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS, 'published_at' => now()]);
        $criterion = $framework->criteria()->create(['number' => 1, 'name' => 'Market proof', 'weight' => 100,
            'descriptors' => ['strong' => 'Market evidence is clear.'], 'is_placeholder' => false]);
        $snapshot = app(BusinessPlanSnapshot::class)->capture($plan);
        $followUp = app(PlanBudgetFollowUp::class);
        $coherence = ['approval_available' => false, 'findings' => [
            ['category' => 'budget_support', 'severity' => 'review', 'message' => 'Runway needs checking.', 'next_action' => 'Review.'],
        ]];
        $review = ['note' => 'The planned reserve covers the reviewed cash exposure.', 'reviewed_at' => now()->toIso8601String(),
            'reviewed_by_user_id' => $advisor->id, 'findings_hash' => $followUp->findingsHash($followUp->present($coherence)['advisor_reviews']),
            'inputs_hash' => $followUp->snapshotInputsHash($snapshot)];
        $assessment = PlanAssessment::query()->create(['business_plan_id' => $plan->id, 'round' => 1,
            'rating_framework_id' => $framework->id, 'ai_scores' => [['criterion_number' => 1, 'criterion_id' => $criterion->id, 'score' => 80]],
            'plan_snapshot' => $snapshot, 'overall_grade' => 'strong', 'finalised_at' => now(), 'finalised_by_user_id' => $advisor->id,
            'scoring_scope' => ['plan_budget_coherence' => $coherence, 'plan_budget_review' => $review]]);
        $gateReads = 0;
        DB::listen(function (QueryExecuted $query) use (&$gateReads): void {
            if (str_contains($query->sql, 'from "plan_claim_versions"')) {
                $gateReads++;
            }
        });
        $inputs = app(BusinessPlanSnapshot::class)->captureInputs($plan);
        self::assertArrayNotHasKey('issue_readiness', $inputs);
        self::assertSame($followUp->snapshotInputsHash($snapshot), $followUp->snapshotInputsHash($inputs));
        self::assertSame(0, $gateReads, 'Input fingerprints must not evaluate release readiness.');
        $url = route('advisor.entrepreneurs.show', $profile);
        $started = microtime(true);
        $this->actingAsMfa($advisor)->get($url)->assertOk();
        $oneRoundSeconds = microtime(true) - $started;
        $oneRoundGateReads = $gateReads;
        self::assertGreaterThan(0, $oneRoundGateReads);
        app(RequestContext::class)->apply('system', []);
        for ($round = 2; $round <= 25; $round++) {
            $copy = $assessment->replicate(['id', 'created_at', 'updated_at']);
            $copy->forceFill(['round' => $round])->save();
        }
        $gateReads = 0;
        $started = microtime(true);
        $response = $this->actingAsMfa($advisor)->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])->assertOk();
        $manyRoundSeconds = microtime(true) - $started;
        $response->assertJsonCount(25, 'props.entrepreneur.latest_plan.assessment_history')
            ->assertJsonPath('props.entrepreneur.latest_plan.assessment_history.0.round', 25)
            ->assertJsonPath('props.entrepreneur.latest_plan.assessment_history.0.weighted_score', 80)
            ->assertJsonPath('props.entrepreneur.latest_plan.assessment_history.0.score_delta', 0)
            ->assertJsonPath('props.entrepreneur.latest_plan.latest_assessment.plan_budget_coherence.review_completed', true)
            ->assertJsonPath('props.entrepreneur.latest_plan.lender_brief.active', false);
        self::assertSame($oneRoundGateReads, $gateReads, sprintf('Current release checks grew with history: one round %.3fs / %d captures, 25 rounds %.3fs / %d captures.', $oneRoundSeconds, $oneRoundGateReads, $manyRoundSeconds, $gateReads));
    }
}
