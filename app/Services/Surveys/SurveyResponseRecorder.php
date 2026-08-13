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
                        'draft_answers' => null,
                        'draft_saved_at' => null,
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
     */
    public function saveDraft(SurveyAssignment $assignment, array $input): SurveyAssignment
    {
        return DB::transaction(function () use ($assignment, $input): SurveyAssignment {
            /** @var SurveyAssignment $locked */
            $locked = SurveyAssignment::query()
                ->with('survey.questions')
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isActive() || $locked->response()->exists()) {
                throw ValidationException::withMessages([
                    'assignment' => 'This survey is no longer open for draft responses.',
                ]);
            }

            $savedAt = now();
            $draft = $this->normaliseDraftAnswers($locked, $input);
            $status = $locked->status === SurveyAssignmentStatus::Pending
                ? SurveyAssignmentStatus::InProgress
                : $locked->status;

            $this->context->withSystemContext(function () use ($draft, $locked, $savedAt, $status): void {
                SurveyAssignment::query()
                    ->whereKey($locked->getKey())
                    ->whereIn('status', SurveyAssignmentStatus::activeValues())
                    ->update([
                        'status' => $status->value,
                        'draft_answers' => $draft,
                        'draft_saved_at' => $savedAt,
                    ]);
            });

            return $locked->refresh();
        });
    }

    /**
     * Preserve a just-entered response when a resend has replaced the assignment
     * before the founder reaches Submit. Drafts are stored by question key so a
     * newer survey version can restore matching questions safely.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveReplacementDraft(SurveyAssignment $source, SurveyAssignment $replacement, array $input): SurveyAssignment
    {
        return DB::transaction(function () use ($input, $replacement, $source): SurveyAssignment {
            /** @var SurveyAssignment $lockedSource */
            $lockedSource = SurveyAssignment::query()
                ->with('survey.questions')
                ->whereKey($source->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var SurveyAssignment $lockedReplacement */
            $lockedReplacement = SurveyAssignment::query()
                ->whereKey($replacement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReplacement->isActive() || $lockedReplacement->response()->exists()) {
                return $lockedReplacement;
            }

            $draft = [
                ...(is_array($lockedReplacement->draft_answers) ? $lockedReplacement->draft_answers : []),
                ...$this->normaliseDraftAnswers($lockedSource, $input),
            ];
            if ($draft === []) {
                return $lockedReplacement;
            }

            $savedAt = now();
            $this->context->withSystemContext(function () use ($draft, $lockedReplacement, $savedAt): void {
                SurveyAssignment::query()
                    ->whereKey($lockedReplacement->getKey())
                    ->whereIn('status', SurveyAssignmentStatus::activeValues())
                    ->update([
                        'status' => SurveyAssignmentStatus::InProgress->value,
                        'draft_answers' => $draft,
                        'draft_saved_at' => $savedAt,
                    ]);
            });

            return $lockedReplacement->refresh();
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
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function normaliseDraftAnswers(SurveyAssignment $assignment, array $input): array
    {
        $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $draft = [];

        /** @var Collection<int, SurveyQuestion> $questions */
        $questions = $assignment->survey->questions;

        foreach ($questions as $question) {
            $entry = $answers[(string) $question->getKey()] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            if ($question->type === SurveyQuestionType::AnchoredMatrix) {
                $anchors = $this->draftAnchors($assignment, $entry);
                if ($anchors !== []) {
                    $draft[$question->key] = ['anchors' => $anchors];
                }

                continue;
            }

            $answer = $this->draftFlatAnswer($question, $entry);
            if ($answer !== null) {
                $draft[$question->key] = $answer;
            }
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function draftFlatAnswer(SurveyQuestion $question, array $entry): ?array
    {
        $raw = $entry['value'] ?? null;

        if ($question->type === SurveyQuestionType::Text) {
            if (! is_string($raw) || trim($raw) === '') {
                return null;
            }

            return ['value' => mb_substr(trim($raw), 0, 4000)];
        }

        if ($question->type === SurveyQuestionType::Likert || $question->type === SurveyQuestionType::Nps) {
            $errors = [];
            [$value] = $this->boundedNumber(
                $raw,
                $question->type === SurveyQuestionType::Likert ? 1 : 0,
                $question->type === SurveyQuestionType::Likert ? 5 : 10,
                'draft',
                $errors,
                1.0,
            );

            if ($value === null) {
                return null;
            }

            $answer = ['value' => $value];
            if (is_string($entry['comment'] ?? null) && trim($entry['comment']) !== '') {
                $answer['comment'] = mb_substr(trim($entry['comment']), 0, 2000);
            }

            return $answer;
        }

        if ($question->type === SurveyQuestionType::Boolean) {
            [$value] = $this->booleanValue($raw);

            return $value === null ? null : ['value' => $value];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, array<string, bool|string>>
     */
    private function draftAnchors(SurveyAssignment $assignment, array $entry): array
    {
        $deliverables = $this->deliverableMap($assignment);
        $anchors = is_array($entry['anchors'] ?? null) ? array_values($entry['anchors']) : [];
        $draft = [];

        foreach ($anchors as $anchor) {
            if (! is_array($anchor)) {
                continue;
            }

            $sourceType = trim((string) ($anchor['source_type'] ?? ''));
            $sourceId = trim((string) ($anchor['source_id'] ?? ''));
            if (! isset($deliverables["{$sourceType}:{$sourceId}"])) {
                continue;
            }

            $answer = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ];

            foreach (self::ANSWER_KEYS as $answerKey) {
                [$value] = $this->booleanValue($anchor[$answerKey] ?? null);
                if ($value !== null) {
                    $answer[$answerKey] = $value;
                }
            }

            if (count($answer) > 2) {
                $draft[] = $answer;
            }
        }

        return $draft;
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
            SurveyQuestionType::Text => $this->textValue($raw, "answers.{$questionId}.value", $errors),
            default => [null, null, null],
        };

        $comment = match ($question->type) {
            SurveyQuestionType::Likert, SurveyQuestionType::Nps => $this->optionalComment(
                is_array($entry) ? ($entry['comment'] ?? null) : null,
                "answers.{$questionId}.comment",
                $errors,
            ),
            default => null,
        };

        $answerValue = $value === null ? null : ['value' => $value];
        if ($comment !== null && $answerValue !== null) {
            $answerValue['comment'] = $comment;
        }

        return [
            'question_id' => $questionId,
            'question_type' => $question->type->value,
            'anchor_ref' => null,
            'answer_key' => null,
            'value' => $answerValue,
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
     * @param  array<string, string>  $errors
     * @return array{0:string|null,1:null,2:null}
     */
    private function textValue(mixed $raw, string $field, array &$errors): array
    {
        if (! is_string($raw)) {
            $errors[$field] = 'Enter written feedback.';

            return [null, null, null];
        }

        $value = trim($raw);

        if ($value === '') {
            $errors[$field] = 'Enter written feedback.';

            return [null, null, null];
        }

        if (mb_strlen($value) > 4000) {
            $errors[$field] = 'Written feedback may not exceed 4,000 characters.';

            return [null, null, null];
        }

        return [$value, null, null];
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function optionalComment(mixed $raw, string $field, array &$errors): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw)) {
            $errors[$field] = 'Enter a written explanation.';

            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 2000) {
            $errors[$field] = 'Your explanation may not exceed 2,000 characters.';

            return null;
        }

        return $value;
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
