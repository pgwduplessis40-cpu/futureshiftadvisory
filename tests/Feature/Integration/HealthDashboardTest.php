<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Console\Commands\AlertStuckRedIntegrations;
use App\Models\IntegrationHealthSample;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_advisor_can_view_latest_integration_health_samples(): void
    {
        $advisor = $this->userWithRole(User::TYPE_ADVISOR, 'advisor@example.test');
        $this->sample('nzbn', IntegrationHealthSample::HEALTH_GREEN, now()->subMinutes(15), 1.0, 140);
        $this->sample('nzbn', IntegrationHealthSample::HEALTH_RED, now()->subMinutes(2), 0.40, 4200);
        $this->sample('ird', IntegrationHealthSample::HEALTH_AMBER, now()->subMinutes(3), 0.96, 2100);

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
                ->where('services.1.service', 'ird'));
    }

    public function test_client_users_cannot_view_integration_health_dashboard(): void
    {
        $client = $this->userWithRole(User::TYPE_CLIENT_PRIMARY, 'client@example.test');

        $this->actingAsMfa($client)
            ->get(route('admin.integration-health.index'))
            ->assertForbidden();
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
}
