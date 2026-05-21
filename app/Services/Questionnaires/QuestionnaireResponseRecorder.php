<?php

declare(strict_types=1);

namespace App\Services\Questionnaires;

use App\Enums\QuestionnaireQuestionType;
use App\Models\Client;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QuestionnaireResponseRecorder
{
    public function __construct(
        private readonly QuestionnaireRuleEngine $rules,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(Client $client, User $user, Questionnaire $questionnaire, array $input): QuestionnaireResponse
    {
        $questionnaire->loadMissing('sections.questions');

        $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $visibleIds = $this->rules->visibleQuestionIds($questionnaire, $answers);
        $visibleMap = array_fill_keys($visibleIds, true);
        $errors = [];
        $normalised = [];

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $questionId = (string) $question->getKey();

                if (! isset($visibleMap[$questionId])) {
                    continue;
                }

                $entry = $this->answerEntry($answers[$questionId] ?? null);
                $attachedDocumentIds = $this->normaliseDocumentIds($entry['attached_document_ids'] ?? []);
                $value = $this->normaliseValue($question, $entry['value'] ?? null, $errors);

                if ($question->required && $this->isEmpty($question, $value, $attachedDocumentIds)) {
                    $errors["answers.{$questionId}.value"] = 'This question is required.';
                }

                foreach ($attachedDocumentIds as $documentId) {
                    if (! Str::isUuid($documentId)) {
                        $errors["answers.{$questionId}.attached_document_ids"] = 'Attached documents must use document UUIDs.';
                        break;
                    }
                }

                $normalised[$questionId] = [
                    'value' => $value,
                    'attached_document_ids' => $attachedDocumentIds,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($client, $normalised, $questionnaire, $user): QuestionnaireResponse {
            $response = QuestionnaireResponse::query()->updateOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'questionnaire_id' => $questionnaire->getKey(),
                ],
                [
                    'submitted_at' => now(),
                    'submitted_by_user_id' => $user->getKey(),
                ],
            );

            $response->answers()
                ->whereNotIn('question_id', array_keys($normalised))
                ->delete();

            foreach ($normalised as $questionId => $answer) {
                $response->answers()->updateOrCreate(
                    ['question_id' => $questionId],
                    [
                        'value' => $answer['value'],
                        'attached_document_ids' => $answer['attached_document_ids'],
                    ],
                );
            }

            $this->auditWriter->record('questionnaire.submitted', subject: $response, actor: $user, after: [
                'client_id' => $client->getKey(),
                'questionnaire_id' => $questionnaire->getKey(),
                'answers_recorded' => count($normalised),
            ]);

            return $response->refresh()->load('answers');
        });
    }

    /**
     * @return array{value:mixed, attached_document_ids:mixed}
     */
    private function answerEntry(mixed $entry): array
    {
        if (is_array($entry)) {
            return [
                'value' => $entry['value'] ?? null,
                'attached_document_ids' => $entry['attached_document_ids'] ?? [],
            ];
        }

        return [
            'value' => $entry,
            'attached_document_ids' => [],
        ];
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normaliseValue(QuestionnaireQuestion $question, mixed $value, array &$errors): mixed
    {
        return match ($question->type) {
            QuestionnaireQuestionType::TEXT,
            QuestionnaireQuestionType::LONG_TEXT => $this->stringValue($value),
            QuestionnaireQuestionType::NUMBER,
            QuestionnaireQuestionType::CURRENCY => $this->numberValue($question, $value, $errors),
            QuestionnaireQuestionType::DATE => $this->dateValue($question, $value, $errors),
            QuestionnaireQuestionType::SINGLE_SELECT,
            QuestionnaireQuestionType::LIKERT => $this->choiceValue($question, $value, $errors),
            QuestionnaireQuestionType::MULTI_SELECT => $this->multiChoiceValue($question, $value, $errors),
            QuestionnaireQuestionType::FILE_ATTACH => null,
        };
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function numberValue(QuestionnaireQuestion $question, mixed $value, array &$errors): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            $errors["answers.{$question->id}.value"] = 'Enter a numeric value.';

            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function dateValue(QuestionnaireQuestion $question, mixed $value, array &$errors): ?string
    {
        $value = $this->stringValue($value);
        if ($value === null) {
            return null;
        }

        $date = date_create_from_format('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $errors["answers.{$question->id}.value"] = 'Use YYYY-MM-DD date format.';

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function choiceValue(QuestionnaireQuestion $question, mixed $value, array &$errors): ?string
    {
        $value = $this->stringValue($value);
        if ($value === null) {
            return null;
        }

        if (! in_array($value, $this->optionValues($question), true)) {
            $errors["answers.{$question->id}.value"] = 'Choose one of the available options.';
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<int, string>
     */
    private function multiChoiceValue(QuestionnaireQuestion $question, mixed $value, array &$errors): array
    {
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [];
        $allowed = $this->optionValues($question);

        foreach ($values as $candidate) {
            if (! in_array($candidate, $allowed, true)) {
                $errors["answers.{$question->id}.value"] = 'Choose only available options.';
                break;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, string>
     */
    private function optionValues(QuestionnaireQuestion $question): array
    {
        $options = is_array($question->options) ? $question->options : [];

        return collect($options)
            ->map(function (mixed $option): ?string {
                if (is_array($option) && isset($option['value'])) {
                    return (string) $option['value'];
                }

                return is_scalar($option) ? (string) $option : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normaliseDocumentIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $documentId): string => trim((string) $documentId), $value),
            static fn (string $documentId): bool => $documentId !== '',
        )));
    }

    /**
     * @param  array<int, string>  $attachedDocumentIds
     */
    private function isEmpty(QuestionnaireQuestion $question, mixed $value, array $attachedDocumentIds): bool
    {
        if ($question->type === QuestionnaireQuestionType::FILE_ATTACH) {
            return $attachedDocumentIds === [];
        }

        return $value === null || $value === [];
    }
}
