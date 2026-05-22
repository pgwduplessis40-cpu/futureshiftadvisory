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
use App\Models\EconomicIndicator;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\Scenario;
use App\Models\User;
use App\Services\Analysis\ScenarioPlanner;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class ScenarioPlannerTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_scenarios_rls_app';

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
                DB::statement('REVOKE SELECT ON scenarios FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_planner_rejects_more_than_five_scenarios(): void
    {
        $client = $this->clientWithQuestionnaire('Scenario Bound Limited');

        try {
            app(ScenarioPlanner::class)->plan($client, $this->scenarioInputs(6));
            $this->fail('Expected scenario planner to reject more than five scenarios.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('maximum of five', $e->getMessage());
        }

        $this->assertDatabaseCount('scenarios', 0);
        $this->assertDatabaseMissing('analysis_runs', [
            'client_id' => $client->id,
            'module' => AnalysisModule::Scenario->value,
        ]);
    }

    public function test_planner_computes_pv_per_scenario_with_economic_overlay(): void
    {
        $client = $this->clientWithQuestionnaire('Scenario PV Limited');
        $ocr = $this->indicator(EconomicIndicator::OCR, 'Official Cash Rate', 5.5, 'percent');
        $cpi = $this->indicator(EconomicIndicator::CPI_ANNUAL, 'Annual CPI', 4.0, 'percent');
        $this->indicator(EconomicIndicator::GDP_QUARTERLY, 'Quarterly GDP', 1.0, 'percent');

        $run = app(ScenarioPlanner::class)->plan($client, [
            [
                'name' => 'Best case',
                'kind' => Scenario::KIND_BEST,
                'annual_pv_impact' => 50000,
                'duration_years' => 3,
                'is_client_visible' => true,
            ],
            [
                'name' => 'Worst case',
                'kind' => Scenario::KIND_WORST,
                'annual_pv_impact' => -15000,
                'duration_years' => 2,
                'is_client_visible' => false,
            ],
        ]);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(AnalysisModule::Scenario, $run->module);
        $this->assertSame([AnalysisLens::Predictive->value], $run->framework_lenses);

        $scenarios = Scenario::query()->where('client_id', $client->id)->orderBy('position')->get();
        $this->assertCount(2, $scenarios);
        $this->assertSame('Best case', $scenarios[0]->name);
        $this->assertNotNull($scenarios[0]->pv_calculation_id);
        $this->assertGreaterThan(0, $scenarios[0]->pv_impact);
        $this->assertLessThan(0, $scenarios[1]->pv_impact);
        $this->assertSame("economic_indicator:{$ocr->id}", $scenarios[0]->pvCalculation->source_attributions[0]['source_reference']);
        $this->assertSame($cpi->id, $scenarios[0]->economic_overlay['indicators'][EconomicIndicator::CPI_ANNUAL]['id']);
        $this->assertSame(0.025, $scenarios[0]->economic_overlay['applied_growth_rate']);
    }

    public function test_client_portal_shows_only_client_visible_named_scenarios(): void
    {
        [$user, $client] = $this->clientUserWithClient('Visible Scenario Limited');
        $otherClient = $this->clientWithQuestionnaire('Other Scenario Limited');
        $this->indicator(EconomicIndicator::OCR, 'Official Cash Rate', 5.5, 'percent');

        app(ScenarioPlanner::class)->plan($client, [
            [
                'name' => 'Expected case',
                'kind' => Scenario::KIND_EXPECTED,
                'annual_pv_impact' => 20000,
                'duration_years' => 2,
                'is_client_visible' => true,
            ],
            [
                'name' => 'Advisor working case',
                'kind' => Scenario::KIND_CUSTOM,
                'annual_pv_impact' => 30000,
                'duration_years' => 2,
                'is_client_visible' => false,
            ],
        ]);
        app(ScenarioPlanner::class)->plan($otherClient, [[
            'name' => 'Other client case',
            'kind' => Scenario::KIND_BEST,
            'annual_pv_impact' => 45000,
            'duration_years' => 2,
            'is_client_visible' => true,
        ]]);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->has('scenarios', 1)
                ->where('scenarios.0.name', 'Expected case')
                ->where('scenarios.0.kind', Scenario::KIND_EXPECTED)
                ->where('scenarios.0.position', 1));
    }

    public function test_scenarios_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Scenario RLS assertions require Postgres.');
        }

        $clientA = $this->clientWithQuestionnaire('Scenario A Limited');
        $clientB = $this->clientWithQuestionnaire('Scenario B Limited');
        $scenarioA = $this->storedScenario($clientA, 'A expected');
        $scenarioB = $this->storedScenario($clientB, 'B expected');

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('scenarios')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($scenarioA->id, $visibleIds);
        $this->assertNotContains($scenarioB->id, $visibleIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioInputs(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'name' => "Scenario {$index}",
                'kind' => Scenario::KIND_CUSTOM,
                'annual_pv_impact' => 10000 * $index,
                'duration_years' => 1,
            ])
            ->all();
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(string $name): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Client Scenario Owner',
            'email' => 'scenario.owner@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = $this->clientWithQuestionnaire($name, $user);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    private function clientWithQuestionnaire(string $name, ?User $user = null): Client
    {
        $user ??= User::factory()->create();

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        [$questionnaire, $question] = $this->questionnaireWithQuestion();
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);
        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Scenario planning assumptions are ready for analysis.',
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
            'version' => 'wo53-'.Str::lower(Str::random(8)),
            'title' => 'WO-53 Scenario Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Planning',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'Which assumptions should scenario planning use?',
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

    private function storedScenario(Client $client, string $name): Scenario
    {
        app(RequestContext::class)->apply('system', []);

        return Scenario::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'kind' => Scenario::KIND_EXPECTED,
            'assumptions' => ['annual_pv_impact' => 10000],
            'economic_overlay' => ['indicators' => []],
            'pv_impact' => 10000,
            'position' => 1,
            'is_client_visible' => true,
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
            GRANT SELECT ON scenarios TO %1$s;
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
            DB::statement('SAVEPOINT scenarios_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT scenarios_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT scenarios_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
