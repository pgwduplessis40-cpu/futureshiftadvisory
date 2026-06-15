<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Console\Commands\AlertStuckRedIntegrations;
use App\Models\AiUsageEvent;
use App\Models\IntegrationCall;
use App\Models\IntegrationHealthSample;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_advisor_can_view_latest_integration_health_samples(): void
    {
        $this->travelTo(now()->setMicrosecond(0));
        config([
            'ai.costs.monthly_budget_usd' => 1.0,
            'ai.costs.usd_to_nzd_rate' => 1.7,
        ]);
        $advisor = $this->userWithRole(User::TYPE_ADVISOR, 'advisor@example.test');
        $this->sample('nzbn', IntegrationHealthSample::HEALTH_GREEN, now()->subMinutes(15), 1.0, 140);
        $this->sample('nzbn', IntegrationHealthSample::HEALTH_RED, now()->subMinutes(2), 0.40, 4200);
        $this->sample('ird', IntegrationHealthSample::HEALTH_AMBER, now()->subMinutes(3), 0.96, 2100);
        $this->aiUsage('claude-sonnet-4-6', 1_000, 200, 0.006, now()->subHour());
        $this->aiUsage('claude-sonnet-4-6', 2_000, 400, 0.012, now()->subDays(2));
        $this->aiUsage('claude-sonnet-4-6', 5_000, 500, 0.0225, now()->subMonth());

        $this->actingAsMfa($advisor)
            ->get(route('admin.integration-health.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/integration-health/Index')
                ->where('summary.total', 2)
                ->where('summary.red', 1)
                ->where('summary.amber', 1)
                ->where('summary.stale', 0)
                ->where('services.0.service', 'nzbn')
                ->where('services.0.health', IntegrationHealthSample::HEALTH_RED)
                ->where('services.0.lag_seconds', 120)
                ->where('services.0.fresh', true)
                ->where('services.1.service', 'ird')
                ->where('aiUsage.today.requests', 1)
                ->where('aiUsage.today.total_tokens', 1_200)
                ->where('aiUsage.month.requests', 2)
                ->where('aiUsage.month.total_tokens', 3_600)
                ->where('aiUsage.month.estimated_cost_usd', 0.018)
                ->where('aiUsage.currency.month_estimated_cost_nzd', 0.0306)
                ->where('aiUsage.budget.status', 'within_budget')
                ->where('aiUsage.official.status', 'admin_api_key_missing')
                ->where('aiUsage.official.credit_balance_supported', false)
                ->where('aiUsage.breakdown.0.model', 'claude-sonnet-4-6')
                ->where('aiUsage.breakdown.0.requests', 2));
    }

    public function test_admin_api_key_syncs_official_anthropic_month_cost(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0)->setMicrosecond(0));
        config(['services.anthropic.admin_key' => 'sk-ant-admin01-test']);
        Http::fake([
            'https://api.anthropic.com/v1/organizations/cost_report*' => Http::response([
                'data' => [
                    [
                        'starting_at' => '2026-06-01T00:00:00Z',
                        'ending_at' => '2026-06-02T00:00:00Z',
                        'results' => [
                            ['amount' => '123.45', 'currency' => 'USD'],
                        ],
                    ],
                    [
                        'starting_at' => '2026-06-02T00:00:00Z',
                        'ending_at' => '2026-06-03T00:00:00Z',
                        'results' => [
                            ['amount' => '6.55', 'currency' => 'USD'],
                        ],
                    ],
                ],
                'has_more' => false,
                'next_page' => null,
            ]),
        ]);

        $advisor = $this->userWithRole(User::TYPE_ADVISOR, 'anthropic-cost-advisor@example.test');

        $this->actingAsMfa($advisor)
            ->get(route('admin.integration-health.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/integration-health/Index')
                ->where('aiUsage.official.configured', true)
                ->where('aiUsage.official.status', 'synced')
                ->where('aiUsage.official.month_cost_usd', 1.3)
                ->where('aiUsage.official.credit_balance_supported', false)
                ->where('aiUsage.official.credit_balance_usd', null));

        Http::assertSent(fn ($request): bool => $request->hasHeader('anthropic-version', '2023-06-01')
            && $request->hasHeader('x-api-key', 'sk-ant-admin01-test')
            && str_starts_with((string) $request->url(), 'https://api.anthropic.com/v1/organizations/cost_report'));
    }

    public function test_client_users_cannot_view_integration_health_dashboard(): void
    {
        $client = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'client@example.test');

        $this->actingAsMfa($client)
            ->get(route('admin.integration-health.index'))
            ->assertForbidden();
    }

    public function test_refresh_aggregates_recent_integration_call_logs(): void
    {
        $this->travelTo(now()->setMicrosecond(0));
        $advisor = $this->userWithRole(User::TYPE_ADVISOR, 'refresh-advisor@example.test');

        $this->recordCall('nzbn', IntegrationCall::STATUS_SUCCESS, 180, now()->subMinute());
        $this->recordCall('nzbn', IntegrationCall::STATUS_SUCCESS, 220, now()->subMinute());

        $this->actingAsMfa($advisor)
            ->post(route('admin.integration-health.refresh'))
            ->assertRedirect(route('admin.integration-health.index'))
            ->assertSessionHas('status', 'integration-health-refreshed');

        $this->assertDatabaseHas('integration_health_samples', [
            'service' => 'nzbn',
            'success_rate' => 1.0,
            'p95_latency_ms' => 220,
            'health' => IntegrationHealthSample::HEALTH_GREEN,
        ]);
    }

    public function test_stuck_red_alert_fires_once_per_red_window(): void
    {
        $superAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN, 'admin@example.test');

        $this->sample('nzbn', IntegrationHealthSample::HEALTH_GREEN, now()->subMinutes(45), 1.0, 120);

        for ($offset = 35; $offset >= 5; $offset -= 5) {
            $this->sample(
                service: 'nzbn',
                health: IntegrationHealthSample::HEALTH_RED,
                windowEnd: now()->subMinutes($offset),
                successRate: 0.20,
                p95LatencyMs: 5000,
            );
        }

        $this->artisan(AlertStuckRedIntegrations::class)
            ->assertSuccessful();

        $this->assertDatabaseCount('integration_health_alerts', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->getKey(),
            'type' => 'integration.health.stuck_red',
            'urgency' => 'urgent',
        ]);

        $this->artisan(AlertStuckRedIntegrations::class)
            ->assertSuccessful();

        $this->assertDatabaseCount('integration_health_alerts', 1);
        $this->assertSame(1, $superAdmin->notifications()->where('type', 'integration.health.stuck_red')->count());
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

    private function sample(
        string $service,
        string $health,
        CarbonInterface $windowEnd,
        float $successRate,
        int $p95LatencyMs,
    ): IntegrationHealthSample {
        return IntegrationHealthSample::query()->create([
            'service' => $service,
            'window_start' => $windowEnd->copy()->subMinutes(5),
            'window_end' => $windowEnd,
            'success_rate' => $successRate,
            'p95_latency_ms' => $p95LatencyMs,
            'health' => $health,
        ]);
    }

    private function aiUsage(
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $estimatedCostUsd,
        CarbonInterface $occurredAt,
    ): AiUsageEvent {
        return AiUsageEvent::query()->create([
            'provider' => 'anthropic',
            'task' => 'analyse',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $estimatedCostUsd,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function recordCall(
        string $service,
        string $status,
        int $latencyMs,
        CarbonInterface $occurredAt,
    ): IntegrationCall {
        return IntegrationCall::query()->create([
            'service' => $service,
            'endpoint' => 'https://api.example.test/'.$service,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'attempt' => 1,
            'error_payload' => $status === IntegrationCall::STATUS_FAILURE
                ? ['reason' => 'fixture']
                : null,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => $occurredAt,
        ]);
    }
}
