<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurMilestoneAward;
use App\Models\EntrepreneurProfile;
use App\Models\EntrepreneurStreakEvent;
use App\Models\MessageThread;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
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

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.plan.submit'))
            ->assertRedirect(route('portal.entrepreneur.plan.show'));

        $this->assertTrue($firstSubmittedAt?->equalTo($plan->refresh()->submitted_at));
        $this->assertSame(1, EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->id)
            ->where('milestone_key', EntrepreneurMilestones::PLAN_SUBMITTED)
            ->count());
    }

    public function test_enable_reconciles_prior_plan_submitted_with_estimated_timestamp(): void
    {
        [$advisor, , $profile] = $this->profile('reconcile-gamification@example.test');
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

    public function test_enable_reconciles_legacy_numeric_assessment_scores(): void
    {
        [$advisor, , $profile] = $this->profile('legacy-score-gamification@example.test');
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
        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->id,
            'round' => 1,
            'rating_framework_id' => $framework->id,
            'ai_scores' => [80.0, 70.0],
            'advisor_scores' => ['overall' => 8.2, 'note' => 'Legacy summary shape.'],
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

    public function test_entrepreneur_plan_workspace_exposes_enabled_gamification_state(): void
    {
        [, $entrepreneur, $profile] = $this->profile('portal-state-gamification@example.test', gamificationOn: true);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('gamification.enabled', true)
                ->where('gamification.disable_request_requested', false)
                ->where('gamification.disable_request_url', route('portal.entrepreneur.gamification.disable-request', absolute: false))
            );

        $this->assertTrue($profile->refresh()->gamification_on);
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
    private function profile(string $email, bool $gamificationOn = false): array
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
