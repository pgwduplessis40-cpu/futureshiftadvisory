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

    public function test_executive_summary_is_recovered_from_a_legacy_snapshot_key(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'executiveSummaryEntry');
        $method->setAccessible(true);

        $summary = $method->invoke($renderer, [[
            'sections' => [[
                'key' => 'founder-financial-executive-summary',
                'title' => 'Executive summary',
                'body' => 'A concise summary retained from the submitted plan snapshot.',
                'attached_document_ids' => [],
            ]],
        ]]);

        $this->assertSame('Executive summary', $summary['title']);
        $this->assertSame('A concise summary retained from the submitted plan snapshot.', $summary['body']);
    }

    public function test_generated_executive_summary_uses_the_twelve_assessment_areas(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'executiveSummary');
        $method->setAccessible(true);

        $summary = $method->invoke(
            $renderer,
            new EntrepreneurProfile([
                'name' => 'Tania Hassounia',
                'concept_summary' => 'A practical founder-support studio for small businesses that need clearer planning, delivery, and decision support.',
            ]),
            null,
            [],
            [[
                'title' => 'Foundation',
                'entries' => [[
                    'key' => 'business-type-location',
                    'title' => 'Business type, location, and operating model',
                    'body' => 'Drawer Full of Giants Ltd is a creative-services advisory company based in Hamilton and operating through online, one-to-one, and small-group delivery.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'mission-vision',
                    'title' => 'Mission and vision',
                    'body' => 'The mission is to help working-class people turn promising ideas into practical businesses with clear plans and confidence.',
                    'evidence_count' => 0,
                ]],
            ], [
                'title' => 'Market',
                'entries' => [[
                    'key' => 'industry-context',
                    'title' => 'Industry and customer demand',
                    'body' => 'The industry demand is supported by early workshop feedback, founder interviews, and pilot programme interest from small-business owners.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'differentiation',
                    'title' => 'What sets the business apart',
                    'body' => 'The business sets itself apart through plain-English planning, practical implementation support, and a warm founder-led advisory style.',
                    'evidence_count' => 0,
                ]],
            ], [
                'title' => 'Strategy',
                'entries' => [[
                    'key' => 'success-factors',
                    'title' => 'Unique success factors',
                    'body' => 'Success depends on Tania Hassounia\'s facilitation experience, trusted community relationships, and repeatable planning methods.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'goals-objectives',
                    'title' => 'Goals and objectives',
                    'body' => 'The launch goals are to confirm paid demand, complete pilot programmes, and measure conversion into ongoing advisory support.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'culture',
                    'title' => 'Culture',
                    'body' => 'The culture emphasises honesty, practical encouragement, clear communication, and respect for founders who are learning as they build.',
                    'evidence_count' => 0,
                ]],
            ], [
                'title' => 'Legal & Operations',
                'entries' => [[
                    'key' => 'intellectual-property',
                    'title' => 'Intellectual property',
                    'body' => 'The intellectual property includes the brand, planning templates, client data, facilitation methods, and reusable learning materials.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'legal-environment',
                    'title' => 'Legal environment',
                    'body' => 'Legal and privacy obligations include clear client terms, responsible data handling, supplier commitments, and fit-for-purpose insurance.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'systems-software-processes',
                    'title' => 'Systems and processes',
                    'body' => 'The operating systems include Xero, scheduling, document storage, client communications, and a weekly review process.',
                    'evidence_count' => 0,
                ]],
            ], [
                'title' => 'Financial',
                'entries' => [[
                    'key' => 'financial-assumptions',
                    'title' => 'Financial assumptions',
                    'body' => 'Budget assumptions include known supplier costs, staged sales growth, monthly fixed costs, and founder funding discipline.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'revenue-model',
                    'title' => 'Revenue model',
                    'body' => 'Revenue is planned through fixed-price workshops, advisory packages, and follow-on implementation support with known margins.',
                    'evidence_count' => 0,
                ], [
                    'key' => 'launch-funding',
                    'title' => 'Funding and support',
                    'body' => 'Funding support is expected to cover setup costs, working capital, and a conservative runway while demand is validated.',
                    'evidence_count' => 0,
                ]],
            ]],
        );

        $this->assertStringContainsString('12 assessment areas', $summary['body']);
        $this->assertSame(12, substr_count($summary['body'], "\n- "));
        $this->assertStringContainsString('- Type of business: Drawer Full of Giants Ltd is a creative-services advisory company', $summary['body']);
        $this->assertStringContainsString('- Budget: Budget assumptions include known supplier costs', $summary['body']);
        $this->assertStringNotContainsString('operating, market, strategy, legal, and financial case across', $summary['body']);
    }

    public function test_index_lists_each_rendered_requirement_instead_of_phase_headings(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'indexEntries');
        $method->setAccessible(true);
        $entries = array_map(
            fn (int $position): array => [
                'key' => 'requirement-'.$position,
                'title' => 'Plan response '.$position,
                'body' => 'Completed plan response '.$position,
                'evidence_count' => 0,
            ],
            range(1, 12),
        );

        $index = $method->invoke($renderer, [[
            'title' => 'Foundation',
            'entries' => $entries,
        ]], null);

        $this->assertCount(12, $index);
        $this->assertSame('Plan response 1', $index[0]['title']);
        $this->assertSame('Plan response 12', $index[11]['title']);
    }

    public function test_index_markup_keeps_numbers_and_titles_in_aligned_cells(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'overviewHtml');
        $method->setAccessible(true);

        $html = $method->invoke(
            $renderer,
            [[
                'title' => 'Foundation',
                'entries' => [[
                    'key' => 'business-type-location',
                    'title' => 'Business type, location, and operating model',
                    'body' => 'The founder operates a Hamilton-based advisory business.',
                    'evidence_count' => 0,
                ]],
            ]],
            [
                'key' => 'executive-summary',
                'title' => 'Executive summary',
                'body' => 'A concise summary.',
                'evidence_count' => 0,
            ],
        );

        $this->assertStringContainsString('<table class="reader-index"><tbody><tr><td class="reader-index-number">01</td><td><strong>Executive summary</strong></td></tr>', $html);
        $this->assertStringContainsString('<tr><td class="reader-index-number">02</td><td><strong>Business type, location, and operating model</strong></td></tr>', $html);
        $this->assertStringNotContainsString('<ol>', $html);
    }

    public function test_business_plan_css_allows_plan_sections_to_flow_across_pages(): void
    {
        $renderer = app(BusinessPlanPreviewRenderer::class);
        $method = new ReflectionMethod($renderer, 'businessPlanCss');
        $method->setAccessible(true);

        $css = $method->invoke($renderer);

        $this->assertStringContainsString('.plan-phase { border: 0; border-left: 0; break-inside: auto;', $css);
        $this->assertStringContainsString('.phase-heading { border-bottom: 1px solid #ded6c7; break-after: avoid; break-inside: avoid;', $css);
        $this->assertStringNotContainsString('.plan-phase { border: 0; border-left: 0; break-before: page;', $css);
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
            ], [
                'title' => 'Financial',
                'requirements' => [[
                    'key' => 'executive-summary',
                    'title' => 'Executive summary',
                    'complete' => true,
                ]],
                'sections' => [[
                    'key' => 'founder-financial-executive-summary',
                    'title' => 'Executive summary',
                    'body' => 'A lender-ready summary of the business, its funding need, and the decision requested.',
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
        $this->assertStringContainsString('prepared with Future Shift Advisory assistance', $pdf);
        $this->assertStringContainsString('Executive summary', $pdf);
        $this->assertStringContainsString('Index', $pdf);
        $this->assertStringContainsString('Business type, location, and operating model', $pdf);
        $this->assertStringNotContainsString('Reader roadmap', $pdf);
        $this->assertStringNotContainsString('FALLBACK PDF', $pdf);
        $this->assertStringNotContainsString('Fallback rendering', $pdf);
        $this->assertStringContainsString('INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE', $pdf);
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
