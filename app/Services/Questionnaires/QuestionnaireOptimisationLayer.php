<?php

declare(strict_types=1);

namespace App\Services\Questionnaires;

use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\Questionnaire;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuestionnaireOptimisationLayer
{
    public const LAYER_ID = 16;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(
        int $minimumResponses = 3,
        float $blankRateThreshold = 0.5,
        int $windowDays = 90,
        int $maxCandidates = 5,
        ?CarbonInterface $windowEnd = null,
    ): LearningLayerRun {
        $minimumResponses = max(1, $minimumResponses);
        $blankRateThreshold = max(0.01, min(1.0, $blankRateThreshold));
        $windowDays = max(1, $windowDays);
        $maxCandidates = max(1, $maxCandidates);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        return DB::transaction(function () use ($minimumResponses, $blankRateThreshold, $windowDays, $maxCandidates, $windowStart, $windowEnd): LearningLayerRun {
            $candidates = $this->candidates($minimumResponses, $blankRateThreshold, $windowStart, $windowEnd)
                ->sortByDesc('blank_rate')
                ->take($maxCandidates);
            $created = 0;

            foreach ($candidates as $candidate) {
                if ($this->candidateExists($candidate, $windowStart, $windowEnd)) {
                    continue;
                }

                $this->createCandidate($candidate, $windowStart, $windowEnd);
                $created++;
            }

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $created,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => $windowDays,
                    'minimum_responses' => $minimumResponses,
                    'blank_rate_threshold' => $blankRateThreshold,
                    'max_candidates' => $maxCandidates,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('questionnaire_optimisation_layer.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'candidates_created' => $created,
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ]);

            return $run;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $limit = 5): array
    {
        $latestRun = LearningLayerRun::query()
            ->where('layer_id', self::LAYER_ID)
            ->latest('ran_at')
            ->first();
        $query = LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'questionnaire_optimisation_layer')
            ->latest();
        $total = (clone $query)->count();
        $items = $query
            ->limit($limit)
            ->get()
            ->map(fn (LearningUpdate $update): array => [
                'id' => $update->id,
                'summary' => $update->summary,
                'magnitude' => $update->magnitude,
                'confidence' => $update->confidence,
                'questionnaire_title' => data_get($update->source, 'questionnaire_title'),
                'question_prompt' => data_get($update->source, 'question_prompt'),
                'created_at' => $update->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'detected_candidates' => $total,
                'latest_run_at' => $latestRun?->ran_at?->toIso8601String(),
                'latest_candidates_created' => $latestRun?->candidates_created ?? 0,
            ],
            'items' => $items,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function candidates(
        int $minimumResponses,
        float $blankRateThreshold,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): Collection {
        return Questionnaire::query()
            ->published()
            ->with([
                'sections.questions',
                'responses' => fn ($query) => $query
                    ->whereBetween('submitted_at', [$windowStart, $windowEnd])
                    ->with('answers'),
            ])
            ->get()
            ->flatMap(function (Questionnaire $questionnaire) use ($minimumResponses, $blankRateThreshold): Collection {
                /** @var Collection<int, QuestionnaireResponse> $responses */
                $responses = $questionnaire->responses
                    ->filter(fn (QuestionnaireResponse $response): bool => $response->submitted_at !== null)
                    ->values();

                if ($responses->count() < $minimumResponses) {
                    return collect();
                }

                return $questionnaire->sections
                    ->flatMap(fn ($section) => $section->questions)
                    ->map(function (QuestionnaireQuestion $question) use ($questionnaire, $responses, $minimumResponses, $blankRateThreshold): ?array {
                        $answered = $responses->count();
                        $blank = $responses->filter(fn (QuestionnaireResponse $response): bool => $this->blankAnswer($response, $question))->count();
                        $blankRate = $answered === 0 ? 0.0 : round($blank / $answered, 4);

                        if ($answered < $minimumResponses || $blankRate < $blankRateThreshold) {
                            return null;
                        }

                        return [
                            'questionnaire_id' => $questionnaire->id,
                            'questionnaire_title' => $questionnaire->title,
                            'questionnaire_set' => $questionnaire->set->value,
                            'questionnaire_version' => $questionnaire->version,
                            'question_id' => $question->id,
                            'question_prompt' => Str::limit($question->prompt, 160),
                            'question_type' => $question->type->value,
                            'responses' => $answered,
                            'blank_answers' => $blank,
                            'blank_rate' => $blankRate,
                            'clients_affected' => $responses
                                ->pluck('client_id')
                                ->filter()
                                ->unique()
                                ->count(),
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->values();
    }

    private function blankAnswer(QuestionnaireResponse $response, QuestionnaireQuestion $question): bool
    {
        $answer = $response->answers
            ->first(fn (QuestionnaireAnswer $answer): bool => (string) $answer->question_id === (string) $question->id);

        if (! $answer instanceof QuestionnaireAnswer) {
            return true;
        }

        $value = $answer->value;

        if (is_array($value) && array_key_exists('value', $value)) {
            $value = $value['value'];
        }

        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function candidateExists(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'questionnaire_optimisation_layer')
            ->where('source->signal_key', $this->signalKey($candidate, $windowStart, $windowEnd))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function createCandidate(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'questionnaire_optimisation_layer',
                'signal_key' => $this->signalKey($candidate, $windowStart, $windowEnd),
                'questionnaire_id' => $candidate['questionnaire_id'],
                'questionnaire_title' => $candidate['questionnaire_title'],
                'questionnaire_set' => $candidate['questionnaire_set'],
                'questionnaire_version' => $candidate['questionnaire_version'],
                'question_id' => $candidate['question_id'],
                'question_prompt' => $candidate['question_prompt'],
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => sprintf(
                'Questionnaire question "%s" has %.1f%% blank or omitted responses; review the prompt, optionality, or helper text.',
                $candidate['question_prompt'],
                ((float) $candidate['blank_rate']) * 100,
            ),
            'proposed_change' => [
                'action' => 'review_questionnaire_question',
                'questionnaire_id' => $candidate['questionnaire_id'],
                'question_id' => $candidate['question_id'],
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'questionnaire_set' => $candidate['questionnaire_set'],
                'questionnaire_version' => $candidate['questionnaire_version'],
                'question_type' => $candidate['question_type'],
            ],
            'clients_affected' => (int) $candidate['clients_affected'],
            'magnitude' => ((float) $candidate['blank_rate']) >= 0.75 ? 'medium' : 'low',
            'confidence' => 0.72,
            'evidence' => $candidate,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function signalKey(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): string
    {
        return hash('sha256', implode('|', [
            $candidate['questionnaire_id'],
            $candidate['question_id'],
            $windowStart->toDateString(),
            $windowEnd->toDateString(),
        ]));
    }
}
