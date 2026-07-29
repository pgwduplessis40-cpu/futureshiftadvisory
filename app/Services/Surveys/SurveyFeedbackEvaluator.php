<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Models\LearningUpdate;
use App\Models\SurveyResponse;
use App\Services\Learning\LayerCadenceRegistry;

final class SurveyFeedbackEvaluator
{
    public function evaluate(SurveyResponse $response): ?LearningUpdate
    {
        $response->loadMissing('survey', 'answers.question', 'assignment');

        $isServiceImprovement = $response->survey?->type === SurveyType::ServiceImprovement;

        $lowOverall = $response->overall_score !== null && (float) $response->overall_score < 60.0;
        $lowNps = $response->nps_score !== null && (int) $response->nps_score <= 6;
        $negativeAnchors = $response->answers
            ->whereNotNull('answer_key')
            ->filter(fn ($answer): bool => (int) ($answer->numeric_value ?? 1) === 0)
            ->count();
        $writtenFeedback = $response->answers
            ->filter(fn ($answer): bool => $answer->question?->type === SurveyQuestionType::Text)
            ->filter(fn ($answer): bool => is_string(data_get($answer->value, 'value')) && trim((string) data_get($answer->value, 'value')) !== '')
            ->count();

        if (! $lowOverall && ! $lowNps && $negativeAnchors === 0 && (! $isServiceImprovement || $writtenFeedback === 0)) {
            return null;
        }

        $signalKey = hash('sha256', 'survey_response|'.$response->getKey());
        $layerId = $isServiceImprovement
            ? LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION
            : LayerCadenceRegistry::LAYER_CLIENT_EXPERIENCE_SURVEY;
        $sourceType = $isServiceImprovement ? 'service_improvement_survey' : 'client_experience_survey';
        $serviceModule = $response->assignment?->service_activation_id !== null
            ? 'service_activation'
            : 'entrepreneur_service';

        $existing = LearningUpdate::query()
            ->where('layer_id', $layerId)
            ->where('source->type', $sourceType)
            ->where('source->signal_key', $signalKey)
            ->first();

        if ($existing instanceof LearningUpdate) {
            return $existing;
        }

        return LearningUpdate::query()->create([
            'layer_id' => $layerId,
            'source' => [
                'type' => $sourceType,
                'signal_key' => $signalKey,
                'survey_response_id' => $response->getKey(),
                'survey_id' => $response->survey_id,
                'survey_title' => $response->survey?->title,
                'submitted_at' => $response->submitted_at?->toIso8601String(),
                'service_activation_id' => $response->assignment?->service_activation_id,
                'service' => $response->assignment?->service_snapshot,
            ],
            'summary' => $isServiceImprovement
                ? 'Service improvement survey feedback is ready for review before changing the delivered service.'
                : 'Client experience survey feedback indicates follow-up may be needed on a delivered advisory item.',
            'proposed_change' => [
                'action' => $isServiceImprovement ? 'review_service_improvement_feedback' : 'review_client_experience_feedback',
                'automatic_application' => false,
                'survey_response_id' => $response->getKey(),
            ],
            'impact_scope' => [
                'module' => $isServiceImprovement ? $serviceModule : 'surveys',
                'client_id' => $response->client_id,
                'entrepreneur_profile_id' => $response->entrepreneur_profile_id,
                'service_activation_id' => $response->assignment?->service_activation_id,
            ],
            'clients_affected' => $response->client_id === null ? 0 : 1,
            'magnitude' => $response->overall_score !== null && (float) $response->overall_score < 40.0 ? 'medium' : 'low',
            'confidence' => $negativeAnchors > 0 ? 0.78 : ($isServiceImprovement ? 0.74 : 0.68),
            'evidence' => [
                'overall_score' => $response->overall_score,
                'nps_score' => $response->nps_score,
                'negative_anchor_count' => $negativeAnchors,
                'written_feedback_count' => $writtenFeedback,
                'answer_count' => $response->answers->count(),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
