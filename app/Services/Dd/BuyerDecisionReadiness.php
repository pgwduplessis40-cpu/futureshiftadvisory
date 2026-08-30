<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Models\AnalysisFinding;
use App\Models\DdEngagement;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\Report;
use Illuminate\Support\Collection;

final class BuyerDecisionReadiness
{
    /**
     * @return array<string, mixed>
     */
    public function forEngagement(DdEngagement $engagement, ?Report $report = null): array
    {
        $snapshot = data_get($report?->metadata, 'buyer_decision_readiness');
        $payload = is_array($snapshot)
            ? $this->normalise($snapshot)
            : $this->evaluate(
                $engagement,
                $this->findings($engagement),
                $this->latestValuation($engagement),
                $this->risks($engagement),
                $this->recommendationPayload($engagement),
            );

        return $this->withReviewGate($payload, $report);
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array<string, mixed>
     */
    public function evaluate(
        DdEngagement $engagement,
        Collection $findings,
        ?DdValuation $valuation,
        Collection $risks,
        array $recommendation,
    ): array {
        $completedWorkstreams = $engagement->workstreams()
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count();
        $requiredWorkstreams = count(DataRoom::WORKSTREAMS);
        $evidenceItemCount = $engagement->dataRoomItems()->count();
        $support = $this->supportSummary($findings);
        $dealKillers = $risks->where('risk_level', DdRiskRegisterItem::LEVEL_DEAL_KILLER)->count();
        $majorRisks = $risks->where('risk_level', DdRiskRegisterItem::LEVEL_MAJOR)->count();
        $materialRisks = $dealKillers + $majorRisks;
        $priceAdjustment = round((float) $risks->sum('price_adjustment_nzd'), 2);
        $valuationMidpoint = $this->valuationMidpoint($valuation);
        $gates = $this->gates(
            completedWorkstreams: $completedWorkstreams,
            requiredWorkstreams: $requiredWorkstreams,
            evidenceItemCount: $evidenceItemCount,
            findings: $findings,
            support: $support,
            valuation: $valuation,
            risks: $risks,
            recommendation: $recommendation,
        );
        $ready = collect($gates)->every(fn (array $gate): bool => (bool) $gate['passed']);
        $confidence = $this->confidence($ready, $support, $valuation, $completedWorkstreams, $requiredWorkstreams);
        $decision = $this->decisionPayload($recommendation, $dealKillers, $majorRisks);

        return $this->normalise([
            'ready' => $ready,
            'client_release_ready' => false,
            'label' => $ready ? 'Buyer decision-ready' : 'Decision gaps to resolve',
            'decision_label' => $decision['label'],
            'decision_headline' => $decision['headline'],
            'decision_status' => $decision['status'],
            'confidence' => $confidence['level'],
            'confidence_reason' => $confidence['reason'],
            'recommendation' => $recommendation['recommendation'],
            'recommendation_rationale' => $recommendation['rationale'],
            'completed_workstreams' => $completedWorkstreams,
            'required_workstreams' => $requiredWorkstreams,
            'evidence_item_count' => $evidenceItemCount,
            'finding_count' => $findings->count(),
            'verified_finding_count' => $support['verified'],
            'flagged_finding_count' => $support['flagged'],
            'material_risk_count' => $materialRisks,
            'deal_killer_risk_count' => $dealKillers,
            'major_risk_count' => $majorRisks,
            'total_risk_count' => $risks->count(),
            'price_adjustment_nzd' => $priceAdjustment,
            'valuation_midpoint_nzd' => $valuationMidpoint,
            'gates' => $gates,
            'blockers' => collect($gates)
                ->reject(fn (array $gate): bool => (bool) $gate['passed'])
                ->pluck('label')
                ->values()
                ->all(),
            'decision_questions' => $this->decisionQuestions(
                engagement: $engagement,
                readiness: $ready,
                decision: $decision,
                valuation: $valuation,
                valuationMidpoint: $valuationMidpoint,
                risks: $risks,
                priceAdjustment: $priceAdjustment,
                support: $support,
                evidenceItemCount: $evidenceItemCount,
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalise(array $payload): array
    {
        return [
            'ready' => (bool) ($payload['ready'] ?? false),
            'client_release_ready' => (bool) ($payload['client_release_ready'] ?? false),
            'label' => (string) ($payload['label'] ?? 'Decision gaps to resolve'),
            'decision_label' => (string) ($payload['decision_label'] ?? 'Decision not ready'),
            'decision_headline' => (string) ($payload['decision_headline'] ?? 'The buyer decision cannot be relied on until the DD report quality gates pass.'),
            'decision_status' => (string) ($payload['decision_status'] ?? 'needs_evidence'),
            'confidence' => (string) ($payload['confidence'] ?? 'low'),
            'confidence_reason' => (string) ($payload['confidence_reason'] ?? 'DD evidence and valuation gates have not all passed.'),
            'recommendation' => (string) ($payload['recommendation'] ?? 'pending'),
            'recommendation_rationale' => (string) ($payload['recommendation_rationale'] ?? 'No DD recommendation has been generated yet.'),
            'completed_workstreams' => (int) ($payload['completed_workstreams'] ?? 0),
            'required_workstreams' => (int) ($payload['required_workstreams'] ?? count(DataRoom::WORKSTREAMS)),
            'evidence_item_count' => (int) ($payload['evidence_item_count'] ?? 0),
            'finding_count' => (int) ($payload['finding_count'] ?? 0),
            'verified_finding_count' => (int) ($payload['verified_finding_count'] ?? 0),
            'flagged_finding_count' => (int) ($payload['flagged_finding_count'] ?? 0),
            'material_risk_count' => (int) ($payload['material_risk_count'] ?? 0),
            'deal_killer_risk_count' => (int) ($payload['deal_killer_risk_count'] ?? 0),
            'major_risk_count' => (int) ($payload['major_risk_count'] ?? 0),
            'total_risk_count' => (int) ($payload['total_risk_count'] ?? 0),
            'price_adjustment_nzd' => (float) ($payload['price_adjustment_nzd'] ?? 0),
            'valuation_midpoint_nzd' => is_numeric($payload['valuation_midpoint_nzd'] ?? null)
                ? (float) $payload['valuation_midpoint_nzd']
                : null,
            'review_status' => $payload['review_status'] ?? null,
            'release_label' => (string) ($payload['release_label'] ?? 'Advisor review required before client release'),
            'gates' => array_values((array) ($payload['gates'] ?? [])),
            'blockers' => array_values((array) ($payload['blockers'] ?? [])),
            'decision_questions' => array_values((array) ($payload['decision_questions'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withReviewGate(array $payload, ?Report $report): array
    {
        $reviewed = $report instanceof Report
            && in_array((string) $report->review_status, ['reviewed', 'not_required'], true);

        return [
            ...$payload,
            'client_release_ready' => (bool) $payload['ready'] && $reviewed,
            'review_status' => $report?->review_status,
            'release_label' => $reviewed
                ? ((bool) $payload['ready'] ? 'Reviewed and ready for client reliance' : 'Reviewed with decision-quality gaps')
                : 'Advisor review required before client release',
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array{verified:int,flagged:int,unsupported:int}
     */
    private function supportSummary(Collection $findings): array
    {
        return [
            'verified' => $findings
                ->where('document_support', AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED)
                ->count(),
            'flagged' => $findings
                ->filter(fn (AnalysisFinding $finding): bool => in_array($finding->document_support, [
                    AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG,
                    AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY,
                ], true))
                ->count(),
            'unsupported' => $findings
                ->where('document_support', AnalysisFinding::DOCUMENT_SUPPORT_NONE)
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array{verified:int,flagged:int,unsupported:int}  $support
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array<int, array{key:string,label:string,passed:bool,detail:string}>
     */
    private function gates(
        int $completedWorkstreams,
        int $requiredWorkstreams,
        int $evidenceItemCount,
        Collection $findings,
        array $support,
        ?DdValuation $valuation,
        Collection $risks,
        array $recommendation,
    ): array {
        return [
            $this->gate(
                'workstream_coverage',
                'All DD workstreams assessed',
                $completedWorkstreams >= $requiredWorkstreams,
                "{$completedWorkstreams} of {$requiredWorkstreams} workstreams are complete.",
            ),
            $this->gate(
                'evidence_traceability',
                'Findings are traceable to evidence',
                $evidenceItemCount > 0 && $findings->isNotEmpty(),
                "{$evidenceItemCount} data-room item(s) and {$findings->count()} finding(s) are available.",
            ),
            $this->gate(
                'evidence_quality',
                'Evidence has no unresolved document flags',
                $support['flagged'] === 0,
                "{$support['verified']} verified finding(s), {$support['unsupported']} unsupported finding(s), {$support['flagged']} unresolved flag(s).",
            ),
            $this->gate(
                'valuation_price_position',
                'Valuation and price position available',
                $valuation instanceof DdValuation,
                $valuation instanceof DdValuation
                    ? 'A DD valuation is available for the price decision.'
                    : 'No DD valuation is available yet.',
            ),
            $this->gate(
                'risk_classification',
                'Red flags and ordinary risks are separated',
                $findings->isNotEmpty()
                    && $risks->isNotEmpty()
                    && $risks->every(fn (DdRiskRegisterItem $risk): bool => is_numeric($risk->rank) && is_string($risk->risk_level) && $risk->risk_level !== ''),
                "{$risks->count()} risk(s) are ranked from the completed findings.",
            ),
            $this->gate(
                'client_decision',
                'Buy / renegotiate / walk-away position is explicit',
                in_array($recommendation['recommendation'], [
                    DdEngagement::RECOMMENDATION_PROCEED,
                    DdEngagement::RECOMMENDATION_RENEGOTIATE,
                    DdEngagement::RECOMMENDATION_ABANDON,
                ], true),
                $recommendation['rationale'],
            ),
        ];
    }

    /**
     * @return array{key:string,label:string,passed:bool,detail:string}
     */
    private function gate(string $key, string $label, bool $passed, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }

    /**
     * @param  array{verified:int,flagged:int,unsupported:int}  $support
     * @return array{level:string,reason:string}
     */
    private function confidence(bool $ready, array $support, ?DdValuation $valuation, int $completedWorkstreams, int $requiredWorkstreams): array
    {
        if (! $ready) {
            return [
                'level' => 'low',
                'reason' => 'One or more DD quality gates still needs evidence, valuation, workstream completion, or advisor review.',
            ];
        }

        if ($support['verified'] > 0 && $valuation instanceof DdValuation && $completedWorkstreams >= $requiredWorkstreams) {
            return [
                'level' => 'high',
                'reason' => 'All DD workstreams are complete, a valuation is available, and at least one finding is supported by verified evidence.',
            ];
        }

        return [
            'level' => 'medium',
            'reason' => 'The decision position is complete, but evidence should still be read with the report limitations.',
        ];
    }

    /**
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array{label:string,headline:string,status:string}
     */
    private function decisionPayload(array $recommendation, int $dealKillers, int $majorRisks): array
    {
        return match ($recommendation['recommendation']) {
            DdEngagement::RECOMMENDATION_ABANDON => [
                'label' => 'Do not buy unless deal-killers are resolved',
                'headline' => 'The current DD position does not support buying this business unless the deal-killer risk is resolved and re-assessed.',
                'status' => 'do_not_buy',
            ],
            DdEngagement::RECOMMENDATION_RENEGOTIATE => [
                'label' => 'Renegotiate or walk away',
                'headline' => 'Do not buy on the current terms. Renegotiate price, warranties, conditions, or walk away if the material issues remain unresolved.',
                'status' => 'renegotiate',
            ],
            DdEngagement::RECOMMENDATION_PROCEED => [
                'label' => $dealKillers + $majorRisks > 0 ? 'Proceed only with conditions' : 'Proceed subject to normal completion controls',
                'headline' => 'A buyer can consider proceeding, provided normal completion checks, professional advice, funding, and settlement conditions are satisfied.',
                'status' => 'proceed',
            ],
            default => [
                'label' => 'Decision not ready',
                'headline' => 'The buy / renegotiate / walk-away decision is not ready until the DD quality gates pass.',
                'status' => 'needs_evidence',
            ],
        };
    }

    /**
     * @param  array{label:string,headline:string,status:string}  $decision
     * @param  array{verified:int,flagged:int,unsupported:int}  $support
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<int, array{question:string,answer:string,status:string}>
     */
    private function decisionQuestions(
        DdEngagement $engagement,
        bool $readiness,
        array $decision,
        ?DdValuation $valuation,
        ?float $valuationMidpoint,
        Collection $risks,
        float $priceAdjustment,
        array $support,
        int $evidenceItemCount,
    ): array {
        $materialRisks = $risks
            ->whereIn('risk_level', [DdRiskRegisterItem::LEVEL_DEAL_KILLER, DdRiskRegisterItem::LEVEL_MAJOR])
            ->values();
        $askingPrice = data_get($engagement->target_details, 'asking_price');
        $conditions = $materialRisks->isEmpty()
            ? 'No deal-killer or major DD condition is currently ranked; confirm ordinary completion conditions with the advisor and professional advisers.'
            : $materialRisks
                ->take(4)
                ->map(fn (DdRiskRegisterItem $risk): string => $risk->title)
                ->implode('; ');

        return [
            [
                'question' => 'Should I buy this business?',
                'answer' => $decision['headline'],
                'status' => $readiness ? 'answered' : 'needs_evidence',
            ],
            [
                'question' => 'Is the price supportable?',
                'answer' => $valuation instanceof DdValuation
                    ? 'DD valuation midpoint is '.$this->money($valuationMidpoint).'. Asking price is '.$this->money(is_numeric($askingPrice) ? (float) $askingPrice : null).'. Current DD risk adjustment is '.$this->money($priceAdjustment).'.'
                    : 'No DD valuation is available yet, so price support cannot be confirmed.',
                'status' => $valuation instanceof DdValuation ? 'answered' : 'needs_evidence',
            ],
            [
                'question' => 'What must be resolved before signing or settlement?',
                'answer' => $conditions,
                'status' => $materialRisks->isEmpty() ? 'answered' : 'needs_action',
            ],
            [
                'question' => 'How strong is the evidence?',
                'answer' => "{$evidenceItemCount} evidence item(s), {$support['verified']} verified finding(s), and {$support['flagged']} unresolved document flag(s) support the current DD position.",
                'status' => $support['flagged'] === 0 && $evidenceItemCount > 0 ? 'answered' : 'needs_evidence',
            ],
            [
                'question' => 'What happens if I proceed?',
                'answer' => 'Use the DD risk register, price adjustment schedule, and first 100-day actions as settlement and handover controls; BP&B should carry the funding impact forward if that service is active.',
                'status' => $readiness ? 'answered' : 'needs_evidence',
            ],
        ];
    }

    private function valuationMidpoint(?DdValuation $valuation): ?float
    {
        $mid = data_get($valuation?->normalised_values, 'reconciled.mid')
            ?? data_get($valuation?->normalised_values, 'mid');

        return is_numeric($mid) ? (float) $mid : null;
    }

    /**
     * @return Collection<int, AnalysisFinding>
     */
    private function findings(DdEngagement $engagement): Collection
    {
        return DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->with('analysisRun.findings')
            ->get()
            ->flatMap(fn (DdWorkstream $workstream): Collection => $workstream->analysisRun?->findings ?? collect())
            ->filter(fn (mixed $finding): bool => $finding instanceof AnalysisFinding)
            ->values();
    }

    /**
     * @return Collection<int, DdRiskRegisterItem>
     */
    private function risks(DdEngagement $engagement): Collection
    {
        return DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->orderBy('rank')
            ->get();
    }

    private function latestValuation(DdEngagement $engagement): ?DdValuation
    {
        return DdValuation::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    /**
     * @return array{recommendation:string,rationale:string}
     */
    private function recommendationPayload(DdEngagement $engagement): array
    {
        $recommendation = is_string($engagement->recommendation) && $engagement->recommendation !== ''
            ? $engagement->recommendation
            : 'pending';

        return [
            'recommendation' => $recommendation,
            'rationale' => $recommendation === 'pending'
                ? 'No DD recommendation has been generated yet.'
                : 'Latest persisted DD recommendation.',
        ];
    }

    private function money(?float $amount): string
    {
        return $amount === null ? 'n/a' : 'NZD '.number_format($amount, 0);
    }
}
