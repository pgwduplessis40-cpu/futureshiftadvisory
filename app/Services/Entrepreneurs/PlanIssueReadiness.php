<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanSection;

final class PlanIssueReadiness
{
    public function __construct(
        private readonly BudgetFundingReadiness $budgetReadiness,
        private readonly ExternalIssueReview $externalIssueReview,
        private readonly BusinessPlanExecutiveSummary $executiveSummaries,
        private readonly ReleaseGate\ReleaseGate $releaseGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $completion = PlanRequirements::completion($plan);
        $completedSections = $plan->sections
            ->filter(fn (PlanSection $section): bool => $section->completeness_status === PlanSection::STATUS_COMPLETE);
        $evidencedSections = $completedSections
            ->filter(fn (PlanSection $section): bool => count((array) $section->attached_document_ids) > 0);
        $evidenceCount = $completedSections
            ->sum(fn (PlanSection $section): int => count((array) $section->attached_document_ids));
        $budget = $this->budgetReadiness->evaluate($plan->budgetRunway);
        $contentReview = $this->externalIssueReview->evaluate($plan);
        $executiveSummary = $this->executiveSummaries->status($plan);
        $gate = $this->releaseGate->evaluate($plan, 'plan');
        $reasons = [];
        $summaryReason = null;

        if (! $completion['complete']) {
            $reasons[] = 'Complete all business-plan requirements before external issue.';
        }

        if ($completedSections->isNotEmpty() && $evidencedSections->isEmpty()) {
            $reasons[] = 'Attach supporting documents to at least one completed response before external issue.';
        }

        if (! (bool) ($budget['external_issue_ready'] ?? false)) {
            $reasons[] = 'Budget funding readiness: '.(string) ($budget['readiness_label'] ?? 'Not ready for external issue').'.';
        }

        if (! (bool) ($executiveSummary['usable'] ?? false)) {
            $summaryReason = (bool) ($executiveSummary['legacy_draft'] ?? false)
                ? 'A manually authored executive-summary draft is held from external issue. Finalise a passing assessment to generate the approved AI summary.'
                : (string) ($executiveSummary['readiness_reason'] ?? 'A current executive summary is required before external issue.');
            $reasons[] = $summaryReason;
        }

        $reasons = [...$reasons, ...(array) ($contentReview['blocking_reasons'] ?? []), ...(array) ($contentReview['warnings'] ?? [])];

        $reasons[] = 'Draft: complete the release checks, evidence review and final-file verification before external issue.';
        $ready = $gate->jsonSerialize()['can_certify'] && $completion['complete']
            && $evidencedSections->isNotEmpty()
            && (bool) ($budget['external_issue_ready'] ?? false)
            && (bool) ($executiveSummary['usable'] ?? false)
            && ((array) ($contentReview['blocking_reasons'] ?? [])) === [];

        return [
            'external_issue_ready' => $ready,
            'label' => $ready ? 'Ready for external issue' : 'Not ready for external issue',
            'tone' => $ready ? 'good' : 'high',
            'reasons' => $reasons,
            'requirements_completed' => $completion['completed'],
            'requirements_missing' => $completion['total'] - $completion['completed'],
            'requirements_total' => $completion['total'],
            'evidence_supported_responses' => $evidencedSections->count(),
            'completed_responses' => $completedSections->count(),
            'evidence_count' => $evidenceCount,
            'budget' => $budget,
            'content_review' => $contentReview,
            'executive_summary' => $executiveSummary,
            'executive_summary_reason' => $summaryReason,
            'release_gate' => $gate->jsonSerialize(),
        ];
    }
}
