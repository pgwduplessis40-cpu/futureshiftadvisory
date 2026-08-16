<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Models\LearningUpdate;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\BiasDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class BiasDetectorTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_repeated_open_bias_signal_rolls_up_existing_learning_candidate(): void
    {
        $detector = app(BiasDetector::class);
        $prompt = new PromptEnvelope(
            id: 'entrepreneur_plan_score_criterion',
            version: 'v1',
            task: 'score',
            body: 'Score the entrepreneur plan.',
        );
        $response = $this->aiResponse($prompt, 'This is an exceptional plan.');

        $detector->inspect($prompt, $response, ['business_plan_id' => 'plan-1']);
        $detector->inspect($prompt, $response, ['business_plan_id' => 'plan-2']);

        $this->assertSame(1, LearningUpdate::query()
            ->where('layer_id', BiasDetector::LAYER_ID)
            ->where('source->type', 'bias_detector')
            ->count());

        $candidate = LearningUpdate::query()->firstOrFail();

        $this->assertSame(2, data_get($candidate->evidence, 'occurrences'));
        $this->assertNotEmpty(data_get($candidate->source, 'signal_key'));
        $this->assertSame('plan-2', data_get($candidate->source, 'latest_subject_metadata.business_plan_id'));
        $this->assertSame('This is an exceptional plan.', data_get($candidate->evidence, 'latest_response_excerpt'));
    }

    private function aiResponse(PromptEnvelope $prompt, string $text): AiResponse
    {
        return new AiResponse(
            text: $text,
            attributions: [
                [
                    'claim' => $text,
                    'source_reference' => 'source:test',
                ],
            ],
            uncertainty: Uncertainty::Medium,
            biasSignals: [],
            model: 'test',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 1,
            tokensOut: 1,
        );
    }
}
