<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Pdf\SimpleTextPdf;
use Tests\TestCase;

final class SimpleTextPdfTest extends TestCase
{
    public function test_it_does_not_add_a_blank_trailing_page_after_a_page_break(): void
    {
        $pdf = app(SimpleTextPdf::class)->render(
            'Business plan preview',
            array_fill(0, 33, 'Requirement content.'),
        );

        $this->assertSame(2, substr_count($pdf, '/Type /Page /Parent'));
    }
}
