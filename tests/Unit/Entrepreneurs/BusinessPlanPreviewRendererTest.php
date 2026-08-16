<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use ReflectionMethod;
use Tests\TestCase;

final class BusinessPlanPreviewRendererTest extends TestCase
{
    public function test_key_points_ignore_punctuation_and_incomplete_fragments(): void
    {
        $points = $this->keyPoints(
            ",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n".
            "based in Hamilton, Waikato.\n".
            "Drawer Full of Giants Ltd will operate from Hamilton while maintaining a high quality client experience.\n".
            "- Validate demand with five customer discovery interviews before lender issue.\n".
            '- ,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,'
        );

        $this->assertSame([
            'Drawer Full of Giants Ltd will operate from Hamilton while maintaining a high quality client experience',
            'Validate demand with five customer discovery interviews before lender issue',
        ], $points);
    }

    public function test_completed_executive_summary_is_held_for_the_dedicated_reader_summary_page(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'documentSections');
        $method->setAccessible(true);
        $sections = $method->invoke($renderer, [
            [
                'title' => 'Foundation',
                'requirements' => [[
                    'key' => 'mission-vision',
                    'title' => 'Mission and vision',
                ]],
                'sections' => [[
                    'requirement_key' => 'mission-vision',
                    'body' => 'A focused mission and vision statement for the business.',
                    'attached_document_ids' => [],
                ]],
            ],
            [
                'title' => 'Financial',
                'requirements' => [[
                    'key' => 'executive-summary',
                    'title' => 'Executive summary',
                ]],
                'sections' => [[
                    'requirement_key' => 'executive-summary',
                    'body' => 'A concise lender-ready overview of the business, funding need, and decision.',
                    'attached_document_ids' => ['document-1'],
                ]],
            ],
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('Foundation', $sections[0]['title']);
        $this->assertSame('mission-vision', $sections[0]['entries'][0]['key']);

        $summaryMethod = new ReflectionMethod($renderer, 'executiveSummaryEntry');
        $summaryMethod->setAccessible(true);
        $summary = $summaryMethod->invoke($renderer, [[
            'sections' => [[
                'requirement_key' => 'executive-summary',
                'body' => 'A concise lender-ready overview of the business, funding need, and decision.',
                'attached_document_ids' => ['document-1'],
            ]],
        ]]);

        $this->assertSame('Executive summary', $summary['title']);
    }

    public function test_document_sections_clean_punctuation_fragments_from_detail_copy(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'documentSections');
        $method->setAccessible(true);

        $sections = $method->invoke($renderer, [[
            'title' => 'Foundation',
            'requirements' => [[
                'key' => 'business-type-location',
                'title' => 'Business type, location, and operating model',
            ]],
            'sections' => [[
                'requirement_key' => 'business-type-location',
                'body' => ", , Drawer Full of Giants Ltd is based in Hamilton, Waikato.\n- ,,,,,,,,,,,,,\nThe company works with purpose-led founders.",
                'attached_document_ids' => [],
            ]],
        ]]);

        $this->assertSame('Drawer Full of Giants Ltd is based in Hamilton, Waikato.', strtok($sections[0]['entries'][0]['body'], "\n"));
        $this->assertStringNotContainsString(',,,,', $sections[0]['entries'][0]['body']);
    }

    public function test_fallback_pdf_uses_client_facing_report_label_and_executive_summary(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'fallbackPdf');
        $method->setAccessible(true);

        $pdf = $method->invoke(
            $renderer,
            new EntrepreneurProfile(['name' => 'Plan Founder']),
            null,
            [[
                'title' => 'Foundation',
                'requirements' => [[
                    'key' => 'business-type-location',
                    'title' => 'Business type, location, and operating model',
                    'complete' => true,
                ]],
                'sections' => [[
                    'requirement_key' => 'business-type-location',
                    'body' => 'Plan Founder operates a specialist advisory business from Hamilton with validated demand from paid pilots.',
                    'attached_document_ids' => [],
                ]],
            ]],
            [
                'external_issue_ready' => false,
                'label' => 'Not ready for external issue',
                'reasons' => ['Evidence coverage is incomplete.'],
                'evidence_supported_responses' => 0,
                'completed_responses' => 1,
            ],
        );

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('BUSINESS PLAN', $pdf);
        $this->assertStringContainsString('Business Plan', $pdf);
        $this->assertStringContainsString('Founder - Plan Founder', $pdf);
        $this->assertStringContainsString('Plan overview', $pdf);
        $this->assertStringNotContainsString('FALLBACK PDF', $pdf);
        $this->assertStringNotContainsString('Fallback rendering', $pdf);
        $this->assertStringNotContainsString('External issue', $pdf);
        $this->assertStringNotContainsString('Evidence coverage', $pdf);
    }

    /**
     * @return array<int, string>
     */
    private function keyPoints(string $body): array
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'keyPoints');
        $method->setAccessible(true);

        return $method->invoke($renderer, $body);
    }
}
