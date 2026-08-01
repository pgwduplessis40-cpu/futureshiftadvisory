<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\ResilientPdfPreviewRenderer;
use App\Services\Pdf\SimpleTextPdf;
use RuntimeException;
use Tests\TestCase;

final class ResilientPdfPreviewRendererTest extends TestCase
{
    public function test_it_returns_a_readable_pdf_when_the_browser_renderer_fails(): void
    {
        $browser = new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new RuntimeException('Chrome is unavailable.');
            }
        };

        $pdf = (new ResilientPdfPreviewRenderer($browser, new SimpleTextPdf))->render(
            '<html><style>body { color: red; }</style><body><h1>Plan summary</h1><p>Current client content.</p></body></html>',
            'Client plan preview',
        );

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('Client plan preview', $pdf);
        self::assertStringContainsString('Plan summary', $pdf);
        self::assertStringContainsString('Current client content.', $pdf);
        self::assertStringNotContainsString('color: red', $pdf);
    }
}
