<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Models\IntegrationHealthSample;
use App\Services\Integration\IntegrationHealthBander;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class IntegrationHealthBanderTest extends TestCase
{
    public function test_bands_health_from_configured_success_rate_and_latency_thresholds(): void
    {
        Config::set('integrations.health.green.min_success_rate', 0.99);
        Config::set('integrations.health.green.max_p95_latency_ms', 1000);
        Config::set('integrations.health.amber.min_success_rate', 0.95);
        Config::set('integrations.health.amber.max_p95_latency_ms', 3000);

        $bander = new IntegrationHealthBander;

        $this->assertSame(IntegrationHealthSample::HEALTH_GREEN, $bander->band(1.0, 1000));
        $this->assertSame(IntegrationHealthSample::HEALTH_GREEN, $bander->band(1.0, null));
        $this->assertSame(IntegrationHealthSample::HEALTH_AMBER, $bander->band(0.95, 3000));
        $this->assertSame(IntegrationHealthSample::HEALTH_RED, $bander->band(0.94, 100));
        $this->assertSame(IntegrationHealthSample::HEALTH_RED, $bander->band(0.99, 3001));
    }

    public function test_ai_completion_time_is_separate_from_availability_and_billing_latency(): void
    {
        config(['integrations.health.anthropic.green_p95_ms' => 30000, 'integrations.health.anthropic.amber_p95_ms' => 60000, 'integrations.health.anthropic.latency_red_min_samples' => 5]);
        $bander = new IntegrationHealthBander;
        $checks = $bander->checks(1.0, 25805, 'anthropic', 1);

        $this->assertSame('green', $checks['health']);
        $this->assertSame('green', $checks['availability']);
        $this->assertSame(30000, $checks['green_latency_ms']);
        $this->assertSame('red', $bander->band(1.0, 25805, 'anthropic_admin', 1));
        $this->assertSame('amber', $bander->band(1.0, 60001, 'anthropic', 1));
        $this->assertSame('red', $bander->band(1.0, 60001, 'anthropic', 5));
        $this->assertSame('red', $bander->band(0.0, 100, 'anthropic', 1));
        $this->assertSame('unknown', $bander->checks(1.0, null, 'anthropic', 1)['response_time']);

        config(['integrations.health.anthropic.green_p95_ms' => 20000]);
        $this->assertSame('amber', $bander->band(1.0, 25805, 'anthropic', 1));
    }
}
