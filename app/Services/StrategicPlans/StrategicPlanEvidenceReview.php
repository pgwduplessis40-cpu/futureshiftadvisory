<?php

declare(strict_types=1);

namespace App\Services\StrategicPlans;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Models\AnalysisFinding;
use App\Models\StrategicBudget;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Records the evidence a strategic plan was based on and turns material
 * source changes or delivery variance into concise, reviewable signals.
 * Client narratives stay on their source records; this class stores hashes,
 * identifiers and advisor-entered connection rationale only.
 *
 * @phpstan-type EvidenceSource array{key:string,category:string,label:string,advisor_label:string,materiality:'low'|'medium'|'high',hash:string}
 * @phpstan-type EvidenceBinding array{source_key:string,target_key:string,disposition:'supports'|'out_of_scope',rationale:string,source_hash:string,confirmed_at:string}
 * @phpstan-type Finding array{category:string,severity:'missing'|'review',message:string,next_action:string,materiality:'low'|'medium'|'high'}
 * @phpstan-type EvidenceBindingInput array{source_key?: string, target_key?: string, disposition?: string, rationale?: string}
 * @phpstan-type EvidencePayload array<array-key, mixed>
 */
final class StrategicPlanEvidenceReview
{
    public const OUTCOME_VARIANCE_THRESHOLD = 0.20;

    /**
     * @return list<array{key:string,category:string,label:string,materiality:'low'|'medium'|'high'}>
     */
    public function sourcesForAdvisor(StrategicPlan $plan): array
    {
        return collect($this->sources($plan))
            ->map(fn (array $source): array => [
                'key' => $source['key'],
                'category' => $source['category'],
                'label' => $source['advisor_label'],
                'materiality' => $source['materiality'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key:string,category:string,hash:string,captured_at:string}>
     */
    public function captureSourceSnapshots(StrategicPlan $plan): array
    {
        return collect($this->sources($plan))
            ->map(fn (array $source): array => [
                'key' => $source['key'],
                'category' => $source['category'],
                'hash' => $source['hash'],
                'captured_at' => now()->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<EvidenceBindingInput>  $input
     * @return list<EvidenceBinding>
     */
    public function normaliseBindings(StrategicPlan $plan, array $input, bool $confirmCurrentSources): array
    {
        $sources = collect($this->sources($plan))->keyBy('key');
        $targets = $this->targets($plan);
        $existing = collect((array) ($plan->evidence_bindings ?? []))
            ->filter(fn (mixed $binding): bool => is_array($binding))
            ->keyBy(fn (array $binding): string => $this->bindingKey(
                (string) ($binding['source_key'] ?? ''),
                (string) ($binding['target_key'] ?? ''),
            ));

        return collect($input)
            ->map(function (array $binding) use ($sources, $targets, $existing, $confirmCurrentSources): array {
                $sourceKey = trim((string) ($binding['source_key'] ?? ''));
                $targetKey = trim((string) ($binding['target_key'] ?? ''));
                $disposition = (string) ($binding['disposition'] ?? 'supports');
                $rationale = trim((string) ($binding['rationale'] ?? ''));

                $source = $sources->get($sourceKey);
                if (! is_array($source)) {
                    throw new InvalidArgumentException('Each evidence link must reference a current Business Plan, Budget, client-goal or advisory source.');
                }

                if ($disposition !== 'supports' && $disposition !== 'out_of_scope') {
                    throw new InvalidArgumentException('Evidence links must either support the plan or be recorded as intentionally out of scope.');
                }

                if ($disposition === 'supports' && ! isset($targets[$targetKey])) {
                    throw new InvalidArgumentException('Each supporting evidence link must point to a current strategic-plan section or milestone.');
                }

                if ($disposition === 'out_of_scope' && mb_strlen($rationale) < 12) {
                    throw new InvalidArgumentException('An intentionally out-of-scope source needs a short decision rationale.');
                }

                $key = $this->bindingKey($sourceKey, $targetKey);
                $previous = $existing->get($key);
                $unchanged = is_array($previous)
                    && (string) ($previous['disposition'] ?? '') === $disposition
                    && (string) ($previous['rationale'] ?? '') === $rationale;

                return [
                    'source_key' => $sourceKey,
                    'target_key' => $targetKey,
                    'disposition' => $disposition,
                    'rationale' => $rationale,
                    'source_hash' => $unchanged && ! $confirmCurrentSources
                        ? (string) ($previous['source_hash'] ?? $source['hash'])
                        : $source['hash'],
                    'confirmed_at' => $unchanged && ! $confirmCurrentSources
                        ? (string) ($previous['confirmed_at'] ?? now()->toIso8601String())
                        : now()->toIso8601String(),
                ];
            })
            ->unique(fn (array $binding): string => $this->bindingKey($binding['source_key'], $binding['target_key']))
            ->values()
            ->all();
    }

    /**
     * @return array{findings:list<Finding>,summary:array<string, int|float>}
     */
    public function review(StrategicPlan $plan): array
    {
        $sources = collect($this->sources($plan))->keyBy('key');
        $bindings = collect((array) ($plan->evidence_bindings ?? []))
            ->filter(fn (mixed $binding): bool => is_array($binding));
        $snapshots = collect((array) ($plan->source_snapshots ?? []))
            ->filter(fn (mixed $snapshot): bool => is_array($snapshot))
            ->keyBy(fn (array $snapshot): string => (string) ($snapshot['key'] ?? ''));
        $targets = $this->targets($plan);
        $findings = [];
        $untraced = 0;
        $stale = 0;
        $exclusions = 0;

        foreach ($sources as $sourceKey => $source) {
            $sourceBindings = $bindings
                ->filter(fn (array $binding): bool => (string) ($binding['source_key'] ?? '') === $sourceKey)
                ->values();
            $snapshot = $snapshots->get($sourceKey);

            if ($sourceBindings->isEmpty()) {
                $untraced++;
                $findings[] = $this->finding(
                    $source['category'],
                    'review',
                    $source['label'].' is not explicitly linked to a strategic-plan section or recorded as intentionally out of scope.',
                    'Link it to an outcome, priority, budget section or milestone, or record the advisor decision and rationale for excluding it.',
                    $source['materiality'],
                );
            }

            if (is_array($snapshot) && (string) ($snapshot['hash'] ?? '') !== $source['hash']) {
                $stale++;
                $findings[] = $this->finding(
                    'source_snapshot_stale',
                    'review',
                    $source['label'].' has changed since the strategic plan was last reviewed against its source evidence.',
                    'Review the changed source, update the plan or its evidence link, then explicitly confirm the current source snapshot.',
                    $source['materiality'],
                );
            }

            foreach ($sourceBindings as $binding) {
                $disposition = (string) ($binding['disposition'] ?? '');
                $targetKey = (string) ($binding['target_key'] ?? '');
                $rationale = trim((string) ($binding['rationale'] ?? ''));

                if ($disposition === 'out_of_scope') {
                    $exclusions++;
                    if (mb_strlen($rationale) < 12) {
                        $findings[] = $this->finding(
                            'out_of_scope_rationale',
                            'missing',
                            $source['label'].' is marked out of scope without a usable rationale.',
                            'Record why this source is out of scope and when that decision should be revisited.',
                            $source['materiality'],
                        );
                    }

                    continue;
                }

                if (! isset($targets[$targetKey])) {
                    $findings[] = $this->finding(
                        'evidence_target_missing',
                        'missing',
                        $source['label'].' points to a strategic-plan item that no longer exists.',
                        'Reconnect the source to an active section or milestone, or record it as intentionally out of scope.',
                        $source['materiality'],
                    );
                }

                if ((string) ($binding['source_hash'] ?? '') !== $source['hash']) {
                    $stale++;
                    $findings[] = $this->finding(
                        'source_binding_stale',
                        'review',
                        $source['label'].' changed after this evidence link was confirmed.',
                        'Review the source and explicitly reconfirm or revise this strategic-plan link.',
                        $source['materiality'],
                    );
                }
            }
        }

        foreach ($bindings as $binding) {
            $sourceKey = (string) ($binding['source_key'] ?? '');
            if ($sourceKey !== '' && ! $sources->has($sourceKey)) {
                $findings[] = $this->finding(
                    'evidence_source_unavailable',
                    'review',
                    'A strategic-plan evidence link points to a source that is no longer available to this plan.',
                    'Replace the link with current client evidence, or record a reviewed out-of-scope decision.',
                    'medium',
                );
            }
        }

        $outcomes = $this->outcomeReview($plan);

        return [
            'findings' => [...$findings, ...$outcomes['findings']],
            'summary' => [
                'available_sources' => $sources->count(),
                'evidence_bindings' => $bindings->count(),
                'untraced_sources' => $untraced,
                'stale_source_signals' => $stale,
                'intentional_exclusions' => $exclusions,
                ...$outcomes['summary'],
            ],
        ];
    }

    /** @return array{bindings:int,stale_source_signals:int,untraced_sources:int,intentional_exclusions:int} */
    public function advisorSummary(StrategicPlan $plan): array
    {
        $summary = $this->review($plan)['summary'];

        return [
            'bindings' => (int) $summary['evidence_bindings'],
            'stale_source_signals' => (int) $summary['stale_source_signals'],
            'untraced_sources' => (int) $summary['untraced_sources'],
            'intentional_exclusions' => (int) $summary['intentional_exclusions'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function targets(StrategicPlan $plan): array
    {
        $sections = collect((array) ($plan->sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->mapWithKeys(fn (array $section): array => [
                'section:'.(string) ($section['key'] ?? '') => (string) ($section['title'] ?? 'Strategic plan section'),
            ])
            ->filter(fn (string $label, string $key): bool => $key !== 'section:')
            ->all();
        $milestones = $plan->relationLoaded('milestones')
            ? $plan->getRelation('milestones')
            : $plan->milestones()->get();

        /** @var Collection<int, StrategicPlanMilestone> $milestones */
        $milestoneTargets = $milestones
            ->mapWithKeys(fn (StrategicPlanMilestone $milestone): array => [
                'milestone:'.$milestone->getKey() => $milestone->title,
            ])
            ->all();

        return [...$sections, ...$milestoneTargets];
    }

    /** @return list<EvidenceSource> */
    private function sources(StrategicPlan $plan): array
    {
        $plan->loadMissing(['strategicBudget.businessPlan.sections', 'proposal']);
        $budget = $plan->getRelation('strategicBudget');
        if (! $budget instanceof StrategicBudget) {
            return [];
        }

        $sources = [
            $this->source(
                'strategic_budget:'.$budget->getKey(),
                'budget_source',
                'Business Plan & Budget snapshot',
                'high',
                [
                    'id' => $budget->getKey(),
                    'revision' => $budget->revision,
                    'updated_at' => $budget->updated_at?->toIso8601String(),
                    'status' => $budget->status,
                    'computed' => $budget->computed,
                ],
            ),
        ];

        foreach ((array) $budget->client_goals as $index => $goal) {
            if (is_array($goal) && $this->sourceText($goal) !== '') {
                $label = 'Client goal '.((int) $index + 1);
                $sources[] = $this->source(
                    'client_goal:'.$index,
                    'client_goal',
                    $label,
                    'medium',
                    $goal,
                    $this->advisorLabel($label, $this->sourceText($goal)),
                );
            }
        }

        foreach ((array) $budget->advisor_goals as $index => $goal) {
            if (is_array($goal) && $this->sourceText($goal) !== '') {
                $label = 'Advisor goal '.((int) $index + 1);
                $sources[] = $this->source(
                    'advisor_goal:'.$index,
                    'advisor_goal',
                    $label,
                    'medium',
                    $goal,
                    $this->advisorLabel($label, $this->sourceText($goal)),
                );
            }
        }

        foreach ((array) $budget->business_plan_sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) ($section['key'] ?? ''));
            if ($key !== '' && $this->sourceText($section) !== '') {
                $sources[] = $this->source(
                    'business_plan_section:'.$key,
                    $key === 'action_priorities' ? 'business_plan_priority' : 'business_plan_source',
                    $label = $key === 'action_priorities' ? 'Business-plan action priorities' : 'Business-plan section '.str($key)->replace('_', ' ')->title(),
                    $key === 'action_priorities' ? 'high' : 'low',
                    $section,
                    $this->advisorLabel($label, $this->sourceText($section)),
                );
            }
        }

        foreach ((array) data_get($plan->proposal?->scope, 'focus_areas', []) as $index => $area) {
            if (is_array($area) && $this->sourceText($area) !== '') {
                $label = 'Proposal focus area '.((int) $index + 1);
                $sources[] = $this->source(
                    'proposal_focus_area:'.$index,
                    'advisory_finding',
                    $label,
                    'medium',
                    $area,
                    $this->advisorLabel($label, $this->sourceText($area)),
                );
            }
        }

        AnalysisFinding::query()
            ->where('client_id', $plan->client_id)
            ->where('lens', AnalysisLens::Prescriptive->value)
            ->whereHas('run', fn ($query) => $query->where('module', AnalysisModule::WebsiteAudit->value))
            ->latest()
            ->limit(3)
            ->get()
            ->each(function (AnalysisFinding $finding) use (&$sources): void {
                $sources[] = $this->source(
                    'analysis_finding:'.$finding->getKey(),
                    'advisory_finding',
                    'Verified advisory finding',
                    'medium',
                    [
                        'id' => $finding->getKey(),
                        'updated_at' => $finding->updated_at?->toIso8601String(),
                        'title' => $finding->title,
                        'body' => $finding->body,
                        'severity' => $finding->severity->value,
                    ],
                    $this->advisorLabel('Verified advisory finding', (string) $finding->title),
                );
            });

        return $sources;
    }

    /**
     * @param  EvidencePayload  $payload
     * @return EvidenceSource
     */
    private function source(string $key, string $category, string $label, string $materiality, array $payload, ?string $advisorLabel = null): array
    {
        return [
            'key' => $key,
            'category' => $category,
            'label' => $label,
            'advisor_label' => $advisorLabel ?? $label,
            'materiality' => $materiality === 'high' || $materiality === 'low' ? $materiality : 'medium',
            'hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param EvidencePayload $source */
    private function sourceText(array $source): string
    {
        return trim(implode(' ', array_filter([
            is_string($source['title'] ?? null) ? $source['title'] : null,
            is_string($source['measure'] ?? null) ? $source['measure'] : null,
            is_string($source['answer'] ?? null) ? $source['answer'] : null,
            is_string($source['body'] ?? null) ? $source['body'] : null,
        ])));
    }

    private function advisorLabel(string $prefix, string $reference): string
    {
        $reference = trim(preg_replace('/\s+/', ' ', $reference) ?? '');

        return $reference === ''
            ? $prefix
            : $prefix.': '.str($reference)->limit(110, '');
    }

    /**
     * @return array{findings:list<Finding>,summary:array{measured_outcomes:int,material_outcome_variances:int,variance_threshold:float}}
     */
    private function outcomeReview(StrategicPlan $plan): array
    {
        $milestones = $plan->relationLoaded('milestones')
            ? $plan->getRelation('milestones')
            : $plan->milestones()->get();
        $findings = [];
        $measured = 0;
        $materialVariances = 0;

        /** @var StrategicPlanMilestone $milestone */
        foreach ($milestones as $milestone) {
            $label = trim((string) $milestone->metric_label);
            $target = $milestone->target_value;
            $actual = $milestone->actual_value;
            if ($label === '' || ! is_numeric($target)) {
                continue;
            }

            $measured++;
            if (! is_numeric($actual)) {
                if ($milestone->status === StrategicPlanMilestone::STATUS_COMPLETED || ($milestone->due_date !== null && $milestone->due_date->isPast())) {
                    $findings[] = $this->finding(
                        'outcome_measurement_missing',
                        'review',
                        'A due or completed milestone has a target outcome but no actual result recorded.',
                        'Record the actual result, its unit and the supporting evidence before treating this outcome as complete.',
                        'medium',
                    );
                }

                continue;
            }

            $variance = $this->outcomeVariance($milestone);
            if ($variance === null || $variance < self::OUTCOME_VARIANCE_THRESHOLD) {
                continue;
            }

            $materialVariances++;
            $findings[] = $this->finding(
                'outcome_variance',
                'review',
                'A measured strategic-plan outcome is materially behind its agreed target.',
                'Review the delivery evidence, budget assumptions and corrective action; update the plan only after advisor approval.',
                $variance >= (self::OUTCOME_VARIANCE_THRESHOLD * 2) ? 'high' : 'medium',
            );
        }

        return [
            'findings' => $findings,
            'summary' => [
                'measured_outcomes' => $measured,
                'material_outcome_variances' => $materialVariances,
                'variance_threshold' => self::OUTCOME_VARIANCE_THRESHOLD,
            ],
        ];
    }

    private function outcomeVariance(StrategicPlanMilestone $milestone): ?float
    {
        if (! is_numeric($milestone->target_value) || ! is_numeric($milestone->actual_value)) {
            return null;
        }

        $baseline = is_numeric($milestone->baseline_value) ? (float) $milestone->baseline_value : null;
        $target = (float) $milestone->target_value;
        $actual = (float) $milestone->actual_value;
        $direction = in_array($milestone->target_direction, ['increase', 'decrease'], true)
            ? $milestone->target_direction
            : ($baseline !== null && $target < $baseline ? 'decrease' : 'increase');

        if ($baseline !== null && abs($target - $baseline) > 0.0001) {
            $expectedChange = $direction === 'decrease' ? $baseline - $target : $target - $baseline;
            $actualChange = $direction === 'decrease' ? $baseline - $actual : $actual - $baseline;

            return max(0.0, round(1 - ($actualChange / $expectedChange), 4));
        }

        if (abs($target) < 0.0001) {
            return null;
        }

        $attainment = $direction === 'decrease'
            ? $target / max(abs($actual), 0.0001)
            : $actual / $target;

        return max(0.0, round(1 - $attainment, 4));
    }

    /** @return Finding */
    private function finding(string $category, string $severity, string $message, string $nextAction, string $materiality): array
    {
        return [
            'category' => $category,
            'severity' => $severity === 'missing' ? 'missing' : 'review',
            'message' => $message,
            'next_action' => $nextAction,
            'materiality' => $materiality === 'high' || $materiality === 'low' ? $materiality : 'medium',
        ];
    }

    private function bindingKey(string $sourceKey, string $targetKey): string
    {
        return $sourceKey.'|'.$targetKey;
    }
}
