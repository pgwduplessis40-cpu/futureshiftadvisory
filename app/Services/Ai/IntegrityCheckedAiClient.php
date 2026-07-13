<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\QuoteSourceExtractionClient;
use App\Services\Ai\Integrity\BiasDetector;
use App\Services\Ai\Integrity\SourceAttribution;

final class IntegrityCheckedAiClient implements AiClient, QuoteSourceExtractionClient
{
    public function __construct(
        private readonly AiClient $delegate,
        private readonly SourceAttribution $sourceAttribution,
        private readonly BiasDetector $biasDetector,
    ) {}

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->checked($prompt, $this->delegate->analyse($prompt));
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->checked($prompt, $this->delegate->verifyDocument($prompt));
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->checked($prompt, $this->delegate->scoreCriterion($prompt));
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->checked($prompt, $this->delegate->summarise($prompt));
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->checked($prompt, $this->delegate->redFlag($prompt));
    }

    public function extractQuoteSource(PromptEnvelope $prompt): AiResponse
    {
        if (! $this->delegate instanceof QuoteSourceExtractionClient) {
            return $this->checked($prompt, $this->delegate->analyse($prompt));
        }

        return $this->checked($prompt, $this->delegate->extractQuoteSource($prompt));
    }

    private function checked(PromptEnvelope $prompt, AiResponse $response): AiResponse
    {
        $this->sourceAttribution->validate($response);
        $signals = $this->biasDetector->inspect($prompt, $response);

        return $response->withBiasSignals($signals);
    }
}
