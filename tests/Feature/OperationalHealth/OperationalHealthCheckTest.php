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
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Notifications\OperationalHealthAttentionNotification;
use App\Services\OperationalHealth\OperationalHealthAlerter;
use App\Services\OperationalHealth\OperationalHealthCheckRunner;
use App\Services\OperationalHealth\OperationalHealthSchedule;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\SimpleTextPdf;
use App\Services\Security\StepUpEvaluator;
use App\Support\ReleaseVersion;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    public function test_health_checks_are_scheduled_for_business_hours_with_an_hourly_sentinel(): void
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

        $this->assertCount(10, $weekday);
        $this->assertCount(1, $weekend);
        $this->assertSame(OperationalHealthSchedule::DEFAULT_WEEKDAY_TIMES, $weekday->map(
            fn ($event): string => $this->timeFromOperationalHealthEventName((string) $event->description),
        )->all());
        $this->assertSame(OperationalHealthSchedule::DEFAULT_WEEKEND_TIMES, $weekend->map(
            fn ($event): string => $this->timeFromOperationalHealthEventName((string) $event->description),
        )->all());
        $this->assertSame('30 7 * * 1-5', $weekday->first()->expression);
        $this->assertSame('30 16 * * 1-5', $weekday->last()->expression);
        $this->assertSame('30 7 * * 6,0', $weekend->first()->expression);
        $this->assertSame(['Pacific/Auckland'], $weekday->pluck('timezone')->unique()->values()->all());
        $this->assertSame('Pacific/Auckland', $weekend->first()->timezone);

        $sentinel = $events
            ->first(fn ($event): bool => $event->description === 'fsa-operational-health-sentinel');

        $this->assertNotNull($sentinel);
        $this->assertSame('0 * * * *', $sentinel->expression);
        $this->assertSame('Pacific/Auckland', $sentinel->timezone);
        $this->assertStringContainsString('--sentinel', $sentinel->command);

        $dueRunner = $events
            ->first(fn ($event): bool => $event->description === 'fsa-operational-health-due-runner');

        $this->assertNotNull($dueRunner);
        $this->assertSame('15,45 * * * *', $dueRunner->expression);
        $this->assertSame('Pacific/Auckland', $dueRunner->timezone);
        $this->assertStringContainsString('fsa:operational-health-check:due', $dueRunner->command);

        $quarantineRecovery = $events
            ->first(fn ($event): bool => $event->description === 'fsa-rescan-quarantined-documents');

        $this->assertNotNull($quarantineRecovery);
        $this->assertSame('*/10 * * * *', $quarantineRecovery->expression);
        $this->assertStringContainsString('fsa:rescan-quarantined-documents', $quarantineRecovery->command);
    }

    public function test_due_runner_catches_up_missed_weekday_health_checks_once(): void
    {
        config()->set('operational_health.enabled', true);
        config()->set('operational_health.timezone', 'Pacific/Auckland');
        config()->set('operational_health.weekday_times', null);
        config()->set('operational_health.weekend_times', null);

        Carbon::setTestNow(Carbon::parse('2026-08-10 08:50:00', 'Pacific/Auckland'));

        $this->artisan('fsa:operational-health-check:due', [
            '--ensure-fixtures' => true,
            '--grace-minutes' => 10,
        ])->assertSuccessful();

        $runs = OperationalHealthCheckRun::query()
            ->where('metadata->trigger', 'scheduled_due')
            ->orderBy('metadata->scheduled_for')
            ->get();

        $this->assertCount(2, $runs);
        $this->assertSame([
            '2026-08-10T07:30:00+12:00',
            '2026-08-10T08:30:00+12:00',
        ], $runs->pluck('metadata.scheduled_for')->all());

        $this->artisan('fsa:operational-health-check:due', [
            '--ensure-fixtures' => true,
            '--grace-minutes' => 10,
        ])->assertSuccessful();

        $this->assertSame(2, OperationalHealthCheckRun::query()
            ->where('metadata->trigger', 'scheduled_due')
            ->count());
    }

    public function test_command_records_specific_findings_and_skips_missing_monitor_fixtures(): void
    {
        config()->set('operational_health.ensure_fixtures', false);

        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'ops-monitor-admin@example.test');

        $this->artisan(RunOperationalHealthChecks::class)
            ->assertSuccessful();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(OperationalHealthCheckRun::STATUS_PASSED, $run->status);
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

    public function test_sentinel_scope_runs_only_always_on_client_facing_checks(): void
    {
        config()->set('operational_health.ensure_fixtures', false);

        $this->userWithRole(User::TYPE_SUPER_ADMIN, 'sentinel-admin@example.test');

        $this->artisan(RunOperationalHealthChecks::class, ['--sentinel' => true])
            ->assertSuccessful();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();
        $checkKeys = $run->results()->pluck('check_key')->all();

        $this->assertSame('sentinel', data_get($run->metadata, 'scope'));
        $this->assertContains('core.up', $checkKeys);
        $this->assertContains('system.pending_migrations', $checkKeys);
        $this->assertContains('public.home', $checkKeys);
        $this->assertContains('auth.login', $checkKeys);
        $this->assertContains('pwa.service_worker', $checkKeys);
        $this->assertContains('deployment.identity', $checkKeys);
        $this->assertContains('staff.dashboard', $checkKeys);
        $this->assertContains('portal.dashboard', $checkKeys);
        $this->assertContains('portal.entrepreneur.dashboard', $checkKeys);
        $this->assertContains('portal.documents.show', $checkKeys);
        $this->assertNotContains('portal.business_plan_budget.pdf', $checkKeys);
        $this->assertNotContains('advisor.dd_client.show', $checkKeys);
        $this->assertNotContains('portal.dd_business_plan_budget.workspace', $checkKeys);
        $this->assertNotContains('portal.dd_business_plan_budget.business_plan_pdf', $checkKeys);
        $this->assertNotContains('portal.dd_business_plan_budget.budget_pack_pdf', $checkKeys);
        $this->assertNotContains('portal.dd_plan.preview', $checkKeys);
        $this->assertNotContains('advisor.templates.preview', $checkKeys);

        $serviceWorker = OperationalHealthCheckResult::query()
            ->where('run_id', $run->id)
            ->where('check_key', 'pwa.service_worker')
            ->firstOrFail();

        $this->assertSame(OperationalHealthCheckResult::STATUS_PASSED, $serviceWorker->status);
        $this->assertSame([], data_get($serviceWorker->context, 'header_failures'));
    }

    public function test_sentinel_checks_the_public_login_route_through_the_nginx_edge(): void
    {
        config()->set('operational_health.external_edge_enabled', true);
        config()->set('operational_health.external_edge_url', 'https://futureshiftadvisory.nz');
        Http::fake([
            'https://futureshiftadvisory.nz/login' => Http::response(
                '<!doctype html><title>Log in</title>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $run = app(OperationalHealthCheckRunner::class)
            ->run(OperationalHealthCheckRunner::SCOPE_SENTINEL);

        /** @var OperationalHealthCheckResult $result */
        $result = $run->results()
            ->where('check_key', 'public.edge.login')
            ->firstOrFail();

        $this->assertSame(OperationalHealthCheckResult::STATUS_PASSED, $result->status);
        $this->assertSame(200, $result->actual_status);
        $this->assertFalse((bool) data_get($result->context, 'internal_request'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://futureshiftadvisory.nz/login'
            && $request->method() === 'GET');
    }

    public function test_sentinel_records_an_nginx_bad_gateway_from_the_public_edge(): void
    {
        config()->set('operational_health.external_edge_enabled', true);
        config()->set('operational_health.external_edge_url', 'https://futureshiftadvisory.nz');
        Http::fake([
            'https://futureshiftadvisory.nz/login' => Http::response(
                '502 Bad Gateway',
                502,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $run = app(OperationalHealthCheckRunner::class)
            ->run(OperationalHealthCheckRunner::SCOPE_SENTINEL);

        /** @var OperationalHealthCheckResult $result */
        $result = $run->results()
            ->where('check_key', 'public.edge.login')
            ->firstOrFail();

        $this->assertSame(OperationalHealthCheckResult::STATUS_FAILED, $result->status);
        $this->assertSame(502, $result->actual_status);
        $this->assertFalse((bool) data_get($result->context, 'internal_request'));
        $this->assertStringContainsString('HTTP 502', (string) $result->issue_summary);
    }

    public function test_seeded_monitor_fixtures_let_recurring_workflow_checks_run(): void
    {
        Storage::fake('secure_local');

        $this->fakePdfRenderer();

        $this->assertArrayHasKey('fsa:seed-operational-health-fixtures', Artisan::all());

        $this->artisan('fsa:seed-operational-health-fixtures')
            ->assertSuccessful();

        $this->assertDatabaseHas('service_activations', [
            'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
        ]);
        $this->assertDatabaseHas('reports', [
            'title' => 'Operational Health DD Decision Report',
            'render_status' => Report::RENDER_STATUS_RENDERED,
            'review_status' => 'reviewed',
        ]);
        $feedbackReport = Report::query()
            ->where('title', 'Operational Health DD Decision Report')
            ->firstOrFail();
        $feedbackMetadata = $feedbackReport->metadata;

        $exitCode = $this->artisan(RunOperationalHealthChecks::class)->run();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(
            0,
            $exitCode,
            $run->results()
                ->where('status', '!=', OperationalHealthCheckResult::STATUS_PASSED)
                ->get(['check_key', 'status', 'actual_status', 'issue_summary', 'exception_message'])
                ->toJson(),
        );
        $feedbackReportAfterRun = $feedbackReport->fresh();
        $this->assertInstanceOf(Report::class, $feedbackReportAfterRun);
        $this->assertSame($feedbackMetadata, $feedbackReportAfterRun->metadata);

        $this->assertSame(
            OperationalHealthCheckRun::STATUS_PASSED,
            $run->status,
            $run->results()
                ->where('status', '!=', OperationalHealthCheckResult::STATUS_PASSED)
                ->get(['check_key', 'status', 'actual_status', 'issue_summary'])
                ->toJson(),
        );
        $this->assertSame(0, $run->failed_checks);
        $this->assertSame(0, $run->skipped_checks);

        /** @var OperationalHealthCheckResult $feedbackCheck */
        $feedbackCheck = $run->results()
            ->where('check_key', 'advisor.dd_client.feedback')
            ->firstOrFail();

        $this->assertSame(200, $feedbackCheck->actual_status);
        $this->assertSame('PATCH', $feedbackCheck->method);
        $this->assertSame('PATCH', data_get($feedbackCheck->context, 'response_headers.allow'));
        $this->assertFalse((bool) data_get($feedbackCheck->context, 'internal_request'));

        foreach ([
            'system.pending_migrations',
            'portal.dashboard',
            'advisor.clients.show',
            'advisor.dd_client.show',
            'advisor.dd_client.feedback',
            'portal.dd_business_plan_budget.workspace',
            'portal.business_plan_budget.document',
            'portal.business_plan_budget.pdf',
            'portal.dd_business_plan_budget.business_plan_pdf',
            'portal.dd_business_plan_budget.budget_pack_pdf',
            'portal.dd_plan.preview',
            'portal.entrepreneur.plan.preview',
            'portal.entrepreneur.dashboard',
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

    public function test_pdf_health_checks_warn_when_the_simple_fallback_renderer_is_served(): void
    {
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new \RuntimeException('Browser renderer unavailable');
            }
        });

        $this->artisan('fsa:seed-operational-health-fixtures')
            ->assertSuccessful();

        $exitCode = $this->artisan(RunOperationalHealthChecks::class)->run();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(
            0,
            $exitCode,
            $run->results()
                ->where('status', OperationalHealthCheckResult::STATUS_FAILED)
                ->get(['check_key', 'actual_status', 'issue_summary', 'issue_detail'])
                ->toJson(),
        );

        $this->assertSame(OperationalHealthCheckRun::STATUS_WARNING, $run->status);
        $this->assertGreaterThanOrEqual(1, $run->warning_checks);

        /** @var OperationalHealthCheckResult $result */
        $result = OperationalHealthCheckResult::query()
            ->where('run_id', $run->id)
            ->where('check_key', 'portal.entrepreneur.plan.preview')
            ->firstOrFail();

        $this->assertSame(OperationalHealthCheckResult::STATUS_WARNING, $result->status);
        $this->assertStringContainsString('simple fallback PDF renderer', (string) $result->issue_summary);
        $this->assertTrue((bool) data_get($result->context, 'fallback_pdf_detected'));
        $this->assertSame(SimpleTextPdf::FALLBACK_MARKER, data_get($result->context, 'fallback_pdf_marker'));
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

    public function test_admin_page_treats_skipped_monitor_fixtures_as_coverage_gaps_not_active_warnings(): void
    {
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'app-health-skipped-admin@example.test');
        $oldRun = $this->healthRun(OperationalHealthCheckRun::STATUS_FAILED, [
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        $this->healthResult($oldRun, [
            'created_at' => now()->subDay(),
            'consecutive_failures' => 4,
            'failures_last_7_days' => 4,
            'failures_last_30_days' => 4,
        ]);
        $latestRun = $this->healthRun(OperationalHealthCheckRun::STATUS_PASSED, [
            'total_checks' => 2,
            'passed_checks' => 1,
            'failed_checks' => 0,
            'skipped_checks' => 1,
        ]);
        $this->healthResult($latestRun, [
            'status' => OperationalHealthCheckResult::STATUS_SKIPPED,
            'actual_status' => null,
            'actual_content_type' => null,
            'issue_summary' => 'Monitor fixture missing: No entrepreneur monitor user with an entrepreneur profile is available.',
            'fingerprint' => hash('sha256', 'skipped-monitor-fixture'),
            'consecutive_failures' => 12,
            'failures_last_7_days' => 7,
            'failures_last_30_days' => 12,
        ]);

        $this->actingAsMfa($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.app-health.index'))
            ->assertOk()
            ->assertJsonPath('props.summary.latest_status', OperationalHealthCheckRun::STATUS_PASSED)
            ->assertJsonPath('props.summary.failed_checks', 0)
            ->assertJsonPath('props.summary.warning_checks', 0)
            ->assertJsonPath('props.summary.skipped_checks', 1)
            ->assertJsonPath('props.summary.latest_issue', null)
            ->assertJsonCount(0, 'props.recurringIssues');
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
                ->assertJsonPath('props.summary.schedule.expected_runs_today', 10)
                ->assertJsonPath('props.summary.schedule.due_runs_today', 10)
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
        $this->assertDatabaseHas('operational_health_check_results', [
            'check_key' => 'auth.login',
            'status' => OperationalHealthCheckResult::STATUS_PASSED,
            'actual_status' => 200,
            'actor_user_id' => null,
        ]);

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->latest()->firstOrFail();

        $this->assertSame(
            OperationalHealthCheckRun::STATUS_PASSED,
            $run->status,
            $run->results()
                ->where('status', '!=', OperationalHealthCheckResult::STATUS_PASSED)
                ->get(['check_key', 'status', 'actual_status', 'issue_summary'])
                ->toJson(),
        );
        $this->assertSame(0, $run->failed_checks);
        $this->assertSame(0, $run->skipped_checks);

        $this->withHeaders($this->inertiaHeaders())
            ->get(route('admin.app-health.index'))
            ->assertOk();
    }

    public function test_run_now_records_failed_checks_without_delivering_alerts_from_browser_request(): void
    {
        Notification::fake();
        Storage::fake('secure_local');

        $previousExecutionLimit = ini_get('max_execution_time');

        config()->set('operational_health.ensure_fixtures', false);
        config()->set('operational_health.require_verified_deployment', true);
        config()->set('operational_health.alerts.consecutive_failures', 1);

        $this->fakePdfRenderer();

        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'manual-app-health-alerts@example.test');

        $this->actingAsMfa($admin)
            ->post(route('admin.app-health.run'))
            ->assertRedirect(route('admin.app-health.index'));

        $this->assertDatabaseHas('operational_health_check_results', [
            'check_key' => 'deployment.identity',
            'status' => OperationalHealthCheckResult::STATUS_FAILED,
        ]);

        $this->assertSame($previousExecutionLimit, ini_get('max_execution_time'));

        Notification::assertNothingSent();
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

    public function test_dashboard_action_board_treats_skipped_app_checks_as_clean_coverage_gaps(): void
    {
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'dashboard-app-health-skipped@example.test');
        $run = $this->healthRun(OperationalHealthCheckRun::STATUS_PASSED, [
            'total_checks' => 2,
            'passed_checks' => 1,
            'failed_checks' => 0,
            'skipped_checks' => 1,
        ]);
        $this->healthResult($run, [
            'status' => OperationalHealthCheckResult::STATUS_SKIPPED,
            'actual_status' => null,
            'fingerprint' => hash('sha256', 'dashboard-skipped-operational-health-fixture'),
            'consecutive_failures' => 12,
            'failures_last_7_days' => 7,
            'failures_last_30_days' => 12,
        ]);

        $this->actingAsMfa($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('component', 'advisor/Dashboard')
            ->assertJsonPath('props.operationalHealth.summary.status', OperationalHealthCheckRun::STATUS_PASSED)
            ->assertJsonPath('props.operationalHealth.summary.warning', 0)
            ->assertJsonPath('props.operationalHealth.summary.failed', 0)
            ->assertJsonPath('props.operationalHealth.latest_issue', null);
    }

    public function test_repeated_operational_health_findings_notify_real_super_admins(): void
    {
        Notification::fake();

        $superAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'ops-alert-admin@example.test');
        $fixtureAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'operational-health-admin@futureshiftadvisory.test');
        $run = $this->healthRun(OperationalHealthCheckRun::STATUS_FAILED);
        $result = $this->healthResult($run, [
            'fingerprint' => hash('sha256', 'repeated-operational-health-failure'),
            'consecutive_failures' => 2,
            'failures_last_7_days' => 2,
            'failures_last_30_days' => 2,
        ]);

        $sent = app(OperationalHealthAlerter::class)->notify($run);

        $this->assertSame(1, $sent);
        Notification::assertSentTo(
            $superAdmin,
            OperationalHealthAttentionNotification::class,
            fn (OperationalHealthAttentionNotification $notification): bool => $notification->result->is($result),
        );
        Notification::assertNotSentTo($fixtureAdmin, OperationalHealthAttentionNotification::class);
    }

    public function test_skipped_monitor_fixtures_do_not_notify_and_clear_stale_operational_health_notifications(): void
    {
        $superAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'ops-alert-skipped-admin@example.test');
        $fingerprint = hash('sha256', 'resolved-operational-health-failure');
        $superAdmin->notifications()->create([
            'type' => 'operational_health.attention',
            'data' => [
                'fingerprint' => $fingerprint,
                'status' => OperationalHealthCheckResult::STATUS_FAILED,
                'message' => 'Previous app health failure.',
            ],
            'urgency' => 'urgent',
            'channel_decision' => json_encode([], JSON_THROW_ON_ERROR),
            'read_at' => null,
        ]);
        $run = $this->healthRun(OperationalHealthCheckRun::STATUS_PASSED, [
            'total_checks' => 1,
            'passed_checks' => 0,
            'failed_checks' => 0,
            'skipped_checks' => 1,
        ]);
        $this->healthResult($run, [
            'status' => OperationalHealthCheckResult::STATUS_SKIPPED,
            'actual_status' => null,
            'fingerprint' => hash('sha256', 'skipped-operational-health-fixture'),
            'consecutive_failures' => 12,
            'failures_last_7_days' => 7,
            'failures_last_30_days' => 12,
        ]);

        $sent = app(OperationalHealthAlerter::class)->notify($run);

        $this->assertSame(0, $sent);
        $this->assertSame(0, $superAdmin->refresh()->unreadNotifications()->where('type', 'operational_health.attention')->count());
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

    private function timeFromOperationalHealthEventName(string $description): string
    {
        preg_match('/-(\d{2})(\d{2})$/', $description, $matches);

        $this->assertNotEmpty($matches, "Operational health event name [{$description}] should end with HHMM.");

        return $matches[1].':'.$matches[2];
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
