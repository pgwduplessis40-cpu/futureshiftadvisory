<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoTiritiMode;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\NpoBoardMember;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\NpoFunderReportSession;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Npo\NpoBoardAccess;
use App\Services\Npo\NpoFunderReportAccess;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class NpoAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_npo_access_rls_app';

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
                DB::statement('REVOKE SELECT ON npo_board_members, npo_engagements, npo_dimension_scores, reports, report_sections, milestones, milestone_actions, entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE EXECUTE ON FUNCTION fsa_user_is_board_member_of(uuid) FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_board_member_access_is_engagement_scoped_at_rls_and_policy_layers(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Board RLS assertions require Postgres.');
        }

        [$advisor, $client, $engagementA] = $this->npoClient('board-advisor@example.test', 'Board Scope Trust');
        $engagementB = $this->npoEngagement($client, NpoEngagementSubType::StandardNpo);
        [, $otherClient, $engagementC] = $this->npoClient('board-other-advisor@example.test', 'Other Board Scope Trust');
        $board = $this->boardUser();
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $board->id,
            'role' => 'accidental_client_scope',
        ]);

        $boardAccess = app(NpoBoardAccess::class);
        $memberA = $boardAccess->activate($engagementA, $board, $advisor);
        $boardAccess->activate($engagementB, $board, $advisor, treasurer: true);
        $this->assertSame([], $board->fresh()->accessibleClientIds());

        $governanceReport = $this->report($client, $engagementA, ReportType::GovernanceReview, 'reviewed');
        $advisorReport = $this->report($client, $engagementA, ReportType::Advisor, 'not_required');
        $otherEngagementReport = $this->report($client, $engagementB, ReportType::GovernanceReview, 'reviewed');
        $this->section($governanceReport);
        $this->section($advisorReport);
        $this->section($otherEngagementReport);
        $this->dimensionScore($client, $engagementA, 3);
        $this->dimensionScore($client, $engagementB, 3);
        [$milestoneA, $actionA] = $this->milestoneAndAction($client, $engagementA);
        [$milestoneB, $actionB] = $this->milestoneAndAction($client, $engagementB);

        $this->assertTrue(Gate::forUser($board)->allows('view', $governanceReport));
        $this->assertFalse(Gate::forUser($board)->allows('view', $advisorReport));
        $this->assertFalse(Gate::forUser($board)->allows('viewFinancialDetail', $governanceReport));
        $this->assertFalse(Gate::forUser($board)->allows('viewFundingStrategy', [Report::class, $engagementA]));
        $this->assertTrue(Gate::forUser($board)->allows('viewFundingStrategy', [Report::class, $engagementB]));

        app(RequestContext::class)->apply(User::TYPE_NPO_BOARD_MEMBER, [], (string) $board->id, npoEngagementId: (string) $engagementA->id);
        $visible = $this->withRlsRole(fn (): array => [
            'engagements' => NpoEngagement::query()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
            'member_engagements' => NpoBoardMember::query()->pluck('npo_engagement_id')->map(fn (mixed $id): string => (string) $id)->all(),
            'report_ids' => Report::query()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
            'other_sections' => ReportSection::query()->where('report_id', $otherEngagementReport->id)->count(),
            'own_sections' => ReportSection::query()->where('report_id', $governanceReport->id)->count(),
            'other_scores' => NpoDimensionScore::query()->where('npo_engagement_id', $engagementB->id)->count(),
            'own_milestones' => Milestone::query()->whereKey($milestoneA->id)->count(),
            'other_milestones' => Milestone::query()->whereKey($milestoneB->id)->count(),
            'own_actions' => MilestoneAction::query()->whereKey($actionA->id)->count(),
            'other_actions' => MilestoneAction::query()->whereKey($actionB->id)->count(),
        ]);

        $this->assertContains($engagementA->id, $visible['engagements']);
        $this->assertNotContains($engagementB->id, $visible['engagements']);
        $this->assertNotContains($engagementC->id, $visible['engagements']);
        $this->assertContains($engagementA->id, $visible['member_engagements']);
        $this->assertContains($engagementB->id, $visible['member_engagements']);
        $this->assertContains($governanceReport->id, $visible['report_ids']);
        $this->assertContains($advisorReport->id, $visible['report_ids']);
        $this->assertNotContains($otherEngagementReport->id, $visible['report_ids']);
        $this->assertSame(1, $visible['own_sections']);
        $this->assertSame(0, $visible['other_sections']);
        $this->assertSame(0, $visible['other_scores']);
        $this->assertSame(1, $visible['own_milestones']);
        $this->assertSame(0, $visible['other_milestones']);
        $this->assertSame(1, $visible['own_actions']);
        $this->assertSame(0, $visible['other_actions']);

        app(RequestContext::class)->apply(User::TYPE_NPO_BOARD_MEMBER, [], (string) $board->id, npoEngagementId: (string) $engagementB->id);
        $this->assertSame(1, $this->withRlsRole(fn (): int => Report::query()->whereKey($otherEngagementReport->id)->count()));

        $this->expectException(AuthorizationException::class);
        app(RequestContext::class)->apply(User::TYPE_NPO_BOARD_MEMBER, [], (string) $board->id, npoEngagementId: (string) $engagementC->id);

        $this->assertSame($otherClient->id, $engagementC->client_id);
        $this->assertTrue($memberA->active());
    }

    public function test_board_membership_composite_fk_rejects_cross_client_rows_and_revoked_members(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Board FK assertions require Postgres.');
        }

        [$advisor, $client, $engagement] = $this->npoClient('board-fk-advisor@example.test', 'Board FK Trust');
        [, $otherClient] = $this->npoClient('board-fk-other-advisor@example.test', 'Other Board FK Trust');
        $board = $this->boardUser('board-fk@example.test');
        $member = app(NpoBoardAccess::class)->activate($engagement, $board, $advisor);
        app(NpoBoardAccess::class)->revoke($member, $advisor);

        try {
            app(RequestContext::class)->apply(User::TYPE_NPO_BOARD_MEMBER, [], (string) $board->id, npoEngagementId: (string) $engagement->id);
            $this->fail('A revoked board member was allowed to set NPO engagement scope.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        DB::statement('SAVEPOINT npo_board_mismatch_probe');

        try {
            $mismatchUser = $this->boardUser('board-fk-mismatch@example.test');

            DB::table('npo_board_members')->insert([
                'id' => (string) Str::uuid(),
                'client_id' => $otherClient->id,
                'npo_engagement_id' => $engagement->id,
                'user_id' => $mismatchUser->id,
                'treasurer' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('A board membership row was attached to an engagement from another client.');
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT npo_board_mismatch_probe');
            $this->assertStringContainsString('violates foreign key constraint', $e->getMessage());
        } finally {
            DB::statement('RELEASE SAVEPOINT npo_board_mismatch_probe');
        }

        $this->assertSame($client->id, $engagement->client_id);
    }

    public function test_funder_contact_links_are_report_scoped_review_gated_and_bound_to_the_link(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Funder report RLS assertions require Postgres.');
        }

        [$advisor, $client, $engagement] = $this->npoClient('funder-access-advisor@example.test', 'Funder Access Trust');
        $report = $this->report($client, $engagement, ReportType::FunderAccountability, 'reviewed');
        $otherReport = $this->report($client, $engagement, ReportType::FunderAccountability, 'reviewed');
        $impactReport = $this->report($client, $engagement, ReportType::ImpactSummary, 'reviewed');
        $draftReport = $this->report($client, $engagement, ReportType::FunderAccountability, 'pending_review');
        $this->section($report);
        $this->section($otherReport);
        $this->section($impactReport);
        $this->section($draftReport);
        $access = app(NpoFunderReportAccess::class);

        ['link' => $link, 'token' => $token] = $access->issueLink($report, $advisor, 'funder@example.test');

        $this->assertNotSame($token, $link->token_hash);
        $this->assertSame(64, strlen((string) $link->token_hash));

        $this->expectException(InvalidArgumentException::class);
        $access->issueLink($impactReport, $advisor, 'impact@example.test');
    }

    public function test_funder_contact_rls_and_link_resolution_do_not_follow_requested_report_ids(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Funder report RLS assertions require Postgres.');
        }

        [$advisor, $client, $engagement] = $this->npoClient('funder-rls-advisor@example.test', 'Funder RLS Trust');
        $report = $this->report($client, $engagement, ReportType::FunderAccountability, 'reviewed');
        $otherReport = $this->report($client, $engagement, ReportType::FunderAccountability, 'reviewed');
        $impactReport = $this->report($client, $engagement, ReportType::ImpactSummary, 'reviewed');
        $draftReport = $this->report($client, $engagement, ReportType::FunderAccountability, 'pending_review');
        $this->section($report);
        $this->section($otherReport);
        $this->section($impactReport);
        $this->section($draftReport);
        $access = app(NpoFunderReportAccess::class);
        ['token' => $token] = $access->issueLink($report, $advisor, 'funder-rls@example.test');

        $resolved = $access->resolveToken($token, requestedReportId: (string) $otherReport->id);

        $this->assertSame($report->id, $resolved->id);
        $this->assertDatabaseHas('npo_funder_report_sessions', [
            'report_id' => $report->id,
        ]);
        $this->assertSame(1, NpoFunderReportSession::query()->where('report_id', $report->id)->count());

        app(RequestContext::class)->apply(NpoFunderReportAccess::ROLE_FUNDER_CONTACT, [], reportId: (string) $report->id);
        $visible = $this->withRlsRole(fn (): array => [
            'reports' => Report::query()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
            'section_report_ids' => ReportSection::query()->pluck('report_id')->unique()->values()->map(fn (mixed $id): string => (string) $id)->all(),
        ]);
        $this->assertSame([$report->id], $visible['reports']);
        $this->assertSame([$report->id], $visible['section_report_ids']);

        app(RequestContext::class)->apply(NpoFunderReportAccess::ROLE_FUNDER_CONTACT, [], reportId: (string) $impactReport->id);
        $this->assertSame(0, $this->withRlsRole(fn (): int => Report::query()->count()));

        app(RequestContext::class)->apply(NpoFunderReportAccess::ROLE_FUNDER_CONTACT, [], reportId: (string) $draftReport->id);
        $this->assertSame(0, $this->withRlsRole(fn (): int => Report::query()->count()));

        app(RequestContext::class)->apply('advisor', [], (string) $advisor->id, reportId: (string) $report->id);
        $this->assertSame(0, $this->withRlsRole(fn (): int => Report::query()->count()));

        app(RequestContext::class)->apply(NpoFunderReportAccess::ROLE_FUNDER_CONTACT, [], reportId: (string) $report->id);
        app(RequestContext::class)->apply('advisor', [], (string) $advisor->id);
        $this->assertSame(0, $this->withRlsRole(fn (): int => Report::query()->count()));

        $this->expectException(InvalidArgumentException::class);
        $access->issueLink($draftReport, $advisor, 'draft@example.test');
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(string $advisorEmail, string $clientName): array
    {
        $advisor = User::factory()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->id,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        return [$advisor, $client, $this->npoEngagement($client, NpoEngagementSubType::StandardNpo)];
    }

    private function npoEngagement(Client $client, NpoEngagementSubType $type): NpoEngagement
    {
        return NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => $type,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);
    }

    private function boardUser(string $email = 'board-member@example.test'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'user_type' => User::TYPE_NPO_BOARD_MEMBER,
            'primary_role' => User::TYPE_NPO_BOARD_MEMBER,
        ]);
        $user->assignRole(User::TYPE_NPO_BOARD_MEMBER);

        return $user;
    }

    private function report(Client $client, NpoEngagement $engagement, ReportType $type, string $reviewStatus): Report
    {
        return Report::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'type' => $type,
            'title' => $type->label().' - '.$client->legal_name,
            'generated_at' => now(),
            'metadata' => [],
            'review_status' => $reviewStatus,
            'reviewed_at' => $reviewStatus === 'reviewed' ? now() : null,
        ]);
    }

    private function section(Report $report): ReportSection
    {
        return ReportSection::query()->create([
            'report_id' => $report->id,
            'client_id' => $report->client_id,
            'key' => 'summary',
            'title' => 'Summary',
            'body' => 'Board-visible summary.',
            'position' => 1,
            'attributions' => [['claim' => 'Summary', 'source_reference' => 'test']],
            'document_support' => 'none',
            'document_support_note' => 'No document support required for this test.',
            'data_quality_note' => 'Data quality note: test fixture.',
            'metadata' => [],
        ]);
    }

    private function dimensionScore(Client $client, NpoEngagement $engagement, int $dimension): NpoDimensionScore
    {
        return NpoDimensionScore::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'assessment_batch_id' => (string) Str::uuid(),
            'dimension_number' => $dimension,
            'dimension_key' => 'governance',
            'dimension_label' => 'Governance',
            'tiriti_mode' => NpoTiritiMode::Standalone,
            'score' => 72,
            'advisor_weight' => 25,
            'weighted_score' => 18,
            'health_score' => 72,
            'findings' => [],
            'source_attributions' => [],
            'scoring_context' => [],
            'captured_at' => now(),
        ]);
    }

    /**
     * @return array{0: Milestone, 1: MilestoneAction}
     */
    private function milestoneAndAction(Client $client, NpoEngagement $engagement): array
    {
        $goalId = (string) Str::uuid();
        DB::table('goals')->insert([
            'id' => $goalId,
            'client_id' => $client->id,
            'title' => 'Board plan',
            'pv_target' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milestone = Milestone::query()->create([
            'goal_id' => $goalId,
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'title' => 'Board-visible milestone',
            'pv_of_impact' => 0,
            'status' => Milestone::STATUS_PENDING,
        ]);

        $action = MilestoneAction::query()->create([
            'milestone_id' => $milestone->id,
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'title' => 'Board-visible action',
            'priority' => 'normal',
            'status' => MilestoneAction::STATUS_PENDING,
        ]);

        return [$milestone, $action];
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
            GRANT SELECT ON npo_board_members, npo_engagements, npo_dimension_scores, reports, report_sections, milestones, milestone_actions, entrepreneur_profiles TO %1$s;
            GRANT EXECUTE ON FUNCTION fsa_user_is_board_member_of(uuid) TO %1$s;
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
            DB::statement('SAVEPOINT npo_access_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT npo_access_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT npo_access_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
