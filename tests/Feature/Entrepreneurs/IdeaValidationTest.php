<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IdeaValidationTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_idea_validation_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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
                DB::statement('REVOKE SELECT ON idea_validations FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE SELECT ON entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_idea_validation_evaluates_with_fake_ai_and_cites_past_plan_patterns(): void
    {
        [$advisor, $profile] = $this->profile();

        $validation = app(IdeaValidationService::class)->evaluate($profile, $this->strongPayload(), $advisor);

        $this->assertSame('fake-ai-client', data_get($validation->ai_evaluation, 'model'));
        $sources = collect(data_get($validation->ai_evaluation, 'attributions', []))->pluck('source_reference');
        $this->assertTrue($sources->contains(fn (string $source): bool => str_starts_with($source, 'past_plan_patterns:')));
        $this->assertSame([], $validation->viability_alerts);
        $this->assertFalse(app(IdeaValidationService::class)->planBuilderUnlocked($profile));
        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->refresh()->stage);
    }

    public function test_viability_alerts_are_informational_and_do_not_open_plan_builder(): void
    {
        [$advisor, $profile] = $this->profile('weak-idea@example.test');

        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'unclear',
            'target_customer' => 'everyone',
            'solution' => 'app',
            'value_proposition' => 'better',
            'demand_signal' => 'none yet',
            'revenue_model' => 'unknown',
        ], $advisor);

        $this->assertNotEmpty($validation->viability_alerts);
        $this->assertFalse((bool) data_get($validation->viability_alerts, '0.blocking'));
        $this->assertSame('informational', data_get($validation->viability_alerts, '0.severity'));
        $this->assertFalse(app(IdeaValidationService::class)->planBuilderUnlocked($profile));
    }

    public function test_advisor_gate_requires_note_before_plan_builder_opens(): void
    {
        [$advisor, $profile] = $this->profile('gate-idea@example.test');
        $validation = app(IdeaValidationService::class)->evaluate($profile, $this->strongPayload(), $advisor);

        $this->expectException(ValidationException::class);
        app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, '   ');
    }

    public function test_advisor_gate_opens_builder_and_records_reviewer(): void
    {
        [$advisor, $profile] = $this->profile('open-idea@example.test');
        $validation = app(IdeaValidationService::class)->evaluate($profile, $this->strongPayload(), $advisor);

        $opened = app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, 'Evidence is enough to start the plan builder.');

        $this->assertInstanceOf(IdeaValidation::class, $opened);
        $this->assertNotNull($opened->advisor_gate_passed_at);
        $this->assertSame($advisor->id, $opened->advisor_gate_passed_by_user_id);
        $this->assertTrue(app(IdeaValidationService::class)->planBuilderUnlocked($profile));
        $this->assertSame(EntrepreneurStage::BUILDING_PHASE_1, $profile->refresh()->stage);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.idea_gate_passed',
            'subject_id' => $validation->id,
        ]);
    }

    public function test_idea_validations_are_profile_scoped_by_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Idea validation RLS assertions require Postgres.');
        }

        [$advisorA, $profileA] = $this->profile('rls-a@example.test');
        [, $profileB] = $this->profile('rls-b@example.test');
        $validationA = app(IdeaValidationService::class)->evaluate($profileA, $this->strongPayload(), $advisorA);
        app(IdeaValidationService::class)->evaluate($profileB, $this->strongPayload(), $advisorA);

        app(RequestContext::class)->apply('advisor', [], (string) $advisorA->id);
        $visible = $this->withRlsRole(fn (): array => DB::table('idea_validations')->pluck('id')->all());

        $this->assertContains($validationA->id, $visible);
        $this->assertCount(1, $visible);
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile}
     */
    private function profile(string $email = 'idea-founder@example.test'): array
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

        return [$advisor, EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Idea Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'A retail operating platform for regional service businesses.',
        ])];
    }

    /**
     * @return array{problem:string,target_customer:string,solution:string,value_proposition:string,demand_signal:string,revenue_model:string}
     */
    private function strongPayload(): array
    {
        return [
            'problem' => 'Regional retailers waste hours reconciling supplier stock signals manually.',
            'target_customer' => 'Owner-operated regional retail stores with three to fifteen staff.',
            'solution' => 'A lightweight demand and supplier reconciliation workflow built around existing spreadsheets.',
            'value_proposition' => 'Stores reduce stockouts and owner admin time without adopting a full ERP.',
            'demand_signal' => 'Eight discovery calls and three paid pilots are already scheduled.',
            'revenue_model' => 'Monthly subscription with an onboarding fee for supplier mapping.',
        ];
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
            GRANT SELECT ON idea_validations TO %1$s;
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
