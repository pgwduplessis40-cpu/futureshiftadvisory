<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\QuoteSourceExtractionClient;
use App\Services\Ai\Exceptions\AiIntegrityViolation;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Fake\FakeAiClient;

final class FallbackAiClient implements AiClient, QuoteSourceExtractionClient
{
    public function __construct(
        private readonly ?AiClient $live,
        private readonly FakeAiClient $fake,
        private readonly AdvisorAiNotice $notice,
        private readonly bool $forceFake,
        private readonly string $unavailableReason = 'AI provider is not configured.',
    ) {}

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'analyse');
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'verifyDocument');
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'scoreCriterion');
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'summarise');
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'redFlag');
    }

    public function extractQuoteSource(PromptEnvelope $prompt): AiResponse
    {
        return $this->call($prompt, 'extractQuoteSource');
    }

    private function call(PromptEnvelope $prompt, string $method): AiResponse
    {
        if ($this->forceFake || $this->live === null) {
            $this->notice->recordUnavailable($prompt, $this->unavailableReason);

            return $this->degradedResponse($prompt, $method, $this->unavailableReason);
        }

        if ($method === 'extractQuoteSource' && ! $this->live instanceof QuoteSourceExtractionClient) {
            $this->notice->recordUnavailable($prompt, 'Configured AI provider does not support implementation-plan extraction.');

            return $this->degradedResponse($prompt, $method, 'Configured AI provider does not support implementation-plan extraction.');
        }

        try {
            $response = $this->live->{$method}($prompt);
            $this->notice->clear();

            return $response;
        } catch (AiUnavailableException|AiIntegrityViolation $e) {
            $this->notice->recordUnavailable($prompt, $e->getMessage());

            return $this->degradedResponse($prompt, $method, $e->getMessage());
        }
    }

    private function degradedResponse(PromptEnvelope $prompt, string $method, string $reason): AiResponse
    {
        $response = $this->fake->{$method}($prompt);

        return new AiResponse(
            text: $response->text,
            attributions: $response->attributions,
            uncertainty: $response->uncertainty,
            biasSignals: $response->biasSignals,
            model: $response->model,
            promptVersion: $response->promptVersion,
            promptHash: $response->promptHash,
            tokensIn: $response->tokensIn,
            tokensOut: $response->tokensOut,
            metadata: [
                ...$response->metadata,
                'degraded' => true,
                'unavailable_reason' => $reason,
            ],
        );
    }
}
