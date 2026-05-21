<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Services\Ai\Contracts\Uncertainty;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnalysisRlsTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_analysis_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Analysis RLS assertions require Postgres.');
        }

        $this->connectionBypassesRls = $this->currentRoleBypassesRls();
        if ($this->connectionBypassesRls) {
            $this->createNonBypassRole();
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON analysis_runs, analysis_findings, analysis_feedback FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_analysis_runs_findings_and_feedback_are_isolated_by_client_scope(): void
    {
        [$clientA, $runA] = $this->clientWithRun('A Client Limited');
        [$clientB] = $this->clientWithRun('B Client Limited');

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleRunIds = $this->withRlsRole(
            fn (): array => DB::table('analysis_runs')->pluck('id')->all(),
        );
        $visibleFindingBodies = $this->withRlsRole(
            fn (): array => DB::table('analysis_findings')->pluck('body')->all(),
        );
        $visibleFeedbackNotes = $this->withRlsRole(
            fn (): array => DB::table('analysis_feedback')->pluck('note')->all(),
        );

        $this->assertSame([(string) $runA->getKey()], $visibleRunIds);
        $this->assertSame(['Finding for A Client Limited'], $visibleFindingBodies);
        $this->assertSame(['Feedback for A Client Limited'], $visibleFeedbackNotes);

        app(RequestContext::class)->apply('advisor', [(string) $clientB->getKey()]);
        $this->assertSame(
            1,
            $this->withRlsRole(fn (): int => DB::table('analysis_runs')->count()),
        );
    }

    /**
     * @return array{0: Client, 1: AnalysisRun}
     */
    private function clientWithRun(string $name): array
    {
        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Descriptive->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $finding = AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Descriptive,
            'severity' => FindingSeverity::Info,
            'title' => 'Finding',
            'body' => 'Finding for '.$name,
            'attributions' => [
                ['claim' => 'Finding', 'source_reference' => 'test:rls'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Low,
            'bias_signals' => [],
        ]);

        AnalysisFeedback::query()->create([
            'analysis_finding_id' => $finding->id,
            'decision' => AnalysisFeedback::DECISION_CONFIRM,
            'note' => 'Feedback for '.$name,
        ]);

        return [$client, $run];
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
            GRANT SELECT ON analysis_runs, analysis_findings, analysis_feedback TO %1$s;
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
            DB::statement('SAVEPOINT analysis_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT analysis_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT analysis_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
