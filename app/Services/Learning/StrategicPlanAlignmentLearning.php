<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use App\Models\StrategicBudget;
use App\Models\StrategicPlan;
use App\Services\Audit\AuditWriter;
use App\Services\Budgets\StrategicBudgetPlanBudgetCoherence;
use App\Services\Budgets\StrategicBudgetPlanBudgetReconciliation;
use App\Services\StrategicPlans\StrategicPlanEvidenceReview;

/**
 * Turns unresolved plan, budget and strategic-plan traceability checks into
 * governed learning candidates. It deliberately records identifiers, check
 * outcomes and remediation steps rather than copying client narrative into
 * the shared learning queue.
 *
 * @phpstan-type Finding array{category:string,severity:'missing'|'review',message:string,next_action:string,materiality:'low'|'medium'|'high'}
 */
final class StrategicPlanAlignmentLearning
{
    public function __construct(
        private readonly StrategicBudgetPlanBudgetCoherence $planBudgetCoherence,
        private readonly StrategicBudgetPlanBudgetReconciliation $planBudgetReconciliation,
        private readonly StrategicPlanEvidenceReview $evidenceReview,
        private readonly AuditWriter $audit,
    ) {}

    public function syncBudgetPlanCoherence(StrategicBudget $budget): ?LearningUpdate
    {
        $coherence = $this->planBudgetCoherence->evaluate($budget);
        $reconciliation = $this->planBudgetReconciliation->evaluate($budget);
        $findings = [
            ...$this->findings('budget_support', $coherence['findings']),
            ...$this->findings('plan_correlation', $reconciliation['findings']),
        ];

        return $this->syncCandidate(
            sourceType: 'strategic_budget_plan_budget_alignment',
            subjectId: (string) $budget->getKey(),
            findings: $findings,
            summary: 'Business Plan & Budget alignment needs review before it can support strategic decisions.',
            proposedChange: [
                'action' => 'review_business_plan_budget_alignment',
                'automatic_application' => false,
                'requires_approval' => true,
                'review_topics' => ['budget_support', 'plan_correlation'],
            ],
            impactScope: [
                'module' => 'strategic_planning',
                'surface' => 'business_plan_budget',
                'client_id' => $budget->client_id,
                'strategic_budget_id' => $budget->getKey(),
                'governance_gate' => 'advisor_or_admin_review_required',
            ],
            evidence: [
                'strategic_budget_id' => $budget->getKey(),
                'budget_status' => $budget->status,
                'budget_support' => $this->checkSummary($coherence),
                'plan_correlation' => $this->checkSummary($reconciliation),
            ],
        );
    }

    public function syncStrategicPlan(StrategicPlan $plan): ?LearningUpdate
    {
        $plan->loadMissing(['strategicBudget.businessPlan.sections', 'proposal', 'milestones']);
        $relatedBudget = $plan->getRelation('strategicBudget');
        $budget = $relatedBudget instanceof StrategicBudget ? $relatedBudget : null;
        $evidenceReview = $this->evidenceReview->review($plan);
        $findings = [
            ...$this->strategicPlanFindings($plan, $budget),
            ...$evidenceReview['findings'],
        ];
        $budgetChecks = $budget !== null
            ? [
                'budget_support' => $this->checkSummary($this->planBudgetCoherence->evaluate($budget)),
                'plan_correlation' => $this->checkSummary($this->planBudgetReconciliation->evaluate($budget)),
            ]
            : null;
        $clientGoalCount = $budget !== null ? count($budget->client_goals) : 0;
        $advisorGoalCount = $budget !== null ? count($budget->advisor_goals) : 0;

        return $this->syncCandidate(
            sourceType: 'strategic_plan_evidence_alignment',
            subjectId: (string) $plan->getKey(),
            findings: $findings,
            summary: 'Strategic plan needs evidence-alignment review against the Business Plan, Budget or advisory-client inputs.',
            proposedChange: [
                'action' => 'review_strategic_plan_evidence_alignment',
                'automatic_application' => false,
                'requires_approval' => true,
                'review_topics' => ['business_plan', 'budget', 'client_goals', 'advisor_evidence'],
            ],
            impactScope: [
                'module' => 'strategic_planning',
                'surface' => 'advisor_strategic_plan',
                'client_id' => $plan->client_id,
                'strategic_plan_id' => $plan->getKey(),
                'strategic_budget_id' => $budget?->getKey(),
                'proposal_id' => $plan->proposal_id,
                'governance_gate' => 'advisor_or_admin_review_required',
            ],
            evidence: [
                'strategic_plan_id' => $plan->getKey(),
                'strategic_plan_status' => $plan->status,
                'strategic_plan_sections' => $this->sectionKeys($plan),
                'strategic_budget_id' => $budget?->getKey(),
                'business_plan_id' => $budget?->business_plan_id,
                'client_goal_count' => $clientGoalCount,
                'advisor_goal_count' => $advisorGoalCount,
                'proposal_focus_area_count' => count((array) data_get($plan->proposal?->scope, 'focus_areas', [])),
                'business_plan_budget_checks' => $budgetChecks,
                'evidence_alignment' => $evidenceReview['summary'],
                'client_narrative_excluded' => true,
            ],
        );
    }

    public function syncPlansForBudget(StrategicBudget $budget): int
    {
        return StrategicPlan::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->cursor()
            ->reduce(function (int $count, StrategicPlan $plan): int {
                $existing = $this->openStrategicPlanCandidate((string) $plan->getKey());
                $update = $this->syncStrategicPlan($plan);

                return $count + ($existing === null && $update instanceof LearningUpdate ? 1 : 0);
            }, 0);
    }

    public function run(): int
    {
        return StrategicPlan::query()
            ->whereIn('status', [StrategicPlan::STATUS_DRAFT, StrategicPlan::STATUS_DEPLOYED])
            ->cursor()
            ->reduce(function (int $count, StrategicPlan $plan): int {
                $existing = $this->openStrategicPlanCandidate((string) $plan->getKey());
                $update = $this->syncStrategicPlan($plan);

                return $count + ($existing === null && $update instanceof LearningUpdate ? 1 : 0);
            }, 0);
    }

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $proposedChange
     * @param  array<string, mixed>  $impactScope
     * @param  array<string, mixed>  $evidence
     */
    private function syncCandidate(
        string $sourceType,
        string $subjectId,
        array $findings,
        string $summary,
        array $proposedChange,
        array $impactScope,
        array $evidence,
    ): ?LearningUpdate {
        $open = LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT)
            ->where('source->type', $sourceType)
            ->where('source->subject_id', $subjectId)
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
            ])
            ->latest()
            ->first();

        if ($findings === []) {
            if (! $open instanceof LearningUpdate) {
                return null;
            }

            $resolvedSummary = 'Previously detected planning-alignment issues now appear resolved; confirm and close this review.';
            $resolvedEvidence = [
                ...$evidence,
                'findings' => [],
                'current_status' => 'appears_resolved_pending_human_confirmation',
                'last_checked_at' => now()->toIso8601String(),
            ];

            if (! $this->evidenceChanged($open->evidence, $resolvedEvidence) && $open->summary === $resolvedSummary) {
                $open->forceFill(['evidence' => $resolvedEvidence])->save();

                return $open->refresh();
            }

            $open->forceFill([
                'summary' => $resolvedSummary,
                'evidence' => $resolvedEvidence,
            ])->save();

            $this->audit->record('learning_update.refreshed', subject: $open, after: [
                'source_type' => $sourceType,
                'subject_id' => $subjectId,
                'finding_count' => 0,
                'current_status' => 'appears_resolved_pending_human_confirmation',
            ]);

            return $open->refresh();
        }

        $payload = [
            'source' => [
                'type' => $sourceType,
                'subject_id' => $subjectId,
                'signal_key' => hash('sha256', $sourceType.'|'.$subjectId),
                'last_checked_at' => now()->toIso8601String(),
                'rollup_key' => 'strategic_planning:alignment_review',
                'rollup_label' => 'Strategic planning alignment',
            ],
            'summary' => $summary,
            'proposed_change' => $proposedChange,
            'impact_scope' => $impactScope,
            'clients_affected' => 1,
            'magnitude' => $this->magnitude($findings),
            'confidence' => $this->confidence($findings),
            'evidence' => [
                ...$evidence,
                'findings' => $findings,
                'current_status' => 'needs_review',
                'last_checked_at' => now()->toIso8601String(),
            ],
        ];

        if ($open instanceof LearningUpdate) {
            if (! $this->candidateChanged($open, $payload)) {
                $open->forceFill([
                    'source' => $payload['source'],
                    'evidence' => $payload['evidence'],
                ])->save();

                return $open->refresh();
            }

            $open->forceFill($payload)->save();

            $this->audit->record('learning_update.refreshed', subject: $open, after: [
                'source_type' => $sourceType,
                'subject_id' => $subjectId,
                'finding_count' => count($findings),
            ]);

            return $open->refresh();
        }

        /** @var LearningUpdate $update */
        $update = LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT,
            ...$payload,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $update, after: [
            'layer_id' => LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT,
            'source_type' => $sourceType,
            'subject_id' => $subjectId,
            'finding_count' => count($findings),
            'automatic_application' => false,
        ]);

        return $update;
    }

    /**
     * @return list<Finding>
     */
    private function strategicPlanFindings(StrategicPlan $plan, ?StrategicBudget $budget): array
    {
        $findings = [];
        $sections = $this->sections($plan);

        if (! $budget instanceof StrategicBudget) {
            return [$this->finding(
                'budget_link',
                'missing',
                'The strategic plan has no linked Business Plan & Budget snapshot.',
                'Link an approved Business Plan & Budget before using this strategic plan as the delivery baseline.',
            )];
        }

        if ((string) $budget->client_id !== (string) $plan->client_id) {
            $findings[] = $this->finding(
                'client_scope',
                'missing',
                'The linked Business Plan & Budget belongs to a different client.',
                'Replace the linked budget with the approved snapshot for this advisory client.',
            );
        }

        $businessPlan = $budget->businessPlan;
        if ($businessPlan !== null && $businessPlan->client_id !== null && (string) $businessPlan->client_id !== (string) $plan->client_id) {
            $findings[] = $this->finding(
                'business_plan_scope',
                'missing',
                'The linked business plan belongs to a different client.',
                'Use the advisory client’s business plan or remove the incorrect link before review.',
            );
        }

        foreach (['outcomes', 'priorities', 'budget', 'governance'] as $requiredSection) {
            if (trim((string) ($sections[$requiredSection] ?? '')) === '') {
                $findings[] = $this->finding(
                    'strategic_plan_section',
                    'missing',
                    'The strategic plan has no '.$requiredSection.' section to review against its supporting evidence.',
                    'Add the missing strategic-plan section and cite the relevant client, plan or budget input.',
                );
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<Finding>
     */
    private function findings(string $category, array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => $this->finding(
                $category,
                (string) ($item['severity'] ?? 'review'),
                (string) ($item['message'] ?? 'Planning alignment needs review.'),
                (string) ($item['next_action'] ?? 'Review the supporting evidence.'),
                (string) ($item['severity'] ?? 'review') === 'missing' ? 'high' : 'medium',
            ))
            ->values()
            ->all();
    }

    /** @return Finding */
    private function finding(string $category, string $severity, string $message, string $nextAction, string $materiality = 'medium'): array
    {
        return [
            'category' => $category,
            'severity' => $severity === 'missing' ? 'missing' : 'review',
            'message' => $message,
            'next_action' => $nextAction,
            'materiality' => $materiality === 'high' || $materiality === 'low' ? $materiality : 'medium',
        ];
    }

    /**
     * @param  array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function checkSummary(array $check): array
    {
        return [
            'status' => $check['status'] ?? null,
            'score' => $check['score'] ?? null,
            'approval_available' => $check['approval_available'] ?? false,
            'unresolved_count' => $check['unresolved_count'] ?? 0,
            'evidence' => $check['evidence'] ?? [],
        ];
    }

    /** @return array<string, string> */
    private function sections(StrategicPlan $plan): array
    {
        return collect((array) ($plan->sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->mapWithKeys(fn (array $section): array => [
                trim((string) ($section['key'] ?? '')) => trim((string) ($section['body'] ?? '')),
            ])
            ->filter(fn (string $body, string $key): bool => $key !== '')
            ->all();
    }

    /** @return list<string> */
    private function sectionKeys(StrategicPlan $plan): array
    {
        return array_keys($this->sections($plan));
    }

    /** @param list<Finding> $findings */
    private function magnitude(array $findings): string
    {
        $missing = collect($findings)->where('severity', 'missing')->count();
        $highMateriality = collect($findings)->where('materiality', 'high')->count();

        return $highMateriality > 0 || $missing >= 2 || count($findings) >= 5 ? 'high' : ($missing > 0 ? 'medium' : 'low');
    }

    /** @param list<Finding> $findings */
    private function confidence(array $findings): float
    {
        $directChecks = collect($findings)
            ->whereIn('category', ['budget_support', 'plan_correlation', 'client_scope', 'business_plan_scope'])
            ->count();

        return min(0.9, 0.62 + ($directChecks * 0.06));
    }

    private function openStrategicPlanCandidate(string $planId): ?LearningUpdate
    {
        return LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT)
            ->where('source->type', 'strategic_plan_evidence_alignment')
            ->where('source->subject_id', $planId)
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
            ])
            ->latest()
            ->first();
    }

    /** @param array<string, mixed> $payload */
    private function candidateChanged(LearningUpdate $update, array $payload): bool
    {
        return $update->summary !== $payload['summary']
            || $update->proposed_change !== $payload['proposed_change']
            || $update->impact_scope !== $payload['impact_scope']
            || $update->magnitude !== $payload['magnitude']
            || (float) $update->confidence !== (float) $payload['confidence']
            || $this->evidenceChanged($update->source, $payload['source'])
            || $this->evidenceChanged($update->evidence, $payload['evidence']);
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $next
     */
    private function evidenceChanged(?array $current, array $next): bool
    {
        $current ??= [];
        unset($current['last_checked_at'], $next['last_checked_at']);

        return $current !== $next;
    }
}
