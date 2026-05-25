<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Enums\QuestionnaireQuestionType;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\Questionnaires\QuestionnaireRuleEngine;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class QuestionnaireCompletenessCalculator implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['data_quality.questionnaire_completeness'];
    }

    public function __construct(private readonly QuestionnaireRuleEngine $rules) {}

    /**
     * @param  EloquentCollection<int, QuestionnaireResponse>  $responses
     */
    public function calculate(EloquentCollection $responses): QuestionnaireCompletenessResult
    {
        $expected = 0;
        $answered = 0;

        foreach ($responses as $response) {
            $questionnaire = $response->questionnaire;

            if ($questionnaire === null) {
                continue;
            }

            $visibleQuestionIds = $this->visibleQuestionIds($response);
            $visibleMap = array_fill_keys($visibleQuestionIds, true);
            $answers = $response->answers->keyBy('question_id');

            $questions = $questionnaire->sections
                ->flatMap(fn ($section): Collection => $section->questions)
                ->filter(fn (QuestionnaireQuestion $question): bool => isset($visibleMap[(string) $question->getKey()]));

            $expected += $questions->count();

            foreach ($questions as $question) {
                $answer = $answers->get((string) $question->getKey());

                if ($answer instanceof QuestionnaireAnswer && $this->answerHasValue($question, $answer)) {
                    $answered++;
                }
            }
        }

        return new QuestionnaireCompletenessResult(
            answered: $answered,
            expected: $expected,
            score: $this->percent($answered, $expected),
        );
    }

    public function answerHasValue(QuestionnaireQuestion $question, QuestionnaireAnswer $answer): bool
    {
        if ($question->type === QuestionnaireQuestionType::FILE_ATTACH) {
            return $this->attachedDocumentIds($answer) !== [];
        }

        return ! $this->emptyValue($answer->value);
    }

    /**
     * @return array<int, string>
     */
    public function attachedDocumentIds(QuestionnaireAnswer $answer): array
    {
        $documentIds = $answer->attached_document_ids;

        if (! is_array($documentIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $documentId): string => trim((string) $documentId), $documentIds),
            static fn (string $documentId): bool => $documentId !== '',
        ));
    }

    /**
     * @return array<int, string>
     */
    private function visibleQuestionIds(QuestionnaireResponse $response): array
    {
        $questionnaire = $response->questionnaire;

        if ($questionnaire === null) {
            return [];
        }

        $answers = $response->answers
            ->mapWithKeys(fn (QuestionnaireAnswer $answer): array => [
                (string) $answer->question_id => [
                    'value' => $answer->value,
                    'attached_document_ids' => $this->attachedDocumentIds($answer),
                ],
            ])
            ->all();

        return $this->rules->visibleQuestionIds($questionnaire, $answers);
    }

    private function emptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            $flat = Arr::flatten($value);

            return $flat === [] || collect($flat)->every(fn (mixed $item): bool => $this->emptyValue($item));
        }

        return false;
    }

    private function percent(int $part, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($part / $total) * 100);
    }
}
