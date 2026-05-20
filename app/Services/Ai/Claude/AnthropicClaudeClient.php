<?php

declare(strict_types=1);

namespace App\Services\Ai\Claude;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Exceptions\AiIntegrityViolation;
use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use JsonException;

final class AnthropicClaudeClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->send($prompt, 'analyse');
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->send($prompt, 'verify_document');
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->send($prompt, 'score_criterion');
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->send($prompt, 'summarise');
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->send($prompt, 'red_flag');
    }

    private function send(PromptEnvelope $prompt, string $task): AiResponse
    {
        $key = (string) Config::get('services.anthropic.key', '');
        if ($key === '') {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is not configured.');
        }

        $model = (string) Config::get('services.anthropic.model', 'claude-sonnet-4-6');
        $endpoint = (string) Config::get('services.anthropic.endpoint', 'https://api.anthropic.com/v1/messages');

        $response = Http::withToken($key)
            ->withHeaders([
                'anthropic-version' => '2023-06-01',
                'x-api-key' => $key,
            ])
            ->timeout(30)
            ->post($endpoint, [
                'model' => $model,
                'max_tokens' => 2048,
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->renderPrompt($prompt, $task),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new AiUnavailableException(
                'Anthropic API request failed with status '.$response->status()
            );
        }

        $payload = $response->json();
        $text = $payload['content'][0]['text'] ?? null;
        if (! is_string($text) || $text === '') {
            throw new AiIntegrityViolation('Anthropic response did not contain structured text content.');
        }

        try {
            /** @var array<string, mixed> $structured */
            $structured = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AiIntegrityViolation(
                'Anthropic response was freeform prose instead of the required JSON schema.',
                previous: $e,
            );
        }

        return AiResponse::fromStructuredPayload(
            payload: $structured,
            model: $model,
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: (int) ($payload['usage']['input_tokens'] ?? 0),
            tokensOut: (int) ($payload['usage']['output_tokens'] ?? 0),
        );
    }

    private function renderPrompt(PromptEnvelope $prompt, string $task): string
    {
        return json_encode([
            'task' => $task,
            'integrity_preamble' => $prompt->integrityPreamble,
            'integrity_preamble_version' => $prompt->integrityPreambleVersion,
            'prompt' => $prompt->body,
            'input' => $prompt->input,
            'data_quality_summary' => $prompt->dataQualitySummary,
            'source_references' => $prompt->sourceReferences,
            'required_response_schema' => [
                'text' => 'string',
                'attributions' => [
                    ['claim' => 'string', 'source_reference' => 'string'],
                ],
                'uncertainty' => ['high', 'medium', 'low', 'none'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
