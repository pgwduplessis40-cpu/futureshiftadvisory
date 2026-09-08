<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

/**
 * Evaluates the two directions of a financial assessment independently:
 * whether the forecast can support the plan and whether the plan makes
 * explicit claims that agree with the forecast. This runs from the captured
 * assessment snapshot, so a later edit cannot change a recorded result.
 *
 * @phpstan-type Finding array{category:'budget_support'|'plan_correlation',severity:'missing'|'review',message:string,next_action:string}
 * @phpstan-type Direction array{status:'met'|'review'|'missing',status_label:string,summary:string,unresolved_count:int}
 * @phpstan-type Reconciliation array{status:'met'|'review'|'missing',status_label:string,score:int,summary:string,evidence:list<string>,findings:list<Finding>,approval_available:bool,approval_message:string,budget_support:Direction,plan_correlation:Direction,unresolved_count:int}
 * @phpstan-type BudgetRow array{label?:string,type?:string,amount?:float|int,monthly_capacity_units?:float|int,cadence?:string}
 * @phpstan-type BudgetFlag array{key?:string,message?:string,title?:string}
 * @phpstan-type BudgetComputed array{available_after_launch?:float|int,monthly_fixed_costs?:float|int,runway_months?:float|int,runway_open_ended?:bool,input_count?:int,break_even_reached?:bool}
 * @phpstan-type BudgetAssumptions array{opening_cash_balance?:float|int}
 * @phpstan-type FundingReadiness array{required_additional_funding?:float|int,monthly_fixed_costs?:float|int,warnings?:list<string>}
 * @phpstan-type BudgetEvidence array{expected_runway_months?:float|int,assumptions?:BudgetAssumptions,monthly_fixed_costs?:list<BudgetRow>,revenue_forecast?:list<BudgetRow>,funding_sources?:list<BudgetRow>,funding_scenarios?:list<BudgetRow>,computed?:BudgetComputed,flags?:list<BudgetFlag>,funding_readiness?:FundingReadiness}
 * @phpstan-type Budget array{status:string,expected_runway_months?:float|int,assumptions?:BudgetAssumptions,monthly_fixed_costs?:list<BudgetRow>,revenue_forecast?:list<BudgetRow>,funding_sources?:list<BudgetRow>,funding_scenarios?:list<BudgetRow>,computed?:BudgetComputed,flags?:list<BudgetFlag>,funding_readiness?:FundingReadiness}
 * @phpstan-type FinancialSection array{title?:string,body?:string,requirement_key?:string}
 * @phpstan-type SnapshotPhase array{sections?:list<FinancialSection>}
 * @phpstan-type SnapshotBudget array{status?:string,assessment_evidence?:BudgetEvidence}
 * @phpstan-type Snapshot array{budget?:SnapshotBudget|null,phases?:list<SnapshotPhase>}
 */
final class PlanBudgetCoherence
{
    /** @var list<string> */
    private const FINANCIAL_REQUIREMENT_KEYS = [
        'financial-assumptions',
        'revenue-model',
        'launch-funding',
    ];

    /**
     * @param  Snapshot  $snapshot
     * @return Reconciliation
     */
    public function evaluate(array $snapshot): array
    {
        $budgetSnapshot = data_get($snapshot, 'budget');
        $budgetEvidence = data_get($snapshot, 'budget.assessment_evidence');
        if (is_array($budgetSnapshot) && is_array($budgetEvidence)) {
            /** @var BudgetEvidence $budgetEvidence */
            $budget = [
                ...$budgetEvidence,
                'status' => (string) ($budgetSnapshot['status'] ?? 'not_started'),
            ];
        } else {
            $budget = null;
        }
        $financialSections = $this->financialSections($snapshot);
        $planText = implode("\n", array_column($financialSections, 'body'));
        $budgetSupportFindings = [];
        $planCorrelationFindings = [];

        if (! is_array($budget)) {
            $budgetSupportFindings[] = $this->finding(
                'budget_support',
                'missing',
                'No budget evidence was captured for this assessment round.',
                'Complete the budget and run the assessment again so its cash, cost, revenue and funding assumptions can be checked.',
            );
        } else {
            $this->evaluateBudgetSupport($budget, $budgetSupportFindings);
        }

        if ($financialSections === []) {
            $planCorrelationFindings[] = $this->finding(
                'plan_correlation',
                'missing',
                'The submitted plan has no Financial assumptions, Revenue model, or Funding and support evidence to compare with the budget.',
                'State the plan’s opening cash, runway, funding position, cost cadence and delivery capacity in the financial sections, then reassess.',
            );
        } elseif (is_array($budget)) {
            $this->evaluatePlanCorrelation($planText, $budget, $planCorrelationFindings);
        }

        $findings = [...$budgetSupportFindings, ...$planCorrelationFindings];
        $budgetSupport = $this->direction(
            $budgetSupportFindings,
            'The budget supports the submitted plan assumptions.',
            'The budget has unresolved viability or input-quality issues.',
        );
        $planCorrelation = $this->direction(
            $planCorrelationFindings,
            'The submitted financial plan correlates with the budget mechanics.',
            'The submitted financial plan cannot yet be fully reconciled with the budget.',
        );
        $unresolvedCount = count($findings);
        $missingCount = count(array_filter(
            $findings,
            fn (array $finding): bool => $finding['severity'] === 'missing',
        ));
        $score = max(0, 100 - ($missingCount * 30) - (($unresolvedCount - $missingCount) * 20));
        $approvalAvailable = $unresolvedCount === 0;
        $status = $approvalAvailable ? 'met' : ($missingCount > 0 ? 'missing' : 'review');

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'met' => 'Reconciled',
                'missing' => 'Blocked — evidence needed',
                default => 'Blocked — resolve differences',
            },
            'score' => $score,
            'summary' => $approvalAvailable
                ? 'The budget supports the submitted plan and its financial claims agree with the forecast.'
                : $unresolvedCount.' plan–budget '.($unresolvedCount === 1 ? 'issue needs' : 'issues need').' resolution before the assessment can be finalised.',
            'evidence' => [
                count($financialSections).' submitted financial plan section'.(count($financialSections) === 1 ? ' was' : 's were').' checked.',
                is_array($budget) ? 'Captured budget cash, cost, revenue and funding evidence was checked.' : 'No captured budget evidence was available.',
                $approvalAvailable
                    ? 'Budget support and plan correlation are both reconciled.'
                    : $unresolvedCount.' unresolved plan–budget '.($unresolvedCount === 1 ? 'issue remains.' : 'issues remain.'),
            ],
            'findings' => $findings,
            'approval_available' => $approvalAvailable,
            'approval_message' => $approvalAvailable
                ? 'Plan–budget coherence is reconciled.'
                : 'Resolve the plan–budget findings before finalising this assessment or issuing lender-facing material.',
            'budget_support' => $budgetSupport,
            'plan_correlation' => $planCorrelation,
            'unresolved_count' => $unresolvedCount,
        ];
    }

    /**
     * @param  Budget  $budget
     * @param  list<Finding>  $findings
     */
    private function evaluateBudgetSupport(array $budget, array &$findings): void
    {
        $status = (string) data_get($budget, 'status', 'not_started');
        $computed = data_get($budget, 'computed');
        $computed = is_array($computed) ? $computed : [];
        /** @var BudgetComputed $computed */
        $flags = data_get($budget, 'flags');
        $flags = is_array($flags) ? $flags : [];
        /** @var list<BudgetFlag> $flags */
        if ($status !== 'complete') {
            $findings[] = $this->finding(
                'budget_support',
                'missing',
                'The budget is '.str_replace('_', ' ', $status).' rather than complete.',
                'Complete the planned costs, revenue, funding and runway inputs before relying on the financial forecast.',
            );
        }

        $availableAfterLaunch = data_get($computed, 'available_after_launch');
        if (is_numeric($availableAfterLaunch) && (float) $availableAfterLaunch < 0) {
            $findings[] = $this->finding(
                'budget_support',
                'review',
                'The budget has a funding gap of '.$this->money(abs((float) $availableAfterLaunch)).' after planned launch costs.',
                'Reduce, defer or fund the launch costs before treating the plan as financially supported.',
            );
        }

        $expectedRunway = data_get($budget, 'expected_runway_months');
        $actualRunway = data_get($computed, 'runway_months');
        $openEnded = (bool) data_get($computed, 'runway_open_ended', false);
        if (is_numeric($actualRunway) && ! $openEnded && (float) $actualRunway <= 0) {
            $findings[] = $this->finding(
                'budget_support',
                'review',
                'The budget currently shows 0 months of runway.',
                'Correct the opening cash, timing, costs, revenue or funding before relying on this plan.',
            );
        } elseif (is_numeric($expectedRunway) && is_numeric($actualRunway) && ! $openEnded && (float) $actualRunway + 2 < (float) $expectedRunway) {
            $findings[] = $this->finding(
                'budget_support',
                'review',
                sprintf(
                    'The budget targets %d months of runway but forecasts only %d months.',
                    (int) round((float) $expectedRunway),
                    (int) round((float) $actualRunway),
                ),
                'Revise the costs, revenue, cash or funding until the forecast supports the stated runway target.',
            );
        }

        if ((int) data_get($computed, 'input_count', 0) > 0 && ! (bool) data_get($computed, 'break_even_reached', false)) {
            $findings[] = $this->finding(
                'budget_support',
                'review',
                'The forecast does not show break-even within its modelled period.',
                'Recheck price, volume, margins, costs and timing before relying on the plan externally.',
            );
        }

        $this->addInputQualityFindings($flags, $findings);

        $fundingReadiness = $budget['funding_readiness'] ?? null;
        if (is_array($fundingReadiness)) {
            /** @var FundingReadiness $fundingReadiness */
            $this->addFundingReadinessFindings($fundingReadiness, $availableAfterLaunch, $findings);
        }
    }

    /**
     * @param  Budget  $budget
     * @param  list<Finding>  $findings
     */
    private function evaluatePlanCorrelation(string $planText, array $budget, array &$findings): void
    {
        $computed = data_get($budget, 'computed');
        $computed = is_array($computed) ? $computed : [];
        /** @var BudgetComputed $computed */
        $assumptions = data_get($budget, 'assumptions');
        $assumptions = is_array($assumptions) ? $assumptions : [];
        /** @var BudgetAssumptions $assumptions */
        $planRunway = $this->runwayMonths($planText);
        $planOpeningCash = $this->openingCash($planText);
        $planCapacity = $this->monthlyCapacity($planText);
        $planMonthlyCosts = $this->monthlyOperatingCosts($planText);
        $hasCheckableClaim = $planRunway !== null || $planOpeningCash !== null || $planCapacity !== null || $planMonthlyCosts !== null || $this->claimsDebtFree($planText);

        if (! $hasCheckableClaim) {
            $findings[] = $this->finding(
                'plan_correlation',
                'missing',
                'The financial plan does not state a checkable opening-cash, runway, funding or delivery-capacity assumption.',
                'Add the specific assumptions the budget relies on, including values and cadence where relevant, then reassess.',
            );
        }

        $actualRunway = data_get($computed, 'runway_months');
        $openEnded = (bool) data_get($computed, 'runway_open_ended', false);
        if ($planRunway !== null && is_numeric($actualRunway) && ! $openEnded && (float) $actualRunway + 2 < $planRunway) {
            $findings[] = $this->finding(
                'plan_correlation',
                'review',
                sprintf(
                    'The plan states %d months of runway, but the budget forecasts %d months.',
                    (int) round($planRunway),
                    (int) round((float) $actualRunway),
                ),
                'Align the plan’s runway commitment with the corrected budget forecast.',
            );
        }

        $budgetOpeningCash = data_get($assumptions, 'opening_cash_balance');
        if ($planOpeningCash !== null && is_numeric($budgetOpeningCash) && ! $this->withinTolerance($planOpeningCash, (float) $budgetOpeningCash)) {
            $findings[] = $this->finding(
                'plan_correlation',
                'review',
                'The plan states opening cash of '.$this->money($planOpeningCash).', but the budget starts with '.$this->money((float) $budgetOpeningCash).'.',
                'Use the verified opening-cash balance in both the plan and budget.',
            );
        }

        $revenueRows = data_get($budget, 'revenue_forecast');
        $revenueRows = is_array($revenueRows) ? $revenueRows : [];
        /** @var list<BudgetRow> $revenueRows */
        if ($planCapacity !== null) {
            foreach ($revenueRows as $row) {
                if (! is_numeric($row['monthly_capacity_units'] ?? null)) {
                    continue;
                }

                $budgetCapacity = (float) $row['monthly_capacity_units'];
                if ($budgetCapacity > $planCapacity * 1.05) {
                    $findings[] = $this->finding(
                        'plan_correlation',
                        'review',
                        sprintf(
                            'The budget allows %.0f units per month for "%s", above the plan’s stated capacity of %.0f.',
                            $budgetCapacity,
                            $this->rowLabel($row),
                            $planCapacity,
                        ),
                        'Reduce the forecast capacity or update the plan with evidence for the higher delivery capacity.',
                    );
                }
            }
        }

        $fixedCosts = data_get($budget, 'monthly_fixed_costs');
        $fixedCosts = is_array($fixedCosts) ? $fixedCosts : [];
        /** @var list<BudgetRow> $fixedCosts */
        foreach ($fixedCosts as $row) {
            if ((string) ($row['cadence'] ?? '') !== 'monthly') {
                continue;
            }

            $label = $this->rowLabel($row);
            if ($label !== 'Budget cost' && $this->planDescribesAnnualCost($planText, $label)) {
                $findings[] = $this->finding(
                    'plan_correlation',
                    'review',
                    'The plan describes "'.$label.'" as an annual cost, but the budget treats it as monthly.',
                    'Correct the cost cadence or revise the plan so both use the same monthly cost basis.',
                );
            }
        }

        $this->addMissingPlannedCostFindings($planText, $fixedCosts, $findings);

        $budgetMonthlyCosts = data_get($computed, 'monthly_fixed_costs');
        if ($planMonthlyCosts !== null && is_numeric($budgetMonthlyCosts) && ! $this->withinTolerance($planMonthlyCosts, (float) $budgetMonthlyCosts)) {
            $findings[] = $this->finding(
                'plan_correlation',
                'review',
                'The plan states monthly operating costs of '.$this->money($planMonthlyCosts).', but the budget uses '.$this->money((float) $budgetMonthlyCosts).' of monthly fixed costs.',
                'Reconcile the recurring cost base in Financial assumptions and Budget before relying on the stated runway or funding requirement.',
            );
        }

        if ($this->claimsDebtFree($planText) && $this->budgetUsesDebtFunding($budget)) {
            $findings[] = $this->finding(
                'plan_correlation',
                'review',
                'The plan describes the venture as debt-free, but the budget includes debt funding.',
                'Remove the debt funding from the budget or explain the funding position consistently in the plan.',
            );
        }
    }

    /**
     * @param  list<BudgetFlag>  $flags
     * @param  list<Finding>  $findings
     */
    private function addInputQualityFindings(array $flags, array &$findings): void
    {
        $keys = [
            'fixed_cost_cadences_need_confirmation' => 'Confirm the billing cadence for the fixed-cost rows before relying on the forecast.',
            'revenue_capacity_needs_confirmation' => 'Set and confirm the maximum delivery capacity for each affected revenue line.',
            'contractor_delivery_cost_needs_confirmation' => 'Confirm the cost of delivery beyond founder capacity before relying on projected revenue.',
            'fixed_cost_sources_need_verification' => 'Verify fixed costs against current source records before external issue.',
            'revenue_sources_need_verification' => 'Verify revenue with pipeline, contract or pricing evidence before external issue.',
            'cash_timing_needs_verification' => 'Verify cash timing from current records before relying on the forecast.',
            'funding_position_needs_confirmation' => 'Confirm whether the plan is self-funded or requires external funding.',
        ];

        foreach ($flags as $flag) {
            $key = (string) ($flag['key'] ?? '');
            if (! array_key_exists($key, $keys)) {
                continue;
            }

            $findings[] = $this->finding(
                'budget_support',
                'missing',
                (string) ($flag['message'] ?? $flag['title'] ?? 'A required budget input has not been confirmed.'),
                $keys[$key],
            );
        }
    }

    /**
     * Adds the fuller readiness diagnostics which are captured with new assessment
     * snapshots. These diagnostics are intentionally separate from the existing
     * small set of gating flags: they explain the specific budget rows and
     * assumptions a founder must correct.
     *
     * @param  FundingReadiness  $readiness
     * @param  float|int|mixed  $availableAfterLaunch
     * @param  list<Finding>  $findings
     */
    private function addFundingReadinessFindings(array $readiness, mixed $availableAfterLaunch, array &$findings): void
    {
        $requiredFunding = $readiness['required_additional_funding'] ?? null;
        $sameAsLaunchGap = is_numeric($requiredFunding)
            && is_numeric($availableAfterLaunch)
            && (float) $availableAfterLaunch < 0
            && $this->withinTolerance((float) $requiredFunding, abs((float) $availableAfterLaunch));

        if (is_numeric($requiredFunding) && (float) $requiredFunding > 0 && ! $sameAsLaunchGap) {
            $this->addFindingOnce(
                $findings,
                'budget_support',
                'review',
                'The forecast requires '.$this->money((float) $requiredFunding).' of additional funding to cover the modelled cash trough and planned operating buffer.',
                'Confirm the cost cadence, opening cash, forecast timing, revenue capacity and funding position before treating this as a funding requirement.',
            );
        }

        foreach ($readiness['warnings'] ?? [] as $warning) {
            $message = trim($warning);
            if ($message === '' || str_starts_with($message, 'Tax not configured:')) {
                continue;
            }

            $this->addFindingOnce(
                $findings,
                'budget_support',
                $this->warningNeedsEvidence($message) ? 'missing' : 'review',
                $message,
                $this->warningNextAction($message),
            );
        }
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function addFindingOnce(array &$findings, string $category, string $severity, string $message, string $nextAction): void
    {
        $normalisedMessage = strtolower(trim($message));
        foreach ($findings as $finding) {
            if (strtolower(trim($finding['message'])) === $normalisedMessage) {
                return;
            }
        }

        $findings[] = $this->finding($category, $severity, $message, $nextAction);
    }

    private function warningNeedsEvidence(string $warning): bool
    {
        if (str_contains(strtolower($warning), 'needs review')) {
            return false;
        }

        return preg_match('/\b(?:confirm|missing|incomplete|not confirmed|set the|verify)\b/i', $warning) === 1;
    }

    private function warningNextAction(string $warning): string
    {
        $normalised = strtolower($warning);

        if (str_contains($normalised, 'funding') || str_contains($normalised, 'cash') || str_contains($normalised, 'runway') || str_contains($normalised, 'break-even')) {
            return 'Update Budget > Funding and runway with the verified cash position, funding source, timing and funding purpose.';
        }

        if (str_contains($normalised, 'fixed cost') || str_contains($normalised, 'owner compensation') || str_contains($normalised, 'cadence')) {
            return 'Update Budget > Monthly fixed costs so every recurring cost has one verified amount, cadence and source.';
        }

        if (str_contains($normalised, 'revenue') || str_contains($normalised, 'capacity') || str_contains($normalised, 'contractor')) {
            return 'Update Budget > Revenue forecast with supported price, growth cadence, delivery capacity and contractor cost assumptions.';
        }

        return 'Update Budget > Financial assumptions with the missing source, timing and basis, then check the revised forecast against the financial plan.';
    }

    /**
     * @param  list<BudgetRow>  $fixedCosts
     * @param  list<Finding>  $findings
     */
    private function addMissingPlannedCostFindings(string $planText, array $fixedCosts, array &$findings): void
    {
        $budgetLabels = strtolower(implode(' ', array_map(
            fn (array $row): string => $this->rowLabel($row),
            $fixedCosts,
        )));
        $commitments = [
            'professional indemnity insurance' => [
                'plan_pattern' => '/\b(?:professional\s+indemnity|pi)\s+insurance\b/i',
                'budget_pattern' => '/\b(?:professional\s+indemnity|pi)\s+insurance\b/i',
            ],
            'trademark registration and protection' => [
                'plan_pattern' => '/\b(?:trade\s?mark|trademark)\b/i',
                'budget_pattern' => '/\b(?:trade\s?mark|trademark|intellectual\s+property)\b/i',
            ],
        ];

        foreach ($commitments as $commitment => $patterns) {
            if (preg_match($patterns['plan_pattern'], $planText) !== 1 || preg_match($patterns['budget_pattern'], $budgetLabels) === 1) {
                continue;
            }

            $this->addFindingOnce(
                $findings,
                'plan_correlation',
                'review',
                'The plan refers to '.$commitment.', but the budget does not name a matching cost row.',
                'Add the expected cost, timing and source in Budget > Monthly fixed costs or launch costs, then align the financial plan statement.',
            );
        }
    }

    /**
     * @param  Snapshot  $snapshot
     * @return list<array{title:string,body:string}>
     */
    private function financialSections(array $snapshot): array
    {
        $sections = [];
        $phases = $snapshot['phases'] ?? [];

        foreach ($phases as $phase) {
            foreach ($phase['sections'] ?? [] as $section) {
                $body = trim((string) ($section['body'] ?? ''));
                $isFinancialRequirement = in_array((string) ($section['requirement_key'] ?? ''), self::FINANCIAL_REQUIREMENT_KEYS, true);
                if ($body === '' || (! $isFinancialRequirement && ! $this->containsFinancialClaim($body))) {
                    continue;
                }

                $sections[] = [
                    'title' => trim((string) ($section['title'] ?? 'Financial plan')) ?: 'Financial plan',
                    'body' => $body,
                ];
            }
        }

        return $sections;
    }

    private function runwayMonths(string $planText): ?float
    {
        $patterns = [
            '/\\b(\\d{1,2}(?:\\.\\d+)?)\\s*(?:months?|mths?)\\s*(?:of\\s*)?(?:cash\\s*)?runway\\b/i',
            '/\\brunway(?:\\s+(?:of|target(?:ing)?|is|:))?\\s*(\\d{1,2}(?:\\.\\d+)?)\\s*(?:months?|mths?)\\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $planText, $matches) === 1 && is_numeric($matches[1])) {
                return max(0.0, (float) $matches[1]);
            }
        }

        return null;
    }

    private function openingCash(string $planText): ?float
    {
        if (preg_match('/\\b(?:opening|starting|initial)\\s+(?:cash|cash balance|cash position)\\D{0,35}?\\$?\\s*([0-9][0-9,]*(?:\\.\\d{1,2})?)/i', $planText, $matches) !== 1) {
            return null;
        }

        $amount = str_replace(',', '', (string) $matches[1]);

        return is_numeric($amount) ? max(0.0, (float) $amount) : null;
    }

    private function monthlyCapacity(string $planText): ?float
    {
        if (preg_match('/\\b(?:capacity|delivery capacity|serve|services?|clients?)\\D{0,60}?(\\d+(?:\\.\\d+)?)\\s*(?:[a-z][a-z -]{0,25})?\\s*(?:per\\s*month|\\/\\s*month|monthly)\\b/is', $planText, $matches) !== 1) {
            return null;
        }

        return is_numeric($matches[1]) ? max(0.0, (float) $matches[1]) : null;
    }

    private function monthlyOperatingCosts(string $planText): ?float
    {
        $patterns = [
            '/\b(?:operating|fixed|overhead)\s+costs?\D{0,30}?\$?\s*([0-9][0-9,]*(?:\.\d{1,2})?)\s*(?:\/|per\s*)\s*month\b/i',
            '/\$?\s*([0-9][0-9,]*(?:\.\d{1,2})?)\s*(?:\/|per\s*)\s*month\D{0,30}?\b(?:operating|fixed|overhead)\s+costs?\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $planText, $matches) === 1) {
                $amount = str_replace(',', '', (string) $matches[1]);

                return is_numeric($amount) ? max(0.0, (float) $amount) : null;
            }
        }

        return null;
    }

    private function containsFinancialClaim(string $body): bool
    {
        return preg_match('/\b(?:runway|opening\s+cash|starting\s+cash|monthly\s+(?:operating|fixed|overhead)\s+costs?|funding|debt[- ]?free|delivery\s+capacity|(?:professional\s+indemnity|pi)\s+insurance|trade\s?mark|trademark)\b/i', $body) === 1;
    }

    private function claimsDebtFree(string $planText): bool
    {
        return preg_match('/\\b(?:debt[- ]?free|no debt|without debt)\\b/i', $planText) === 1;
    }

    /** @param Budget $budget */
    private function budgetUsesDebtFunding(array $budget): bool
    {
        foreach ([
            $budget['funding_sources'] ?? [],
            $budget['funding_scenarios'] ?? [],
        ] as $rows) {
            foreach ($rows as $row) {
                if (! is_numeric($row['amount'] ?? null) || (float) $row['amount'] <= 0) {
                    continue;
                }

                $label = (string) ($row['label'] ?? '');
                $type = (string) ($row['type'] ?? '');
                if ($type === 'bank_loan' || preg_match('/\\b(?:loan|debt|overdraft|credit)\\b/i', $label) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function planDescribesAnnualCost(string $planText, string $label): bool
    {
        $quotedLabel = preg_quote($label, '/');
        $annual = '(?:annual|annually|per annum|per year|yearly)';

        return preg_match('/'.$quotedLabel.'.{0,100}'.$annual.'/is', $planText) === 1
            || preg_match('/'.$annual.'.{0,100}'.$quotedLabel.'/is', $planText) === 1;
    }

    /** @param BudgetRow $row */
    private function rowLabel(array $row): string
    {
        $label = trim((string) ($row['label'] ?? ''));

        return $label === '' ? 'Budget cost' : $label;
    }

    /**
     * @param  list<Finding>  $findings
     * @return Direction
     */
    private function direction(array $findings, string $metSummary, string $unresolvedSummary): array
    {
        $missing = count(array_filter(
            $findings,
            fn (array $finding): bool => $finding['severity'] === 'missing',
        ));
        $status = $findings === [] ? 'met' : ($missing > 0 ? 'missing' : 'review');

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'met' => 'Met',
                'missing' => 'Evidence needed',
                default => 'Needs review',
            },
            'summary' => $findings === [] ? $metSummary : $unresolvedSummary,
            'unresolved_count' => count($findings),
        ];
    }

    /** @return Finding */
    private function finding(string $category, string $severity, string $message, string $nextAction): array
    {
        return [
            'category' => $category,
            'severity' => $severity,
            'message' => $message,
            'next_action' => $nextAction,
        ];
    }

    private function withinTolerance(float $left, float $right): bool
    {
        return abs($left - $right) <= max(1.0, max(abs($left), abs($right)) * 0.05);
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 0, '.', ',');
    }
}
