<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyQuestionType;
use App\Models\SurveyAnswer;
use App\Models\SurveyAssignment;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SurveyResponseRecorder
{
    /**
     * @var array<int, string>
     */
    private const ANSWER_KEYS = ['received', 'accessible', 'met_objective'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
        private readonly SurveyFeedbackEvaluator $feedback,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(SurveyAssignment $assignment, User $user, array $input): SurveyResponse
    {
        return DB::transaction(function () use ($assignment, $input, $user): SurveyResponse {
            /** @var SurveyAssignment $locked */
            $locked = SurveyAssignment::query()
                ->with('survey.questions')
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->due_at !== null && $locked->due_at->isPast() && $locked->isActive()) {
                $this->context->withSystemContext(function () use ($locked): void {
                    $locked->forceFill(['status' => SurveyAssignmentStatus::Expired->value])->save();
                });
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'assignment' => 'This survey is no longer open for responses.',
                ]);
            }

            if ($locked->response()->exists()) {
                throw ValidationException::withMessages([
                    'assignment' => 'This survey has already been submitted.',
                ]);
            }

            $normalised = $this->normaliseAnswers($locked, $input);
            $scores = $this->scores($normalised);

            if ($locked->status === SurveyAssignmentStatus::Pending) {
                $this->context->withSystemContext(function () use ($locked): void {
                    SurveyAssignment::query()
                        ->whereKey($locked->getKey())
                        ->where('status', SurveyAssignmentStatus::Pending->value)
                        ->update(['status' => SurveyAssignmentStatus::InProgress->value]);
                });
                $locked->forceFill(['status' => SurveyAssignmentStatus::InProgress]);
            }

            $response = SurveyResponse::query()->create([
                'survey_assignment_id' => $locked->getKey(),
                'survey_id' => $locked->survey_id,
                'client_id' => $locked->client_id,
                'entrepreneur_profile_id' => $locked->entrepreneur_profile_id,
                'submitted_by_user_id' => $user->getKey(),
                'submitted_at' => now(),
                'overall_score' => $scores['overall_score'],
                'nps_score' => $scores['nps_score'],
            ]);

            foreach ($normalised as $answer) {
                SurveyAnswer::query()->create([
                    'response_id' => $response->getKey(),
                    'question_id' => $answer['question_id'],
                    'survey_id' => $locked->survey_id,
                    'client_id' => $locked->client_id,
                    'entrepreneur_profile_id' => $locked->entrepreneur_profile_id,
                    'anchor_ref' => $answer['anchor_ref'],
                    'answer_key' => $answer['answer_key'],
                    'value' => $answer['value'],
                    'numeric_value' => $answer['numeric_value'],
                ]);
            }

            $this->context->withSystemContext(function () use ($locked): void {
                SurveyAssignment::query()
                    ->whereKey($locked->getKey())
                    ->whereIn('status', SurveyAssignmentStatus::activeValues())
                    ->update([
                        'status' => SurveyAssignmentStatus::Completed->value,
                        'completed_at' => now(),
                    ]);
            });

            $response = $response->refresh()->load('answers.question', 'assignment');

            $this->audit->record('survey_response.submitted', subject: $response, actor: $user, after: [
                'survey_assignment_id' => $locked->getKey(),
                'survey_id' => $locked->survey_id,
                'client_id' => $locked->client_id,
                'entrepreneur_profile_id' => $locked->entrepreneur_profile_id,
                'answers_recorded' => count($normalised),
                'overall_score' => $response->overall_score,
                'nps_score' => $response->nps_score,
            ]);

            $this->feedback->evaluate($response);

            return $response;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array{question_id:string,question_type:string,anchor_ref:array<string, string>|null,answer_key:string|null,value:array<string, mixed>|null,numeric_value:float|int|null,normalised_score:float|null}>
     */
    private function normaliseAnswers(SurveyAssignment $assignment, array $input): array
    {
        $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $normalised = [];
        $errors = [];

        /** @var Collection<int, SurveyQuestion> $questions */
        $questions = $assignment->survey->questions;

        foreach ($questions as $question) {
            $questionId = (string) $question->getKey();
            $entry = $answers[$questionId] ?? null;

            if ($question->type === SurveyQuestionType::AnchoredMatrix) {
                foreach ($this->normaliseAnchoredAnswers($assignment, $question, $entry, $errors) as $answer) {
                    $normalised[] = $answer;
                }

                continue;
            }

            $answer = $this->normaliseFlatAnswer($question, $entry, $errors);
            if ($answer !== null) {
                $normalised[] = $answer;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalised;
    }

    /**
     * @param  array<string, string>  $errors
     * @return array{question_id:string,question_type:string,anchor_ref:null,answer_key:null,value:array<string, mixed>|null,numeric_value:float|int|null,normalised_score:float|null}|null
     */
    private function normaliseFlatAnswer(SurveyQuestion $question, mixed $entry, array &$errors): ?array
    {
        $raw = is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $entry;
        $questionId = (string) $question->getKey();

        if (($raw === null || $raw === '') && $question->required) {
            $errors["answers.{$questionId}.value"] = 'This question is required.';

            return null;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        [$value, $numeric, $normalisedScore] = match ($question->type) {
            SurveyQuestionType::Likert => $this->boundedNumber($raw, 1, 5, "answers.{$questionId}.value", $errors, 20.0),
            SurveyQuestionType::Nps => $this->boundedNumber($raw, 0, 10, "answers.{$questionId}.value", $errors, 10.0),
            SurveyQuestionType::Boolean => $this->booleanValue($raw),
            default => [null, null, null],
        };

        return [
            'question_id' => $questionId,
            'question_type' => $question->type->value,
            'anchor_ref' => null,
            'answer_key' => null,
            'value' => $value === null ? null : ['value' => $value],
            'numeric_value' => $numeric,
            'normalised_score' => $normalisedScore,
        ];
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<int, array{question_id:string,question_type:string,anchor_ref:array<string, string>,answer_key:string,value:array<string, mixed>,numeric_value:int,normalised_score:float}>
     */
    private function normaliseAnchoredAnswers(SurveyAssignment $assignment, SurveyQuestion $question, mixed $entry, array &$errors): array
    {
        $anchors = is_array($entry) && is_array($entry['anchors'] ?? null)
            ? array_values($entry['anchors'])
            : [];
        $deliverables = $this->deliverableMap($assignment);
        $normalised = [];
        $questionId = (string) $question->getKey();

        if ($deliverables === []) {
            return [];
        }

        if ($anchors === [] && $question->required) {
            $errors["answers.{$questionId}.anchors"] = 'Please respond for each delivered item.';

            return [];
        }

        foreach ($anchors as $index => $anchor) {
            if (! is_array($anchor)) {
                $errors["answers.{$questionId}.anchors.{$index}"] = 'Deliverable answers are invalid.';

                continue;
            }

            $sourceType = trim((string) ($anchor['source_type'] ?? ''));
            $sourceId = trim((string) ($anchor['source_id'] ?? ''));
            $anchorKey = "{$sourceType}:{$sourceId}";

            if (! isset($deliverables[$anchorKey])) {
                $errors["answers.{$questionId}.anchors.{$index}.source_id"] = 'Choose one of the survey deliverables.';

                continue;
            }

            foreach (self::ANSWER_KEYS as $answerKey) {
                [$value, $numeric, $score] = $this->booleanValue($anchor[$answerKey] ?? null);

                if ($value === null) {
                    $errors["answers.{$questionId}.anchors.{$index}.{$answerKey}"] = 'Please choose yes or no.';

                    continue;
                }

                $normalised[] = [
                    'question_id' => $questionId,
                    'question_type' => $question->type->value,
                    'anchor_ref' => [
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ],
                    'answer_key' => $answerKey,
                    'value' => ['value' => $value],
                    'numeric_value' => (int) $numeric,
                    'normalised_score' => $score,
                ];
            }
        }

        return $normalised;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function deliverableMap(SurveyAssignment $assignment): array
    {
        $snapshot = is_array($assignment->deliverable_snapshot) ? $assignment->deliverable_snapshot : [];
        $map = [];

        foreach ($snapshot as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sourceType = trim((string) ($item['source_type'] ?? ''));
            $sourceId = trim((string) ($item['source_id'] ?? ''));

            if ($sourceType !== '' && $sourceId !== '') {
                $map["{$sourceType}:{$sourceId}"] = $item;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $errors
     * @return array{0:int|null,1:int|null,2:float|null}
     */
    private function boundedNumber(mixed $raw, int $min, int $max, string $field, array &$errors, float $scale): array
    {
        if (! is_numeric($raw)) {
            $errors[$field] = "Enter a number from {$min} to {$max}.";

            return [null, null, null];
        }

        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            $errors[$field] = "Enter a number from {$min} to {$max}.";

            return [null, null, null];
        }

        return [$value, $value, round($value * $scale, 2)];
    }

    /**
     * @return array{0:bool|null,1:int|null,2:float|null}
     */
    private function booleanValue(mixed $raw): array
    {
        if ($raw === true || $raw === 1 || $raw === '1' || $raw === 'true' || $raw === 'yes') {
            return [true, 1, 100.0];
        }

        if ($raw === false || $raw === 0 || $raw === '0' || $raw === 'false' || $raw === 'no') {
            return [false, 0, 0.0];
        }

        return [null, null, null];
    }

    /**
     * @param  array<int, array{question_type:string,answer_key:string|null,numeric_value:float|int|null,normalised_score:float|null}>  $answers
     * @return array{overall_score:float|null,nps_score:int|null}
     */
    private function scores(array $answers): array
    {
        $scoreValues = collect($answers)
            ->pluck('normalised_score')
            ->filter(static fn (mixed $score): bool => is_numeric($score))
            ->map(static fn (mixed $score): float => (float) $score)
            ->values();

        $nps = collect($answers)
            ->first(fn (array $answer): bool => $answer['question_type'] === SurveyQuestionType::Nps->value && $answer['numeric_value'] !== null);

        return [
            'overall_score' => $scoreValues->isEmpty() ? null : round($scoreValues->avg(), 2),
            'nps_score' => is_array($nps) ? (int) $nps['numeric_value'] : null,
        ];
    }
}
