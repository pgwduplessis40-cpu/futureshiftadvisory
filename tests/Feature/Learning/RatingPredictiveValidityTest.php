<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\ConversionOutcome;
use App\Models\EntrepreneurProfile;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\RatingValidityTest;
use App\Models\User;
use App\Services\Learning\Layers\RatingPredictiveValidity;
use App\Support\RequestContext;
use Carbon\Carbon;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RatingPredictiveValidityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('privacy.min_cohort', 5);
        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_rating_predictive_validity_records_test_and_candidate_only_review(): void
    {
        foreach ([[60, 55], [65, 60], [70, 72], [85, 88], [90, 95]] as $index => [$planScore, $outcomeScore]) {
            $this->outcomeForPlan("rating-validity-{$index}@example.test", $planScore, $outcomeScore);
        }

        $run = app(RatingPredictiveValidity::class)->run(
            windowDays: 365,
            testedAt: Carbon::parse('2026-07-01 06:30:00'),
            period: '2026-H2',
        );

        $this->assertSame(1, $run->candidates_created);

        $validityTest = RatingValidityTest::query()->firstOrFail();
        $this->assertSame('2026-H2', $validityTest->period);
        $this->assertFalse($validityTest->correlation['suppressed']);
        $this->assertSame(5, $validityTest->correlation['cohort_size']);
        $this->assertTrue($validityTest->correlation['privacy']['aggregate_only']);

        $candidate = LearningUpdate::query()
            ->where('layer_id', RatingPredictiveValidity::LAYER_ID)
            ->where('source->type', 'rating_predictive_validity')
            ->firstOrFail();

        $this->assertSame('review_rating_framework_predictive_validity', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame($validityTest->id, $candidate->evidence['rating_validity_test_id']);
        $this->assertSame(5, $candidate->evidence['correlation']['cohort_size']);
        $this->assertSame('candidate_only_no_rating_framework_change', $candidate->evidence['guardrail']);
        $this->assertArrayNotHasKey('values', $candidate->evidence['correlation']);
        $this->assertArrayNotHasKey('client_ids', $candidate->evidence['correlation']);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        app(RatingPredictiveValidity::class)->run(
            windowDays: 365,
            testedAt: Carbon::parse('2026-07-01 06:30:00'),
            period: '2026-H2',
        );

        $this->assertSame(1, RatingValidityTest::query()->count());
        $this->assertSame(1, LearningUpdate::query()->where('source->type', 'rating_predictive_validity')->count());
    }

    private function outcomeForPlan(string $email, int $planScore, int $outcomeScore): ConversionOutcome
    {
        [$profile, $assessment] = $this->planWithAssessment($email, $planScore);

        return ConversionOutcome::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'plan_assessment_id' => $assessment->id,
            'outcome_signal' => [
                'success_score' => $outcomeScore,
                'source' => 'advisor_review',
            ],
            'observed_at' => Carbon::parse('2026-06-15 09:00:00'),
        ]);
    }

    /**
     * @return array{0: EntrepreneurProfile, 1: PlanAssessment}
     */
    private function planWithAssessment(string $email, int $score): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Rating Validity Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'concept_summary' => 'Retail rating validity concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name,
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_LAUNCHED,
            'current_phase' => 1,
            'founding_advisory_payload' => ['industry' => 'retail'],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now()->subMonth(),
        ]);

        return [$profile, $this->assessmentFor($plan, $score)];
    }

    private function assessmentFor(BusinessPlan $plan, int $score): PlanAssessment
    {
        $framework = RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->latest('version')
            ->firstOrFail();

        return PlanAssessment::query()->create([
            'business_plan_id' => $plan->id,
            'round' => 1,
            'rating_framework_id' => $framework->id,
            'ai_scores' => $framework->criteria
                ->map(fn ($criterion): array => [
                    'criterion_id' => $criterion->id,
                    'criterion_number' => $criterion->number,
                    'criterion_name' => $criterion->name,
                    'score' => $score,
                    'rationale' => 'Fixture score.',
                    'attributions' => [
                        ['claim' => 'Fixture score', 'source_reference' => 'test:rating-validity'],
                    ],
                ])
                ->values()
                ->all(),
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => $framework->gradeFor($score),
            'finalised_at' => now(),
        ]);
    }
}
