<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyQuestionType;
use App\Models\SurveyResponse;

final class SurveyFeedbackThemes
{
    /**
     * @return array<int, array{key:string,label:string}>
     */
    public function forResponse(SurveyResponse $response): array
    {
        return $this->forText($this->freeTextValues($response));
    }

    /**
     * @return array<int, array{question:string,value:string,score:float|int|null,scale_max:int|null,type:string}>
     */
    public function writtenFeedback(SurveyResponse $response): array
    {
        $response->loadMissing('answers.question');

        return $response->answers
            ->flatMap(function ($answer): array {
                $question = $answer->question;
                if ($question === null) {
                    return [];
                }

                if ($question->type === SurveyQuestionType::Text) {
                    $value = trim((string) data_get($answer->value, 'value', ''));

                    return $value === '' ? [] : [[
                        'question' => $question->prompt,
                        'value' => $value,
                        'score' => null,
                        'scale_max' => null,
                        'type' => 'written_answer',
                    ]];
                }

                if (! in_array($question->type, [SurveyQuestionType::Likert, SurveyQuestionType::Nps], true)) {
                    return [];
                }

                $comment = trim((string) data_get($answer->value, 'comment', ''));

                return $comment === '' ? [] : [[
                    'question' => $question->prompt,
                    'value' => $comment,
                    'score' => $answer->numeric_value,
                    'scale_max' => $question->type === SurveyQuestionType::Nps ? 10 : 5,
                    'type' => 'rating_comment',
                ]];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array{key:string,label:string}>
     */
    public function forText(array $texts): array
    {
        $normalised = strtolower(implode(' ', $texts));

        return collect($this->themeDefinitions())
            ->filter(fn (array $theme): bool => collect($theme['needles'])
                ->contains(fn (string $needle): bool => str_contains($normalised, $needle)))
            ->map(fn (array $theme): array => [
                'key' => $theme['key'],
                'label' => $theme['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function freeTextValues(SurveyResponse $response): array
    {
        return collect($this->writtenFeedback($response))
            ->pluck('value')
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,label:string,needles:array<int,string>}>
     */
    private function themeDefinitions(): array
    {
        return [
            [
                'key' => 'plain_language',
                'label' => 'Plain-language/jargon reduction',
                'needles' => ['jargon', 'plain english', 'plain language', 'break it down', 'understand', 'confusing language'],
            ],
            [
                'key' => 'process_clarity',
                'label' => 'Process, expectations, and next-step clarity',
                'needles' => ['next step', 'next steps', 'needed next', 'process', 'expectation', 'what is needed', 'what was needed', "what's needed", 'complete it', 'stage'],
            ],
            [
                'key' => 'timing_pace',
                'label' => 'Timing, pace, and response expectations',
                'needles' => ['timing', 'pace', 'hear back', 'response time', 'time to hear', 'feedback timing'],
            ],
            [
                'key' => 'human_support',
                'label' => 'Advisor-led human support',
                'needles' => ['human element', 'advisor', 'conversation', 'handholding', 'not just ai', 'ai handholding'],
            ],
            [
                'key' => 'survey_reliability',
                'label' => 'Survey reliability and recovery',
                'needles' => ['could not submit', 'submit issue', 'lost my answer', 'retry', 'system issue', 'error submitting'],
            ],
        ];
    }
}
