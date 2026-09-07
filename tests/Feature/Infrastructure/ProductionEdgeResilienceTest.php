<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

final class ProductionEdgeResilienceTest extends TestCase
{
    public function test_deployment_installs_the_nginx_recovery_path_and_minute_watchdog(): void
    {
        $deploy = file_get_contents(base_path('deploy.sh'));

        $this->assertIsString($deploy);
        $this->assertStringContainsString('install_nginx_resilience_configuration', $deploy);
        $this->assertStringContainsString('location = /sw.js', $deploy);
        $this->assertStringContainsString('error_page 502 503 504 /_fsa-reconnecting.html;', $deploy);
        $this->assertStringContainsString('Cache-Control "no-store, no-cache, must-revalidate, max-age=0" always;', $deploy);
        $this->assertStringContainsString('configure_edge_watchdog', $deploy);
        $this->assertStringContainsString('OnCalendar=*-*-* *:*:00', $deploy);
        $this->assertStringContainsString('EDGE_WATCHDOG_FAILURE_THRESHOLD', $deploy);
        $this->assertStringContainsString('verify_live_service_worker_contract', $deploy);
        $this->assertStringContainsString('-v resilience_snippet="$NGINX_RESILIENCE_SNIPPET"', $deploy);
        $this->assertStringNotContainsString('-v include="$NGINX_RESILIENCE_SNIPPET"', $deploy);
        $this->assertStringNotContainsString('Clear-Site-Data', $deploy);
    }

    public function test_watchdog_captures_evidence_alerts_and_restarts_only_at_the_threshold(): void
    {
        $watchdog = file_get_contents(base_path('scripts/watch-production-edge.sh'));

        $this->assertIsString($watchdog);
        $this->assertStringContainsString('journalctl -u "$nginx_service" -u "$php_fpm_service"', $watchdog);
        $this->assertStringContainsString('nginx-error.log', $watchdog);
        $this->assertStringContainsString('failure_count" -eq "$failure_threshold"', $watchdog);
        $this->assertStringContainsString('systemctl restart "$php_fpm_service"', $watchdog);
        $this->assertStringContainsString('failure_count" -eq 1', $watchdog);
    }

    public function test_release_probe_requires_the_live_service_worker_contract(): void
    {
        $probe = file_get_contents(base_path('scripts/probe-production-availability.sh'));

        $this->assertIsString($probe);
        $this->assertStringContainsString('Checking the public service-worker contract.', $probe);
        $this->assertStringContainsString('"$production_url/sw.js"', $probe);
        $this->assertStringContainsString('application/javascript', $probe);
        $this->assertStringContainsString('cache-control:.*no-store', $probe);
    }

    public function test_reconnecting_page_retries_without_storing_a_client_error(): void
    {
        $page = file_get_contents(public_path('_fsa-reconnecting.html'));

        $this->assertIsString($page);
        $this->assertStringContainsString('Reconnecting…', $page);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $page);
        $this->assertStringContainsString('window.setTimeout(retry, 5000)', $page);
    }
}
