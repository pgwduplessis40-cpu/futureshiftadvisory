<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IntegrationStoreMigrationFallbackTest extends TestCase
{
    public function test_live_check_uses_config_fallback_when_integration_tables_are_missing(): void
    {
        config([
            'integrations.stats_nz.live' => true,
            'integrations.stats_nz.api_key' => 'stats-env-key',
        ]);

        Schema::shouldReceive('hasTable')
            ->with('integration_activations')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('integration_credentials')
            ->andReturn(false);

        $this->assertTrue(app(IntegrationActivationResolver::class)->isLive('stats_nz'));
    }

    public function test_credential_registry_rows_render_when_credential_table_is_missing(): void
    {
        config(['integrations.stats_nz.api_key' => 'stats-env-key']);

        Schema::shouldReceive('hasTable')
            ->with('integration_credentials')
            ->andReturn(false);

        $statsNz = app(IntegrationCredentials::class)
            ->registryRows()
            ->firstWhere('integration_key', 'stats_nz');

        $this->assertIsArray($statsNz);
        $this->assertSame('stats_nz', $statsNz['integration_key']);
        $this->assertTrue($statsNz['credentials'][0]['has_env_fallback']);

        $cin7 = app(IntegrationCredentials::class)
            ->registryRows()
            ->firstWhere('integration_key', 'cin7');

        $this->assertIsArray($cin7);
        $this->assertStringContainsString('inventory', strtolower((string) $cin7['purpose']));
        $this->assertStringContainsString('cash-flow', strtolower((string) $cin7['api_outcome']));
    }
}
