<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyType;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\SurveyAssignment;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Builder;

final class SurveyResultAggregator
{
    public function __construct(private readonly SurveyFeedbackThemes $themes) {}

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client, SurveyType $type = SurveyType::GeneralExperience): array
    {
        return $this->payload(
            SurveyAssignment::query()->where('client_id', $client->getKey())->whereHas('survey', fn (Builder $query) => $query->where('type', $type->value)),
            SurveyResponse::query()->where('client_id', $client->getKey())->whereHas('survey', fn (Builder $query) => $query->where('type', $type->value)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forEntrepreneur(EntrepreneurProfile $profile, SurveyType $type = SurveyType::GeneralExperience): array
    {
        return $this->payload(
            SurveyAssignment::query()->where('entrepreneur_profile_id', $profile->getKey())->whereHas('survey', fn (Builder $query) => $query->where('type', $type->value)),
            SurveyResponse::query()->where('entrepreneur_profile_id', $profile->getKey())->whereHas('survey', fn (Builder $query) => $query->where('type', $type->value)),
        );
    }

    /**
     * @param  Builder<SurveyAssignment>  $assignments
     * @param  Builder<SurveyResponse>  $responses
     * @return array<string, mixed>
     */
    private function payload(Builder $assignments, Builder $responses): array
    {
        $items = $assignments
            ->with(['survey', 'response.answers.question'])
            ->latest('activated_at')
            ->limit(50)
            ->get()
            ->map(fn (SurveyAssignment $assignment): array => [
                'id' => $assignment->id,
                'survey_title' => $assignment->survey?->title,
                'status' => $assignment->status?->value,
                'activated_at' => $assignment->activated_at?->toIso8601String(),
                'due_at' => $assignment->due_at?->toIso8601String(),
                'completed_at' => $assignment->completed_at?->toIso8601String(),
                'deliverable_count' => count($assignment->deliverable_snapshot ?? []),
                'response' => $assignment->response instanceof SurveyResponse ? [
                    'id' => $assignment->response->id,
                    'overall_score' => $assignment->response->overall_score,
                    'nps_score' => $assignment->response->nps_score,
                    'submitted_at' => $assignment->response->submitted_at?->toIso8601String(),
                    'themes' => $this->themes->forResponse($assignment->response),
                    'written_feedback' => $this->themes->writtenFeedback($assignment->response),
                    'feedback' => $assignment->response->answers
                        ->filter(fn ($answer): bool => is_string(data_get($answer->value, 'comment')) && trim((string) data_get($answer->value, 'comment')) !== '')
                        ->map(fn ($answer): array => [
                            'question' => $answer->question?->prompt ?? 'Rating feedback',
                            'score' => $answer->numeric_value,
                            'scale_max' => $answer->question?->type?->value === 'nps' ? 10 : 5,
                            'value' => (string) data_get($answer->value, 'comment'),
                        ])
                        ->values()
                        ->all(),
                ] : null,
            ])
            ->values()
            ->all();

        $completed = (clone $responses)
            ->whereNotNull('submitted_at')
            ->get(['overall_score', 'nps_score']);

        return [
            'summary' => [
                'assignments' => count($items),
                'completed' => $completed->count(),
                'average_score' => $completed->whereNotNull('overall_score')->avg('overall_score'),
                'average_nps' => $completed->whereNotNull('nps_score')->avg('nps_score'),
            ],
            'items' => $items,
        ];
    }
}
