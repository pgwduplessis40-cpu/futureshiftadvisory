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
}
