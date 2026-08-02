<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyQuestionType;
use App\Enums\SurveyStatus;
use App\Enums\SurveyType;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SurveyLibrary
{
    public const CLIENT_EXPERIENCE_KEY = 'client_experience';

    public const SERVICE_IMPROVEMENT_KEY = 'service_improvement';

    public const DEFAULT_VERSION = '1.0';

    public const SERVICE_IMPROVEMENT_VERSION = '1.1';

    public function ensureDefault(?User $creator = null): Survey
    {
        $survey = Survey::query()
            ->where('key', self::CLIENT_EXPERIENCE_KEY)
            ->where('version', self::DEFAULT_VERSION)
            ->where('type', SurveyType::GeneralExperience->value)
            ->first();

        if ($survey instanceof Survey) {
            return $survey->load('questions');
        }

        return DB::transaction(function () use ($creator): Survey {
            $survey = Survey::query()->create([
                'key' => self::CLIENT_EXPERIENCE_KEY,
                'version' => self::DEFAULT_VERSION,
                'type' => SurveyType::GeneralExperience->value,
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
                    'help_text' => 'Consider the full experience of receiving the advice and supporting material.',
                    'options' => $this->likertOptions(),
                ],
                [
                    'order' => 2,
                    'type' => SurveyQuestionType::Nps->value,
                    'key' => 'recommendation',
                    'prompt' => 'How likely are you to recommend Future Shift Advisory?',
                    'help_text' => 'Consider whether you would recommend Future Shift Advisory based on this experience.',
                    'options' => [
                        'min' => 0,
                        'max' => 10,
                        'min_label' => 'Not at all likely',
                        'max_label' => 'Extremely likely',
                    ],
                ],
                [
                    'order' => 3,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'objectives_met',
                    'prompt' => 'How well did the engagement meet its objective?',
                    'help_text' => 'Consider how well the work addressed the objective agreed for this engagement.',
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

    public function ensureServiceImprovement(?User $creator = null): Survey
    {
        $survey = Survey::query()
            ->where('key', self::SERVICE_IMPROVEMENT_KEY)
            ->where('version', self::SERVICE_IMPROVEMENT_VERSION)
            ->where('type', SurveyType::ServiceImprovement->value)
            ->first();

        if ($survey instanceof Survey) {
            return $survey->load('questions');
        }

        return DB::transaction(function () use ($creator): Survey {
            $survey = Survey::query()->create([
                'key' => self::SERVICE_IMPROVEMENT_KEY,
                'version' => self::SERVICE_IMPROVEMENT_VERSION,
                'type' => SurveyType::ServiceImprovement->value,
                'title' => 'Service improvement survey',
                'description' => 'Feedback on a completed Future Shift Advisory service to guide continuous improvement.',
                'status' => SurveyStatus::Published->value,
                'settings' => [
                    'allow_free_text' => true,
                    'service_activation_required' => true,
                ],
                'created_by_user_id' => $creator?->getKey(),
                'published_by_user_id' => $creator?->getKey(),
                'published_at' => now(),
            ]);

            $questions = [
                [
                    'order' => 1,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'service_fit',
                    'prompt' => 'How well did this service meet the need you engaged us for?',
                    'help_text' => 'Consider how well this completed service addressed the need you engaged us for.',
                    'options' => $this->likertOptions(),
                    'required' => true,
                ],
                [
                    'order' => 2,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'practical_value',
                    'prompt' => 'How useful was the advice or output in helping you move forward?',
                    'help_text' => 'Consider how useful the advice or output has been in helping you take the next practical step.',
                    'options' => $this->likertOptions(),
                    'required' => true,
                ],
                [
                    'order' => 3,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'process_clarity',
                    'prompt' => 'How clear were the process, expectations, and next steps?',
                    'help_text' => 'Consider the clarity of the process, expectations, and next steps throughout the service.',
                    'options' => $this->likertOptions(),
                    'required' => true,
                ],
                [
                    'order' => 4,
                    'type' => SurveyQuestionType::Likert->value,
                    'key' => 'timeliness',
                    'prompt' => 'How well did the timing of the service work for you?',
                    'help_text' => 'Consider whether the timing and pace of the service worked for you.',
                    'options' => $this->likertOptions(),
                    'required' => true,
                ],
                [
                    'order' => 5,
                    'type' => SurveyQuestionType::Text->value,
                    'key' => 'most_valuable',
                    'prompt' => 'What was the most valuable part of this service?',
                    'help_text' => null,
                    'options' => null,
                    'required' => false,
                ],
                [
                    'order' => 6,
                    'type' => SurveyQuestionType::Text->value,
                    'key' => 'improve_next_time',
                    'prompt' => 'What is the one thing we should improve next time?',
                    'help_text' => 'Please be as specific as you can.',
                    'options' => null,
                    'required' => true,
                ],
                [
                    'order' => 7,
                    'type' => SurveyQuestionType::Text->value,
                    'key' => 'missing_or_unclear',
                    'prompt' => 'Was anything missing, unclear, or harder than it needed to be?',
                    'help_text' => null,
                    'options' => null,
                    'required' => false,
                ],
                [
                    'order' => 8,
                    'type' => SurveyQuestionType::Nps->value,
                    'key' => 'recommend_service',
                    'prompt' => 'How likely are you to recommend this service to another business?',
                    'help_text' => 'Consider whether you would recommend this specific service to another business.',
                    'options' => [
                        'min' => 0,
                        'max' => 10,
                        'min_label' => 'Not at all likely',
                        'max_label' => 'Extremely likely',
                    ],
                    'required' => true,
                ],
            ];

            foreach ($questions as $question) {
                $survey->questions()->create($question);
            }

            Survey::query()
                ->where('key', self::SERVICE_IMPROVEMENT_KEY)
                ->where('version', self::DEFAULT_VERSION)
                ->where('type', SurveyType::ServiceImprovement->value)
                ->where('status', SurveyStatus::Published->value)
                ->update([
                    'status' => SurveyStatus::Archived->value,
                    'archived_at' => now(),
                    'updated_at' => now(),
                ]);

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
