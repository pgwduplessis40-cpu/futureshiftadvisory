<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyQuestionType;
use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SurveyLibrary
{
    public const CLIENT_EXPERIENCE_KEY = 'client_experience';

    public const DEFAULT_VERSION = '1.0';

    public function ensureDefault(?User $creator = null): Survey
    {
        $survey = Survey::query()
            ->where('key', self::CLIENT_EXPERIENCE_KEY)
            ->where('version', self::DEFAULT_VERSION)
            ->first();

        if ($survey instanceof Survey) {
            return $survey->load('questions');
        }

        return DB::transaction(function () use ($creator): Survey {
            $survey = Survey::query()->create([
                'key' => self::CLIENT_EXPERIENCE_KEY,
                'version' => self::DEFAULT_VERSION,
                'title' => 'Client experience survey',
                'description' => 'Structured feedback on delivered advice and supporting material.',
                'status' => SurveyStatus::Draft->value,
                'settings' => [
                    'allow_free_text' => false,
                    'deliverable_anchor_types' => ['report', 'document', 'plan_assessment'],
                ],
                'created_by_user_id' => $creator?->getKey(),
            ]);

            $questions = [
                [
                    'order' => 1,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'overall_experience',
                    'prompt' => 'How would you rate your overall experience?',
                    'help_text' => null,
                    'options' => $this->likertOptions(),
                ],
                [
                    'order' => 2,
                    'type' => SurveyQuestionType::Nps->value,
                    'key' => 'recommendation',
                    'prompt' => 'How likely are you to recommend Future Shift Advisory?',
                    'help_text' => null,
                    'options' => [
                        'min' => 0,
                        'max' => 10,
                    ],
                ],
                [
                    'order' => 3,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'objectives_met',
                    'prompt' => 'How well did the engagement meet its objective?',
                    'help_text' => null,
                    'options' => $this->likertOptions(),
                ],
                [
                    'order' => 4,
                    'type' => SurveyQuestionType::AnchoredMatrix->value,
                    'key' => 'deliverable_feedback',
                    'prompt' => 'Please confirm each delivered item was received, accessible, and met its objective.',
                    'help_text' => null,
                    'options' => [
                        'answer_keys' => ['received', 'accessible', 'met_objective'],
                    ],
                ],
            ];

            foreach ($questions as $question) {
                $survey->questions()->create([
                    ...$question,
                    'required' => true,
                ]);
            }

            return $survey->load('questions');
        });
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function likertOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Very poor'],
            ['value' => 2, 'label' => 'Poor'],
            ['value' => 3, 'label' => 'Acceptable'],
            ['value' => 4, 'label' => 'Good'],
            ['value' => 5, 'label' => 'Excellent'],
        ];
    }
}
