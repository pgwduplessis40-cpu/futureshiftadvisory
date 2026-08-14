<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurBudget;

final class BudgetFundingReadiness
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(?EntrepreneurBudget $budget): array
    {
        if (! $budget instanceof EntrepreneurBudget) {
            return [
                'input_complete' => false,
                'input_status_label' => 'Not started',
                'external_issue_ready' => false,
                'readiness_label' => 'Not ready for external issue',
                'readiness_tone' => 'high',
                'headline' => 'Complete the budget inputs before relying on this pack outside the advisory workspace.',
                'lowest_cash_month' => null,
                'lowest_cash' => null,
                'additional_funding_needed' => 0.0,
                'available_funding' => 0.0,
                'launch_costs' => 0.0,
                'monthly_fixed_costs' => 0.0,
                'operating_cover_months' => 0,
                'operating_cover_amount' => 0.0,
                'contingency_amount' => 0.0,
                'recommended_funding_target' => 0.0,
                'funding_gap_or_surplus' => 0.0,
                'risk_reasons' => ['No saved budget is available yet.'],
                'warnings' => ['No saved budget is available yet.'],
                'scenario_count' => 0,
            ];
        }

        $computed = (array) ($budget->computed ?? []);
        $monthlyRows = collect((array) data_get($computed, 'monthly_detail', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->all();
        $trough = $this->cashTrough($monthlyRows);
        $additionalFunding = $this->additionalFundingNeeded($trough['value']);
        $availableFunding = round((float) ($computed['opening_cash_balance'] ?? 0) + (float) ($computed['total_funding'] ?? 0), 2);
        $launchCosts = round((float) ($computed['total_launch_costs'] ?? 0), 2);
        $monthlyFixedCosts = round((float) ($computed['monthly_fixed_costs'] ?? 0), 2);
        $operatingCoverMonths = $this->operatingCoverMonths($budget->expected_runway_months);
        $operatingCover = round($monthlyFixedCosts * $operatingCoverMonths, 2);
        $contingency = round(($launchCosts + $operatingCover) * 0.10, 2);
        $recommendedFundingTarget = round($launchCosts + $operatingCover + $contingency, 2);
        $fundingGapOrSurplus = round($availableFunding - $recommendedFundingTarget, 2);
        $warnings = $this->warnings($budget);
        $riskReasons = [];

        if ($budget->status !== EntrepreneurBudget::STATUS_COMPLETE) {
            $riskReasons[] = 'Budget inputs are not complete.';
        }

        if ($additionalFunding > 0) {
            $riskReasons[] = 'The monthly cash curve falls below zero.';
        }

        if (data_get($computed, 'break_even_year') === null) {
            $riskReasons[] = 'Break-even is not visible in the forecast horizon.';
        }

        if (data_get($computed, 'cash_flow_positive_year') === null) {
            $riskReasons[] = 'Cumulative cash does not turn positive inside the forecast horizon.';
        }

        if ($warnings !== []) {
            $riskReasons[] = 'Open budget quality warnings need advisor review.';
        }

        $notReady = $budget->status !== EntrepreneurBudget::STATUS_COMPLETE
            || $additionalFunding > 0
            || data_get($computed, 'cash_flow_positive_year') === null;
        $needsReview = ! $notReady && ($warnings !== [] || data_get($computed, 'break_even_year') === null);
        $readinessLabel = $notReady
            ? 'Not ready for external issue'
            : ($needsReview ? 'Needs advisor review' : 'Ready for external issue');

        return [
            'input_complete' => $budget->status === EntrepreneurBudget::STATUS_COMPLETE,
            'input_status_label' => $this->inputStatusLabel($budget->status),
            'external_issue_ready' => ! $notReady && ! $needsReview,
            'readiness_label' => $readinessLabel,
            'readiness_tone' => $notReady ? 'high' : ($needsReview ? 'medium' : 'good'),
            'headline' => $this->decisionHeadline($readinessLabel, $additionalFunding, $fundingGapOrSurplus),
            'lowest_cash_month' => $trough['month'],
            'lowest_cash' => $trough['value'],
            'additional_funding_needed' => $additionalFunding,
            'available_funding' => $availableFunding,
            'launch_costs' => $launchCosts,
            'monthly_fixed_costs' => $monthlyFixedCosts,
            'operating_cover_months' => $operatingCoverMonths,
            'operating_cover_amount' => $operatingCover,
            'contingency_amount' => $contingency,
            'recommended_funding_target' => $recommendedFundingTarget,
            'funding_gap_or_surplus' => $fundingGapOrSurplus,
            'risk_reasons' => $riskReasons,
            'warnings' => $warnings,
            'scenario_count' => count((array) data_get($computed, 'scenarios', [])),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function warnings(EntrepreneurBudget $budget): array
    {
        $computed = (array) ($budget->computed ?? []);
        $warnings = collect((array) ($budget->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->map(fn (array $flag): string => (string) ($flag['title'] ?? 'Budget warning').': '.(string) ($flag['message'] ?? ''));

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $warnings->push('Tax not configured: company tax is missing from Reference data, so after-tax profit is indicative only.');
        }

        if ($budget->status !== EntrepreneurBudget::STATUS_COMPLETE) {
            $warnings->push('Budget inputs are incomplete. Viability, scoring, and funding readiness may be affected.');
        }

        return $warnings->unique()->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{month:int|null,value:float|null}
     */
    private function cashTrough(array $rows): array
    {
        $lowest = collect($rows)
            ->filter(fn (array $row): bool => is_numeric($row['cumulative_cash'] ?? null))
            ->sortBy(fn (array $row): float => (float) $row['cumulative_cash'])
            ->first();

        if (! is_array($lowest)) {
            return ['month' => null, 'value' => null];
        }

        return [
            'month' => is_numeric($lowest['month'] ?? null) ? (int) $lowest['month'] : null,
            'value' => round((float) $lowest['cumulative_cash'], 2),
        ];
    }

    private function additionalFundingNeeded(?float $lowestCash): float
    {
        return $lowestCash !== null && $lowestCash < 0 ? round(abs($lowestCash), 2) : 0.0;
    }

    private function operatingCoverMonths(?int $expectedRunwayMonths): int
    {
        return max(3, min(12, $expectedRunwayMonths ?? 6));
    }

    private function inputStatusLabel(string $status): string
    {
        return match ($status) {
            EntrepreneurBudget::STATUS_COMPLETE => 'Complete',
            EntrepreneurBudget::STATUS_PARTIAL => 'Partial',
            default => 'Not started',
        };
    }

    private function decisionHeadline(string $readinessLabel, float $additionalFunding, float $fundingGapOrSurplus): string
    {
        if ($readinessLabel === 'Not ready for external issue') {
            if ($additionalFunding > 0) {
                return 'The cash curve falls below zero, so funding action or revised assumptions are needed before lender or investor issue.';
            }

            return 'Complete the funding case and reach a positive cumulative cash position before presenting this budget externally.';
        }

        if ($readinessLabel === 'Needs advisor review') {
            return 'The forecast is internally complete enough to review, but open quality checks still need an advisor decision before external issue.';
        }

        return $fundingGapOrSurplus >= 0
            ? 'The forecast covers the recommended funding target and has no open budget quality warnings.'
            : 'The forecast is cash-positive, but the funding buffer remains below the recommended target and should be discussed with an advisor.';
    }
}
