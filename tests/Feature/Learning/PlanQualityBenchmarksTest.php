<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Learning\Layers\PlanQualityBenchmarks;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlanQualityBenchmarksTest extends TestCase
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

    public function test_plan_quality_benchmarks_are_suppressed_below_cohort_and_candidate_only_at_threshold(): void
    {
        foreach ([62, 66, 78, 84] as $index => $score) {
            $this->planWithAssessment("plan-benchmark-small-{$index}@example.test", $score);
        }

        $benchmarks = app(PlanQualityBenchmarks::class);
        $suppressed = $benchmarks->benchmarkForIndustry('retail');

        $this->assertTrue($suppressed['suppressed']);
        $this->assertSame(5, $suppressed['minimum_cohort']);
        $this->assertArrayNotHasKey('distribution', $suppressed);

        $this->planWithAssessment('plan-benchmark-threshold@example.test', 92);
        $benchmark = $benchmarks->benchmarkForIndustry('retail');

        $this->assertFalse($benchmark['suppressed']);
        $this->assertSame(5, $benchmark['cohort_size']);
        $this->assertSame(5, array_sum($benchmark['distribution']));
        $this->assertTrue($benchmark['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('business_plan_ids', $benchmark);
        $this->assertArrayNotHasKey('values', $benchmark);
        $this->assertArrayNotHasKey('min', $benchmark);
        $this->assertArrayNotHasKey('max', $benchmark);

        $run = $benchmarks->run(windowDays: 365);

        $this->assertSame(1, $run->candidates_created);

        $candidate = LearningUpdate::query()
            ->where('layer_id', PlanQualityBenchmarks::LAYER_ID)
            ->where('source->type', 'plan_quality_benchmarks')
            ->firstOrFail();

        $this->assertSame('review_entrepreneur_guidance_against_plan_quality_benchmark', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame(5, $candidate->evidence['benchmark']['cohort_size']);
        $this->assertTrue($candidate->evidence['benchmark']['privacy']['aggregate_only']);
        $this->assertSame('cohort_guard_aggregate_only_candidate_only', $candidate->evidence['guardrail']);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        $benchmarks->run(windowDays: 365);

        $this->assertSame(1, LearningUpdate::query()->where('source->type', 'plan_quality_benchmarks')->count());
    }

    private function planWithAssessment(string $email, int $score): BusinessPlan
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
            'name' => 'Plan Benchmark Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::LAUNCHED,
            'concept_summary' => 'Retail plan benchmark concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name,
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 1,
            'founding_advisory_payload' => ['industry' => 'retail'],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now(),
        ]);

        $this->assessmentFor($plan, $score);

        return $plan->refresh();
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
                        ['claim' => 'Fixture benchmark score', 'source_reference' => 'test:plan-quality-benchmark'],
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
