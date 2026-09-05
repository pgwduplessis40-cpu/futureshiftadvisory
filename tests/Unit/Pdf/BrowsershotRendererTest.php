<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Pdf\BrowsershotRenderer;
use ReflectionMethod;
use Tests\TestCase;

final class BrowsershotRendererTest extends TestCase
{
    public function test_pdf_execution_time_limit_is_restored_after_rendering(): void
    {
        $renderer = new BrowsershotRenderer;
        $method = new ReflectionMethod($renderer, 'withExecutionTimeLimit');
        $previousLimit = ini_get('max_execution_time');

        self::assertIsString($previousLimit);

        try {
            $observedLimit = $method->invoke(
                $renderer,
                70,
                static fn (): string => (string) ini_get('max_execution_time'),
            );

            self::assertSame('70', $observedLimit);
            self::assertSame($previousLimit, ini_get('max_execution_time'));
        } finally {
            ini_set('max_execution_time', $previousLimit);
        }
    }

    public function test_puppeteer_lookup_uses_the_application_owned_browser_cache(): void
    {
        config()->set('services.browsershot.puppeteer_cache_dir', storage_path('app/test-browsershot-cache'));

        $renderer = new BrowsershotRenderer;
        $method = new ReflectionMethod($renderer, 'puppeteerCacheDirectory');

        self::assertSame(storage_path('app/test-browsershot-cache'), $method->invoke($renderer));
    }

    public function test_deployment_provisions_a_browser_for_the_pdf_renderer(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        self::assertIsString($script);
        self::assertStringContainsString('PUPPETEER_CACHE_DIR', $script);
        self::assertStringContainsString('puppeteer browsers install chrome', $script);
        self::assertStringContainsString('puppeteer.launch', $script);
    }
}
