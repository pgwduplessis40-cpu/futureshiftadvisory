<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Console\Command;
use Tests\TestCase;

final class ProductionSecurityConfigurationTest extends TestCase
{
    public function test_production_configuration_gate_accepts_a_safe_configuration(): void
    {
        $this->setSafeProductionConfiguration();

        $this->artisan('fsa:assert-production-security-config')
            ->expectsOutput('Production security configuration is safe.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_production_configuration_gate_rejects_debug_mode(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('app.debug', true);

        $this->artisan('fsa:assert-production-security-config')
            ->expectsOutput('APP_DEBUG must be false in production.')
            ->assertExitCode(Command::FAILURE);
    }

    private function setSafeProductionConfiguration(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'security.mfa_required' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'virus-scanner.live' => true,
            'virus-scanner.fail_open_on_error' => false,
        ]);
    }
}
