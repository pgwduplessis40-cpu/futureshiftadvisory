<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\CoachingSignal;
use App\Models\EntrepreneurProfile;
use App\Models\ReadinessAssessment;
use App\Models\User;
use App\Services\Entrepreneurs\Readiness;
use App\Support\RequestContext;
use Database\Seeders\EntrepreneurReadinessQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_entrepreneur_readiness_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EntrepreneurReadinessQuestionnaireSeeder::class);
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
                DB::statement('REVOKE SELECT ON readiness_assessments FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE SELECT ON entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_readiness_assessment_computes_outcome_and_advances_ready_profiles(): void
    {
        [$advisor, $profile] = $this->profile();

        $assessment = app(Readiness::class)->assess($profile, [
            'problem_clarity' => 5,
            'customer_urgency' => 4,
            'demand_evidence' => 4,
            'runway' => 4,
            'feedback_readiness' => 5,
            'personal_barriers' => [],
        ], $advisor);

        $this->assertSame(ReadinessAssessment::OUTCOME_READY, $assessment->outcome);
        $this->assertGreaterThanOrEqual(78.0, $assessment->score);
        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->refresh()->stage);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.readiness_assessed',
            'subject_id' => $assessment->id,
        ]);
    }

    public function test_develop_first_with_personal_barriers_writes_raw_coaching_signal(): void
    {
        [$advisor, $profile] = $this->profile('barrier-founder@example.test');

        $assessment = app(Readiness::class)->assess($profile, [
            'problem_clarity' => 4,
            'customer_urgency' => 3,
            'runway' => 3,
            'feedback_readiness' => 3,
            'personal_barriers' => ['family capacity', 'financial stress'],
        ], $advisor);

        $this->assertSame(ReadinessAssessment::OUTCOME_DEVELOP_FIRST, $assessment->outcome);
        $this->assertSame(['family capacity', 'financial stress'], $assessment->personal_barriers);
        $this->assertDatabaseHas('coaching_signals', [
            'entrepreneur_profile_id' => $profile->id,
            'signal_type' => CoachingSignal::TYPE_ENTREPRENEUR_PERSONAL_BARRIER,
            'status' => 'detected',
        ]);

        $signal = CoachingSignal::query()->firstOrFail();
        $this->assertTrue((bool) data_get($signal->evidence, 'raw_observation_only'));
        $this->assertFalse((bool) data_get($signal->evidence, 'auto_referral'));
    }

    public function test_entrepreneur_profiles_and_readiness_assessments_are_user_scoped_by_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Entrepreneur RLS assertions require Postgres.');
        }

        [$advisorA, $profileA] = $this->profile('founder-a@example.test');
        [, $profileB] = $this->profile('founder-b@example.test');
        $assessmentA = app(Readiness::class)->assess($profileA, [
            'problem_clarity' => 5,
            'customer_urgency' => 5,
            'runway' => 5,
        ], $advisorA);
        app(Readiness::class)->assess($profileB, [
            'problem_clarity' => 5,
            'customer_urgency' => 5,
            'runway' => 5,
        ], $advisorA);

        app(RequestContext::class)->apply('advisor', [], (string) $advisorA->id);
        $advisorVisibleProfiles = $this->withRlsRole(fn (): array => DB::table('entrepreneur_profiles')->pluck('id')->all());
        $advisorVisibleAssessments = $this->withRlsRole(fn (): array => DB::table('readiness_assessments')->pluck('id')->all());

        $this->assertContains($profileA->id, $advisorVisibleProfiles);
        $this->assertNotContains($profileB->id, $advisorVisibleProfiles);
        $this->assertContains($assessmentA->id, $advisorVisibleAssessments);

        $otherEntrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        app(RequestContext::class)->apply('entrepreneur', [], (string) $otherEntrepreneur->id);
        $otherVisibleProfiles = $this->withRlsRole(fn (): array => DB::table('entrepreneur_profiles')->pluck('id')->all());
        $otherVisibleAssessments = $this->withRlsRole(fn (): array => DB::table('readiness_assessments')->pluck('id')->all());

        $this->assertSame([], $otherVisibleProfiles);
        $this->assertSame([], $otherVisibleAssessments);

        app(RequestContext::class)->apply('entrepreneur', [], (string) $profileA->user_id);
        $selfVisibleProfiles = $this->withRlsRole(fn (): array => DB::table('entrepreneur_profiles')->pluck('id')->all());

        $this->assertContains($profileA->id, $selfVisibleProfiles);
        $this->assertNotContains($profileB->id, $selfVisibleProfiles);
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile}
     */
    private function profile(string $email = 'founder@example.test'): array
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
            'name' => 'Founder Person',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::READINESS,
            'concept_summary' => 'A practical entrepreneur concept.',
        ]);

        return [$advisor, $profile];
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
            GRANT SELECT ON entrepreneur_profiles TO %1$s;
            GRANT SELECT ON readiness_assessments TO %1$s;
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
