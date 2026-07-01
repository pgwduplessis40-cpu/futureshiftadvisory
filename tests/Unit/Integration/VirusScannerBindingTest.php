<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Services\Integration\VirusScanner\Contracts\FileScanner;
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
}
