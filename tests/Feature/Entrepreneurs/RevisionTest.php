<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\Revision;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesIdeaReviewEligible;
use Tests\TestCase;

final class RevisionTest extends TestCase
{
    use MakesIdeaReviewEligible, RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_plan_revisions_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON plan_revisions, business_plans, entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_resubmission_creates_revision_and_new_assessment_round(): void
    {
        [$advisor, $plan] = $this->plan();
        $first = app(Assessment::class)->firstPass($plan, $advisor);
        $first->forceFill(['ai_scores' => $this->scoresAt($first, 45)])->save();

        app(Revision::class)->open($plan, $advisor);
        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'market',
            key: 'market-demand',
            title: 'Market demand',
            body: 'The revised plan names customer segments, pilot interviews, demand evidence, price tests, competitor positioning, revenue assumptions, and launch goals with clear milestones.',
            actor: $advisor,
        );

        $revision = app(Revision::class)->submit($plan->refresh(), $advisor);

        $this->assertInstanceOf(PlanRevision::class, $revision);
        $this->assertSame(2, $revision->round);
        $this->assertSame(2, PlanAssessment::query()->where('business_plan_id', $plan->id)->count());
        $this->assertSame(2, $revision->progress_comparison['current_round']);
        $this->assertSame(BusinessPlan::STATUS_ASSESSING, $plan->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_revision_submitted',
            'subject_id' => $revision->id,
        ]);
    }

    public function test_round_comparison_highlights_improvements_and_remaining_gaps(): void
    {
        [$advisor, $plan] = $this->plan('revision-compare-founder@example.test');
        $previous = app(Assessment::class)->firstPass($plan, $advisor);
        $previous->forceFill(['ai_scores' => $this->scoresAt($previous, 50)])->save();
        $current = $this->assessmentRound($previous, 2, [
            1 => 82,
            2 => 55,
            3 => 78,
        ], 75);

        $comparison = app(Revision::class)->compare($previous->refresh(), $current);

        $this->assertSame(1, $comparison['previous_round']);
        $this->assertSame(2, $comparison['current_round']);
        $this->assertGreaterThan(0, $comparison['overall_delta']);
        $this->assertSame('improving', $comparison['trajectory_label']);
        $this->assertSame(1, $comparison['biggest_improvements'][0]['criterion_number']);
        $this->assertTrue(collect($comparison['remaining_gaps'])->contains('criterion_number', 2));
    }

    public function test_trajectory_percent_uses_remaining_opportunity(): void
    {
        [$advisor, $plan] = $this->plan('revision-trajectory-founder@example.test');
        $previous = app(Assessment::class)->firstPass($plan, $advisor);
        $previous->forceFill(['ai_scores' => $this->scoresAt($previous, 50)])->save();
        $current = $this->assessmentRound($previous, 2, [], 75);

        $comparison = app(Revision::class)->compare($previous->refresh(), $current);

        $this->assertSame(50.0, $comparison['trajectory_percent']);
    }

    public function test_plan_revision_rows_are_scoped_by_entrepreneur_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Plan revision RLS assertions require Postgres.');
        }

        [$advisorA, $planA] = $this->plan('revision-rls-a@example.test');
        $revisionA = $this->revisionFor($advisorA, $planA);
        [$advisorB, $planB] = $this->plan('revision-rls-b@example.test');
        $revisionB = $this->revisionFor($advisorB, $planB);

        app(RequestContext::class)->apply('advisor', [], (string) $advisorA->getKey());
        $visibleRevisionIds = $this->withRlsRole(fn (): array => DB::table('plan_revisions')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($revisionA->id, $visibleRevisionIds);
        $this->assertNotContains($revisionB->id, $visibleRevisionIds);
    }

    private function revisionFor(User $advisor, BusinessPlan $plan): PlanRevision
    {
        $first = app(Assessment::class)->firstPass($plan, $advisor);
        $first->forceFill(['ai_scores' => $this->scoresAt($first, 45)])->save();

        return app(Revision::class)->submit($plan->refresh(), $advisor);
    }

    /**
     * @param  array<int, int>  $overrides
     */
    private function assessmentRound(PlanAssessment $previous, int $round, array $overrides, int $defaultScore): PlanAssessment
    {
        return PlanAssessment::query()->create([
            'business_plan_id' => $previous->business_plan_id,
            'round' => $round,
            'rating_framework_id' => $previous->rating_framework_id,
            'ai_scores' => collect($previous->ai_scores)
                ->map(fn (array $row): array => [
                    ...$row,
                    'score' => $overrides[(int) $row['criterion_number']] ?? $defaultScore,
                ])
                ->values()
                ->all(),
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => 'developing',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scoresAt(PlanAssessment $assessment, int $score): array
    {
        return collect($assessment->ai_scores)
            ->map(fn (array $row): array => [
                ...$row,
                'score' => $score,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function plan(string $email = 'revision-founder@example.test'): array
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
            'name' => 'Revision Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Revision concept for regional retail services.',
        ]);
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Retail service operators need clearer goals and legal operating decisions.',
            'target_customer' => 'Regional retail service owners.',
            'solution' => 'A guided plan with market, legal, culture, and financial milestones.',
            'value_proposition' => 'The owner focuses effort and reduces launch risk.',
            'demand_signal' => 'Pilot interviews and customer evidence are complete.',
            'revenue_model' => 'Subscription revenue with onboarding support.',
        ], $advisor);
        app(IdeaValidationService::class)->passAdvisorGate($this->completedIdeaReview($validation), $advisor, 'Ready for revision.');
        $plan = app(PlanBuilder::class)->start($profile, $advisor);

        foreach ([
            ['market', 'market-demand', 'Market demand', 'The industry, location, customer segment, competitors, demand, revenue, and goals are described with pilot evidence.'],
            ['strategy', 'strategy-goals', 'Strategy goals', 'The mission and vision statement, culture, goals and objectives, and unique success factors are connected to milestones.'],
            ['legal_operations', 'legal-environment', 'Legal environment', 'The legal environment, intellectual property, contracts, privacy duties, and means of doing business are listed.'],
            ['financial', 'financial-model', 'Financial model', 'The plan explains pricing, cash needs, margin, revenue, and support required to operate.'],
        ] as [$phase, $key, $title, $body]) {
            app(PlanBuilder::class)->upsertSection(
                plan: $plan,
                phaseKey: $phase,
                key: $key,
                title: $title,
                body: $body,
                actor: $advisor,
            );
        }

        return [$advisor, $plan->refresh()->load('sections')];
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON plan_revisions, business_plans, entrepreneur_profiles TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);

        try {
            return $callback();
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
