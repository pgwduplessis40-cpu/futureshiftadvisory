<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Console\Commands\GeneratePreMeetingBriefs;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\ReportType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EconomicIndicator;
use App\Models\FeeCalculation;
use App\Models\FinancialAlert;
use App\Models\FinancialSnapshot;
use App\Models\IndustryBriefing;
use App\Models\Meeting;
use App\Models\PreMeetingBrief;
use App\Models\Proposal;
use App\Models\RedFlag;
use App\Models\Report;
use App\Models\User;
use App\Notifications\IndustryBriefingNotification;
use App\Notifications\PreMeetingBriefNotification;
use App\Services\Reports\IndustryBriefingGenerator;
use App\Services\Reports\PreMeetingBriefGenerator;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BriefingGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_briefings_rls_app';

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
                DB::statement('REVOKE SELECT ON meetings, industry_briefings, pre_meeting_briefs FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_monthly_industry_briefing_is_draft_until_advisor_review_sends_it(): void
    {
        Notification::fake();
        [$advisor, $client, $clientUser] = $this->clientWithTeamAndClientUser();
        $this->economicIndicator(EconomicIndicator::OCR, 'Official cash rate', 5.5, 'percent');

        $briefing = app(IndustryBriefingGenerator::class)->generate($client, now(), $advisor);

        $this->assertSame(IndustryBriefing::STATUS_DRAFT, $briefing->status);
        $this->assertStringContainsString('NZ source signals', $briefing->body);
        $this->assertNotSame([], $briefing->sources);
        Notification::assertNothingSent();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.industry-briefings.review', $briefing))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertSame(IndustryBriefing::STATUS_SENT, $briefing->refresh()->status);
        $this->assertSame($advisor->getKey(), $briefing->reviewed_by_user_id);
        Notification::assertSentTo($clientUser, IndustryBriefingNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'industry_briefing.reviewed_sent',
            'client_id' => $client->id,
        ]);
    }

    public function test_pre_meeting_trigger_uses_24_hour_window_and_does_not_duplicate(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $meeting = $this->meeting($client, now()->addDay());
        $this->meeting($client, now()->addHours(30), 'Outside trigger window');
        $this->redFlag($client, 'Cash red flag');
        $this->financialAlert($client, 'Debtor days worsened');
        $this->releasedProposal($client);
        $this->storedReport($client);

        $generated = app(PreMeetingBriefGenerator::class)->generateDue(now());

        $this->assertSame(1, $generated);
        $brief = PreMeetingBrief::query()->firstOrFail();
        $this->assertSame($meeting->id, $brief->meeting_id);
        $this->assertStringContainsString('Cash red flag', $brief->body);
        $this->assertStringContainsString('Debtor days worsened', $brief->body);
        $this->assertStringContainsString('1 released proposal(s), 1 generated report(s)', $brief->body);

        $this->assertSame(0, app(PreMeetingBriefGenerator::class)->generateDue(now()));
        $this->assertDatabaseCount('pre_meeting_briefs', 1);

        Notification::fake();
        $this->actingAsMfa($advisor)
            ->patch(route('advisor.pre-meeting-briefs.review', $brief))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        Notification::assertSentTo($advisor, PreMeetingBriefNotification::class);
        $this->assertSame($advisor->getKey(), $brief->refresh()->reviewed_by_user_id);
        $this->assertNotNull($brief->sent_at);
    }

    public function test_pre_meeting_command_runs_the_same_due_window(): void
    {
        [, $client] = $this->clientWithTeam('meeting-command-advisor@example.test');
        $meeting = $this->meeting($client, now()->addDay());

        $this->artisan(GeneratePreMeetingBriefs::class, ['--now' => now()->toIso8601String()])
            ->assertSuccessful();

        $this->assertDatabaseHas('pre_meeting_briefs', [
            'meeting_id' => $meeting->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_advisor_client_page_accepts_meetings_and_shows_briefing_payloads(): void
    {
        [$advisor, $client] = $this->clientWithTeam('meeting-ui-advisor@example.test');
        $briefing = app(IndustryBriefingGenerator::class)->generate($client, now(), $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.meetings.store', $client), [
                'title' => 'Quarterly review',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'location' => 'Zoom',
                'attendees' => 'Advisor, Owner',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.meeting_store_url', route('advisor.clients.meetings.store', $client, absolute: false))
                ->where('client.meetings.0.title', 'Quarterly review')
                ->where('client.industry_briefings.0.id', $briefing->id)
                ->where('client.industry_briefings.0.can_review', true));
    }

    public function test_meetings_and_briefs_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Briefing RLS assertions require Postgres.');
        }

        $clientA = $this->client('Briefing A Limited');
        $clientB = $this->client('Briefing B Limited');
        $meetingA = $this->meeting($clientA, now()->addDay(), 'Client A meeting');
        $meetingB = $this->meeting($clientB, now()->addDay(), 'Client B meeting');
        app(IndustryBriefingGenerator::class)->generate($clientA, now());
        app(IndustryBriefingGenerator::class)->generate($clientB, now());
        app(PreMeetingBriefGenerator::class)->generate($meetingA);
        app(PreMeetingBriefGenerator::class)->generate($meetingB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleMeetingIds = $this->withRlsRole(fn (): array => DB::table('meetings')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $visibleBriefingClientIds = $this->withRlsRole(fn (): array => DB::table('industry_briefings')
            ->pluck('client_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $visiblePreMeetingClientIds = $this->withRlsRole(fn (): array => DB::table('pre_meeting_briefs')
            ->pluck('client_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($meetingA->id, $visibleMeetingIds);
        $this->assertNotContains($meetingB->id, $visibleMeetingIds);
        $this->assertSame([$clientA->id], $visibleBriefingClientIds);
        $this->assertSame([$clientA->id], $visiblePreMeetingClientIds);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithTeam(string $advisorEmail = 'briefing-advisor@example.test'): array
    {
        [$advisor, $client] = $this->clientWithTeamAndClientUser($advisorEmail);

        return [$advisor, $client];
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithTeamAndClientUser(string $advisorEmail = 'briefing-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'briefing-client-'.strtolower(fake()->bothify('????')).'@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = $this->client('Briefing Client Limited', $advisor, $clientUser);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client, $clientUser];
    }

    private function client(string $name, ?User $createdBy = null, ?User $primaryContact = null): Client
    {
        app(RequestContext::class)->apply('system', []);

        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'entity_type' => 'Professional services',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $createdBy?->getKey(),
            'primary_contact_user_id' => $primaryContact?->getKey(),
        ]);
    }

    private function economicIndicator(string $indicator, string $label, float $value, string $unit): EconomicIndicator
    {
        return EconomicIndicator::query()->create([
            'indicator' => $indicator,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'period_date' => now()->toDateString(),
            'source' => 'rbnz',
            'source_badge' => 'fixture',
            'degraded' => false,
            'fetched_at' => now(),
            'payload' => ['fixture' => true],
        ]);
    }

    private function meeting(Client $client, mixed $scheduledAt, string $title = 'Strategy meeting'): Meeting
    {
        return Meeting::query()->create([
            'client_id' => $client->id,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'location' => 'Board room',
            'attendees' => ['Advisor', 'Owner'],
        ]);
    }

    private function redFlag(Client $client, string $headline): RedFlag
    {
        return RedFlag::query()->create([
            'client_id' => $client->id,
            'source_type' => 'briefing_test',
            'source_key' => $headline,
            'category' => RedFlag::CATEGORY_FINANCIAL,
            'severity' => 'critical',
            'headline' => $headline,
            'detail' => 'Critical briefing flag.',
            'surfaced_at' => now(),
        ]);
    }

    private function releasedProposal(Client $client): Proposal
    {
        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->id,
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 40000,
            'risk_cost_pv_total' => 5000,
            'roi_ratio' => 4,
            'justification' => ['fixture' => true],
        ]);

        return Proposal::query()->create([
            'client_id' => $client->id,
            'fee_calculation_id' => $calculation->id,
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['summary' => 'Fixture.'],
            'services' => [['name' => 'Advisory']],
            'pv_summary' => ['roi_ratio' => 4],
            'roi_ratio' => 4,
            'acceptance_terms' => ['phase' => 'phase_2_release_only'],
            'released_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function storedReport(Client $client): Report
    {
        return Report::query()->create([
            'client_id' => $client->id,
            'type' => ReportType::Advisor,
            'title' => 'Advisor report',
            'generated_at' => now(),
            'metadata' => [],
        ]);
    }

    private function financialAlert(Client $client, string $headline): FinancialAlert
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-'.$client->id,
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'fixture',
            'token_envelope_meta' => ['fixture' => true],
            'scopes' => ['accounting.reports.read'],
            'connected_at' => now(),
        ]);
        $previous = $this->financialSnapshot($client, $connection, now()->subMonth(), ['debtor_days' => 28]);
        $current = $this->financialSnapshot($client, $connection, now(), ['debtor_days' => 45]);

        return FinancialAlert::query()->create([
            'client_id' => $client->id,
            'accounting_connection_id' => $connection->id,
            'previous_snapshot_id' => $previous->id,
            'current_snapshot_id' => $current->id,
            'alert_key' => 'briefing-'.$client->id,
            'category' => FinancialAlert::CATEGORY_CASH_FLOW,
            'severity' => FinancialAlert::SEVERITY_WARNING,
            'metric' => 'debtor_days',
            'headline' => $headline,
            'detail' => 'Debtor days increased materially.',
            'previous_value' => 28,
            'current_value' => 45,
            'change_amount' => 17,
            'change_percent' => 0.61,
            'citation' => ['source_reference' => 'financial_snapshot:'.$current->id],
            'surfaced_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function financialSnapshot(Client $client, AccountingConnection $connection, mixed $periodEnd, array $metrics): FinancialSnapshot
    {
        return FinancialSnapshot::query()->create([
            'client_id' => $client->id,
            'accounting_connection_id' => $connection->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => $periodEnd->copy()->subMonth()->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'source' => 'fixture',
            'source_badge' => 'fixture',
            'degraded' => false,
            'profit_and_loss' => [],
            'balance_sheet' => [],
            'cash_flow' => [],
            'metrics' => $metrics,
            'pulled_at' => now(),
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
            GRANT SELECT ON meetings, industry_briefings, pre_meeting_briefs TO %1$s;
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
            DB::statement('SAVEPOINT briefings_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT briefings_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT briefings_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
