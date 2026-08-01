<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\VirusScanner\ClamAvScanner;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\NoopScanner;
use App\Services\Integration\VirusScanner\UnavailableScanner;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class VirusScannerBindingTest extends TestCase
{
    public function test_scanner_binding_fails_closed_when_live_scanning_and_noop_are_disabled(): void
    {
        Config::set('virus-scanner.live', false);
        Config::set('virus-scanner.allow_noop', false);
        $this->app->forgetInstance(FileScanner::class);

        $scanner = app(FileScanner::class);

        $this->assertInstanceOf(UnavailableScanner::class, $scanner);

        $stream = fopen('php://temp', 'r+b');
        $this->assertIsResource($stream);

        $result = $scanner->scan($stream);
        fclose($stream);

        $this->assertTrue($result->isError());
        $this->assertSame('configuration_guard', $result->payload['engine']);
    }

    public function test_production_environment_cannot_use_noop_scanner_even_when_enabled(): void
    {
        Config::set('app.env', 'production');
        Config::set('virus-scanner.live', false);
        Config::set('virus-scanner.allow_noop', true);
        $this->app->forgetInstance(FileScanner::class);

        $this->assertInstanceOf(UnavailableScanner::class, app(FileScanner::class));
    }

    public function test_local_environment_can_opt_into_noop_scanner(): void
    {
        Config::set('app.env', 'local');
        Config::set('virus-scanner.live', false);
        Config::set('virus-scanner.allow_noop', true);
        $this->app->forgetInstance(FileScanner::class);

        $this->assertInstanceOf(NoopScanner::class, app(FileScanner::class));
    }

    public function test_virus_scanner_registry_readiness_matches_fail_closed_binding(): void
    {
        $resolver = app(IntegrationActivationResolver::class);

        Config::set('app.env', 'production');
        Config::set('virus-scanner.live', false);
        Config::set('virus-scanner.allow_noop', true);

        $this->assertFalse($resolver->readiness('virus_scanner'));
        $this->assertFalse($resolver->isLive('virus_scanner'));

        Config::set('virus-scanner.live', true);

        $this->assertTrue($resolver->readiness('virus_scanner'));
        $this->assertTrue($resolver->isLive('virus_scanner'));

        Config::set('app.env', 'local');
        Config::set('virus-scanner.live', false);
        Config::set('virus-scanner.allow_noop', true);

        $this->assertTrue($resolver->readiness('virus_scanner'));
        $this->assertFalse($resolver->isLive('virus_scanner'));
    }

    public function test_clamav_scanner_falls_back_from_unix_socket_to_tcp_and_fails_closed(): void
    {
        Config::set('virus-scanner.clamav.socket', storage_path('missing-clamd.sock'));
        Config::set('virus-scanner.clamav.host', '127.0.0.1');
        Config::set('virus-scanner.clamav.port', 1);
        Config::set('virus-scanner.clamav.timeout_seconds', 0.05);

        $stream = fopen('php://temp', 'r+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'safe test content');
        rewind($stream);

        $result = app(ClamAvScanner::class)->scan($stream);
        fclose($stream);

        $this->assertTrue($result->isError());
        $this->assertSame('ClamAV daemon unavailable.', $result->message);
        $this->assertSame(
            ['unix', 'tcp'],
            array_column($result->payload['connection_errors'], 'endpoint'),
        );
    }
}
