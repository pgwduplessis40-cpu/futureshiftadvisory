<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Models\AuditEvent;
use App\Models\LearningUpdate;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class BiasDetector
{
    public const LAYER_ID = 3;

    private const CALIBRATION_WINDOW_DAYS = 30;

    private const MINIMUM_SAMPLE_SIZE = 10;

    private const MINIMUM_FLAGGED_OCCURRENCES = 3;

    private const MINIMUM_FLAGGED_RATE = 0.20;

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
        bool $recordLearningCandidate = true,
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
        if ($recordLearningCandidate) {
            $this->recordLearningUpdateIfNeeded($prompt, $response, $signals, $subjectMetadata);
        }

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
            $signalKey = $this->signalKey($prompt);
            $calibration = $this->calibrationSample($prompt);

            if (! $this->thresholdReached($calibration)) {
                return;
            }

            $existing = $this->openLearningCandidate($prompt, $signalKey);

            if ($existing instanceof LearningUpdate) {
                $this->recordAdditionalOccurrence($existing, $response, $signals, $subjectMetadata, $calibration);

                return;
            }

            LearningUpdate::query()->create([
                'layer_id' => self::LAYER_ID,
                'source' => [
                    'type' => 'bias_detector',
                    'prompt_id' => $prompt->id,
                    'prompt_hash' => $prompt->hash(),
                    'signal_key' => $signalKey,
                    'subject_metadata' => $subjectMetadata,
                ],
                'summary' => 'Bias detector calibration threshold reached for governed prompt review.',
                'proposed_change' => [
                    'action' => 'calibrate_bias_prompt_or_output_policy',
                    'signals' => $signals,
                    'requires_human_calibration' => true,
                    'automatic_application' => false,
                ],
                'impact_scope' => [
                    'prompt_id' => $prompt->id,
                    'model' => $response->model,
                    'surface' => 'ai_output_quality',
                ],
                'clients_affected' => 0,
                'magnitude' => 'low',
                'confidence' => min(0.9, 0.55 + ($calibration['flagged_rate'] / 2)),
                'evidence' => [
                    'response_excerpt' => mb_substr($response->text, 0, 500),
                    'signals' => $signals,
                    'occurrences' => $calibration['flagged_occurrences'],
                    'sample_size' => $calibration['sample_size'],
                    'flagged_rate' => $calibration['flagged_rate'],
                    'calibration_window_days' => self::CALIBRATION_WINDOW_DAYS,
                    'minimum_sample_size' => self::MINIMUM_SAMPLE_SIZE,
                    'minimum_flagged_occurrences' => self::MINIMUM_FLAGGED_OCCURRENCES,
                    'minimum_flagged_rate' => self::MINIMUM_FLAGGED_RATE,
                ],
                'status' => LearningUpdate::STATUS_DETECTED,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to create AI learning update candidate', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function openLearningCandidate(PromptEnvelope $prompt, string $signalKey): ?LearningUpdate
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
            ])
            ->where('source->type', 'bias_detector')
            ->where(function ($query) use ($prompt, $signalKey): void {
                $query
                    ->where('source->signal_key', $signalKey)
                    ->orWhere(function ($fallback) use ($prompt): void {
                        $fallback
                            ->where('source->prompt_id', $prompt->id)
                            ->where('source->prompt_hash', $prompt->hash());
                    });
            })
            ->latest()
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @param  array<string, mixed>  $subjectMetadata
     * @param  array{sample_size:int,flagged_occurrences:int,flagged_rate:float}  $calibration
     */
    private function recordAdditionalOccurrence(
        LearningUpdate $update,
        AiResponse $response,
        array $signals,
        array $subjectMetadata,
        array $calibration,
    ): void {
        $evidence = $update->evidence ?? [];
        $excerpt = mb_substr($response->text, 0, 500);
        $sampleExcerpts = collect(Arr::wrap(data_get($evidence, 'sample_excerpts', [])))
            ->push(data_get($evidence, 'response_excerpt'))
            ->push($excerpt)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $update->forceFill([
            'source' => array_merge($update->source ?? [], [
                'last_seen_at' => now()->toIso8601String(),
                'latest_subject_metadata' => $subjectMetadata,
            ]),
            'proposed_change' => array_merge($update->proposed_change ?? [], [
                'signals' => $this->mergeSignals((array) data_get($update->proposed_change, 'signals', []), $signals),
            ]),
            'evidence' => array_merge($evidence, [
                'signals' => $this->mergeSignals((array) data_get($evidence, 'signals', []), $signals),
                'occurrences' => $calibration['flagged_occurrences'],
                'sample_size' => $calibration['sample_size'],
                'flagged_rate' => $calibration['flagged_rate'],
                'latest_response_excerpt' => $excerpt,
                'last_seen_at' => now()->toIso8601String(),
                'sample_excerpts' => $sampleExcerpts,
            ]),
        ])->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function mergeSignals(array $existing, array $incoming): array
    {
        return collect([...$existing, ...$incoming])
            ->filter(fn (mixed $signal): bool => is_array($signal))
            ->unique(fn (array $signal): string => implode('|', [
                (string) ($signal['type'] ?? ''),
                (string) ($signal['term'] ?? ''),
                (string) ($signal['severity'] ?? ''),
                Str::limit((string) ($signal['reason'] ?? ''), 120, ''),
            ]))
            ->values()
            ->all();
    }

    private function signalKey(PromptEnvelope $prompt): string
    {
        return hash('sha256', json_encode([
            'bias_detector',
            $prompt->id,
            $prompt->hash(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{sample_size:int,flagged_occurrences:int,flagged_rate:float}
     */
    private function calibrationSample(PromptEnvelope $prompt): array
    {
        $events = AuditEvent::query()
            ->where('action', 'ai.bias_assessed')
            ->where('occurred_at', '>=', now()->subDays(self::CALIBRATION_WINDOW_DAYS))
            ->orderByDesc('occurred_at')
            ->get(['after']);

        $forPrompt = $events->filter(function (AuditEvent $event) use ($prompt): bool {
            $payload = $event->after ?? [];

            return data_get($payload, 'prompt_id') === $prompt->id
                && data_get($payload, 'prompt_hash') === $prompt->hash();
        });
        $sampleSize = $forPrompt->count();
        $flagged = $forPrompt
            ->filter(fn (AuditEvent $event): bool => is_array(data_get($event->after, 'signals')) && data_get($event->after, 'signals') !== [])
            ->count();

        return [
            'sample_size' => $sampleSize,
            'flagged_occurrences' => $flagged,
            'flagged_rate' => $sampleSize === 0 ? 0.0 : round($flagged / $sampleSize, 4),
        ];
    }

    /**
     * @param  array{sample_size:int,flagged_occurrences:int,flagged_rate:float}  $calibration
     */
    private function thresholdReached(array $calibration): bool
    {
        return $calibration['sample_size'] >= self::MINIMUM_SAMPLE_SIZE
            && $calibration['flagged_occurrences'] >= self::MINIMUM_FLAGGED_OCCURRENCES
            && $calibration['flagged_rate'] >= self::MINIMUM_FLAGGED_RATE;
    }
}
