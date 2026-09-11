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

    public function test_bias_learning_candidate_requires_a_calibrated_sample_then_rolls_up(): void
    {
        $detector = app(BiasDetector::class);
        $prompt = new PromptEnvelope(
            id: 'entrepreneur_plan_score_criterion',
            version: 'v1',
            task: 'score',
            body: 'Score the entrepreneur plan.',
        );
        $response = $this->aiResponse($prompt, 'This is an exceptional plan.');
        $cleanResponse = $this->aiResponse($prompt, 'The available evidence indicates a moderate risk profile.');

        foreach (range(1, 7) as $index) {
            $detector->inspect($prompt, $cleanResponse, ['business_plan_id' => 'clean-'.$index]);
        }

        foreach (range(1, 3) as $index) {
            $detector->inspect($prompt, $response, ['business_plan_id' => 'plan-'.$index]);
        }

        $this->assertSame(1, LearningUpdate::query()
            ->where('layer_id', BiasDetector::LAYER_ID)
            ->where('source->type', 'bias_detector')
            ->count());

        $candidate = LearningUpdate::query()->firstOrFail();

        $this->assertSame(3, data_get($candidate->evidence, 'occurrences'));
        $this->assertSame(10, data_get($candidate->evidence, 'sample_size'));
        $this->assertEqualsWithDelta(0.3, (float) data_get($candidate->evidence, 'flagged_rate'), 0.0001);
        $this->assertNotEmpty(data_get($candidate->source, 'signal_key'));
        $this->assertSame('plan-3', data_get($candidate->source, 'subject_metadata.business_plan_id'));
        $this->assertSame('This is an exceptional plan.', data_get($candidate->evidence, 'response_excerpt'));

        $detector->inspect($prompt, $response, ['business_plan_id' => 'plan-4']);

        $candidate->refresh();
        $this->assertSame(4, data_get($candidate->evidence, 'occurrences'));
        $this->assertSame(11, data_get($candidate->evidence, 'sample_size'));
        $this->assertSame('plan-4', data_get($candidate->source, 'latest_subject_metadata.business_plan_id'));
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
