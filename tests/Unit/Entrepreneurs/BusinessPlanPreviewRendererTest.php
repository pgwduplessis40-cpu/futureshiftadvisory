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
