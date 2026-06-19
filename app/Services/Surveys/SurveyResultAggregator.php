<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\SurveyAssignment;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Builder;

final class SurveyResultAggregator
{
    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        return $this->payload(
            SurveyAssignment::query()->where('client_id', $client->getKey()),
            SurveyResponse::query()->where('client_id', $client->getKey()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forEntrepreneur(EntrepreneurProfile $profile): array
    {
        return $this->payload(
            SurveyAssignment::query()->where('entrepreneur_profile_id', $profile->getKey()),
            SurveyResponse::query()->where('entrepreneur_profile_id', $profile->getKey()),
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
            ->with(['survey', 'response'])
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
