<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\PlanSection;
use Illuminate\Support\Str;

final class ExternalIssueReview
{
    /**
     * @return array{blocking_reasons:array<int,string>,warnings:array<int,string>}
     */
    public function evaluate(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $bodies = $plan->sections
            ->map(fn (PlanSection $section): string => trim((string) $section->body))
            ->filter()
            ->values()
            ->all();
        $content = implode("\n", $bodies);
        $blocking = [];
        $warnings = [];

        if (preg_match('/\{\{\s*[^}]+\s*\}\}|\[\s*(?:blank|tbd|todo|insert|business type|founder(?:\'s)? name|company name|owner name|business name|location)\s*\]/i', $content) === 1
            || preg_match('/\b(?:business type|founder(?:\'s)? name|company name)\s*:\s*(?:[,.;-]*\s*)?(?:\R|$)/im', $content) === 1
            || preg_match('/(?:^|\R)\s*(?:,\s*){2,}(?=\S)/', $content) === 1
            || preg_match('/\b(?:is|are|by|for)\s*-\s*(?=(?:an?|the)\b)/i', $content) === 1) {
            $blocking[] = 'Resolve blank merge fields or placeholder identity details before external issue.';
        }

        if (preg_match('/\bchatgbt\b/i', $content) === 1) {
            $blocking[] = 'Correct product-name typos before external issue.';
        }

        if ($this->hasDatedUpdateHeading($content)) {
            $blocking[] = 'Integrate dated update headings into the relevant plan sections before external issue.';
        }

        if (preg_match('/\*\*[^*\n]*\s+\*\*(?:\s|[-.,;:!?]|$)/', $content) === 1) {
            $blocking[] = 'Resolve raw Markdown emphasis markers before external issue.';
        }

        if (preg_match('/\b\w{4,}(?:\.\.\.|…)(?:\s|$)/u', $content) === 1) {
            $blocking[] = 'Replace truncated text fragments with complete sentences before external issue.';
        }

        if (preg_match('/\bis\s+(?:a\s+)?(?:based|located|operated)\b|\bthe\s+(?:service|business|company)\s+is\s+(?:an?\s+)?(?:-\s*)?(?:immersive|based)\b/i', $content) === 1) {
            $blocking[] = 'Resolve incomplete identity or offer wording before external issue.';
        }

        $budget = $plan->budgetRunway;
        $computed = (array) ($budget?->computed ?? []);
        $monthly = collect((array) ($computed['monthly_detail'] ?? []));
        $monthTwelve = $monthly->first(fn (mixed $row): bool => (int) data_get($row, 'month') === 12);
        $monthThirteen = $monthly->first(fn (mixed $row): bool => (int) data_get($row, 'month') === 13);
        $monthTwelveRevenue = (float) data_get($monthTwelve, 'revenue', 0);
        $monthThirteenRevenue = (float) data_get($monthThirteen, 'revenue', 0);

        if ($monthTwelveRevenue > 0 && $monthThirteenRevenue < ($monthTwelveRevenue * 0.8)) {
            $blocking[] = 'Month 13 revenue drops materially below the Year 1 exit run-rate. Confirm a seasonal averaging basis or revise the forecast before external issue.';
        }

        if ($budget instanceof EntrepreneurBudget) {
            $fixedCostReconciliation = $this->fixedCostReconciliation($budget, $computed);
            if (! (bool) ($fixedCostReconciliation['reconciled'] ?? true)) {
                $blocking[] = 'Fixed-cost trace does not reconcile to the model base. Add missing fixed-cost rows or relabel the table as a subset before external issue.';
            }

            $ambiguousOwnerCompensation = collect((array) ($budget->monthly_fixed_costs ?? []))
                ->first(fn (array $row): bool => $this->isAmbiguousOwnerCompensation((string) ($row['label'] ?? '')));
            if (is_array($ambiguousOwnerCompensation)) {
                $blocking[] = 'Clarify the owner compensation row so weekly, monthly, and annual figures are not concatenated.';
            }
        }

        if (! $this->industryEvidenceIsCited($plan)) {
            $blocking[] = 'Cite the source or attach evidence for the industry and customer-demand claims before external issue.';
        }

        foreach ($this->scaleMismatchReasons($content, $monthTwelveRevenue) as $reason) {
            $blocking[] = $reason;
        }

        if ($this->hasUnreconciledPricingEvidence($content)) {
            $blocking[] = 'Reconcile historical rates with current pricing before relying on the revenue assumptions externally.';
        }

        $cashTrough = $monthly
            ->filter(fn (mixed $row): bool => is_numeric(data_get($row, 'cumulative_cash')))
            ->min(fn (mixed $row): float => (float) data_get($row, 'cumulative_cash'));
        $signalsNoCapitalNeed = preg_match('/\b(?:no external capital|no borrowing|borrowing (?:is )?not anticipated|no outside funding|self[- ]funded)\b/i', $content) === 1;
        $fundingDecision = $budget instanceof EntrepreneurBudget
            ? (new BudgetFundingReadiness)->evaluate($budget)
            : null;
        $requiredAdditionalFunding = (float) ($fundingDecision['required_additional_funding'] ?? 0);
        $declaredFundingPosition = (string) data_get($computed, 'assumptions.funding_position', 'undecided');

        if (($requiredAdditionalFunding > 0 || (is_numeric($cashTrough) && $cashTrough < 0)) && $signalsNoCapitalNeed) {
            $blocking[] = is_numeric($cashTrough) && $cashTrough < 0
                ? 'The written funding position conflicts with a negative forecast cash balance.'
                : 'The written funding position conflicts with the forecast funding requirement.';
        }

        if ($declaredFundingPosition === 'self_funded' && $requiredAdditionalFunding > 0) {
            $blocking[] = 'The saved self-funded position conflicts with the required additional funding in the budget.';
        }

        if ($declaredFundingPosition === 'external_funding' && $signalsNoCapitalNeed) {
            $blocking[] = 'The saved external funding position conflicts with the written self-funded or no-capital statement.';
        }

        if ($this->hasRepeatedSentence($bodies) || $this->hasRepeatedSignaturePhrase($content)) {
            $warnings[] = 'Repeated narrative phrasing detected. Consolidate duplicated wording before sharing the plan externally.';
        }

        return [
            'blocking_reasons' => array_values(array_unique($blocking)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<int, string>  $bodies
     */
    private function hasRepeatedSentence(array $bodies): bool
    {
        $sentences = collect($bodies)
            ->flatMap(function (string $body): array {
                $plain = Str::of(strip_tags(Str::markdown($body)))->squish()->toString();

                return preg_split('/(?<=[.!?])\s+/', $plain) ?: [];
            })
            ->map(fn (string $sentence): string => strtolower(trim(preg_replace('/\s+/', ' ', $sentence) ?? '')))
            ->filter(fn (string $sentence): bool => strlen($sentence) >= 45)
            ->countBy();

        return $sentences->contains(fn (int $count): bool => $count >= 3);
    }

    private function hasDatedUpdateHeading(string $content): bool
    {
        $monthPattern = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

        return preg_match('/^\s*(?:#{1,6}\s*)?(?:(?:update(?:d)?|amendment|patch)\b.*(?:20\d{2}|'.$monthPattern.')|(?:'.$monthPattern.')\s+\d{1,2}(?:st|nd|rd|th)?\s*(?:update|amendment|patch)?)/im', $content) === 1;
    }

    private function industryEvidenceIsCited(BusinessPlan $plan): bool
    {
        $industrySection = $plan->sections->first(fn (PlanSection $section): bool => (
            (string) data_get($section->metadata, 'requirement_key') === 'industry-context'
            || $section->key === 'founder-market-industry-context'
        ));

        if (! $industrySection instanceof PlanSection || $industrySection->completeness_status !== PlanSection::STATUS_COMPLETE) {
            return true;
        }

        if (trim((string) $industrySection->body) === '') {
            return false;
        }

        if (count((array) $industrySection->attached_document_ids) > 0) {
            return true;
        }

        return preg_match('/(?:https?:\/\/|www\.|\[[^\]]+\]\([^\)]+\)|\b(?:source|sources|reference|references)\s*:)/i', (string) $industrySection->body) === 1;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array{listed_total:float,model_base:float,difference:float,reconciled:bool}
     */
    private function fixedCostReconciliation(EntrepreneurBudget $budget, array $computed): array
    {
        $listedTotal = collect((array) ($budget->monthly_fixed_costs ?? []))
            ->sum(fn (array $row): float => $this->monthlyEquivalent($row));
        $modelBase = (float) ($computed['monthly_fixed_costs'] ?? data_get($computed, 'base_scenario.summary.year_one_monthly_fixed_costs', 0));
        $difference = round($modelBase - $listedTotal, 2);

        return [
            'listed_total' => round($listedTotal, 2),
            'model_base' => round($modelBase, 2),
            'difference' => $difference,
            'reconciled' => abs($difference) < 1.0,
        ];
    }

    private function isAmbiguousOwnerCompensation(string $label): bool
    {
        $normalised = strtolower($label);

        return preg_match('/owners?\s+compensation|owner\s+draw|founder\s+pay|founder\s+salary/', $normalised) === 1
            && preg_match_all('/\$?\d[\d,]*(?:\.\d+)?\s*(?:wk|week|weekly|pa|p\.a\.|annual|annually|year|yr)?/i', $label) >= 2;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function monthlyEquivalent(array $row): float
    {
        $amount = (float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1);

        return match ($row['cadence'] ?? 'monthly') {
            'weekly' => $amount * (52 / 12),
            'fortnightly' => $amount * (26 / 12),
            'quarterly' => $amount / 3,
            'annual' => $amount / 12,
            default => $amount,
        };
    }

    /**
     * @return array<int, string>
     */
    private function scaleMismatchReasons(string $content, float $monthTwelveRevenue): array
    {
        if ($monthTwelveRevenue <= 0) {
            return [];
        }

        $reasons = [];
        $annualTarget = $this->writtenAnnualRevenueTarget($content);
        if ($annualTarget !== null && $monthTwelveRevenue > $annualTarget) {
            $reasons[] = 'Budget Year 1 December revenue exceeds the written annual revenue target. Align plan targets and forecast scale before external issue.';
        }

        $capacityRevenue = $this->writtenMonthlyCapacityRevenue($content);
        if ($capacityRevenue !== null && $capacityRevenue > 0 && $monthTwelveRevenue > ($capacityRevenue * 5)) {
            $reasons[] = 'Budget Year 1 December revenue materially exceeds the written monthly capacity and pricing evidence. Align capacity, pricing, and forecast scale before external issue.';
        }

        return $reasons;
    }

    private function writtenAnnualRevenueTarget(string $content): ?float
    {
        $matches = [];
        preg_match_all('/(?:revenue|sales|turnover|target)[^.\n$]{0,80}\$?\s*([0-9][0-9,.]*)\s*(k|m)?\s*(?:-|to|and)\s*\$?\s*([0-9][0-9,.]*)\s*(k|m)?[^.\n]{0,80}(?:revenue|sales|turnover|target)?/i', $content, $matches, PREG_SET_ORDER);

        $values = collect($matches)
            ->map(fn (array $match): float => max(
                $this->moneyNumber((string) $match[1], (string) ($match[2] ?? '')),
                $this->moneyNumber((string) $match[3], (string) ($match[4] ?? '')),
            ))
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        return $values->isEmpty() ? null : (float) $values->max();
    }

    private function writtenMonthlyCapacityRevenue(string $content): ?float
    {
        $capacityMatched = preg_match('/([0-9]+)\s*(?:-|to)\s*([0-9]+)\s+(?:[A-Za-z]+\s+){0,3}(?:intensives|workshops|sessions|projects|clients)\s*(?:\/|per\s+)?month/i', $content, $capacity);
        $priceMatched = preg_match('/\$\s*([0-9][0-9,.]*)\s*(k|m)?\s*(?:\+?\s*GST)?\s*(?:\/|per\s+)(?:day|session|intensive|workshop|project)/i', $content, $price);

        if ($capacityMatched !== 1 || $priceMatched !== 1) {
            return null;
        }

        $maxCapacity = max((float) $capacity[1], (float) $capacity[2]);
        $unitPrice = $this->moneyNumber((string) $price[1], (string) ($price[2] ?? ''));

        return $maxCapacity > 0 && $unitPrice > 0 ? round($maxCapacity * $unitPrice, 2) : null;
    }

    private function hasUnreconciledPricingEvidence(string $content): bool
    {
        $hasHistoricalRange = preg_match('/(?:historical|historic|previous|past|earlier)[^.\n$]{0,80}\$?\s*[0-9][0-9,.]*\s*(?:-|to)\s*\$?\s*[0-9][0-9,.]*(?:\s*\+?\s*GST)?(?:\s*\/?\s*(?:day|session|intensive|workshop))?/i', $content) === 1;
        $hasCurrentPrice = preg_match('/(?:current|now|today|standard|stated)[^.\n$]{0,80}\$?\s*[0-9][0-9,.]*(?:\s*\+?\s*GST)?(?:\s*\/?\s*(?:day|session|intensive|workshop))?/i', $content) === 1;
        $hasReconciliation = preg_match('/\b(?:reconcil|bridge|because|now priced|current pricing reflects|price increase|gst-exclusive|gst exclusive)\b/i', $content) === 1;

        return $hasHistoricalRange && $hasCurrentPrice && ! $hasReconciliation;
    }

    private function hasRepeatedSignaturePhrase(string $content): bool
    {
        return substr_count(
            strtolower($content),
            'strategic thinking, creative problem-solving and practical implementation',
        ) >= 2;
    }

    private function moneyNumber(string $value, string $suffix = ''): float
    {
        $number = (float) str_replace(',', '', $value);
        $suffix = strtolower($suffix);

        return match ($suffix) {
            'm' => $number * 1_000_000,
            'k' => $number * 1_000,
            default => $number,
        };
    }
}
