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

        $this->assertStringContainsString(SimpleTextPdf::FALLBACK_MARKER, $pdf);
        $this->assertSame(2, substr_count($pdf, '/Type /Page /Parent'));
    }

    public function test_table_row_rules_sit_above_cell_text_instead_of_through_it(): void
    {
        $pdf = app(SimpleTextPdf::class)->renderStructured('Budget Pack', [[
            'type' => 'table',
            'headers' => ['Cost item', 'Monthly amount'],
            'rows' => [
                ['Owner compensation - current $550 wk', '$45,500'],
            ],
        ]]);

        $this->assertStringContainsString('50 622 m 545 622 l S', $pdf);
        $this->assertStringNotContainsString('50 616 m 545 616 l S', $pdf);
    }

    public function test_structured_entries_render_detail_lists_as_bullets(): void
    {
        $pdf = app(SimpleTextPdf::class)->renderStructured('Business Plan', [[
            'type' => 'entry',
            'title' => 'Risk register and mitigations',
            'body' => 'The founder reviews the register monthly.',
            'body_bullets' => [
                'Key-person risk: maintain an advisor cover plan.',
                'Competitor risk: review named alternatives quarterly.',
            ],
        ]]);

        $this->assertStringContainsString('Detail points', $pdf);
        $this->assertStringContainsString('Key-person risk: maintain an advisor cover plan.', $pdf);
        $this->assertStringContainsString('Competitor risk: review named alternatives quarterly.', $pdf);
    }
}
