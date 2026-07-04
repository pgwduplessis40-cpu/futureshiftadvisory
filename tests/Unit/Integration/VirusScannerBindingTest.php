<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

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
}
