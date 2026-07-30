<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Pdf\BrowsershotRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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
}
