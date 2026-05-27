<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ReportType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\ClientTeamMember;
use App\Models\FinancialSnapshot;
use App\Models\Funder;
use App\Models\Goal;
use App\Models\LearningUpdate;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Goals\GoalTracker;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Npo\FunderRegistry;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

final class FunderAccountabilityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_funder_accountability_report_reads_engagement_scoped_sources_and_requires_review(): void
    {
        Carbon::setTestNow('2026-05-27 12:00:00');
        [$advisor, $client, $engagement] = $this->npoClient();
        $otherEngagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);
        $funder = $this->funder('Accountability Fund');
        $record = $this->record($client, $engagement, $funder);
        $this->financialSnapshot($client);
        $this->milestone($client, $engagement, 'Own completed milestone', Milestone::STATUS_COMPLETED);
        $this->milestone($client, $engagement, 'Own pending milestone', Milestone::STATUS_PENDING);
        $otherMilestone = $this->milestone($client, $otherEngagement, 'Other engagement milestone', Milestone::STATUS_COMPLETED);

        $report = app(ReportComposer::class)->composeFunderAccountability($engagement, $record, $advisor);

        $this->assertSame(ReportType::FunderAccountability, $report->type);
        $this->assertSame($engagement->id, $report->npo_engagement_id);
        $this->assertSame('pending_review', $report->review_status);
        $this->assertFalse(app(ReportComposer::class)->canShareWithFunder($report));
        $this->assertTrue($report->sections->contains('key', 'financial_acquittal'));
        $milestoneSection = $report->sections->firstWhere('key', 'milestone_completion');
        $this->assertNotNull($milestoneSection);
        $this->assertStringContainsString('1 of 2', $milestoneSection->body);
        $this->assertNotContains($otherMilestone->id, $milestoneSection->metadata['milestone_ids']);
        $this->assertTrue((bool) $report->sections->firstWhere('key', 'ai_accountability_narrative')->metadata['advisor_review_required']);

        $reviewed = app(ReportComposer::class)->markReviewed($report, $advisor);

        $this->assertTrue($reviewed->reviewed());
        $this->assertTrue(app(ReportComposer::class)->canShareWithFunder($reviewed));
    }

    public function test_impact_summary_is_client_authored_fact_checked_and_auto_releasable(): void
    {
        Carbon::setTestNow('2026-05-27 10:00:00');
        [$advisor, , $engagement] = $this->npoClient('impact-summary-advisor@example.test', 'Impact Summary Trust');

        $report = app(ReportComposer::class)->composeImpactSummary($engagement, [
            'summary' => 'We served families with warm meals and wraparound support.',
            'metrics' => ['beneficiaries_served' => 90],
            'platform_metrics' => ['beneficiaries_served' => 100],
        ], $advisor);

        $this->assertSame(ReportType::ImpactSummary, $report->type);
        $this->assertSame($engagement->id, $report->npo_engagement_id);
        $this->assertTrue((bool) $report->metadata['client_authored']);
        $this->assertFalse((bool) $report->metadata['fsa_ip']);
        $this->assertNotNull($report->metadata['auto_release_at']);
        $this->assertSame(['fsa_ip'], $report->metadata['redactions']);
        $this->assertTrue(Carbon::parse($report->metadata['auto_release_at'])->equalTo(now()->addHours(48)));
        $this->assertSame(0, app(ReportComposer::class)->autoReleaseDueImpactSummaries());

        Carbon::setTestNow(Carbon::parse($report->metadata['auto_release_at'])->addMinute());

        $this->assertSame(1, app(ReportComposer::class)->autoReleaseDueImpactSummaries());
        $this->assertTrue($report->refresh()->reviewed());
        $this->assertTrue((bool) $report->metadata['auto_released']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.impact_summary_auto_released',
            'subject_id' => $report->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds recorded platform data');

        app(ReportComposer::class)->composeImpactSummary($engagement, [
            'summary' => 'Overstated summary.',
            'metrics' => ['beneficiaries_served' => 120],
            'platform_metrics' => ['beneficiaries_served' => 100],
        ], $advisor);
    }

    public function test_goal_tracker_stamps_milestones_and_actions_with_valid_engagement(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient('goal-stamp-advisor@example.test', 'Goal Stamp Trust');
        [, , $otherEngagement] = $this->npoClient('other-goal-stamp-advisor@example.test', 'Other Goal Stamp Trust');
        $tracker = app(GoalTracker::class);
        $goal = $tracker->createGoal($client, ['title' => 'Grant delivery plan'], $advisor);

        $milestone = $tracker->createMilestone($goal, [
            'title' => 'Deliver engagement milestone',
            'npo_engagement_id' => $engagement->id,
        ], $advisor);
        $action = $tracker->createAction($milestone, ['title' => 'Prepare board update'], $advisor);

        $this->assertSame($engagement->id, $milestone->npo_engagement_id);
        $this->assertSame($engagement->id, $action->npo_engagement_id);
        $this->assertInstanceOf(MilestoneAction::class, $action);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Milestone NPO engagement must belong to the goal client.');

        $tracker->createMilestone($goal, [
            'title' => 'Invalid cross-client engagement',
            'npo_engagement_id' => $otherEngagement->id,
        ], $advisor);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(string $advisorEmail = 'accountability-advisor@example.test', string $clientName = 'Accountability Trust'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function financialSnapshot(Client $client): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-'.$client->id,
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'test-token',
            'token_envelope_meta' => [],
            'scopes' => ['financials.read'],
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->id,
            'accounting_connection_id' => $connection->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'source' => 'xero_fixture',
            'source_badge' => 'Xero',
            'degraded' => false,
            'profit_and_loss' => ['revenue' => 200000, 'operating_expenses' => 120000],
            'balance_sheet' => ['cash' => 50000],
            'cash_flow' => ['operating_cash_flow' => 10000],
            'metrics' => [],
            'pulled_at' => now(),
        ]);
    }

    private function funder(string $name): Funder
    {
        return app(FunderRegistry::class)->upsertFromLearningUpdate(
            $this->learningUpdate(),
            [
                'name' => $name,
                'type' => Funder::TYPE_PHILANTHROPIC,
                'funding_windows' => [],
                'criteria' => ['region' => 'Aotearoa'],
                'reporting_requirements' => ['six_month_report' => true],
                'renewal_intelligence' => ['renewal_weight' => 0.7],
                'last_verified_at' => now()->toIso8601String(),
            ],
        );
    }

    private function record(Client $client, NpoEngagement $engagement, Funder $funder): ClientFunderRecord
    {
        /** @var ClientFunderRecord $record */
        $record = ClientFunderRecord::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'funder_id' => $funder->id,
            'grant_name' => 'Community grant',
            'grant_amount' => 50000,
            'currency' => 'NZD',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->addYear()->toDateString(),
            'conditions' => [],
            'renewal_probability' => 75,
            'history' => [['event' => 'created']],
        ]);

        return $record;
    }

    private function milestone(Client $client, NpoEngagement $engagement, string $title, string $status): Milestone
    {
        $goal = Goal::query()->create([
            'client_id' => $client->id,
            'title' => 'Grant delivery',
            'status' => Goal::STATUS_ACTIVE,
        ]);

        /** @var Milestone $milestone */
        $milestone = Milestone::query()->create([
            'goal_id' => $goal->id,
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'title' => $title,
            'pv_of_impact' => 0,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => $status,
            'completed_at' => $status === Milestone::STATUS_COMPLETED ? now() : null,
        ]);

        return $milestone;
    }

    private function learningUpdate(): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'source' => ['type' => 'npo_funder_report_test'],
            'summary' => 'Update funder registry',
            'proposed_change' => ['action' => 'update_funder_registry'],
            'impact_scope' => ['surface' => 'funder_registry'],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.8,
            'evidence' => ['source' => 'test'],
            'status' => LearningUpdate::STATUS_APPROVED,
        ]);
    }
}
