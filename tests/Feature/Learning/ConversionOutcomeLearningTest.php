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
use App\Models\User;
use App\Services\Learning\Layers\ConversionOutcomeLearning;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ConversionOutcomeLearningTest extends TestCase
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

    public function test_conversion_outcome_learning_is_cohort_guarded_and_candidate_only(): void
    {
        foreach ([[60, 55], [65, 60], [70, 72], [85, 88]] as $index => [$planScore, $outcomeScore]) {
            $this->outcomeForPlan("conversion-small-{$index}@example.test", $planScore, $outcomeScore);
        }

        $learning = app(ConversionOutcomeLearning::class);
        $suppressedRun = $learning->run(windowDays: 365);

        $this->assertSame(0, $suppressedRun->candidates_created);
        $this->assertSame(0, LearningUpdate::query()->where('source->type', 'conversion_outcome_learning')->count());

        $this->outcomeForPlan('conversion-threshold@example.test', 90, 95);
        $run = $learning->run(windowDays: 365);

        $this->assertSame(1, $run->candidates_created);

        $candidate = LearningUpdate::query()
            ->where('layer_id', ConversionOutcomeLearning::LAYER_ID)
            ->where('source->type', 'conversion_outcome_learning')
            ->firstOrFail();
        $aggregate = $candidate->evidence['aggregate'];

        $this->assertSame('review_conversion_outcome_guidance_signal', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame(5, $aggregate['cohort_size']);
        $this->assertTrue($aggregate['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('client_ids', $aggregate);
        $this->assertArrayNotHasKey('values', $aggregate);
        $this->assertArrayNotHasKey('min', $aggregate);
        $this->assertArrayNotHasKey('max', $aggregate);
        $this->assertSame('cohort_guard_aggregate_only_candidate_only', $candidate->evidence['guardrail']);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        $learning->run(windowDays: 365);

        $this->assertSame(1, LearningUpdate::query()->where('source->type', 'conversion_outcome_learning')->count());
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
            'observed_at' => now(),
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
            'name' => 'Conversion Outcome Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'concept_summary' => 'Retail conversion outcome concept.',
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
                        ['claim' => 'Fixture score', 'source_reference' => 'test:conversion-outcome'],
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
