<?php

declare(strict_types=1);

namespace Tests\Feature\Integration\Resilience;

use App\Console\Commands\AggregateIntegrationHealth;
use App\Models\IntegrationCall;
use App\Models\IntegrationHealthSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AggregateIntegrationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_green_amber_and_red_health_samples(): void
    {
        Config::set('integrations.health.green.min_success_rate', 0.99);
        Config::set('integrations.health.green.max_p95_latency_ms', 1000);
        Config::set('integrations.health.amber.min_success_rate', 0.95);
        Config::set('integrations.health.amber.max_p95_latency_ms', 3000);

        $windowEnd = Carbon::parse('2026-05-21T12:00:00+12:00');
        $occurredAt = $windowEnd->copy()->subMinute();

        for ($i = 0; $i < 100; $i++) {
            $this->recordCall('green-service', IntegrationCall::STATUS_SUCCESS, 100, $occurredAt);
        }

        for ($i = 0; $i < 19; $i++) {
            $this->recordCall('amber-service', IntegrationCall::STATUS_SUCCESS, 2000, $occurredAt);
        }
        $this->recordCall('amber-service', IntegrationCall::STATUS_FAILURE, 2500, $occurredAt);

        $this->recordCall('red-service', IntegrationCall::STATUS_SUCCESS, 100, $occurredAt);
        for ($i = 0; $i < 4; $i++) {
            $this->recordCall('red-service', IntegrationCall::STATUS_FAILURE, 4000, $occurredAt);
        }

        $this->artisan(AggregateIntegrationHealth::class, [
            '--minutes' => 5,
            '--window-end' => $windowEnd->toIso8601String(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('integration_health_samples', [
            'service' => 'green-service',
            'health' => IntegrationHealthSample::HEALTH_GREEN,
        ]);
        $this->assertDatabaseHas('integration_health_samples', [
            'service' => 'amber-service',
            'health' => IntegrationHealthSample::HEALTH_AMBER,
        ]);
        $this->assertDatabaseHas('integration_health_samples', [
            'service' => 'red-service',
            'health' => IntegrationHealthSample::HEALTH_RED,
        ]);

        $amber = IntegrationHealthSample::query()
            ->where('service', 'amber-service')
            ->firstOrFail();

        $this->assertSame(0.95, $amber->success_rate);
        $this->assertSame(2000, $amber->p95_latency_ms);
    }

    private function recordCall(
        string $service,
        string $status,
        int $latencyMs,
        Carbon $occurredAt,
    ): void {
        IntegrationCall::query()->create([
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
