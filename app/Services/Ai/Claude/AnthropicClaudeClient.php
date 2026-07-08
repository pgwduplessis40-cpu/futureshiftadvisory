<?php

declare(strict_types=1);

namespace App\Services\Ai\Claude;

use App\Services\Ai\AiUsageRecorder;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Exceptions\AiIntegrityViolation;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;

final class AnthropicClaudeClient implements AiClient
{
    private const PROVIDER = 'anthropic';

    public function __construct(
        private readonly IntegrationCredentials $credentials,
        private readonly AiUsageRecorder $usage,
        private readonly ResilientHttp $http,
    ) {}

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
        $key = (string) ($this->credentials->get('anthropic', 'key') ?? '');
        if ($key === '') {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is not configured.');
        }

        $model = (string) Config::get('services.anthropic.model', 'claude-sonnet-4-6');
        $endpoint = (string) Config::get('services.anthropic.endpoint', 'https://api.anthropic.com/v1/messages');
        $timeoutSeconds = max(1, (int) Config::get('services.anthropic.timeout_seconds', 20));

        $result = $this->http->post(
            service: 'anthropic',
            endpoint: $endpoint,
            payload: [
                'model' => $model,
                'max_tokens' => 2048,
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->renderPrompt($prompt, $task),
                    ],
                ],
            ],
            headers: [
                'anthropic-version' => '2023-06-01',
                'x-api-key' => $key,
            ],
            timeoutSeconds: $timeoutSeconds,
        );

        if (! $result->successful() || $result->fromFallback) {
            throw new AiUnavailableException($this->unavailableMessage($result));
        }

        $payload = is_array($result->data) ? $result->data : null;
        $this->recordUsage($payload, $prompt, $task, $model);

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

    private function unavailableMessage(IntegrationResult $result): string
    {
        $statusCode = $this->upstreamStatusCode($result);
        $message = 'Anthropic API request failed';

        if ($statusCode !== null) {
            $message .= ' with status '.$statusCode;
        }

        $reason = $this->upstreamFailureReason($result);
        if ($reason !== null) {
            $message .= ': '.$reason;
        }

        return Str::limit($message, 300, '');
    }

    private function upstreamStatusCode(IntegrationResult $result): ?int
    {
        $statusCode = data_get($result->data, 'error_payload.http_status');

        if (is_numeric($statusCode)) {
            return (int) $statusCode;
        }

        return $result->statusCode > 0 ? $result->statusCode : null;
    }

    private function upstreamFailureReason(IntegrationResult $result): ?string
    {
        $body = data_get($result->data, 'error_payload.body');

        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $candidate = data_get($decoded, 'error.message')
                ?? data_get($decoded, 'error.type')
                ?? data_get($decoded, 'message');

            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return Str::limit(trim((string) $candidate), 160, '');
            }
        }

        return Str::limit(trim($body), 160, '');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function recordUsage(?array $payload, PromptEnvelope $prompt, string $task, string $model): void
    {
        if ($payload === null) {
            return;
        }

        $usage = $payload['usage'] ?? [];
        if (! is_array($usage)) {
            return;
        }

        $this->usage->record(
            provider: self::PROVIDER,
            task: $task,
            model: $model,
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            cacheCreationInputTokens: (int) ($usage['cache_creation_input_tokens'] ?? 0),
            cacheReadInputTokens: (int) ($usage['cache_read_input_tokens'] ?? 0),
        );
    }

    private function renderPrompt(PromptEnvelope $prompt, string $task): string
    {
        $requiredSchema = [
            'text' => 'string',
            'attributions' => [
                ['claim' => 'string', 'source_reference' => 'string'],
            ],
            'uncertainty' => ['high', 'medium', 'low', 'none'],
        ];

        if ($task === 'score_criterion') {
            $requiredSchema['metadata'] = [
                'score' => 'integer from 0 to 100, calibrated to the supplied rating framework descriptors',
            ];
        }

        if ($task === 'analyse') {
            $requiredSchema['metadata'] = [
                'findings' => [
                    [
                        'lens' => 'descriptive|diagnostic|predictive|prescriptive',
                        'severity' => 'info|low|medium|high|critical',
                        'title' => 'string',
                        'body' => 'string',
                        'attributions' => [
                            ['claim' => 'string', 'source_reference' => 'string'],
                        ],
                        'uncertainty' => 'high|medium|low|none',
                    ],
                ],
            ];
        }

        return json_encode([
            'task' => $task,
            'integrity_preamble' => $prompt->integrityPreamble,
            'integrity_preamble_version' => $prompt->integrityPreambleVersion,
            'prompt' => $prompt->body,
            'input' => $prompt->input,
            'data_quality_summary' => $prompt->dataQualitySummary,
            'source_references' => $prompt->sourceReferences,
            'required_response_schema' => $requiredSchema,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
