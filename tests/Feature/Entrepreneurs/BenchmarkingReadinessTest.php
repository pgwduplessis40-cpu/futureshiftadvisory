<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\RatingFramework;
use App\Models\User;
use App\Notifications\AdvisoryReadinessNotification;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Entrepreneurs\Benchmarking;
use App\Services\Entrepreneurs\LivingPlan;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class BenchmarkingReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_benchmarking_is_suppressed_below_minimum_cohort(): void
    {
        [, $target] = $this->planWithAssessment('benchmark-target@example.test', 72);
        foreach ([62, 66, 78, 84] as $index => $score) {
            $this->planWithAssessment("benchmark-small-{$index}@example.test", $score);
        }

        $benchmark = app(Benchmarking::class)->forPlan($target);

        $this->assertTrue($benchmark['suppressed']);
        $this->assertSame(5, $benchmark['minimum_cohort']);
        $this->assertStringContainsString('Not enough comparable', $benchmark['message']);
        $this->assertArrayNotHasKey('distribution', $benchmark);
        $this->assertArrayNotHasKey('cohort_average_score', $benchmark);
        $this->assertArrayNotHasKey('percentile_band', $benchmark);
        $this->assertArrayNotHasKey('cohort_size', $benchmark);
    }

    public function test_benchmarking_returns_aggregate_only_output_at_minimum_cohort(): void
    {
        [, $target] = $this->planWithAssessment('benchmark-shown@example.test', 82);
        foreach ([58, 64, 76, 88, 92] as $index => $score) {
            $this->planWithAssessment("benchmark-shown-{$index}@example.test", $score);
        }

        $benchmark = app(Benchmarking::class)->forPlan($target);

        $this->assertFalse($benchmark['suppressed']);
        $this->assertSame(5, $benchmark['cohort_size']);
        $this->assertSame(5, array_sum($benchmark['distribution']));
        $this->assertContains($benchmark['percentile_band'], ['top_quartile', 'above_median', 'below_median', 'bottom_quartile']);
        $this->assertTrue($benchmark['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('plan_ids', $benchmark);
        $this->assertArrayNotHasKey('values', $benchmark);
        $this->assertArrayNotHasKey('min', $benchmark);
        $this->assertArrayNotHasKey('max', $benchmark);
    }

    public function test_advisory_readiness_signal_alerts_advisor(): void
    {
        Notification::fake();
        [$advisor, $plan] = $this->planWithAssessment('readiness-founder@example.test', 83);

        $signal = app(AdvisoryReadiness::class)->evaluate($plan, $advisor);

        $this->assertNotNull($signal);
        $this->assertSame(83.0, $signal->score);
        $this->assertNotNull($signal->advisor_notified_at);
        $this->assertSame(EntrepreneurStage::ADVISORY_READY, $plan->entrepreneurProfile->refresh()->stage);
        Notification::assertSentTo($advisor, AdvisoryReadinessNotification::class);
    }

    public function test_living_plan_prompts_quarterly_and_reassesses_with_divergence_flags(): void
    {
        [$advisor, $plan] = $this->planWithAssessment(
            email: 'living-plan-founder@example.test',
            score: 95,
            status: BusinessPlan::STATUS_LAUNCHED,
            withSections: true,
        );
        $livingPlan = app(LivingPlan::class);
        $livingPlan->schedule($plan, now()->subMonths(4));

        $this->assertTrue($livingPlan->duePlans()->contains('id', $plan->id));
        $livingPlan->prompt($plan->refresh(), $advisor);
        PlanSection::query()
            ->where('business_plan_id', $plan->id)
            ->update(['body' => 'Too thin.']);

        $assessment = $livingPlan->reassess($plan->refresh(), $advisor);

        $this->assertSame(2, $assessment->round);
        $this->assertSame(2, PlanAssessment::query()->where('business_plan_id', $plan->id)->count());
        $this->assertNotNull($plan->refresh()->living_plan_last_prompted_at);
        $this->assertNotNull($plan->living_plan_last_assessed_at);
        $this->assertTrue((bool) data_get($plan->living_plan_divergence_flags, 'diverged'));
        $this->assertTrue($plan->living_plan_next_update_at->greaterThan(now()));
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function planWithAssessment(
        string $email,
        int $score,
        string $status = BusinessPlan::STATUS_FINALISED,
        bool $withSections = false,
    ): array {
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
            'name' => 'Benchmark Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::LAUNCHED,
            'concept_summary' => 'Retail plan benchmark concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name,
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => $status,
            'current_phase' => 1,
            'founding_advisory_payload' => ['industry' => 'retail'],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now()->subMonth(),
        ]);

        if ($withSections) {
            $phase = PlanPhase::query()->create([
                'business_plan_id' => $plan->id,
                'key' => 'market',
                'title' => 'Market',
                'position' => 1,
                'depends_on' => [],
                'status' => PlanPhase::STATUS_COMPLETE,
            ]);
            PlanSection::query()->create([
                'business_plan_id' => $plan->id,
                'plan_phase_id' => $phase->id,
                'key' => 'market-demand',
                'title' => 'Market demand',
                'body' => 'The plan describes the industry, location, customer demand, competitors, revenue, and goals with pilot evidence.',
                'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
                'completeness_status' => PlanSection::STATUS_COMPLETE,
                'metadata' => [],
            ]);
        }

        $this->assessmentFor($plan, $score);

        return [$advisor, $plan->refresh()->load('entrepreneurProfile', 'assessments.ratingFramework.criteria')];
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
                        ['claim' => 'Fixture benchmark score', 'source_reference' => 'test:benchmark'],
                    ],
                ])
                ->values()
                ->all(),
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => $framework->gradeFor($score),
        ]);
    }
}
