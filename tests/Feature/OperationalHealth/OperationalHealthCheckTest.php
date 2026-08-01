<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalHealth;

use App\Console\Commands\RunOperationalHealthChecks;
use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Models\User;
use App\Services\OperationalHealth\OperationalHealthSchedule;
use App\Services\Pdf\PdfRenderer;
use App\Services\Security\StepUpEvaluator;
use App\Support\ReleaseVersion;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OperationalHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_health_checks_are_scheduled_hourly_in_new_zealand_timezone(): void
    {
        config()->set('operational_health.enabled', true);
        config()->set('operational_health.timezone', 'Pacific/Auckland');
        config()->set('operational_health.weekday_times', null);
        config()->set('operational_health.weekend_times', null);

        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events());
        $weekday = $events
            ->filter(fn ($event): bool => str_starts_with((string) $event->description, 'fsa-operational-health-check-weekday-'))
            ->unique('description')
            ->values();
        $weekend = $events
            ->filter(fn ($event): bool => str_starts_with((string) $event->description, 'fsa-operational-health-check-weekend-'))
            ->unique('description')
            ->values();

        $this->assertCount(24, $weekday);
        $this->assertCount(24, $weekend);
        $this->assertSame(OperationalHealthSchedule::DEFAULT_WEEKDAY_TIMES, $weekday->map(
            fn ($event): string => substr((string) $event->description, -4, 2).':00',
        )->all());
        $this->assertSame('0 0 * * 1-5', $weekday->first()->expression);
        $this->assertSame('0 23 * * 1-5', $weekday->last()->expression);
        $this->assertSame('0 0 * * 6,0', $weekend->first()->expression);
        $this->assertSame('0 23 * * 6,0', $weekend->last()->expression);
        $this->assertSame(['Pacific/Auckland'], $weekday->pluck('timezone')->unique()->values()->all());
        $this->assertSame('Pacific/Auckland', $weekend->first()->timezone);

        $quarantineRecovery = $events
            ->first(fn ($event): bool => $event->description === 'fsa-rescan-quarantined-documents');

        $this->assertNotNull($quarantineRecovery);
        $this->assertSame('*/10 * * * *', $quarantineRecovery->expression);
        $this->assertStringContainsString('fsa:rescan-quarantined-documents', $quarantineRecovery->command);
    }

    public function test_command_records_specific_findings_and_skips_missing_monitor_fixtures(): void
    {
        config()->set('operational_health.ensure_fixtures', false);

        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'ops-monitor-admin@example.test');

        $this->artisan(RunOperationalHealthChecks::class)
            ->assertSuccessful();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(OperationalHealthCheckRun::STATUS_WARNING, $run->status);
        $this->assertGreaterThan(0, $run->passed_checks);
        $this->assertSame(0, $run->failed_checks);
        $this->assertGreaterThan(0, $run->skipped_checks);

        $this->assertDatabaseHas('operational_health_check_results', [
            'run_id' => $run->id,
            'check_key' => 'core.up',
            'status' => OperationalHealthCheckResult::STATUS_PASSED,
            'actual_status' => 200,
        ]);

        $this->assertDatabaseHas('operational_health_check_results', [
            'run_id' => $run->id,
            'check_key' => 'admin.app_health.index',
            'status' => OperationalHealthCheckResult::STATUS_PASSED,
            'actor_user_id' => $admin->getKey(),
        ]);

        $skipped = OperationalHealthCheckResult::query()
            ->where('run_id', $run->id)
            ->where('check_key', 'portal.entrepreneur.plan.preview')
            ->firstOrFail();

        $this->assertSame(OperationalHealthCheckResult::STATUS_SKIPPED, $skipped->status);
        $this->assertStringContainsString('Monitor fixture missing', (string) $skipped->issue_summary);
        $this->assertNotNull($skipped->fingerprint);
        $this->assertSame(1, $skipped->consecutive_failures);
    }

    public function test_seeded_monitor_fixtures_let_recurring_workflow_checks_run(): void
    {
        Storage::fake('secure_local');

        $this->fakePdfRenderer();

        $this->assertArrayHasKey('fsa:seed-operational-health-fixtures', Artisan::all());

        $this->artisan('fsa:seed-operational-health-fixtures')
            ->assertSuccessful();

        $this->artisan(RunOperationalHealthChecks::class)
            ->assertSuccessful();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(OperationalHealthCheckRun::STATUS_PASSED, $run->status);
        $this->assertSame(0, $run->failed_checks);
        $this->assertSame(0, $run->skipped_checks);

        foreach ([
            'portal.dashboard',
            'portal.business_plan_budget.document',
            'portal.business_plan_budget.pdf',
            'portal.dd_plan.preview',
            'portal.entrepreneur.plan.preview',
            'portal.documents.show',
            'advisor.templates.preview',
        ] as $checkKey) {
            $this->assertDatabaseHas('operational_health_check_results', [
                'run_id' => $run->id,
                'check_key' => $checkKey,
                'status' => OperationalHealthCheckResult::STATUS_PASSED,
            ]);
        }
    }

    public function test_client_monitor_selection_ignores_suspended_configured_client_assignments(): void
    {
        config()->set('operational_health.ensure_fixtures', false);
        config()->set('operational_health.users.client_email', 'configured-suspended-client@example.test');

        $this->fakePdfRenderer();

        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'active-client-monitor-admin@example.test');
        $configuredUser = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'configured-suspended-client@example.test');
        $fallbackUser = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'fallback-active-client@example.test');
        $suspendedClient = $this->clientFixture(
            'Suspended monitor fixture',
            EngagementType::STANDARD_ADVISORY,
            ClientStatus::SUSPENDED,
            $configuredUser,
            $admin,
        );
        $activeClient = $this->clientFixture(
            'Active monitor fixture',
            EngagementType::STANDARD_ADVISORY,
            ClientStatus::ACTIVE,
            $fallbackUser,
            $admin,
        );

        ClientTeamMember::query()->create([
            'client_id' => $suspendedClient->getKey(),
            'user_id' => $configuredUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal', EngagementType::STANDARD_ADVISORY->value],
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $activeClient->getKey(),
            'user_id' => $fallbackUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal', EngagementType::STANDARD_ADVISORY->value],
        ]);

        $this->artisan(RunOperationalHealthChecks::class)
            ->assertSuccessful();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        foreach ([
            'portal.dashboard',
            'portal.business_plan_budget.document',
            'portal.business_plan_budget.pdf',
        ] as $checkKey) {
            $this->assertDatabaseHas('operational_health_check_results', [
                'run_id' => $run->id,
                'check_key' => $checkKey,
                'status' => OperationalHealthCheckResult::STATUS_PASSED,
                'actor_user_id' => $fallbackUser->getKey(),
            ]);
        }
    }

    public function test_admin_page_shows_latest_specific_issue_and_recurring_fingerprints(): void
    {
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'app-health-admin@example.test');
        $run = $this->healthRun(OperationalHealthCheckRun::STATUS_FAILED);
        $first = $this->healthResult($run, [
            'created_at' => now()->subDay(),
            'consecutive_failures' => 1,
            'failures_last_7_days' => 1,
            'failures_last_30_days' => 1,
        ]);
        $second = $this->healthResult($run, [
            'consecutive_failures' => 2,
            'failures_last_7_days' => 2,
            'failures_last_30_days' => 2,
            'first_seen_at' => $first->created_at,
        ]);

        $this->actingAsMfa($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.app-health.index'))
            ->assertOk()
            ->assertJsonPath('component', 'admin/app-health/Index')
            ->assertJsonPath('props.summary.latest_status', OperationalHealthCheckRun::STATUS_FAILED)
            ->assertJsonPath('props.summary.failed_checks', 1)
            ->assertJsonPath('props.summary.latest_issue.issue_summary', 'Entrepreneur business plan preview returned HTTP 500; expected 200.')
            ->assertJsonPath('props.summary.latest_issue.consecutive_failures', 2)
            ->assertJsonPath('props.recurringIssues.0.id', $second->id)
            ->assertJsonPath('props.recurringIssues.0.failures_last_7_days', 2)
            ->assertJsonPath('props.results.data.0.issue_summary', 'Entrepreneur business plan preview returned HTTP 500; expected 200.');
    }

    public function test_admin_page_reports_run_dates_and_today_counts_in_the_operational_timezone(): void
    {
        config()->set('operational_health.timezone', 'Pacific/Auckland');
        config()->set('operational_health.weekday_times', null);
        config()->set('operational_health.weekend_times', null);

        Carbon::setTestNow(Carbon::parse('2026-07-31 18:00:00', 'Pacific/Auckland'));

        try {
            $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'app-health-timezone-admin@example.test');
            $this->healthRun(OperationalHealthCheckRun::STATUS_PASSED, [
                'started_at' => Carbon::parse('2026-07-30 21:30:00', 'UTC'),
                'finished_at' => Carbon::parse('2026-07-30 21:31:00', 'UTC'),
                'total_checks' => 3,
                'passed_checks' => 3,
                'failed_checks' => 0,
            ]);

            $this->actingAsMfa($admin)
                ->withHeaders($this->inertiaHeaders())
                ->get(route('admin.app-health.index'))
                ->assertOk()
                ->assertJsonPath('props.summary.latest_started_at_label', '31 Jul 2026, 9:30 AM')
                ->assertJsonPath('props.summary.schedule.today_label', '31 Jul 2026')
                ->assertJsonPath('props.summary.schedule.timezone', 'Pacific/Auckland')
                ->assertJsonPath('props.summary.schedule.expected_runs_today', 24)
                ->assertJsonPath('props.summary.schedule.due_runs_today', 19)
                ->assertJsonPath('props.summary.schedule.completed_runs_today', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_client_users_cannot_view_operational_health_page(): void
    {
        $client = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'app-health-client@example.test');

        $this->actingAsMfa($client)
            ->get(route('admin.app-health.index'))
            ->assertForbidden();
    }

    public function test_run_now_preserves_the_administrator_session(): void
    {
        Storage::fake('secure_local');

        $this->fakePdfRenderer();

        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'run-now-app-health@example.test');
        $browserIp = '203.0.113.10';
        $browserUserAgent = 'FutureShift test browser';

        $this->actingAsMfa($admin)
            ->withServerVariables([
                'REMOTE_ADDR' => $browserIp,
                'HTTP_USER_AGENT' => $browserUserAgent,
            ])
            ->withSession([
                StepUpEvaluator::SESSION_IP_ADDRESS => $browserIp,
                StepUpEvaluator::SESSION_COUNTRY => '',
                StepUpEvaluator::SESSION_USER_AGENT => $browserUserAgent,
                StepUpEvaluator::SESSION_DEVICE_FINGERPRINT => hash('sha256', $browserIp.'||'.$browserUserAgent),
            ])
            ->post(route('admin.app-health.run'))
            ->assertRedirect(route('admin.app-health.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('operational_health_check_results', [
            'check_key' => 'admin.app_health.index',
            'status' => OperationalHealthCheckResult::STATUS_PASSED,
        ]);
        $this->assertDatabaseHas('operational_health_check_results', [
            'check_key' => 'admin.audit_trail.index',
            'status' => OperationalHealthCheckResult::STATUS_PASSED,
        ]);

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(OperationalHealthCheckRun::STATUS_PASSED, $run->status);
        $this->assertSame(0, $run->failed_checks);
        $this->assertSame(0, $run->skipped_checks);

        $this->withHeaders($this->inertiaHeaders())
            ->get(route('admin.app-health.index'))
            ->assertOk();
    }

    public function test_dashboard_action_board_payload_includes_app_check_errors(): void
    {
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'dashboard-app-health@example.test');
        $run = $this->healthRun(OperationalHealthCheckRun::STATUS_FAILED);
        $this->healthResult($run, [
            'consecutive_failures' => 3,
            'failures_last_7_days' => 3,
            'failures_last_30_days' => 3,
        ]);

        $this->actingAsMfa($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('component', 'advisor/Dashboard')
            ->assertJsonPath('props.operationalHealth.summary.failed', 1)
            ->assertJsonPath('props.operationalHealth.summary.warning', 0)
            ->assertJsonPath('props.operationalHealth.index_url', route('admin.app-health.index', absolute: false))
            ->assertJsonPath('props.operationalHealth.latest_issue.issue_summary', 'Entrepreneur business plan preview returned HTTP 500; expected 200.')
            ->assertJsonPath('props.operationalHealth.latest_issue.consecutive_failures', 3);
    }

    private function fakePdfRenderer(): void
    {
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    private function clientFixture(
        string $legalName,
        EngagementType $engagementType,
        ClientStatus $status,
        User $primaryContact,
        User $createdBy,
    ): Client {
        /** @var Client $client */
        $client = Client::query()->create([
            'legal_name' => $legalName,
            'trading_name' => $legalName,
            'engagement_type' => $engagementType->value,
            'status' => $status->value,
            'entity_type' => 'company',
            'address' => ['country' => 'NZ'],
            'gst_registered' => false,
            'directors' => [],
            'filing_status' => 'monitor_fixture',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => [
                'source' => 'operational_health_test',
            ],
            'primary_contact_user_id' => $primaryContact->getKey(),
            'created_by_user_id' => $createdBy->getKey(),
        ]);

        return $client;
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $role,
            'primary_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->inertiaVersion(),
        ];
    }

    private function inertiaVersion(): string
    {
        $assetVersion = null;
        if (config('app.asset_url')) {
            $assetVersion = hash('xxh128', (string) config('app.asset_url'));
        }

        $manifest = public_path('build/manifest.json');
        if ($assetVersion === null && file_exists($manifest)) {
            $assetVersion = (string) hash_file('xxh128', $manifest);
        }

        $mixManifest = public_path('mix-manifest.json');
        if ($assetVersion === null && file_exists($mixManifest)) {
            $assetVersion = (string) hash_file('xxh128', $mixManifest);
        }

        $releaseVersion = app(ReleaseVersion::class)->current();

        if ($releaseVersion === '') {
            return (string) $assetVersion;
        }

        return $assetVersion !== null
            ? $releaseVersion.'-'.$assetVersion
            : $releaseVersion;
    }

    private function healthRun(string $status, array $overrides = []): OperationalHealthCheckRun
    {
        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->create([
            'status' => $status,
            'environment' => 'testing',
            'release_version' => '1.2.3',
            'app_url' => 'https://test.future-shift.test',
            'duration_ms' => 123,
            'total_checks' => 1,
            'passed_checks' => 0,
            'warning_checks' => 0,
            'failed_checks' => 1,
            'skipped_checks' => 0,
            'started_at' => now(),
            'finished_at' => now(),
            ...$overrides,
        ]);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function healthResult(OperationalHealthCheckRun $run, array $overrides = []): OperationalHealthCheckResult
    {
        /** @var OperationalHealthCheckResult $result */
        $result = $run->results()->create([
            'check_key' => 'portal.entrepreneur.plan.preview',
            'name' => 'Entrepreneur business plan preview',
            'area' => 'Plan preview',
            'status' => OperationalHealthCheckResult::STATUS_FAILED,
            'method' => 'GET',
            'url' => '/portal/entrepreneur/plan/preview',
            'route_name' => 'portal.entrepreneur.plan.preview',
            'expected_statuses' => [200],
            'actual_status' => 500,
            'expected_content_type' => 'application/pdf',
            'actual_content_type' => 'text/html; charset=UTF-8',
            'response_time_ms' => 816,
            'actor_label' => 'Monitor Founder <founder@example.test>',
            'actor_role' => User::TYPE_ENTREPRENEUR,
            'workflow_subject_type' => 'entrepreneur_profile',
            'workflow_subject_id' => 'profile-1',
            'workflow_subject_label' => 'Monitor Founder',
            'expected_behavior' => 'An entrepreneur monitor user should be able to open the business plan PDF preview.',
            'issue_summary' => 'Entrepreneur business plan preview returned HTTP 500; expected 200.',
            'issue_detail' => 'Check portal.entrepreneur.plan.preview / Entrepreneur business plan preview. Request: GET /portal/entrepreneur/plan/preview. Actor: Monitor Founder. Expected HTTP status: 200. Actual HTTP status: 500.',
            'fingerprint' => hash('sha256', 'portal.entrepreneur.plan.preview|500'),
            'consecutive_failures' => 1,
            'failures_last_7_days' => 1,
            'failures_last_30_days' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'context' => ['body_excerpt' => 'Server Error'],
            ...$overrides,
        ]);

        return $result;
    }
}
