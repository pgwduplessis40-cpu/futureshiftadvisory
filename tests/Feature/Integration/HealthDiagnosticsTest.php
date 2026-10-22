<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Console\Commands\AggregateIntegrationHealth;
use App\Enums\Permission;
use App\Models\AiUsageEvent;
use App\Models\IntegrationCall;
use App\Models\IntegrationHealthSample;
use App\Models\User;
use App\Services\Ai\AdvisorAiNotice;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HealthDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(now()->setMicrosecond(0));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_ai_and_legacy_billing_events_have_separate_rollups_with_snapshot_evidence(): void
    {
        $this->recordCall('anthropic', '/v1/messages', 'success', 25805);
        $this->recordCall('anthropic', '/v1/organizations/cost_report', 'failure', 150);
        $this->artisan(AggregateIntegrationHealth::class)->assertSuccessful();

        $ai = IntegrationHealthSample::where('service', 'anthropic')->sole();
        $billing = IntegrationHealthSample::where('service', 'anthropic_admin')->sole();
        $this->assertSame('green', $ai->health);
        $this->assertSame(1, $ai->diagnostics['event_count']);
        $this->assertSame('red', $billing->health);
        $this->assertSame('authentication', $billing->diagnostics['latest_issue']['code']);
        $this->assertSame('req_health_test', $billing->diagnostics['latest_issue']['request_id']);
        $this->assertSame(2, IntegrationCall::where('service', 'anthropic')->count());

        config(['integrations.health.anthropic.green_p95_ms' => 100]);
        $this->actingAsMfa($this->admin())->get(route('admin.integration-health.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('summary.green', 1)->where('summary.red', 1)
                ->where('credentialsUrl', '/admin/integration-credentials')
                ->where('services', function ($services): bool {
                    $ai = collect($services)->firstWhere('service', 'anthropic');

                    return data_get($ai, 'diagnostics.green_latency_ms') === 30000
                        && data_get($ai, 'diagnostics.event_count') === 1
                        && data_get($ai, 'label') === 'Anthropic AI requests';
                }));
    }

    public function test_stale_and_legacy_results_are_not_counted_as_current_green_or_red(): void
    {
        $this->recordCall('anthropic', '/v1/messages', 'success', 25805);
        $this->artisan(AggregateIntegrationHealth::class)->assertSuccessful();
        $this->travel(6)->minutes();
        IntegrationHealthSample::create([
            'service' => 'legacy', 'health' => 'red', 'success_rate' => 0.0,
            'p95_latency_ms' => 100, 'window_start' => now()->subMinutes(5), 'window_end' => now(),
        ]);
        $this->actingAsMfa($this->admin())->get(route('admin.integration-health.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('summary.total', 2)->where('summary.green', 0)->where('summary.red', 0)
                ->where('summary.stale', 1)->where('summary.unknown', 1)
                ->where('services.0.display_health', 'unknown')
                ->where('services.1.display_health', 'stale'));
    }

    public function test_billing_refresh_preserves_ai_failure_and_successful_ai_response_resolves_notice(): void
    {
        config(['services.anthropic.admin_key' => 'sk-ant-admin-test']);
        Http::fake(['api.anthropic.com/v1/organizations/cost_report*' => Http::response(['data' => []])]);
        Cache::put(AdvisorAiNotice::CACHE_KEY, ['reason' => 'Anthropic API request failed with status 401.', 'recorded_at' => now()->subMinute()->toIso8601String()], now()->addDay());
        $this->recordCall('anthropic', '/v1/messages', 'failure', 100);
        $this->actingAsMfa($this->admin())->post(route('admin.integration-health.refresh'))->assertRedirect();

        $this->assertNotNull(Cache::get(AdvisorAiNotice::CACHE_KEY));
        $this->assertSame('red', IntegrationHealthSample::where('service', 'anthropic')->sole()->health);
        $this->assertSame('green', IntegrationHealthSample::where('service', 'anthropic_admin')->sole()->health);

        AiUsageEvent::create(['provider' => 'anthropic', 'task' => 'analyse', 'model' => 'test', 'input_tokens' => 1, 'output_tokens' => 1, 'estimated_cost_usd' => 0, 'occurred_at' => now()]);
        $this->assertNull(app(AdvisorAiNotice::class)->actionable());
    }

    public function test_missing_billing_credentials_produce_a_reason_and_action_without_provider_calls(): void
    {
        config(['services.anthropic.admin_key' => null]);
        $this->actingAsMfa($this->admin())->post(route('admin.integration-health.refresh'))->assertRedirect();
        $sample = IntegrationHealthSample::where('service', 'anthropic_admin')->sole();
        $this->assertSame('credentials_missing', $sample->diagnostics['latest_issue']['code']);
        $this->assertNull($sample->p95_latency_ms);
        $this->assertSame('unknown', $sample->diagnostics['response_time']);
        $this->assertStringContainsString('configure the Anthropic Admin API key', $sample->diagnostics['latest_issue']['action']);
        Http::assertNothingSent();
    }

    public function test_health_view_permission_does_not_grant_credentials_management_access(): void
    {
        $advisor = User::factory()->withTwoFactor()->create(['user_type' => User::TYPE_ADVISOR, 'primary_role' => User::TYPE_ADVISOR]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $this->actingAsMfa($advisor)->get(route('admin.integration-health.index'))->assertForbidden();
        $advisor->givePermissionTo(Permission::INTEGRATION_HEALTH_VIEW->value);
        $this->actingAsMfa($advisor)->get(route('admin.integration-health.index'))
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('credentialsUrl', null));
    }

    private function admin(): User
    {
        $user = User::factory()->withTwoFactor()->create(['user_type' => User::TYPE_SUPER_ADMIN, 'primary_role' => User::TYPE_SUPER_ADMIN]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function recordCall(string $service, string $path, string $status, int $latency): void
    {
        IntegrationCall::create([
            'correlation_id' => (string) Str::uuid(),
            'service' => $service, 'endpoint' => 'https://api.anthropic.com'.$path,
            'status' => $status, 'latency_ms' => $latency, 'attempt' => 1, 'occurred_at' => now(),
            'error_payload' => $status === 'failure' ? ['http_status' => 401, 'request_id' => 'req_health_test', 'body' => 'sk-ant-secret private@example.test'] : null,
        ]);
    }
}
