<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Models\LearningUpdate;
use App\Models\SurveyResponse;
use App\Services\Learning\LayerCadenceRegistry;

final class SurveyFeedbackEvaluator
{
    public function evaluate(SurveyResponse $response): ?LearningUpdate
    {
        $response->loadMissing('survey', 'answers');

        $lowOverall = $response->overall_score !== null && (float) $response->overall_score < 60.0;
        $lowNps = $response->nps_score !== null && (int) $response->nps_score <= 6;
        $negativeAnchors = $response->answers
            ->whereNotNull('answer_key')
            ->filter(fn ($answer): bool => (int) ($answer->numeric_value ?? 1) === 0)
            ->count();

        if (! $lowOverall && ! $lowNps && $negativeAnchors === 0) {
            return null;
        }

        $signalKey = hash('sha256', 'survey_response|'.$response->getKey());

        $existing = LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_CLIENT_EXPERIENCE_SURVEY)
            ->where('source->type', 'client_experience_survey')
            ->where('source->signal_key', $signalKey)
            ->first();

        if ($existing instanceof LearningUpdate) {
            return $existing;
        }

        return LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_CLIENT_EXPERIENCE_SURVEY,
            'source' => [
                'type' => 'client_experience_survey',
                'signal_key' => $signalKey,
                'survey_response_id' => $response->getKey(),
                'survey_id' => $response->survey_id,
                'survey_title' => $response->survey?->title,
                'submitted_at' => $response->submitted_at?->toIso8601String(),
            ],
            'summary' => 'Client experience survey feedback indicates follow-up may be needed on a delivered advisory item.',
            'proposed_change' => [
                'action' => 'review_client_experience_feedback',
                'automatic_application' => false,
                'survey_response_id' => $response->getKey(),
            ],
            'impact_scope' => [
                'module' => 'surveys',
                'client_id' => $response->client_id,
                'entrepreneur_profile_id' => $response->entrepreneur_profile_id,
            ],
            'clients_affected' => $response->client_id === null ? 0 : 1,
            'magnitude' => $response->overall_score !== null && (float) $response->overall_score < 40.0 ? 'medium' : 'low',
            'confidence' => $negativeAnchors > 0 ? 0.78 : 0.68,
            'evidence' => [
                'overall_score' => $response->overall_score,
                'nps_score' => $response->nps_score,
                'negative_anchor_count' => $negativeAnchors,
                'answer_count' => $response->answers->count(),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
