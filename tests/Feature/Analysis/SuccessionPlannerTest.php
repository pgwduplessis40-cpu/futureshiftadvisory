<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\EconomicIndicator;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\SuccessionPlan;
use App\Models\User;
use App\Services\Analysis\SuccessionPlanner;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SuccessionPlannerTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_succession_plans_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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
                DB::statement('REVOKE SELECT ON succession_plans FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_succession_plan_records_readiness_options_and_target_exit_pv(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientWithQuestionnaire('Succession PV Limited', $advisor);
        $ocr = $this->indicator(EconomicIndicator::OCR, 'Official Cash Rate', 5.5, 'percent');

        $run = app(SuccessionPlanner::class)->plan($client, [
            'owner_readiness_score' => 7,
            'management_depth_score' => 7,
            'process_documentation_score' => 6,
            'financial_readiness_score' => 8,
            'exit_timeline_score' => 5,
            'target_exit_annual_cash_flow' => 120000,
            'duration_years' => 3,
            'target_exit_growth_rate' => 0.03,
            'options' => [
                [
                    'name' => 'Trade sale',
                    'fit_score' => 7,
                    'rationale' => 'Strongest option once owner dependency is reduced.',
                ],
            ],
        ], [
            'actor' => $advisor,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(AnalysisModule::Succession, $run->module);
        $this->assertSame([AnalysisLens::Predictive->value, AnalysisLens::Prescriptive->value], $run->framework_lenses);

        $plan = SuccessionPlan::query()->firstOrFail();
        $this->assertSame($client->id, $plan->client_id);
        $this->assertSame(7, $plan->exit_readiness_score);
        $this->assertSame('Trade sale', $plan->options[0]['name']);
        $this->assertSame(7, $plan->options[0]['fit_score']);
        $this->assertFalse($plan->owner_readiness_is_primary_constraint);
        $this->assertNotNull($plan->target_exit_pv_calculation_id);
        $this->assertGreaterThan(0, $plan->target_exit_pv);
        $this->assertSame("economic_indicator:{$ocr->id}", $plan->targetExitPvCalculation->source_attributions[0]['source_reference']);
        $this->assertDatabaseCount('coaching_signals', 0);
    }

    public function test_owner_readiness_primary_constraint_records_raw_coaching_signal_only(): void
    {
        $advisor = $this->advisor('owner-constraint@example.test');
        $client = $this->clientWithQuestionnaire('Owner Constraint Limited', $advisor);

        app(SuccessionPlanner::class)->plan($client, [
            'owner_readiness_score' => 3,
            'management_depth_score' => 8,
            'process_documentation_score' => 7,
            'financial_readiness_score' => 8,
            'exit_timeline_score' => 7,
            'target_exit_annual_cash_flow' => 90000,
            'duration_years' => 2,
        ], [
            'actor' => $advisor,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $plan = SuccessionPlan::query()->firstOrFail();
        $this->assertTrue($plan->owner_readiness_is_primary_constraint);
        $this->assertSame(6, $plan->exit_readiness_score);
        $this->assertTrue($plan->owner_dependency_plan['owner_readiness_is_primary_constraint']);

        $signal = CoachingSignal::query()->firstOrFail();
        $this->assertSame($client->id, $signal->client_id);
        $this->assertSame(CoachingSignal::TYPE_OWNER_READINESS_PRIMARY_CONSTRAINT, $signal->signal_type);
        $this->assertSame('succession_plan', $signal->evidence['source']);
        $this->assertSame($plan->id, $signal->evidence['succession_plan_id']);
        $this->assertSame(3, $signal->evidence['owner_readiness_score']);
        $this->assertTrue($signal->evidence['raw_observation_only']);
        $this->assertFalse($signal->evidence['auto_referral']);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'coaching_signal.raw_observation_recorded',
            'subject_type' => CoachingSignal::class,
            'subject_id' => $signal->id,
            'client_id' => $client->id,
        ]);
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertDatabaseCount('red_flags', 0);
    }

    public function test_succession_plans_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Succession-plan RLS assertions require Postgres.');
        }

        $clientA = $this->clientWithQuestionnaire('Succession A Limited');
        $clientB = $this->clientWithQuestionnaire('Succession B Limited');
        $planA = $this->storedPlan($clientA, 7);
        $planB = $this->storedPlan($clientB, 4);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('succession_plans')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($planA->id, $visibleIds);
        $this->assertNotContains($planB->id, $visibleIds);
    }

    private function advisor(string $email = 'succession-advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientWithQuestionnaire(string $name, ?User $advisor = null): Client
    {
        $advisor ??= $this->advisor('advisor-'.Str::lower(Str::random(8)).'@example.test');

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        [$questionnaire, $question] = $this->questionnaireWithQuestion();
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $advisor->getKey(),
        ]);
        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Succession planning evidence is ready for advisor review.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestion(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo54-'.Str::lower(Str::random(8)),
            'title' => 'WO-54 Succession Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Succession',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'Which succession assumptions are current?',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function indicator(string $indicator, string $label, float $value, string $unit): EconomicIndicator
    {
        return EconomicIndicator::query()->create([
            'indicator' => $indicator,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'period_date' => now()->toDateString(),
            'source' => 'fixture',
            'source_badge' => 'fixture',
            'degraded' => false,
            'fetched_at' => now(),
            'payload' => ['fixture' => true],
        ]);
    }

    private function storedPlan(Client $client, int $score): SuccessionPlan
    {
        app(RequestContext::class)->apply('system', []);

        return SuccessionPlan::query()->create([
            'client_id' => $client->id,
            'exit_readiness_score' => $score,
            'options' => [['name' => 'Trade sale', 'fit_score' => $score]],
            'owner_dependency_plan' => ['actions' => []],
            'target_exit_pv' => 100000,
            'owner_readiness_is_primary_constraint' => false,
        ]);
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
            GRANT SELECT ON succession_plans TO %1$s;
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
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT succession_plans_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT succession_plans_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT succession_plans_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
