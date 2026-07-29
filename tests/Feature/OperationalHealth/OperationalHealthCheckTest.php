<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalHealth;

use App\Console\Commands\RunOperationalHealthChecks;
use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Models\User;
use App\Services\Security\StepUpEvaluator;
use App\Support\ReleaseVersion;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class OperationalHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_health_checks_are_scheduled_in_new_zealand_business_hours(): void
    {
        config()->set('operational_health.enabled', true);
        config()->set('operational_health.timezone', 'Pacific/Auckland');
        config()->set('operational_health.weekday_cron', '30 7-17 * * 1-5');
        config()->set('operational_health.weekend_cron', '30 7 * * 0,6');

        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events());
        $weekday = $events->firstWhere('description', 'fsa-operational-health-check-weekday');
        $weekend = $events->firstWhere('description', 'fsa-operational-health-check-weekend');

        $this->assertNotNull($weekday);
        $this->assertNotNull($weekend);
        $this->assertSame('30 7-17 * * 1-5', $weekday->expression);
        $this->assertSame('30 7 * * 0,6', $weekend->expression);
        $this->assertSame('Pacific/Auckland', $weekday->timezone);
        $this->assertSame('Pacific/Auckland', $weekend->timezone);
    }

    public function test_command_records_specific_findings_and_skips_missing_monitor_fixtures(): void
    {
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

    public function test_client_users_cannot_view_operational_health_page(): void
    {
        $client = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'app-health-client@example.test');

        $this->actingAsMfa($client)
            ->get(route('admin.app-health.index'))
            ->assertForbidden();
    }

    public function test_run_now_preserves_the_administrator_session(): void
    {
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

    private function healthRun(string $status): OperationalHealthCheckRun
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
