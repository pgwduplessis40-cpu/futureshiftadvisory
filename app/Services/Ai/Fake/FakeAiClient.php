<?php

declare(strict_types=1);

namespace App\Services\Ai\Fake;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;

final class FakeAiClient implements AiClient
{
    public const DEGRADED_TEXT = 'AI unavailable — analysis deferred';

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'analyse');
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'verify_document');
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'score_criterion');
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'summarise');
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'red_flag');
    }

    private function degraded(PromptEnvelope $prompt, string $task): AiResponse
    {
        $hash = $prompt->hash();

        return new AiResponse(
            text: self::DEGRADED_TEXT,
            attributions: [
                [
                    'claim' => self::DEGRADED_TEXT,
                    'source_reference' => 'system:degraded-mode',
                ],
            ],
            uncertainty: Uncertainty::High,
            biasSignals: [],
            model: 'fake-ai-client',
            promptVersion: $prompt->version,
            promptHash: $hash,
            tokensIn: str_word_count(json_encode($prompt->toArray(), JSON_THROW_ON_ERROR)),
            tokensOut: str_word_count(self::DEGRADED_TEXT),
            metadata: [
                'degraded' => true,
                'task' => $task,
                'response_id' => substr($hash, 0, 16),
            ],
        );
    }
}
