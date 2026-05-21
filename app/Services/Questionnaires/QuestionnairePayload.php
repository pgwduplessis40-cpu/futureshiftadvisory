<?php

declare(strict_types=1);

namespace App\Services\Questionnaires;

use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;

final class QuestionnairePayload
{
    /**
     * @return array<string, mixed>
     */
    public function schema(Questionnaire $questionnaire): array
    {
        $questionnaire->loadMissing('sections.questions');

        return [
            'id' => $questionnaire->id,
            'set' => $questionnaire->set->value,
            'version' => $questionnaire->version,
            'title' => $questionnaire->title,
            'published_at' => $questionnaire->published_at?->toIso8601String(),
            'sections' => $questionnaire->sections->map(fn ($section): array => [
                'id' => $section->id,
                'order' => $section->order,
                'title' => $section->title,
                'help_text' => $section->help_text,
                'questions' => $section->questions->map(fn ($question): array => [
                    'id' => $question->id,
                    'order' => $question->order,
                    'type' => $question->type->value,
                    'prompt' => $question->prompt,
                    'help_text' => $question->help_text,
                    'options' => $question->options ?? [],
                    'conditional_logic' => $question->conditional_logic,
                    'required' => $question->required,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, array{value:mixed, attached_document_ids:array<int, string>}>
     */
    public function answers(?QuestionnaireResponse $response): array
    {
        if (! $response instanceof QuestionnaireResponse) {
            return [];
        }

        $response->loadMissing('answers');

        return $response->answers
            ->mapWithKeys(fn ($answer): array => [
                $answer->question_id => [
                    'value' => $answer->value,
                    'attached_document_ids' => $answer->attached_document_ids ?? [],
                ],
            ])
            ->all();
    }
}
