<?php

declare(strict_types=1);

namespace App\Services\Ai\Fake;

use App\Models\DocumentVerification;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\QuoteSourceExtractionClient;
use App\Services\Ai\Contracts\Uncertainty;
use Illuminate\Support\Arr;

final class FakeAiClient implements AiClient, QuoteSourceExtractionClient
{
    public const DEGRADED_TEXT = 'AI unavailable — analysis deferred';

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->degraded($prompt, 'analyse');
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        $claim = $this->claimText($prompt);
        $haystack = strtolower($claim.' '.$this->documentText($prompt));

        if (str_contains($haystack, 'accuracy discrepancy') || str_contains($haystack, 'discrepancy') || str_contains($haystack, 'does not match')) {
            return $this->verificationResponse(
                prompt: $prompt,
                claim: $claim,
                outcome: DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
                text: 'The document appears to conflict with the attached claim.',
                clientExplanation: 'This document appears to conflict with the attached claim, so related analysis is paused.',
                uncertainty: Uncertainty::Medium,
                confidence: 0.82,
            );
        }

        if (str_contains($haystack, 'advisory') || str_contains($haystack, 'needs review') || str_contains($haystack, 'unclear')) {
            return $this->verificationResponse(
                prompt: $prompt,
                claim: $claim,
                outcome: DocumentVerification::OUTCOME_ADVISORY_FLAG,
                text: 'The document should be reviewed by an advisor before being relied on.',
                clientExplanation: 'An advisor is reviewing this document before it is used in analysis.',
                uncertainty: Uncertainty::Medium,
                confidence: 0.64,
            );
        }

        return $this->verificationResponse(
            prompt: $prompt,
            claim: $claim,
            outcome: DocumentVerification::OUTCOME_VERIFIED,
            text: 'The document supports the attached claim.',
            clientExplanation: 'This document supports the attached claim.',
            uncertainty: Uncertainty::Low,
            confidence: 0.93,
        );
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

    public function extractQuoteSource(PromptEnvelope $prompt): AiResponse
    {
        $hash = $prompt->hash();

        return new AiResponse(
            text: 'Draft scope rows prepared from the supplied implementation-plan evidence.',
            attributions: [[
                'claim' => 'Draft scope rows prepared from the supplied implementation-plan evidence.',
                'source_reference' => 'quote-source:'.$hash,
            ]],
            uncertainty: Uncertainty::Medium,
            biasSignals: [],
            model: 'fake-ai-client',
            promptVersion: $prompt->version,
            promptHash: $hash,
            tokensIn: str_word_count(json_encode($prompt->toArray(), JSON_THROW_ON_ERROR)),
            tokensOut: 10,
            metadata: [
                'task' => 'quote_source_extract',
                'extracted_rows' => [],
                'response_id' => substr($hash, 0, 16),
            ],
        );
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

    private function verificationResponse(
        PromptEnvelope $prompt,
        string $claim,
        string $outcome,
        string $text,
        string $clientExplanation,
        Uncertainty $uncertainty,
        float $confidence,
    ): AiResponse {
        $hash = $prompt->hash();
        $sourceReference = $this->documentSource($prompt);

        return new AiResponse(
            text: $text,
            attributions: [
                [
                    'claim' => $claim === '' ? $text : $claim,
                    'source_reference' => $sourceReference,
                ],
            ],
            uncertainty: $uncertainty,
            biasSignals: [],
            model: 'fake-ai-client',
            promptVersion: $prompt->version,
            promptHash: $hash,
            tokensIn: str_word_count(json_encode($prompt->toArray(), JSON_THROW_ON_ERROR)),
            tokensOut: str_word_count($text),
            metadata: [
                'task' => 'verify_document',
                'verification_outcome' => $outcome,
                'confidence' => $confidence,
                'client_explanation' => $clientExplanation,
                'response_id' => substr($hash, 0, 16),
            ],
        );
    }

    private function claimText(PromptEnvelope $prompt): string
    {
        $value = Arr::get($prompt->input, 'claim.text', Arr::get($prompt->input, 'claim', ''));

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function documentText(PromptEnvelope $prompt): string
    {
        $value = Arr::get($prompt->input, 'document.content_excerpt', '');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function documentSource(PromptEnvelope $prompt): string
    {
        $id = Arr::get($prompt->input, 'document.id');

        return is_scalar($id) && trim((string) $id) !== ''
            ? 'document:'.trim((string) $id)
            : 'document:uploaded';
    }
}
