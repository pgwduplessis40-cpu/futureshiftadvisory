<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Exceptions\MissingAttributionException;
use App\Services\Ai\Integrity\SourceAttribution;
use Tests\TestCase;

final class SourceAttributionTest extends TestCase
{
    public function test_raises_when_response_has_text_without_attributions(): void
    {
        $response = new AiResponse(
            text: 'Revenue increased by 10 percent.',
            attributions: [],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'test',
            promptVersion: 'v1',
            promptHash: hash('sha256', 'prompt'),
            tokensIn: 1,
            tokensOut: 1,
        );

        $this->expectException(MissingAttributionException::class);

        app(SourceAttribution::class)->validate($response);
    }

    public function test_accepts_response_with_claim_and_source_reference(): void
    {
        $response = new AiResponse(
            text: 'Revenue increased by 10 percent.',
            attributions: [
                [
                    'claim' => 'Revenue increased by 10 percent.',
                    'source_reference' => 'uploaded:p-and-l.pdf#page=2',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'test',
            promptVersion: 'v1',
            promptHash: hash('sha256', 'prompt'),
            tokensIn: 1,
            tokensOut: 1,
        );

        app(SourceAttribution::class)->validate($response);

        $this->assertTrue(true);
    }
}
