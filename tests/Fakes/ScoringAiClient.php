<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Fake\FakeAiClient;

final class ScoringAiClient implements AiClient
{
    private readonly FakeAiClient $fallback;

    public function __construct(private readonly int $score = 70)
    {
        $this->fallback = new FakeAiClient;
    }

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->fallback->analyse($prompt);
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->fallback->verifyDocument($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return new AiResponse(
            text: 'Test score is based on the supplied business-plan evidence.',
            attributions: [[
                'claim' => 'Test score is based on the supplied business-plan evidence.',
                'source_reference' => $prompt->sourceReferences[0] ?? 'test:scoring-ai-client',
            ]],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'test-scoring-ai-client',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 1,
            tokensOut: 1,
            metadata: ['band' => $this->bandForScore()],
        );
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->fallback->summarise($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->fallback->redFlag($prompt);
    }

    private function bandForScore(): string
    {
        return match (true) {
            $this->score >= 90 => 'exceptional',
            $this->score >= 75 => 'strong',
            $this->score >= 60 => 'developing',
            default => 'needs_work',
        };
    }
}
