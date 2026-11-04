<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\PlanAssessment;

/**
 * @phpstan-import-type ReviewRecord from PlanBudgetFollowUp
 * @phpstan-import-type SavedCoherence from PlanBudgetFollowUp
 * @phpstan-import-type Finding from PlanBudgetCoherence
 */
final class PlanBudgetReviewDecision
{
    /** @return array{review:ReviewRecord|null,inputs_hash:string|null,assessment_round:int|null,message:string|null} */
    public function resolve(PlanAssessment $assessment): array
    {
        // Never fall back past a newer recorded decision, even if it is no longer valid.
        $source = data_get($assessment->scoring_scope, 'plan_budget_review') !== null
            ? $assessment
            : $assessment->businessPlan?->assessments()->where('round', '<', $assessment->round)
                ->whereNotNull('scoring_scope->plan_budget_review')->orderByDesc('round')->first();
        $review = data_get($source?->scoring_scope, 'plan_budget_review');
        if ($review === null) {
            return ['review' => null, 'inputs_hash' => null, 'assessment_round' => null, 'message' => null];
        }
        if (! is_array($review)) {
            return ['review' => null, 'inputs_hash' => null, 'assessment_round' => null,
                'message' => 'The saved decision is incomplete. Its original approval record needs advisor investigation.'];
        }
        foreach (['note', 'reviewed_at', 'findings_hash', 'inputs_hash'] as $key) {
            if (! is_string($review[$key] ?? null) || trim($review[$key]) === '') {
                return ['review' => null, 'inputs_hash' => null, 'assessment_round' => null,
                    'message' => 'The saved decision is incomplete. Its original approval record needs advisor investigation.'];
            }
        }
        if (! is_numeric($review['reviewed_by_user_id'] ?? null) || (int) $review['reviewed_by_user_id'] <= 0) {
            return ['review' => null, 'inputs_hash' => null, 'assessment_round' => null,
                'message' => 'The saved decision has no recorded advisor. Its original approval record needs investigation.'];
        }
        /** @var ReviewRecord $review */
        $result = ['review' => $review, 'inputs_hash' => null, 'assessment_round' => (int) $source->round, 'message' => null];
        $original = (array) $source->plan_snapshot;
        if (! hash_equals($review['inputs_hash'], $this->snapshotHash($original))
            || ! hash_equals($review['findings_hash'], $this->findingsHash($this->reviews($source)))) {
            return [...$result, 'message' => 'The saved decision cannot be matched to its original assessed inputs and findings. Review the original approval record.'];
        }
        if (! hash_equals($review['findings_hash'], $this->findingsHash($this->reviews($assessment)))
            && ! $this->sameFundingWording($this->reviews($source), $this->reviews($assessment))) {
            return [...$result, 'message' => 'The findings have changed since the saved advisor decision. Review the new findings before finalising.'];
        }
        $current = app(BusinessPlanSnapshot::class)->capture($assessment->businessPlan);
        $fundingOnly = $this->isFundingDecision($this->reviews($source));
        $basis = $this->basisHash($original, $fundingOnly);
        if (! hash_equals($basis, $this->basisHash((array) $assessment->plan_snapshot, $fundingOnly))
            || ! hash_equals($basis, $this->basisHash($current, $fundingOnly))) {
            return [...$result, 'message' => 'The plan or Budget inputs have changed since the saved advisor decision. Reassess the changed information before recording a new decision.'];
        }

        // Present the verified wording equivalent without rewriting the saved approval.
        return [...$result, 'review' => [...$review, 'findings_hash' => $this->findingsHash($this->reviews($assessment))],
            'inputs_hash' => $review['inputs_hash']];
    }

    /** @return list<Finding> */
    private function reviews(PlanAssessment $assessment): array
    {
        /** @var SavedCoherence $coherence */
        $coherence = (array) data_get($assessment->scoring_scope, 'plan_budget_coherence', []);

        return (new PlanBudgetActionMapper)->route(app(PlanBudgetActionMessage::class)->findingsForCoherence($coherence))['advisor_reviews'];
    }

    /** @param list<Finding> $findings */
    public function findingsHash(array $findings): string
    {
        return hash('sha256', json_encode($findings, JSON_THROW_ON_ERROR));
    }

    /** @param list<Finding> $findings */
    private function isFundingDecision(array $findings): bool
    {
        // Legacy findings have no stable identifier. Restrict this compatibility
        // rule to the funding-exposure finding; other reviews keep all evidence.
        return count($findings) === 1 && $findings[0]['category'] === 'budget_support'
            && preg_match('/^The forecast requires \$[\d,]+(?:\.\d+)? of additional funding to cover the modelled cash trough(?: and planned operating buffer)?\.$/', $findings[0]['message']) === 1;
    }

    /**
     * @param  list<Finding>  $original
     * @param  list<Finding>  $current
     */
    private function sameFundingWording(array $original, array $current): bool
    {
        if (! $this->isFundingDecision($original) || ! $this->isFundingDecision($current)) {
            return false;
        }
        $original[0]['message'] = str_replace(' and planned operating buffer', '', $original[0]['message']);
        $current[0]['message'] = str_replace(' and planned operating buffer', '', $current[0]['message']);

        return hash_equals($this->findingsHash($original), $this->findingsHash($current));
    }

    /** @param array<array-key,mixed> $snapshot */
    private function basisHash(array $snapshot, bool $fundingOnly): string
    {
        if ($fundingOnly) {
            // Presentation and warning copy can change between releases without
            // changing the approved exposure. Keep every input, computed result,
            // funding amount, timing and position in the comparison.
            $readiness = (array) data_get($snapshot, 'budget.assessment_evidence.funding_readiness', []);
            if (array_key_exists('cash_deficit', $readiness)) {
                // Compare the old combined exposure on the same basis while
                // retaining every underlying budget input and model result.
                $readiness['required_additional_funding'] = round(max((float) ($readiness['additional_funding_needed'] ?? 0), max(0.0, -(float) ($readiness['funding_gap_or_surplus'] ?? 0))), 2);
            }
            foreach (['cash_deficit', 'reserve_target_shortfall', 'external_funding_request', 'external_issue_ready', 'funding_position_aligned'] as $key) {
                unset($readiness[$key]);
            }
            foreach (['input_status_label', 'readiness_label', 'readiness_tone', 'headline', 'funding_position_label', 'risk_reasons', 'warnings'] as $key) {
                unset($readiness[$key]);
            }
            data_set($snapshot, 'budget.assessment_evidence.funding_readiness', $readiness);
        }

        return $this->snapshotHash($snapshot, false);
    }

    /** @param array<array-key,mixed> $snapshot */
    public function snapshotHash(array $snapshot, bool $includeExecutiveSummary = true): string
    {
        $sections = collect((array) ($snapshot['phases'] ?? []))
            ->flatMap(fn (array $phase): array => (array) ($phase['sections'] ?? []))
            ->reject(fn (array $section): bool => ! $includeExecutiveSummary && (
                ($section['requirement_key'] ?? null) === BusinessPlanExecutiveSummary::REQUIREMENT_KEY
                || ($section['key'] ?? null) === BusinessPlanExecutiveSummary::SECTION_KEY
                || strcasecmp(trim((string) ($section['title'] ?? '')), 'Executive summary') === 0
            ))
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
