<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoEngagementSubType;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SocialEnterpriseAssessment
{
    public function __construct(
        private readonly BusinessHealthRadarBuilder $businessHealth,
        private readonly NpoHealthScorer $npoHealth,
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    public function score(NpoEngagement $engagement, ?User $actor = null): NpoSocialEnterpriseScorecard
    {
        $this->assertSocialEnterprise($engagement);

        $client = $engagement->client()->firstOrFail();
        $commercialAxes = $this->businessHealth->portalPayload($client)['axes'] ?? [];
        $commercialScore = $this->averageScore($commercialAxes, 'commercial score');
        $missionSummary = $this->npoHealth->summary($engagement);
        $missionScore = is_numeric($missionSummary['health_score'] ?? null)
            ? (int) $missionSummary['health_score']
            : throw new InvalidArgumentException('Social enterprise scorecard requires an NPO health score.');
        [$commercialWeight, $missionWeight] = $this->weights($engagement);
        $blended = round((($commercialScore * $commercialWeight) + ($missionScore * $missionWeight)) / 100, 2);

        return DB::transaction(function () use ($engagement, $client, $commercialAxes, $missionSummary, $commercialScore, $missionScore, $commercialWeight, $missionWeight, $blended, $actor): NpoSocialEnterpriseScorecard {
            /** @var NpoSocialEnterpriseScorecard $scorecard */
            $scorecard = NpoSocialEnterpriseScorecard::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'commercial_score' => $commercialScore,
                'mission_score' => $missionScore,
                'commercial_weight' => $commercialWeight,
                'mission_weight' => $missionWeight,
                'blended_score' => $blended,
                'commercial_axes' => $commercialAxes,
                'mission_axes' => $missionSummary['axes'] ?? [],
                'source_attributions' => [
                    ['claim' => 'Commercial score came from the latest business-health radar batch.', 'source_reference' => 'business_health_snapshots:'.$client->getKey()],
                    ['claim' => 'Mission score came from the latest NPO health assessment.', 'source_reference' => 'npo_dimension_scores:'.$engagement->getKey()],
                ],
                'calculated_at' => now(),
            ]);

            $this->audit->record('npo.social_enterprise_scorecard.created', subject: $scorecard, actor: $actor, after: [
                'commercial_score' => $commercialScore,
                'mission_score' => $missionScore,
                'commercial_weight' => $commercialWeight,
                'mission_weight' => $missionWeight,
                'blended_score' => $blended,
            ]);

            return $scorecard->refresh();
        });
    }

    public function analyseTensions(NpoEngagement $engagement, ?User $actor = null): NpoTensionAnalysis
    {
        $this->assertSocialEnterprise($engagement);

        $scorecard = $this->latestScorecard($engagement) ?? $this->score($engagement, $actor);
        $dataPoints = $this->dataPoints($scorecard);
        $prompt = new PromptEnvelope(
            id: 'npo.social_enterprise_tension_analysis',
            version: '1.0',
            task: 'Identify 1 to 5 evidence-backed social enterprise tensions. Use only the supplied data points.',
            body: 'Return tensions with type, title, commercial_implication, mission_implication, strategic_options, advisor_recommended_path, and data_points. Allowed types: '.implode(', ', NpoTensionAnalysis::allowedTypes()).'.',
            input: [
                'scorecard' => $this->scorecardPayload($scorecard),
                'data_points' => $dataPoints,
                'allowed_types' => NpoTensionAnalysis::allowedTypes(),
            ],
            sourceReferences: collect($dataPoints)->pluck('source_reference')->filter()->values()->all(),
        );
        $response = $this->ai->analyse($prompt);
        $tensions = $this->normaliseTensions($response->metadata['tensions'] ?? [], $dataPoints);

        return DB::transaction(function () use ($scorecard, $response, $tensions, $actor): NpoTensionAnalysis {
            /** @var NpoTensionAnalysis $analysis */
            $analysis = NpoTensionAnalysis::query()->create([
                'client_id' => $scorecard->client_id,
                'npo_engagement_id' => $scorecard->npo_engagement_id,
                'npo_social_enterprise_scorecard_id' => $scorecard->getKey(),
                'review_status' => NpoTensionAnalysis::REVIEW_PENDING,
                'tensions' => $tensions,
                'ai_response' => $response->toArray(),
                'source_attributions' => $response->attributions,
                'generated_at' => now(),
            ]);

            $this->audit->record('npo.social_enterprise_tension_analysis.created', subject: $analysis, actor: $actor, after: [
                'tension_count' => count($tensions),
                'review_status' => NpoTensionAnalysis::REVIEW_PENDING,
                'model' => $response->model,
            ]);

            return $analysis->refresh();
        });
    }

    public function markTensionAnalysisReviewed(NpoTensionAnalysis $analysis, User $actor): NpoTensionAnalysis
    {
        $analysis->forceFill([
            'review_status' => NpoTensionAnalysis::REVIEW_REVIEWED,
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $this->audit->record('npo.social_enterprise_tension_analysis.reviewed', subject: $analysis, actor: $actor, after: [
            'review_status' => NpoTensionAnalysis::REVIEW_REVIEWED,
            'reviewed_by_user_id' => $actor->getKey(),
        ]);

        return $analysis->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->where('sub_type', NpoEngagementSubType::SocialEnterprise->value)
            ->latest()
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            return null;
        }

        $scorecard = $this->latestScorecard($engagement);
        if (! $scorecard instanceof NpoSocialEnterpriseScorecard) {
            return null;
        }

        $analysis = NpoTensionAnalysis::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('generated_at')
            ->first();

        return [
            'scorecard' => $this->scorecardPayload($scorecard),
            'tension_analysis' => $analysis instanceof NpoTensionAnalysis
                ? $this->tensionPayload($analysis)
                : null,
        ];
    }

    private function latestScorecard(NpoEngagement $engagement): ?NpoSocialEnterpriseScorecard
    {
        return NpoSocialEnterpriseScorecard::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('calculated_at')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $axes
     */
    private function averageScore(array $axes, string $label): int
    {
        $scores = collect($axes)
            ->pluck('score')
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): float => (float) $score);

        if ($scores->isEmpty()) {
            throw new InvalidArgumentException("Social enterprise scorecard requires a {$label}.");
        }

        return (int) round($scores->avg());
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function weights(NpoEngagement $engagement): array
    {
        $commercial = $engagement->commercial_weight
            ?? $engagement->social_enterprise_type?->commercialWeight()
            ?? 50;
        $mission = $engagement->mission_weight
            ?? $engagement->social_enterprise_type?->missionWeight()
            ?? (100 - $commercial);

        if (($commercial + $mission) !== 100) {
            throw new InvalidArgumentException('Social enterprise commercial and mission weights must sum to 100.');
        }

        return [$commercial, $mission];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataPoints(NpoSocialEnterpriseScorecard $scorecard): array
    {
        $commercialLow = collect($scorecard->commercial_axes)->sortBy('score')->first();
        $missionLow = collect($scorecard->mission_axes)->sortBy('score')->first();

        return array_values(array_filter([
            [
                'key' => 'commercial_score',
                'label' => 'Commercial score',
                'value' => $scorecard->commercial_score,
                'source_reference' => 'npo_social_enterprise_scorecard:'.$scorecard->getKey().':commercial',
            ],
            [
                'key' => 'mission_score',
                'label' => 'Mission score',
                'value' => $scorecard->mission_score,
                'source_reference' => 'npo_social_enterprise_scorecard:'.$scorecard->getKey().':mission',
            ],
            [
                'key' => 'blended_score',
                'label' => 'Blended score',
                'value' => $scorecard->blended_score,
                'source_reference' => 'npo_social_enterprise_scorecard:'.$scorecard->getKey().':blended',
            ],
            is_array($commercialLow) ? [
                'key' => 'lowest_commercial_axis',
                'label' => 'Lowest commercial axis',
                'value' => ($commercialLow['label'] ?? $commercialLow['dimension'] ?? 'Commercial axis').' '.($commercialLow['score'] ?? 'n/a'),
                'source_reference' => 'business_health_snapshots:'.$scorecard->client_id,
            ] : null,
            is_array($missionLow) ? [
                'key' => 'lowest_mission_axis',
                'label' => 'Lowest mission axis',
                'value' => ($missionLow['label'] ?? $missionLow['dimension'] ?? 'Mission axis').' '.($missionLow['score'] ?? 'n/a'),
                'source_reference' => 'npo_dimension_scores:'.$scorecard->npo_engagement_id,
            ] : null,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $dataPoints
     * @return array<int, array<string, mixed>>
     */
    private function normaliseTensions(mixed $raw, array $dataPoints): array
    {
        $rows = is_array($raw) && $raw !== [] ? array_values($raw) : [$this->fallbackTension($dataPoints)];

        if (count($rows) < 1 || count($rows) > 5) {
            throw new InvalidArgumentException('Social enterprise tension analysis requires between 1 and 5 tensions.');
        }

        return collect($rows)
            ->map(function (mixed $row, int $index) use ($dataPoints): array {
                $row = is_array($row) ? $row : [];
                $points = is_array($row['data_points'] ?? null) && $row['data_points'] !== []
                    ? array_values($row['data_points'])
                    : [];

                return [
                    'type' => in_array((string) ($row['type'] ?? ''), NpoTensionAnalysis::allowedTypes(), true)
                        ? (string) $row['type']
                        : NpoTensionAnalysis::TYPE_KPI_MISALIGNMENT,
                    'title' => (string) ($row['title'] ?? 'Social enterprise tension '.($index + 1)),
                    'commercial_implication' => (string) ($row['commercial_implication'] ?? 'Commercial impact requires advisor review.'),
                    'mission_implication' => (string) ($row['mission_implication'] ?? 'Mission impact requires advisor review.'),
                    'strategic_options' => array_values((array) ($row['strategic_options'] ?? ['Review operating model trade-offs.'])),
                    'advisor_recommended_path' => (string) ($row['advisor_recommended_path'] ?? 'Discuss the trade-off with the board before implementation.'),
                    'data_points' => $points !== [] ? $points : array_slice($dataPoints, 0, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $dataPoints
     * @return array<string, mixed>
     */
    private function fallbackTension(array $dataPoints): array
    {
        return [
            'type' => NpoTensionAnalysis::TYPE_KPI_MISALIGNMENT,
            'title' => 'Commercial and mission KPI alignment',
            'commercial_implication' => 'Commercial performance and mission performance need to be managed as a paired decision.',
            'mission_implication' => 'Mission commitments should stay visible when commercial levers are adjusted.',
            'strategic_options' => ['Track commercial and mission KPIs together', 'Set board review thresholds before scaling'],
            'advisor_recommended_path' => 'Use the dual scorecard as the board-level decision frame.',
            'data_points' => array_slice($dataPoints, 0, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scorecardPayload(NpoSocialEnterpriseScorecard $scorecard): array
    {
        return [
            'id' => $scorecard->id,
            'commercial_score' => $scorecard->commercial_score,
            'mission_score' => $scorecard->mission_score,
            'commercial_weight' => $scorecard->commercial_weight,
            'mission_weight' => $scorecard->mission_weight,
            'blended_score' => $scorecard->blended_score,
            'commercial_axes' => $scorecard->commercial_axes,
            'mission_axes' => $scorecard->mission_axes,
            'calculated_at' => $scorecard->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tensionPayload(NpoTensionAnalysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'review_status' => $analysis->review_status,
            'reviewed_at' => $analysis->reviewed_at?->toIso8601String(),
            'is_releasable' => $analysis->reviewed(),
            'tensions' => $analysis->tensions,
            'generated_at' => $analysis->generated_at?->toIso8601String(),
        ];
    }

    private function assertSocialEnterprise(NpoEngagement $engagement): void
    {
        if ($engagement->sub_type !== NpoEngagementSubType::SocialEnterprise || ! $engagement->social_enterprise) {
            throw new InvalidArgumentException('Social enterprise assessment requires a social-enterprise NPO engagement.');
        }
    }
}
