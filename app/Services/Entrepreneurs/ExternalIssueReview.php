<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
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

        if (preg_match('/\{\{\s*[^}]+\s*\}\}|\[\s*(?:business type|founder(?:\'s)? name|company name)\s*\]/i', $content) === 1
            || preg_match('/\b(?:business type|founder(?:\'s)? name|company name)\s*:\s*(?:[,.;-]*\s*)?(?:\R|$)/im', $content) === 1) {
            $blocking[] = 'Resolve blank merge fields or placeholder identity details before external issue.';
        }

        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:update(?:d)?|amendment|patch)\b.*(?:20\d{2}|jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)/im', $content) === 1) {
            $blocking[] = 'Integrate dated update headings into the relevant plan sections before external issue.';
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

        $cashTrough = $monthly
            ->filter(fn (mixed $row): bool => is_numeric(data_get($row, 'cumulative_cash')))
            ->min(fn (mixed $row): float => (float) data_get($row, 'cumulative_cash'));
        $signalsNoCapitalNeed = preg_match('/\b(?:no external capital|no borrowing|borrowing (?:is )?not anticipated|no outside funding|self[- ]funded)\b/i', $content) === 1;

        if (is_numeric($cashTrough) && $cashTrough < 0 && $signalsNoCapitalNeed) {
            $blocking[] = 'The written funding position conflicts with a negative forecast cash balance.';
        }

        if ($this->hasRepeatedSentence($bodies)) {
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
}
