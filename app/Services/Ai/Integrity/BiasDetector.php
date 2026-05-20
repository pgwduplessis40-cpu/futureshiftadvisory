<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Models\LearningUpdate;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BiasDetector
{
    private const BIAS_LAYER_ID = 3;

    private const PRAISE_TERMS = [
        'amazing',
        'best-in-class',
        'excellent',
        'exceptional',
        'guaranteed',
        'outstanding',
        'perfect',
        'world-class',
    ];

    public function __construct(private readonly AuditWriter $auditWriter) {}

    /**
     * @param  array<string, mixed>  $subjectMetadata
     * @return array<int, array<string, mixed>>
     */
    public function inspect(
        PromptEnvelope $prompt,
        AiResponse $response,
        array $subjectMetadata = [],
    ): array {
        $signals = $this->detectSignals($response);
        $payload = [
            'prompt_id' => $prompt->id,
            'prompt_version' => $prompt->version,
            'prompt_hash' => $prompt->hash(),
            'model' => $response->model,
            'uncertainty' => $response->uncertainty->value,
            'signals' => $signals,
            'subject_metadata' => $subjectMetadata,
        ];

        Log::info('ai.bias_assessed', $payload);
        $this->recordAuditEvent($payload);
        $this->recordLearningUpdateIfNeeded($prompt, $response, $signals, $subjectMetadata);

        return $signals;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectSignals(AiResponse $response): array
    {
        $text = strtolower($response->text);
        $signals = [];

        foreach (self::PRAISE_TERMS as $term) {
            if (str_contains($text, $term)) {
                $signals[] = [
                    'type' => 'praise_language',
                    'term' => $term,
                    'severity' => 'review',
                    'reason' => 'Phase 1 heuristic flagged praise-oriented wording for advisor review.',
                ];
            }
        }

        if (str_contains($text, 'no risk') || str_contains($text, 'no risks')) {
            $signals[] = [
                'type' => 'risk_suppression_language',
                'severity' => 'review',
                'reason' => 'Phase 1 heuristic flagged wording that may suppress or overstate risk certainty.',
            ];
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordAuditEvent(array $payload): void
    {
        try {
            $this->auditWriter->record(
                action: 'ai.bias_assessed',
                subject: null,
                after: $payload,
            );
        } catch (Throwable $e) {
            Log::warning('Failed to persist AI bias audit event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @param  array<string, mixed>  $subjectMetadata
     */
    private function recordLearningUpdateIfNeeded(
        PromptEnvelope $prompt,
        AiResponse $response,
        array $signals,
        array $subjectMetadata,
    ): void {
        if ($signals === []) {
            return;
        }

        try {
            LearningUpdate::query()->create([
                'layer_id' => self::BIAS_LAYER_ID,
                'source' => [
                    'type' => 'bias_detector',
                    'prompt_id' => $prompt->id,
                    'prompt_hash' => $prompt->hash(),
                    'subject_metadata' => $subjectMetadata,
                ],
                'summary' => 'Bias detector heuristic flagged AI output for governed review.',
                'proposed_change' => [
                    'action' => 'review_prompt_or_output_policy',
                    'signals' => $signals,
                ],
                'impact_scope' => [
                    'prompt_id' => $prompt->id,
                    'model' => $response->model,
                ],
                'clients_affected' => 0,
                'magnitude' => 'low',
                'confidence' => 0.5,
                'evidence' => [
                    'response_excerpt' => mb_substr($response->text, 0, 500),
                    'signals' => $signals,
                ],
                'status' => LearningUpdate::STATUS_DETECTED,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to create AI learning update candidate', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
