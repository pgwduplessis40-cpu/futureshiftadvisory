<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\BiasDetector;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class BiasDetectorTest extends TestCase
{
    public function test_logs_every_ai_output_and_returns_bias_signals(): void
    {
        Log::spy();

        $detector = app(BiasDetector::class);
        $signals = $detector->inspect(
            new PromptEnvelope(
                id: 'demo',
                version: 'v1',
                task: 'analyse',
                body: 'Analyse.',
            ),
            new AiResponse(
                text: 'This is an excellent result with no risks.',
                attributions: [
                    [
                        'claim' => 'This is an excellent result with no risks.',
                        'source_reference' => 'source:test',
                    ],
                ],
                uncertainty: Uncertainty::Medium,
                biasSignals: [],
                model: 'test',
                promptVersion: 'v1',
                promptHash: hash('sha256', 'prompt'),
                tokensIn: 1,
                tokensOut: 1,
            ),
        );

        $this->assertNotEmpty($signals);
        $this->assertContains('praise_language', array_column($signals, 'type'));
        $this->assertContains('risk_suppression_language', array_column($signals, 'type'));

        Log::shouldHaveReceived('info')
            ->with('ai.bias_assessed', Mockery::type('array'))
            ->once();
    }
}
