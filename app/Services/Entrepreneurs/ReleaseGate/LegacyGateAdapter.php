<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\PlanBudgetGuide;

/** @phpstan-import-type Issue from PlanBudgetGuide */
final class LegacyGateAdapter
{
    public static function ownsGuideIssue(string $key): bool
    {
        return in_array($key, ['opening_cash_balance', 'owner_weekly_pay', 'break_even_year', 'monthly_revenue_ceiling'], true)
            || preg_match('/^(asset|horizon)_\d+$/D', $key) === 1;
    }

    /**
     * @param  list<Issue>  $legacy
     * @return list<Issue>
     */
    public function guide(GateReport $report, array $legacy): array
    {
        $issues = array_values(array_filter($legacy, fn (array $issue): bool => ! self::ownsGuideIssue($issue['key'])));
        foreach ($report->findings() as $finding) {
            $id = $finding['rule_id'];
            if ($finding['accepted'] || (! str_starts_with($id, 'PM-') && ! in_array($id, ['MI-05', 'MI-06'], true))) {
                continue;
            }
            $check = $finding['subcheck'];
            $issues[] = ['key' => $id.':'.$check->id, 'title' => RuleCatalogue::get($id)->name,
                'detail' => $id.' · '.($check->status === CheckStatus::CannotVerify ? 'Cannot verify: ' : '').$check->message,
                'action' => 'Review the release check, source values and missing information with the advisor.',
                'requirement' => 'financial-assumptions', 'focus' => match ($id) {
                    'PM-02' => 'fixed-costs', 'PM-03', 'PM-12' => 'funding-sources', 'PM-04' => 'revenue-forecast',
                    'PM-09', 'MI-05', 'MI-06' => 'future-costs', default => 'budget-overview',
                }];
        }

        return $issues;
    }
}
