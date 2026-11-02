<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanAssessment;

/**
 * @phpstan-import-type Reconciliation from PlanBudgetCoherence
 * @phpstan-import-type Finding from PlanBudgetCoherence
 * @phpstan-import-type Advisory from PlanBudgetCoherence
 *
 * @phpstan-type SavedCoherence array{score?:int,evidence?:list<string>,advisories?:list<Advisory>,approval_available?:bool,actions?:array<int,mixed>|string,findings?:array<int,mixed>|string}
 * @phpstan-type ReviewRecord array{note:string,reviewed_at:string,reviewed_by_user_id:int,findings_hash:string,inputs_hash:string}
 */
final class PlanBudgetFollowUp
{
    /** @return Reconciliation|null */
    public function forAssessment(PlanAssessment $assessment): ?array
    {
        $coherence = data_get($assessment->scoring_scope, 'plan_budget_coherence');
        if (! is_array($coherence)) {
            return null;
        }
        /** @var SavedCoherence $coherence */
        $review = data_get($assessment->scoring_scope, 'plan_budget_review');
        /** @var ReviewRecord|null $review */
        $review = is_array($review) ? $review : null;
        $currentHash = $review !== null && $assessment->businessPlan instanceof BusinessPlan
            ? $this->currentInputsHash($assessment->businessPlan)
            : null;

        return $this->present($coherence, $review, $currentHash);
    }

    /** @param SavedCoherence $coherence
     * @param  ReviewRecord|null  $review
     * @return Reconciliation
     */
    public function present(array $coherence, ?array $review = null, ?string $currentInputsHash = null): array
    {
        $findings = app(PlanBudgetActionMessage::class)->findingsForCoherence($coherence);
        $routing = (new PlanBudgetActionMapper)->route($findings);
        if ($findings === [] && ! ($coherence['approval_available'] ?? false)) {
            $routing['calculation_issues'][] = [
                'category' => 'budget_support', 'severity' => 'review',
                'message' => 'This saved assessment does not contain the detail needed to verify its earlier block.',
                'next_action' => 'Run reassessment to obtain current findings before finalising.',
            ];
        }
        $reviewed = $review !== null
            && hash_equals($this->findingsHash($routing['advisor_reviews']), $review['findings_hash'])
            && $currentInputsHash !== null && hash_equals($currentInputsHash, $review['inputs_hash']);
        $clientCount = count($routing['actions']);
        $calculationCount = count($routing['calculation_issues']);
        $reviewCount = $reviewed ? 0 : count($routing['advisor_reviews']);
        $outstanding = $clientCount + $calculationCount + $reviewCount;
        $summary = $outstanding === 0
            ? 'No plan–budget items are outstanding. The assessment can be finalised when its scoring requirements are met.'
            : implode(' ', array_filter([
                $clientCount > 0 ? $clientCount.' specific client '.($clientCount === 1 ? 'update is' : 'updates are').' needed.' : 'No Budget changes are requested from the client.',
                $calculationCount > 0 ? $calculationCount.' calculation '.($calculationCount === 1 ? 'issue needs' : 'issues need').' investigation.' : null,
                $reviewCount > 0 ? $reviewCount.' '.($reviewCount === 1 ? 'finding needs' : 'findings need').' an advisor decision.' : null,
            ]));

        return [
            'score' => (int) ($coherence['score'] ?? 0),
            'evidence' => $coherence['evidence'] ?? [],
            'findings' => $findings,
            'advisories' => $coherence['advisories'] ?? [],
            'budget_support' => $this->direction('budget_support', $findings, $routing['advisor_reviews'], $reviewed),
            'plan_correlation' => $this->direction('plan_correlation', $findings, $routing['advisor_reviews'], $reviewed),
            ...$routing,
            'review_completed' => $reviewed,
            'review_note' => $reviewed ? $review['note'] : null,
            'summary' => $summary,
            'unresolved_count' => $outstanding,
            'approval_available' => $outstanding === 0,
            'approval_message' => $outstanding === 0 ? 'No plan–budget items are outstanding.' : $summary,
            'status' => $outstanding === 0 ? 'met' : (collect($routing['actions'])->contains('severity', 'missing') ? 'missing' : 'review'),
            'status_label' => $outstanding === 0 ? 'Ready to finalise' : ($clientCount > 0 ? 'Client update needed' : 'Advisor action needed'),
        ];
    }

    /** @param list<Finding> $findings
     * @param  list<Finding>  $reviews
     * @return array{status:'met'|'review'|'missing',status_label:string,summary:string,unresolved_count:int}
     */
    private function direction(string $category, array $findings, array $reviews, bool $reviewed): array
    {
        $pending = array_values(array_filter($findings, fn (array $finding): bool => $finding['category'] === $category
            && ! ($reviewed && collect($reviews)->contains(fn (array $review): bool => $review['message'] === $finding['message']))));
        $status = $pending === [] ? 'met' : (collect($pending)->contains('severity', 'missing') ? 'missing' : 'review');

        return [
            'status' => $status,
            'status_label' => $status === 'met' ? 'Resolved' : 'Review needed',
            'summary' => $pending === [] ? 'No outstanding findings in this check.' : 'See the specific client updates, calculation issues or advisor reviews below.',
            'unresolved_count' => count($pending),
        ];
    }

    /** @param list<Finding> $findings */
    public function findingsHash(array $findings): string
    {
        return hash('sha256', json_encode($findings, JSON_THROW_ON_ERROR));
    }

    public function currentInputsHash(BusinessPlan $plan): string
    {
        return $this->snapshotInputsHash(app(BusinessPlanSnapshot::class)->capture($plan));
    }

    /** @param array<array-key,mixed> $snapshot */
    public function snapshotInputsHash(array $snapshot): string
    {
        $sections = collect((array) ($snapshot['phases'] ?? []))
            ->flatMap(fn (array $phase): array => (array) ($phase['sections'] ?? []))
            ->map(fn (array $section): array => array_intersect_key($section, array_flip([
                'id', 'title', 'body', 'requirement_key', 'attached_document_ids',
            ])))
            ->sortBy('id')->values()->all();

        return hash('sha256', json_encode($this->canonical([
            'sections' => $sections,
            'budget' => data_get($snapshot, 'budget.assessment_evidence'),
        ]), JSON_THROW_ON_ERROR));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }
}
