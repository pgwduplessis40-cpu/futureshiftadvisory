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

    public function test_production_configuration_gate_skips_non_production_environments(): void
    {
        config()->set('app.env', 'testing');

        $this->artisan('fsa:assert-production-security-config')
            ->expectsOutput('Skipping production security configuration checks outside production.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_production_configuration_gate_rejects_each_unsafe_setting(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set([
            'app.debug' => true,
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => null,
            'security.mfa_required' => false,
            'virus-scanner.live' => true,
            'virus-scanner.fail_open_on_error' => true,
        ]);

        $this->artisan('fsa:assert-production-security-config')
            ->expectsOutput('APP_DEBUG must be false in production.')
            ->expectsOutput('SESSION_SECURE_COOKIE must be true in production.')
            ->expectsOutput('SESSION_HTTP_ONLY must be true in production.')
            ->expectsOutput('SESSION_SAME_SITE must be lax or strict in production.')
            ->expectsOutput('MFA must be required in production.')
            ->expectsOutput('Virus scanning must be live and fail closed in production.')
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
