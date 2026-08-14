<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

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

    public function test_completed_executive_summary_is_rendered_before_other_plan_sections(): void
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

        $this->assertSame('Executive summary', $sections[0]['title']);
        $this->assertSame('executive-summary', $sections[0]['entries'][0]['key']);
        $this->assertSame('Foundation', $sections[1]['title']);
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
