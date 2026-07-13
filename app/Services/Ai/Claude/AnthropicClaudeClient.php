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

    private const MIN_TIMEOUT_SECONDS = 60;

    private const DEFAULT_MAX_OUTPUT_TOKENS = 4096;

    private const MIN_MAX_OUTPUT_TOKENS = 1024;

    private const MAX_MAX_OUTPUT_TOKENS = 8192;

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
        $timeoutSeconds = $this->timeoutSeconds();
        $retryAttempts = max(1, (int) Config::get('services.anthropic.retry_attempts', 1));

        $result = $this->http->post(
            service: 'anthropic',
            endpoint: $endpoint,
            payload: [
                'model' => $model,
                'max_tokens' => $this->maxOutputTokens(),
                'temperature' => 0,
                'system' => 'Return the requested assessment as structured JSON only. Do not include prose outside the JSON object. Keep the assessment concise and return no more than five findings.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->renderPrompt($prompt, $task),
                    ],
                ],
                'output_config' => [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $this->responseSchema($task),
                    ],
                ],
            ],
            headers: [
                'anthropic-version' => '2023-06-01',
                'x-api-key' => $key,
            ],
            timeoutSeconds: $timeoutSeconds,
            maxAttempts: $retryAttempts,
        );

        if (! $result->successful() || $result->fromFallback) {
            throw new AiUnavailableException($this->unavailableMessage($result));
        }

        $payload = is_array($result->data) ? $result->data : null;
        $this->recordUsage($payload, $prompt, $task, $model);

        $structured = $this->structuredPayload($payload);

        return AiResponse::fromStructuredPayload(
            payload: $structured,
            model: $model,
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: (int) ($payload['usage']['input_tokens'] ?? 0),
            tokensOut: (int) ($payload['usage']['output_tokens'] ?? 0),
        );
    }

    private function timeoutSeconds(): int
    {
        return max(self::MIN_TIMEOUT_SECONDS, (int) Config::get('services.anthropic.timeout_seconds', self::MIN_TIMEOUT_SECONDS));
    }

    private function maxOutputTokens(): int
    {
        $configured = (int) Config::get('services.anthropic.max_output_tokens', self::DEFAULT_MAX_OUTPUT_TOKENS);

        return min(self::MAX_MAX_OUTPUT_TOKENS, max(self::MIN_MAX_OUTPUT_TOKENS, $configured));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function structuredPayload(?array $payload): array
    {
        $stopReason = $this->stopReason($payload);
        if ($stopReason === 'refusal') {
            throw new AiIntegrityViolation('Anthropic declined the requested assessment.');
        }

        if ($stopReason === 'max_tokens') {
            throw new AiIntegrityViolation('Anthropic reached the output token limit before it could return the required JSON.');
        }

        $content = $payload['content'] ?? null;
        if (! is_array($content)) {
            throw new AiIntegrityViolation('Anthropic response did not contain structured text content.');
        }

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $text = $block['text'] ?? null;
            if (($block['type'] ?? null) === 'text' && is_string($text) && trim($text) !== '') {
                return $this->decodeStructuredText($text, $stopReason);
            }
        }

        throw new AiIntegrityViolation('Anthropic response did not contain structured text content.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructuredText(string $text, ?string $stopReason): array
    {
        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (JsonException) {
            $jsonObject = $this->extractJsonObject($text);
            if ($jsonObject !== null) {
                try {
                    $decoded = json_decode($jsonObject, true, flags: JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                } catch (JsonException) {
                    // Fall through to the integrity violation below.
                }
            }
        }

        $suffix = $stopReason === null ? '' : " (stop reason: {$stopReason})";

        throw new AiIntegrityViolation('Anthropic returned non-JSON content despite the structured-output request.'.$suffix);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function stopReason(?array $payload): ?string
    {
        $value = $payload['stop_reason'] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $reason = trim($value);

        return $reason === '' ? null : Str::limit($reason, 40, '');
    }

    private function extractJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(string $task): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'Client-safe assessment narrative.',
                ],
                'attributions' => [
                    'type' => 'array',
                    'items' => $this->attributionSchema(),
                ],
                'uncertainty' => [
                    'type' => 'string',
                    'enum' => ['high', 'medium', 'low', 'none'],
                ],
                'bias_signals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'signal' => ['type' => 'string'],
                            'mitigation' => ['type' => 'string'],
                        ],
                        'required' => ['signal', 'mitigation'],
                        'additionalProperties' => false,
                    ],
                ],
                'metadata' => $this->metadataSchema($task),
            ],
            'required' => ['text', 'attributions', 'uncertainty', 'metadata'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataSchema(string $task): array
    {
        if ($task === 'analyse') {
            return [
                'type' => 'object',
                'properties' => [
                    'findings' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'lens' => [
                                    'type' => 'string',
                                    'enum' => ['descriptive', 'diagnostic', 'predictive', 'prescriptive'],
                                ],
                                'severity' => [
                                    'type' => 'string',
                                    'enum' => ['info', 'low', 'medium', 'high', 'critical'],
                                ],
                                'title' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                                'recommended_action' => [
                                    'type' => 'string',
                                    'description' => 'A concise, client-facing action the founder can take before resubmitting.',
                                ],
                                'attributions' => [
                                    'type' => 'array',
                                    'items' => $this->attributionSchema(),
                                ],
                                'uncertainty' => [
                                    'type' => 'string',
                                    'enum' => ['high', 'medium', 'low', 'none'],
                                ],
                            ],
                            'required' => ['lens', 'severity', 'title', 'body', 'recommended_action', 'attributions', 'uncertainty'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['findings'],
                'additionalProperties' => false,
            ];
        }

        if ($task === 'score_criterion') {
            return [
                'type' => 'object',
                'properties' => [
                    'score' => [
                        'type' => 'integer',
                        'description' => 'Score from 0 to 100.',
                    ],
                ],
                'required' => ['score'],
                'additionalProperties' => false,
            ];
        }

        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributionSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'claim' => ['type' => 'string'],
                'source_reference' => ['type' => 'string'],
            ],
            'required' => ['claim', 'source_reference'],
            'additionalProperties' => false,
        ];
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

        $requestId = data_get($result->data, 'error_payload.request_id');
        if (is_scalar($requestId) && trim((string) $requestId) !== '') {
            $message .= ' (request id '.trim((string) $requestId).')';
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
        $exceptionMessage = data_get($result->data, 'error_payload.message');

        if ((! is_string($body) || trim($body) === '') && is_scalar($exceptionMessage)) {
            $message = trim((string) $exceptionMessage);

            return $message === '' ? null : Str::limit($message, 160, '');
        }

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
                        'recommended_action' => 'string, concise and client-facing',
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
