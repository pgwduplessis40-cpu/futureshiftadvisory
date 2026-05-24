<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboards;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessHealthSnapshot;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BusinessHealthRadarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_recompute_persists_complete_client_safe_scores_and_exact_evidence(): void
    {
        $advisor = $this->advisor('radar-score@example.test');
        $client = $this->clientFor($advisor, 'Radar Score Limited');

        $financialRun = $this->analysisRun($client, AnalysisModule::Financial, completedAt: now()->subMinutes(20));
        $financialHigh = $this->finding($client, $financialRun, FindingSeverity::High, 'Cash pressure');
        $financialPrescriptive = $this->finding(
            $client,
            $financialRun,
            FindingSeverity::Critical,
            'Advisor-only recommendation',
            AnalysisLens::Prescriptive,
        );

        $operationalRun = $this->analysisRun($client, AnalysisModule::Operational, completedAt: now()->subMinutes(18));
        $systemsRun = $this->analysisRun($client, AnalysisModule::Systems, completedAt: now()->subMinutes(17));
        $operationalHigh = $this->finding($client, $operationalRun, FindingSeverity::High, 'Manual rework');
        $systemsMedium = $this->finding($client, $systemsRun, FindingSeverity::Medium, 'Tooling gap');

        $this->analysisRun($client, AnalysisModule::Hr, completedAt: now()->subMinutes(16));

        $swotRun = $this->analysisRun($client, AnalysisModule::Swot, completedAt: now()->subMinutes(15));
        $strategicLow = $this->finding($client, $swotRun, FindingSeverity::Low, 'Weak market signal');

        $complianceRun = $this->analysisRun($client, AnalysisModule::Compliance, completedAt: now()->subMinutes(14));
        $regulatoryRun = $this->analysisRun($client, AnalysisModule::RegulatoryImpact, completedAt: now()->subMinutes(13));
        $this->finding($client, $complianceRun, FindingSeverity::Critical, 'Compliance breach');
        $this->finding($client, $regulatoryRun, FindingSeverity::Critical, 'Regulatory exposure');

        $snapshots = app(BusinessHealthSnapshotWriter::class)->recompute($client);
        $byDimension = $snapshots->keyBy('dimension');

        $this->assertCount(5, $snapshots);
        $this->assertCount(1, $snapshots->pluck('assessment_batch_id')->unique());
        $this->assertSame(77, $byDimension['financial']->score);
        $this->assertSame(67, $byDimension['operational']->score);
        $this->assertNull($byDimension['people']->score);
        $this->assertSame(97, $byDimension['strategic']->score);
        $this->assertSame(0, $byDimension['compliance']->score);
        $this->assertSame(BusinessHealthSnapshot::STATE_SCORED, $byDimension['strategic']->dimension_run_state);

        $this->assertSame([$financialHigh->id], $byDimension['financial']->contributing_finding_ids);
        $this->assertSame($financialHigh->id, $byDimension['financial']->top_finding_id);
        $this->assertNotContains($financialPrescriptive->id, $byDimension['financial']->contributing_finding_ids);
        $this->assertSame(
            [$financialHigh->id],
            collect($byDimension['financial']->source_attributions)
                ->pluck('analysis_finding_id')
                ->unique()
                ->values()
                ->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$operationalHigh->id, $systemsMedium->id],
            $byDimension['operational']->contributing_finding_ids,
        );
        $this->assertSame($operationalHigh->id, $byDimension['operational']->top_finding_id);
        $this->assertSame([$strategicLow->id], $byDimension['strategic']->contributing_finding_ids);
        $this->assertSame(BusinessHealthSnapshot::STATE_NEVER_RUN, $byDimension['strategic']->module_run_states['competitor']['state']);
    }

    public function test_run_state_provenance_stale_runs_and_complete_batch_reader_are_deterministic(): void
    {
        $advisor = $this->advisor('radar-state@example.test');
        $client = $this->clientFor($advisor, 'Radar State Limited');

        $olderCompleted = $this->analysisRun($client, AnalysisModule::Financial, completedAt: now()->subHour());
        $finding = $this->finding($client, $olderCompleted, FindingSeverity::High, 'Historic margin pressure');
        $this->analysisRun($client, AnalysisModule::Financial, AnalysisRun::STATUS_FAILED, now()->subMinute());
        $this->analysisRun($client, AnalysisModule::Operational, AnalysisRun::STATUS_BLOCKED_DOCUMENTS, now()->subMinutes(5));
        $this->analysisRun($client, AnalysisModule::Systems, AnalysisRun::STATUS_FAILED, now()->subMinutes(6));
        $this->analysisRun($client, AnalysisModule::Hr, AnalysisRun::STATUS_RUNNING, now()->subMinutes(4));

        $prescriptiveOnly = $this->analysisRun($client, AnalysisModule::Swot, completedAt: now()->subMinutes(3));
        $this->finding($client, $prescriptiveOnly, FindingSeverity::Critical, 'Strategic recommendation', AnalysisLens::Prescriptive);

        $snapshots = app(BusinessHealthSnapshotWriter::class)->recompute($client);
        $batchId = (string) $snapshots->first()->assessment_batch_id;
        $byDimension = $snapshots->keyBy('dimension');

        $this->assertSame([$finding->id], $byDimension['financial']->contributing_finding_ids);
        $this->assertSame(BusinessHealthSnapshot::STATE_SCORED, $byDimension['financial']->dimension_run_state);
        $this->assertTrue($byDimension['financial']->module_run_states['financial']['stale']);
        $this->assertSame(AnalysisRun::STATUS_FAILED, $byDimension['financial']->module_run_states['financial']['latest_run_status']);
        $this->assertSame(BusinessHealthSnapshot::STATE_BLOCKED, $byDimension['operational']->dimension_run_state);
        $this->assertSame(BusinessHealthSnapshot::STATE_IN_PROGRESS, $byDimension['people']->dimension_run_state);
        $this->assertSame(BusinessHealthSnapshot::STATE_COMPLETED_NO_CLIENT_SAFE_FINDINGS, $byDimension['strategic']->dimension_run_state);
        $this->assertNull($byDimension['strategic']->score);
        $this->assertSame(BusinessHealthSnapshot::STATE_NEVER_RUN, $byDimension['compliance']->dimension_run_state);

        BusinessHealthSnapshot::query()->create([
            'client_id' => $client->id,
            'assessment_batch_id' => '00000000-0000-0000-0000-000000000123',
            'dimension' => BusinessHealthSnapshot::DIMENSION_FINANCIAL,
            'score' => 100,
            'top_finding_id' => null,
            'contributing_finding_ids' => [],
            'module_run_states' => [],
            'dimension_run_state' => BusinessHealthSnapshot::STATE_COMPLETED_NO_FINDINGS,
            'captured_at' => now()->addMinute(),
            'source_attributions' => [],
        ]);

        $latest = app(BusinessHealthRadarBuilder::class)->latestCompleteBatch($client);
        $this->assertSame($batchId, (string) $latest?->first()?->assessment_batch_id);

        DB::statement('SAVEPOINT business_health_duplicate_probe');

        try {
            BusinessHealthSnapshot::query()->create([
                'client_id' => $client->id,
                'assessment_batch_id' => $batchId,
                'dimension' => BusinessHealthSnapshot::DIMENSION_FINANCIAL,
                'score' => null,
                'top_finding_id' => null,
                'contributing_finding_ids' => [],
                'module_run_states' => [],
                'dimension_run_state' => BusinessHealthSnapshot::STATE_NEVER_RUN,
                'captured_at' => now(),
                'source_attributions' => [],
            ]);

            $this->fail('Duplicate business health dimension row was accepted.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT business_health_duplicate_probe');
            $this->assertTrue(true);
        } finally {
            DB::statement('RELEASE SAVEPOINT business_health_duplicate_probe');
        }
    }

    public function test_portal_payload_and_health_findings_are_client_safe(): void
    {
        $advisor = $this->advisor('radar-portal-advisor@example.test');
        $client = $this->clientFor($advisor, 'Radar Portal Limited');
        $clientUser = $this->clientUserFor($client, 'radar-portal-client@example.test');
        $run = $this->analysisRun($client, AnalysisModule::Financial, completedAt: now()->subMinutes(10));
        $safeFinding = $this->finding($client, $run, FindingSeverity::High, 'Client-safe cash finding');
        $prescriptive = $this->finding($client, $run, FindingSeverity::Critical, 'Prescriptive recommendation', AnalysisLens::Prescriptive);

        app(BusinessHealthSnapshotWriter::class)->recompute($client);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard', ['focus' => 'health', 'highlight' => 'health-financial']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('businessHealth.axes.0.dimension', 'financial')
                ->where('businessHealth.axes.0.score', 77)
                ->where('businessHealth.axes.0.top_finding.id', $safeFinding->id)
                ->where('businessHealth.axes.0.contributing_finding_ids', [$safeFinding->id])
                ->where('healthFindings.0.anchor', 'health-financial')
                ->where('healthFindings.0.findings.0.id', $safeFinding->id)
                ->where('healthFindings.0.findings.0.body', 'Body for Client-safe cash finding.')
                ->where('healthFindings.0.findings', function ($findings) use ($prescriptive): bool {
                    return $findings->contains(fn (array $finding): bool => $finding['id'] === $prescriptive->id) === false;
                }));
    }

    public function test_recompute_route_is_scoped_manage_gated_and_audited(): void
    {
        $advisor = $this->advisor('radar-route@example.test');
        $otherAdvisor = $this->advisor('radar-route-other@example.test');
        $client = $this->clientFor($advisor, 'Radar Route Limited');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.health-radar.recompute', $client))
            ->assertRedirect();

        app(RequestContext::class)->apply('system', []);
        $this->assertSame(5, BusinessHealthSnapshot::query()->where('client_id', $client->id)->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'business_health.recomputed',
            'client_id' => $client->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
        ]);

        $this->actingAsMfa($otherAdvisor)
            ->post(route('advisor.clients.health-radar.recompute', $client))
            ->assertNotFound();

        app(RequestContext::class)->apply('system', []);
        $this->assertSame(5, BusinessHealthSnapshot::query()->where('client_id', $client->id)->count());
    }

    public function test_artisan_recompute_runs_under_system_context(): void
    {
        $advisor = $this->advisor('radar-command@example.test');
        $client = $this->clientFor($advisor, 'Radar Command Limited');

        $this->artisan('fsa:recompute-health-radar', ['client' => $client->id])
            ->assertExitCode(0);

        app(RequestContext::class)->apply('system', []);
        $this->assertSame(5, BusinessHealthSnapshot::query()->where('client_id', $client->id)->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'business_health.recomputed',
            'client_id' => $client->id,
            'actor_role' => 'system',
        ]);
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientFor(User $advisor, string $name): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000800',
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function clientUserFor(Client $client, string $email): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $user;
    }

    private function analysisRun(
        Client $client,
        AnalysisModule $module,
        string $status = AnalysisRun::STATUS_COMPLETED,
        ?CarbonInterface $startedAt = null,
        ?CarbonInterface $completedAt = null,
    ): AnalysisRun {
        $startedAt ??= now()->subMinutes(2);
        $completedAt = $status === AnalysisRun::STATUS_COMPLETED
            ? ($completedAt ?? $startedAt->copy()->addMinute())
            : null;

        return AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => $status,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => [],
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'created_at' => $startedAt,
            'updated_at' => $completedAt ?? $startedAt,
        ]);
    }

    private function finding(
        Client $client,
        AnalysisRun $run,
        FindingSeverity $severity,
        string $title,
        AnalysisLens $lens = AnalysisLens::Diagnostic,
    ): AnalysisFinding {
        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => $lens,
            'severity' => $severity,
            'title' => $title,
            'body' => "Body for {$title}.",
            'attributions' => [
                [
                    'claim' => "Evidence for {$title}.",
                    'source_reference' => 'radar-test:'.str($title)->slug()->toString(),
                ],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'created_at' => $run->completed_at ?? $run->started_at ?? now(),
            'updated_at' => $run->completed_at ?? $run->started_at ?? now(),
        ]);
    }
}
