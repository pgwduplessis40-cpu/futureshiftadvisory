<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoTiritiMode;
use App\Models\Client;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class NpoHealthScorer
{
    public const DIMENSION_MISSION_STRATEGY = 1;

    public const DIMENSION_SERVICE_OPERATIONS = 2;

    public const DIMENSION_GOVERNANCE_COMPLIANCE = 3;

    public const DIMENSION_FINANCIAL_SUSTAINABILITY = 4;

    public const DIMENSION_PEOPLE_CAPABILITY = 5;

    public const DIMENSION_IMPACT_MEASUREMENT = 6;

    public const DIMENSION_FUNDING_RESILIENCE = 7;

    public const DIMENSION_TE_TIRITI = 8;

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<int|string, int|float|string>  $scores
     * @param  array<int|string, array<int, array<string, mixed>>>  $findings
     * @return EloquentCollection<int, NpoDimensionScore>
     */
    public function recordAssessment(
        NpoEngagement $engagement,
        array $scores,
        array $findings = [],
        ?User $actor = null,
        ?CarbonInterface $capturedAt = null,
    ): EloquentCollection {
        $this->assertFullEngagement($engagement);

        $capturedAt ??= now();
        $mode = $this->modeFor($engagement);
        $definitions = $this->dimensionsForMode($mode);
        $batchId = (string) Str::uuid();
        $rows = [];
        $healthScore = 0.0;

        foreach ($definitions as $definition) {
            $score = $this->scoreFor($scores, $definition);
            $weightedScore = $this->weightedScore($score, $definition['weight']);
            $healthScore += $weightedScore;
            $dimensionFindings = $this->findingsFor($findings, $definition);

            $rows[] = [
                'client_id' => $engagement->client_id,
                'npo_engagement_id' => $engagement->getKey(),
                'assessment_batch_id' => $batchId,
                'dimension_number' => $definition['number'],
                'dimension_key' => $definition['key'],
                'dimension_label' => $definition['label'],
                'tiriti_mode' => $mode->value,
                'score' => $score,
                'advisor_weight' => $definition['weight'],
                'weighted_score' => $weightedScore,
                'health_score' => null,
                'findings' => $dimensionFindings,
                'mode_b_criteria_contributions' => $definition['mode_b_contributions'],
                'source_attributions' => $this->sourceAttributions($dimensionFindings),
                'scoring_context' => $this->scoringContext($engagement),
                'source' => NpoDimensionScore::SOURCE_ADVISOR_ASSESSMENT,
                'source_npo_engagement_id' => null,
                'captured_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];
        }

        $aggregate = max(0, min(100, (int) round($healthScore)));

        return DB::transaction(function () use ($engagement, $batchId, $rows, $aggregate, $actor): EloquentCollection {
            foreach ($rows as $row) {
                NpoDimensionScore::query()->create([
                    ...$row,
                    'health_score' => $aggregate,
                ]);
            }

            $scores = $this->batch($engagement, $batchId);

            $this->audit->record('npo.dimension_scores.assessed', subject: $engagement, actor: $actor, after: [
                'assessment_batch_id' => $batchId,
                'health_score' => $aggregate,
                'dimensions' => $scores->pluck('dimension_number')->values()->all(),
                'tiriti_mode' => $scores->first()?->tiriti_mode?->value,
            ]);

            return $scores;
        });
    }

    public function prepopulateGovernanceDimension(NpoEngagement $engagement, ?User $actor = null): ?NpoDimensionScore
    {
        $this->assertFullEngagement($engagement);

        if ($engagement->converted_from_npo_engagement_id === null) {
            return null;
        }

        $existing = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('dimension_number', self::DIMENSION_GOVERNANCE_COMPLIANCE)
            ->where('source', NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION)
            ->where('source_npo_engagement_id', $engagement->converted_from_npo_engagement_id)
            ->first();

        if ($existing instanceof NpoDimensionScore) {
            return $existing;
        }

        $sourceFindings = GovernanceReviewFinding::query()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->converted_from_npo_engagement_id)
            ->where('status', GovernanceReviewFinding::STATUS_REVIEWED)
            ->orderBy('category')
            ->orderBy('finding_key')
            ->get();

        if ($sourceFindings->isEmpty()) {
            return null;
        }

        $mode = $this->modeFor($engagement);
        $definition = collect($this->dimensionsForMode($mode))
            ->first(fn (array $dimension): bool => $dimension['number'] === self::DIMENSION_GOVERNANCE_COMPLIANCE);

        if (! is_array($definition)) {
            return null;
        }

        $capturedAt = now();
        $findings = $sourceFindings
            ->map(fn (GovernanceReviewFinding $finding): array => $this->governanceFindingPayload($finding))
            ->values()
            ->all();
        $score = $this->scoreFromGovernanceFindings($sourceFindings);
        $weightedScore = $this->weightedScore($score, $definition['weight']);
        $batchId = (string) Str::uuid();

        $scoreRow = NpoDimensionScore::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->getKey(),
            'assessment_batch_id' => $batchId,
            'dimension_number' => $definition['number'],
            'dimension_key' => $definition['key'],
            'dimension_label' => $definition['label'],
            'tiriti_mode' => $mode->value,
            'score' => $score,
            'advisor_weight' => $definition['weight'],
            'weighted_score' => $weightedScore,
            'health_score' => null,
            'findings' => $findings,
            'mode_b_criteria_contributions' => $definition['mode_b_contributions'],
            'source_attributions' => $this->sourceAttributions($findings),
            'scoring_context' => [
                ...$this->scoringContext($engagement),
                'prepopulation' => [
                    'source_npo_engagement_id' => $engagement->converted_from_npo_engagement_id,
                    'finding_count' => count($findings),
                ],
            ],
            'source' => NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION,
            'source_npo_engagement_id' => $engagement->converted_from_npo_engagement_id,
            'captured_at' => $capturedAt,
        ]);

        $this->audit->record('npo.dimension_3.prepopulated_from_governance_review', subject: $engagement, actor: $actor, after: [
            'npo_dimension_score_id' => $scoreRow->getKey(),
            'source_npo_engagement_id' => $engagement->converted_from_npo_engagement_id,
            'finding_count' => count($findings),
            'score' => $score,
        ]);

        return $scoreRow->refresh();
    }

    public function backfillGovernanceDimension(?User $actor = null): int
    {
        $count = 0;

        NpoEngagement::query()
            ->whereNotNull('converted_from_npo_engagement_id')
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->orderBy('id')
            ->each(function (NpoEngagement $engagement) use ($actor, &$count): void {
                $hadPrepopulation = NpoDimensionScore::query()
                    ->where('npo_engagement_id', $engagement->getKey())
                    ->where('dimension_number', self::DIMENSION_GOVERNANCE_COMPLIANCE)
                    ->where('source', NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION)
                    ->where('source_npo_engagement_id', $engagement->converted_from_npo_engagement_id)
                    ->exists();
                $created = $this->prepopulateGovernanceDimension($engagement, $actor);

                if (! $hadPrepopulation && $created instanceof NpoDimensionScore) {
                    $count++;
                }
            });

        return $count;
    }

    public function recomputeHistoricalForWeightingChange(NpoEngagement $engagement, ?User $actor = null): int
    {
        $this->assertFullEngagement($engagement);

        $batches = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->select('assessment_batch_id')
            ->distinct()
            ->pluck('assessment_batch_id')
            ->map(fn (mixed $id): string => (string) $id);

        $updated = 0;

        foreach ($batches as $batchId) {
            $rows = $this->batch($engagement, $batchId);
            $healthScore = (int) round($rows->sum(fn (NpoDimensionScore $score): float => $this->weightedScore($score->score, $score->advisor_weight)));

            foreach ($rows as $row) {
                $row->forceFill([
                    'weighted_score' => $this->weightedScore($row->score, $row->advisor_weight),
                    'health_score' => $rows->count() > 1 ? max(0, min(100, $healthScore)) : $row->health_score,
                    'scoring_context' => [
                        ...($row->scoring_context ?? []),
                        'social_weighting' => $this->scoringContext($engagement)['social_weighting'],
                        'last_weighting_recomputed_at' => now()->toIso8601String(),
                    ],
                ])->save();

                $updated++;
            }
        }

        if ($updated > 0) {
            $this->audit->record('npo.dimension_scores.recomputed_for_weighting_change', subject: $engagement, actor: $actor, after: [
                'batch_count' => $batches->count(),
                'row_count' => $updated,
                'social_weighting' => $this->scoringContext($engagement)['social_weighting'],
            ]);
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();

        return $engagement instanceof NpoEngagement ? $this->summary($engagement) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summary(NpoEngagement $engagement): ?array
    {
        $batch = $this->latestBatch($engagement);

        if ($batch === null) {
            return [
                'npo_engagement_id' => $engagement->id,
                'health_score' => null,
                'captured_at' => null,
                'tiriti_mode' => $this->modeFor($engagement)->value,
                'axes' => $this->emptyAxes($engagement),
                'findings' => [],
            ];
        }

        return [
            'npo_engagement_id' => $engagement->id,
            'health_score' => $batch->first()?->health_score,
            'captured_at' => $batch->first()?->captured_at?->toIso8601String(),
            'tiriti_mode' => $batch->first()?->tiriti_mode?->value,
            'axes' => $batch
                ->map(fn (NpoDimensionScore $row): array => $this->axisPayload($row))
                ->values()
                ->all(),
            'findings' => $batch
                ->map(fn (NpoDimensionScore $row): array => [
                    'dimension' => $row->dimension_key,
                    'label' => $row->dimension_label,
                    'findings' => $row->findings ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return EloquentCollection<int, NpoDimensionScore>|null
     */
    private function latestBatch(NpoEngagement $engagement): ?EloquentCollection
    {
        $batchId = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->select('assessment_batch_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('assessment_batch_id')
            ->value('assessment_batch_id');

        return is_string($batchId) ? $this->batch($engagement, $batchId) : null;
    }

    /**
     * @return EloquentCollection<int, NpoDimensionScore>
     */
    private function batch(NpoEngagement $engagement, string $batchId): EloquentCollection
    {
        return NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('assessment_batch_id', $batchId)
            ->orderBy('dimension_number')
            ->get();
    }

    /**
     * @return array<int, array{number:int, key:string, label:string, weight:int, mode_b_contributions:array<int, string>|null}>
     */
    public function dimensionsForMode(NpoTiritiMode $mode): array
    {
        $dimensions = [
            self::DIMENSION_MISSION_STRATEGY => ['key' => 'mission_strategy', 'label' => 'Mission and strategy'],
            self::DIMENSION_SERVICE_OPERATIONS => ['key' => 'service_operations', 'label' => 'Service delivery and operations'],
            self::DIMENSION_GOVERNANCE_COMPLIANCE => ['key' => 'governance_compliance', 'label' => 'Governance and compliance'],
            self::DIMENSION_FINANCIAL_SUSTAINABILITY => ['key' => 'financial_sustainability', 'label' => 'Financial sustainability'],
            self::DIMENSION_PEOPLE_CAPABILITY => ['key' => 'people_capability', 'label' => 'People and capability'],
            self::DIMENSION_IMPACT_MEASUREMENT => ['key' => 'impact_measurement', 'label' => 'Impact measurement'],
            self::DIMENSION_FUNDING_RESILIENCE => ['key' => 'funding_resilience', 'label' => 'Funding resilience'],
            self::DIMENSION_TE_TIRITI => ['key' => 'te_tiriti', 'label' => 'Te Tiriti'],
        ];

        $weights = $mode === NpoTiritiMode::Standalone
            ? [1 => 10, 2 => 10, 3 => 20, 4 => 15, 5 => 10, 6 => 10, 7 => 15, 8 => 10]
            : [1 => 12, 2 => 11, 3 => 22, 4 => 16, 5 => 11, 6 => 11, 7 => 17];
        $modeBContributions = $mode === NpoTiritiMode::Woven ? [
            self::DIMENSION_MISSION_STRATEGY => ['[TIRITI] purpose and partnership alignment'],
            self::DIMENSION_GOVERNANCE_COMPLIANCE => ['[TIRITI] governance obligations and board accountability'],
            self::DIMENSION_IMPACT_MEASUREMENT => ['[TIRITI] equity and outcomes evidence'],
            self::DIMENSION_FUNDING_RESILIENCE => ['[TIRITI] funder obligations and restricted funding impacts'],
        ] : [];

        return collect($weights)
            ->map(fn (int $weight, int $number): array => [
                'number' => $number,
                'key' => $dimensions[$number]['key'],
                'label' => $dimensions[$number]['label'],
                'weight' => $weight,
                'mode_b_contributions' => $modeBContributions[$number] ?? null,
            ])
            ->values()
            ->all();
    }

    private function modeFor(NpoEngagement $engagement): NpoTiritiMode
    {
        return $engagement->tiriti_mode ?? NpoTiritiMode::Woven;
    }

    private function assertFullEngagement(NpoEngagement $engagement): void
    {
        if (! in_array($engagement->sub_type, [NpoEngagementSubType::StandardNpo, NpoEngagementSubType::SocialEnterprise], true)) {
            throw new InvalidArgumentException('NPO health scoring requires a full NPO engagement.');
        }
    }

    /**
     * @param  array<int|string, int|float|string>  $scores
     * @param  array{number:int, key:string}  $definition
     */
    private function scoreFor(array $scores, array $definition): int
    {
        $value = $scores[$definition['number']] ?? $scores[$definition['key']] ?? null;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Missing score for NPO dimension {$definition['number']}.");
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    /**
     * @param  array<int|string, array<int, array<string, mixed>>>  $findings
     * @param  array{number:int, key:string}  $definition
     * @return array<int, array<string, mixed>>
     */
    private function findingsFor(array $findings, array $definition): array
    {
        return array_values($findings[$definition['number']] ?? $findings[$definition['key']] ?? []);
    }

    private function weightedScore(int $score, int $weight): float
    {
        return round($score * $weight / 100, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function sourceAttributions(array $findings): array
    {
        return collect($findings)
            ->flatMap(fn (array $finding): array => collect($finding['attributions'] ?? [])
                ->map(fn (mixed $attribution): array => is_array($attribution) ? $attribution : ['claim' => (string) $attribution])
                ->all())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function scoringContext(NpoEngagement $engagement): array
    {
        return [
            'social_weighting' => [
                'social_enterprise' => (bool) $engagement->social_enterprise,
                'social_enterprise_type' => $engagement->social_enterprise_type?->value,
                'commercial_weight' => $engagement->commercial_weight,
                'mission_weight' => $engagement->mission_weight,
            ],
        ];
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     */
    private function scoreFromGovernanceFindings(Collection $findings): int
    {
        $load = $findings->sum(fn (GovernanceReviewFinding $finding): int => $this->severityWeight($finding->severity));
        $loadCap = max(1, (int) config('dashboards.radar.load_cap', 30));

        return max(0, min(100, 100 - (int) round(100 * $load / $loadCap)));
    }

    private function severityWeight(FindingSeverity|string|null $severity): int
    {
        $key = $severity instanceof FindingSeverity ? $severity->value : (string) $severity;
        $weights = (array) config('dashboards.radar.severity_weights', []);

        return (int) ($weights[$key] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function governanceFindingPayload(GovernanceReviewFinding $finding): array
    {
        return [
            'id' => (string) $finding->getKey(),
            'finding_key' => $finding->finding_key,
            'category' => $finding->category,
            'severity' => $finding->severity->value,
            'title' => $finding->title,
            'body' => $finding->body,
            'attributions' => $finding->attributions ?? [],
            'source' => 'governance_review',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function axisPayload(NpoDimensionScore $row): array
    {
        $topFinding = collect($row->findings ?? [])->first();

        return [
            'dimension' => $row->dimension_key,
            'label' => $row->dimension_label,
            'score' => $row->score,
            'state' => 'scored',
            'message' => $row->source === NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION
                ? 'Dimension pre-populated from the Governance Review.'
                : 'Score reflects the latest NPO health assessment.',
            'trend' => null,
            'top_finding' => is_array($topFinding) ? $this->findingPayload($topFinding) : null,
            'contributing_finding_ids' => collect($row->findings ?? [])
                ->pluck('id')
                ->filter()
                ->values()
                ->all(),
            'module_run_states' => [],
            'drill_url' => '#section-npo-health',
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private function findingPayload(array $finding): array
    {
        return [
            'id' => (string) ($finding['id'] ?? Str::uuid()),
            'module' => 'npo_health',
            'lens' => 'diagnostic',
            'severity' => (string) ($finding['severity'] ?? 'info'),
            'title' => (string) ($finding['title'] ?? 'NPO health finding'),
            'body' => (string) ($finding['body'] ?? ''),
            'attributions' => $finding['attributions'] ?? [],
            'created_at' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emptyAxes(NpoEngagement $engagement): array
    {
        return collect($this->dimensionsForMode($this->modeFor($engagement)))
            ->map(fn (array $definition): array => [
                'dimension' => $definition['key'],
                'label' => $definition['label'],
                'score' => null,
                'state' => 'never_run',
                'message' => 'No NPO health assessment recorded yet.',
                'trend' => null,
                'top_finding' => null,
                'contributing_finding_ids' => [],
                'module_run_states' => [],
                'drill_url' => null,
            ])
            ->values()
            ->all();
    }
}
