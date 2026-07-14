<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurMilestoneAward;
use App\Models\EntrepreneurPointEvent;
use App\Models\EntrepreneurProfile;
use App\Models\EntrepreneurStreakEvent;
use App\Models\MessageThread;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_submit_sets_submitted_at_once_and_awards_plan_submitted_when_enabled(): void
    {
        [$advisor, $entrepreneur, $profile] = $this->profile('submit-gamification@example.test', gamificationOn: true);
        $this->openIdeaGate($profile, $advisor);
        $plan = app(PlanBuilder::class)->start($profile, $entrepreneur);
        $this->completePlan($plan, $entrepreneur);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.plan.submit'))
            ->assertRedirect(route('portal.entrepreneur.plan.show'));

        $plan->refresh();
        $firstSubmittedAt = $plan->submitted_at?->copy();

        $this->assertSame(BusinessPlan::STATUS_SUBMITTED, $plan->status);
        $this->assertNotNull($firstSubmittedAt);
        $this->assertDatabaseHas('entrepreneur_milestone_awards', [
            'entrepreneur_profile_id' => $profile->id,
            'milestone_key' => EntrepreneurMilestones::PLAN_SUBMITTED,
            'evidence_source_id' => $plan->id,
        ]);
        $this->assertDatabaseHas('entrepreneur_point_events', [
            'entrepreneur_profile_id' => $profile->id,
            'milestone_key' => EntrepreneurMilestones::PLAN_SUBMITTED,
            'points' => 150,
        ]);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.plan.submit'))
            ->assertRedirect(route('portal.entrepreneur.plan.show'));

        $this->assertTrue($firstSubmittedAt?->equalTo($plan->refresh()->submitted_at));
        $this->assertSame(1, EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::PLAN_SUBMITTED)
            ->count());
        $this->assertSame(1, EntrepreneurPointEvent::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::PLAN_SUBMITTED)
            ->count());
    }

    public function test_enable_reconciles_prior_plan_submitted_with_estimated_timestamp(): void
    {
        [$advisor, , $profile] = $this->profile('reconcile-gamification@example.test', gamificationOn: false);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Prior submitted plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'created_by_user_id' => $advisor->id,
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.gamification.update', $profile), [
                'enabled' => true,
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile));

        $award = EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::PLAN_SUBMITTED)
            ->firstOrFail();

        $this->assertTrue($profile->refresh()->gamification_on);
        $this->assertSame($plan->id, $award->evidence_source_id);
        $this->assertTrue((bool) data_get($award->evidence_snapshot, 'earned_at_estimated'));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.gamification.update', $profile), [
                'enabled' => false,
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile));

        $this->assertFalse($profile->refresh()->gamification_on);
        $this->assertSame(0, $profile->current_streak);
    }

    public function test_enable_reconciles_canonical_assessment_scores(): void
    {
        [$advisor, , $profile] = $this->profile('legacy-score-gamification@example.test', gamificationOn: false);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Legacy scored plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'created_by_user_id' => $advisor->id,
        ]);
        $framework = RatingFramework::query()->create([
            'version' => 101,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'production_ready' => true,
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
            'published_at' => now(),
        ]);
        $framework->criteria()->createMany([
            [
                'number' => 1,
                'name' => 'Concept',
                'weight' => 50,
                'descriptors' => [],
                'industry_variants' => [],
                'is_placeholder' => false,
            ],
            [
                'number' => 2,
                'name' => 'Evidence',
                'weight' => 50,
                'descriptors' => [],
                'industry_variants' => [],
                'is_placeholder' => false,
            ],
        ]);
        $framework->load('criteria');
        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->id,
            'round' => 1,
            'rating_framework_id' => $framework->id,
            'ai_scores' => $framework->criteria
                ->map(fn ($criterion): array => [
                    'criterion_id' => (string) $criterion->id,
                    'criterion_number' => (int) $criterion->number,
                    'criterion_name' => (string) $criterion->name,
                    'score' => (int) ($criterion->number === 1 ? 80 : 70),
                    'weight' => (float) $criterion->weight,
                    'rationale' => 'Canonical score row for gamification fixture.',
                    'attributions' => [],
                ])
                ->values()
                ->all(),
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => 'strong',
            'finalised_at' => now(),
            'finalised_by_user_id' => $advisor->id,
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.gamification.update', $profile), [
                'enabled' => true,
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile));

        $award = EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::FIRST_ASSESSMENT)
            ->firstOrFail();

        $this->assertTrue($profile->refresh()->gamification_on);
        $this->assertSame($assessment->id, $award->evidence_source_id);
        $this->assertSame(75.0, (float) data_get($award->evidence_snapshot, 'weighted_score'));
    }

    public function test_submitted_at_database_guard_rejects_direct_insert(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('submitted_at trigger coverage requires Postgres.');
        }

        [$advisor, , $profile] = $this->profile('guard-gamification@example.test');

        $this->expectException(QueryException::class);

        BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Inserted submitted plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'created_by_user_id' => $advisor->id,
        ]);
    }

    public function test_submitted_at_database_guard_rejects_invalid_transition(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('submitted_at trigger coverage requires Postgres.');
        }

        [$advisor, , $profile] = $this->profile('transition-guard-gamification@example.test');

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Advanced plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_ASSESSING,
            'created_by_user_id' => $advisor->id,
        ]);

        $this->expectException(QueryException::class);

        $plan->forceFill([
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ])->save();
    }

    public function test_streak_counts_material_section_changes_without_counting_noop_resaves(): void
    {
        [$advisor, $entrepreneur, $profile] = $this->profile('streak-gamification@example.test', gamificationOn: true);
        $this->openIdeaGate($profile, $advisor);
        $plan = app(PlanBuilder::class)->start($profile, $entrepreneur);

        app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'foundation',
            key: 'founder-foundation-business-type-location',
            title: 'Business type, location, and operating model',
            body: 'A regional mobile service business with a clear operating model, launch location, and founder accountability rhythm.',
            actor: $entrepreneur,
            metadata: ['requirement_key' => 'business-type-location'],
        );

        $this->assertSame(1, EntrepreneurStreakEvent::query()->where('entrepreneur_profile_id', $profile->id)->count());
        $this->assertSame(1, $profile->refresh()->current_streak);

        app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'foundation',
            key: 'founder-foundation-business-type-location',
            title: 'Business type, location, and operating model',
            body: 'A regional mobile service business with a clear operating model, launch location, and founder accountability rhythm.',
            actor: $entrepreneur,
            metadata: ['requirement_key' => 'business-type-location'],
        );

        $this->assertSame(1, EntrepreneurStreakEvent::query()->where('entrepreneur_profile_id', $profile->id)->count());

        app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'foundation',
            key: 'founder-foundation-business-type-location',
            title: 'Business type, location, and operating model',
            body: 'A regional mobile service business with a clear operating model, launch location, founder accountability rhythm, supplier cadence, weekly review, and customer booking rules.',
            actor: $entrepreneur,
            metadata: ['requirement_key' => 'business-type-location'],
        );

        $this->assertSame(2, EntrepreneurStreakEvent::query()->where('entrepreneur_profile_id', $profile->id)->count());
        $this->assertSame(1, $profile->refresh()->current_streak);
    }

    public function test_scheduled_streak_recompute_clears_lapsed_streaks(): void
    {
        try {
            $this->travelTo('2026-07-01 09:00:00');

            [$advisor, $entrepreneur, $profile] = $this->profile('streak-recompute-gamification@example.test', gamificationOn: true);
            $this->openIdeaGate($profile, $advisor);
            $plan = app(PlanBuilder::class)->start($profile, $entrepreneur);

            app(PlanBuilder::class)->upsertSection(
                plan: $plan,
                phaseKey: 'foundation',
                key: 'founder-foundation-business-type-location',
                title: 'Business type, location, and operating model',
                body: 'A focused founder update with enough meaningful words to count toward a daily planning streak.',
                actor: $entrepreneur,
                metadata: ['requirement_key' => 'business-type-location'],
            );

            $this->assertSame(1, $profile->refresh()->current_streak);

            $this->travelTo('2026-07-03 00:20:00');

            $this->artisan('entrepreneurs:recompute-streaks')
                ->assertSuccessful();

            $this->assertSame(0, $profile->refresh()->current_streak);
        } finally {
            $this->travelBack();
        }
    }

    public function test_entrepreneur_plan_workspace_exposes_enabled_gamification_state(): void
    {
        [, $entrepreneur, $profile] = $this->profile('portal-state-gamification@example.test', gamificationOn: true);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('gamification.enabled', true)
                ->where('gamification.points.total', 0)
                ->where('gamification.disable_request_requested', false)
                ->where('gamification.disable_request_url', route('portal.entrepreneur.gamification.disable-request', absolute: false))
            );

        $this->assertTrue($profile->refresh()->gamification_on);
    }

    public function test_existing_milestone_awards_receive_points_once_when_the_journey_is_opened(): void
    {
        [, $entrepreneur, $profile] = $this->profile('historical-points@example.test', gamificationOn: true);

        EntrepreneurMilestoneAward::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'milestone_key' => EntrepreneurMilestones::IDEA_VALIDATED,
            'evidence_source_type' => 'idea_validation',
            'evidence_source_id' => 'historic-idea-validation',
            'evidence_snapshot' => ['earned_at_estimated' => true],
            'earned_at' => now()->subDay(),
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('gamification.points.total', 100)
                ->where('gamification.points.milestone_count', 1)
                ->where('gamification.next_quest.key', 'phase_1')
            );

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk();

        $this->assertSame(1, EntrepreneurPointEvent::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::IDEA_VALIDATED)
            ->count());
    }

    public function test_entrepreneur_can_request_gamification_disablement_without_switching_it_off(): void
    {
        [, $entrepreneur, $profile] = $this->profile('disable-request-gamification@example.test', gamificationOn: true);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.gamification.disable-request'))
            ->assertRedirect(route('portal.entrepreneur.plan.show'))
            ->assertSessionHas('status', 'entrepreneur-gamification-disable-requested');

        $profile->refresh();
        $this->assertTrue($profile->gamification_on);

        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', 'Gamification disable request')
            ->firstOrFail();

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->getKey(),
            'sender_user_id' => $entrepreneur->getKey(),
            'body' => 'I would like to request that gamification be disabled for my entrepreneur portal.',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'gamification.disable_requested',
            'subject_id' => $profile->getKey(),
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('gamification.enabled', true)
                ->where('gamification.disable_request_requested', true)
                ->where('gamification.disable_request_thread_url', route('portal.messages.show', $thread, absolute: false))
            );
    }

    /**
     * @return array{0: User, 1: User, 2: EntrepreneurProfile}
     */
    private function profile(string $email, bool $gamificationOn = true): array
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
            'name' => 'Gamification Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'A practical gamification test concept.',
            'gamification_on' => $gamificationOn,
        ]);

        return [$advisor, $entrepreneur, $profile];
    }

    private function openIdeaGate(EntrepreneurProfile $profile, User $advisor): void
    {
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Regional operators need clearer workflow accountability before growth.',
            'target_customer' => 'Owner-operated service businesses with small teams.',
            'solution' => 'A five-phase planning and accountability system.',
            'value_proposition' => 'The owner can focus effort before committing expensive resources.',
            'demand_signal' => 'Four discovery calls and two pilot letters are recorded.',
            'revenue_model' => 'Monthly advisory subscription with milestone-based support.',
        ], $advisor);

        app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, 'Ready to start planning.');
    }

    private function completePlan(BusinessPlan $plan, User $actor): void
    {
        foreach (PlanRequirements::definitions() as $phaseKey => $definition) {
            foreach ($definition['requirements'] as $requirement) {
                if (($requirement['type'] ?? null) === 'budget') {
                    app(EntrepreneurBudgetService::class)->update($plan, [
                        'expected_runway_months' => 12,
                        'forecast_years' => 3,
                        'assumptions' => [
                            'revenue_growth_percent' => 12,
                            'cost_inflation_percent' => 3,
                            'target_gross_profit_percent' => 55,
                            'target_net_profit_before_tax_percent' => 10,
                            'target_net_profit_after_tax_percent' => 7,
                        ],
                        'launch_costs' => [
                            ['label' => 'Launch setup', 'amount' => 5_000, 'quantity' => 1],
                        ],
                        'monthly_fixed_costs' => [
                            ['label' => 'Core operating tools', 'amount' => 1_200, 'quantity' => 1],
                        ],
                        'revenue_forecast' => [
                            ['label' => 'Pilot subscriptions', 'amount' => 2_500, 'quantity' => 2, 'month' => 1, 'monthly_growth_percent' => 0, 'variable_cost_percent' => 12],
                        ],
                        'funding_sources' => [
                            ['label' => 'Founder cash', 'amount' => 25_000, 'quantity' => 1],
                        ],
                    ], $actor);

                    continue;
                }

                app(PlanBuilder::class)->upsertSection(
                    plan: $plan,
                    phaseKey: $phaseKey,
                    key: 'founder-'.$phaseKey.'-'.$requirement['key'],
                    title: $requirement['title'],
                    body: 'This completed section records customer evidence, operating choices, launch constraints, financial assumptions, and advisor-ready next steps for testing.',
                    actor: $actor,
                    metadata: ['requirement_key' => $requirement['key']],
                );
            }
        }
    }
}
