<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Models\StrategicBudget;
use App\Services\Entrepreneurs\BudgetCalculator;
use Illuminate\Support\Str;

/**
 * Checks the deliberate links between the client-owned plan commitments and
 * the budget rows that fund or deliver them. The result is entirely
 * deterministic so it can safely gate advisor approval.
 *
 * @phpstan-type CoherenceFinding array{severity:string,message:string,next_action:string}
 * @phpstan-type Coherence array{status:string,status_label:string,score:int,summary:string,evidence:list<string>,findings:list<CoherenceFinding>,approval_available:bool,approval_message:string,linked_driver_count:int,material_row_count:int,unresolved_count:int}
 * @phpstan-type CoherenceCriterion array{key:'plan_budget_coherence',title:'Plan–budget coherence',status:'met'|'review'|'missing',status_label:'Met'|'Missing'|'Needs review',score:int,summary:string,evidence:list<string>,blocking:bool,findings:list<CoherenceFinding>}
 */
final class StrategicBudgetPlanBudgetCoherence
{
    public const CATEGORY_IMPLEMENTATION_COSTS = 'implementation_costs';

    public const CATEGORY_MONTHLY_FIXED_COSTS = 'monthly_fixed_costs';

    public const CATEGORY_REVENUE_FORECAST = 'revenue_forecast';

    public const CATEGORY_FUNDING_SOURCES = 'funding_sources';

    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
        self::CATEGORY_IMPLEMENTATION_COSTS => 'implementation cost',
        self::CATEGORY_MONTHLY_FIXED_COSTS => 'monthly operating cost',
        self::CATEGORY_REVENUE_FORECAST => 'revenue forecast',
        self::CATEGORY_FUNDING_SOURCES => 'funding source',
    ];

    /** @var list<string> */
    private const CATEGORIES = [
        self::CATEGORY_IMPLEMENTATION_COSTS,
        self::CATEGORY_MONTHLY_FIXED_COSTS,
        self::CATEGORY_REVENUE_FORECAST,
        self::CATEGORY_FUNDING_SOURCES,
    ];

    /**
     * @return array{
     *     status:string,status_label:string,score:int,summary:string,evidence:list<string>,
     *     findings:list<array{severity:string,message:string,next_action:string}>,
     *     approval_available:bool,approval_message:string,linked_driver_count:int,
     *     material_row_count:int,unresolved_count:int
     * }
     */
    public function evaluate(StrategicBudget $budget): array
    {
        $sections = $this->sections($budget);
        $drivers = $this->drivers($sections);
        $driversByKey = [];

        foreach ($drivers as $driver) {
            $driversByKey[$driver['key']] = $driver;
        }

        $rowsByCategory = [];
        foreach (self::CATEGORIES as $category) {
            $rowsByCategory[$category] = $this->materialRows((array) ($budget->{$category} ?? []), $category);
        }

        $materialRows = array_merge(...array_values($rowsByCategory));
        $findings = [];
        $linkedDriverCount = 0;

        foreach ($drivers as $driver) {
            $linkedRows = array_values(array_filter(
                $rowsByCategory[$driver['category']] ?? [],
                fn (array $row): bool => $row['plan_financial_driver_key'] === $driver['key'],
            ));

            if ($linkedRows === []) {
                $findings[] = $this->finding(
                    'missing',
                    sprintf(
                        'Plan driver "%s" in %s has no linked %s row.',
                        $driver['label'],
                        $driver['section_title'],
                        $this->categoryLabel($driver['category']),
                    ),
                    sprintf(
                        'Add the %s row that supports this plan commitment, then link it to "%s".',
                        $this->categoryLabel($driver['category']),
                        $driver['label'],
                    ),
                );

                continue;
            }

            $linkedDriverCount++;
            $budgetValue = array_sum(array_map(
                fn (array $row): float => $this->value($row),
                $linkedRows,
            ));
            $planValue = $this->value($driver);

            if (! $this->withinTolerance($planValue, $budgetValue)) {
                $findings[] = $this->finding(
                    'review',
                    sprintf(
                        'Plan driver "%s" is %s in the plan but %s across its linked budget row%s.',
                        $driver['label'],
                        $this->money($planValue),
                        $this->money($budgetValue),
                        count($linkedRows) === 1 ? '' : 's',
                    ),
                    'Update the plan driver or its linked budget rows so the amounts agree within 5%.',
                );
            }

            if ($driver['category'] === self::CATEGORY_REVENUE_FORECAST && count($linkedRows) === 1) {
                $budgetMonth = (int) $linkedRows[0]['month'];

                if ($budgetMonth !== $driver['month']) {
                    $findings[] = $this->finding(
                        'review',
                        sprintf(
                            'Revenue driver "%s" starts in month %d in the plan but month %d in the budget.',
                            $driver['label'],
                            $driver['month'],
                            $budgetMonth,
                        ),
                        'Align the planned launch timing with the linked revenue forecast row.',
                    );
                }
            }
        }

        foreach ($materialRows as $row) {
            $driverKey = $row['plan_financial_driver_key'];
            $rowLabel = $row['label'] === '' ? 'Unnamed row' : $row['label'];

            if ($driverKey === '') {
                $findings[] = $this->finding(
                    'missing',
                    sprintf(
                        '%s "%s" is not linked to a plan financial driver.',
                        ucfirst($this->categoryLabel($row['category'])),
                        $rowLabel,
                    ),
                    'Link this material budget row to the plan commitment it supports.',
                );

                continue;
            }

            $driver = $driversByKey[$driverKey] ?? null;
            if ($driver === null) {
                $findings[] = $this->finding(
                    'missing',
                    sprintf(
                        '%s "%s" links to a plan driver that no longer exists.',
                        ucfirst($this->categoryLabel($row['category'])),
                        $rowLabel,
                    ),
                    'Choose a current plan financial driver for this budget row.',
                );

                continue;
            }

            if ($driver['category'] !== $row['category']) {
                $findings[] = $this->finding(
                    'review',
                    sprintf(
                        '%s "%s" is linked to the incompatible plan driver "%s".',
                        ucfirst($this->categoryLabel($row['category'])),
                        $rowLabel,
                        $driver['label'],
                    ),
                    'Link rows only to plan drivers of the same financial category.',
                );
            }
        }

        $unresolvedCount = count($findings);
        $missingCount = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'missing'));
        $reviewCount = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'review'));
        $checks = max(1, count($drivers) + count($materialRows));
        $score = max(0, min(100, (int) round((1 - ($unresolvedCount / $checks)) * 100)));
        $approvalAvailable = $unresolvedCount === 0 && ($drivers !== [] || $materialRows !== []);

        $status = match (true) {
            $approvalAvailable => 'met',
            $missingCount > 0 => 'missing',
            default => 'review',
        };
        $statusLabel = match ($status) {
            'met' => 'Aligned',
            'missing' => 'Blocked — links needed',
            default => 'Blocked — resolve differences',
        };
        $summary = $approvalAvailable
            ? sprintf(
                '%d plan financial driver%s and %d material budget row%s agree and are linked.',
                count($drivers),
                count($drivers) === 1 ? '' : 's',
                count($materialRows),
                count($materialRows) === 1 ? '' : 's',
            )
            : ($unresolvedCount === 0
                ? 'Add at least one linked plan financial driver and material budget row before advisor approval.'
                : $unresolvedCount.' plan-to-budget issue'.($unresolvedCount === 1 ? ' needs' : 's need').' resolution before advisor approval.');

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'score' => $score,
            'summary' => $summary,
            'evidence' => [
                sprintf('%d of %d plan financial driver%s have linked budget rows.', $linkedDriverCount, count($drivers), count($drivers) === 1 ? '' : 's'),
                sprintf('%d material budget row%s checked for a plan link.', count($materialRows), count($materialRows) === 1 ? '' : 's'),
                $unresolvedCount === 0 ? 'Amounts and revenue start timing agree within the 5% tolerance.' : $unresolvedCount.' unresolved coherence issue'.($unresolvedCount === 1 ? ' remains.' : 's remain.'),
            ],
            'findings' => $findings,
            'approval_available' => $approvalAvailable,
            'approval_message' => $approvalAvailable
                ? 'Plan financial drivers and budget rows are aligned.'
                : 'Resolve the Plan–budget coherence findings before advisor approval.',
            'linked_driver_count' => $linkedDriverCount,
            'material_row_count' => count($materialRows),
            'unresolved_count' => $unresolvedCount,
        ];
    }

    /**
     * The calculator owns financial normalisation. BP&B alone retains the
     * non-financial link back to a client-authored plan driver.
     *
     * @param  array<int, array<int|string, mixed>>  $rows
     * @return array<int, array<int|string, mixed>>
     */
    public function normaliseRows(array $rows, BudgetCalculator $calculator): array
    {
        return collect($rows)
            ->map(function (array $row) use ($calculator): ?array {
                $normalised = $calculator->normaliseRows([$row]);

                if ($normalised === []) {
                    return null;
                }

                return [
                    ...$normalised[0],
                    'plan_financial_driver_key' => trim((string) ($row['plan_financial_driver_key'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $sections
     * @param  array<int, array{key:string,title:string,prompt:string}>  $prompts
     * @param  list<string>  $sectionKeys
     * @return array<int, array{key:string,title:string,prompt:string,answer:string,financial_drivers:array<int, array{key:string,category:string,label:string,amount:float,quantity:float,month:int}>}>
     */
    public function normaliseBusinessPlanSections(array $sections, array $prompts, array $sectionKeys): array
    {
        $byKey = collect($sections)
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $promptsByKey = collect($prompts)->keyBy('key');

        return collect($sectionKeys)
            ->map(function (string $key) use ($byKey, $promptsByKey): array {
                $prompt = (array) ($promptsByKey->get($key) ?? []);
                $section = (array) ($byKey->get($key) ?? []);

                return [
                    'key' => $key,
                    'title' => (string) ($prompt['title'] ?? str($key)->replace('_', ' ')->title()->toString()),
                    'prompt' => (string) ($prompt['prompt'] ?? ''),
                    'answer' => trim((string) ($section['answer'] ?? $section['body'] ?? '')),
                    'financial_drivers' => $this->normaliseDrivers((array) ($section['financial_drivers'] ?? [])),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Coherence  $coherence
     * @return CoherenceCriterion
     */
    public function criterion(array $coherence): array
    {
        $status = $coherence['status'];
        $safeStatus = in_array($status, ['met', 'review', 'missing'], true) ? $status : 'review';

        return [
            'key' => 'plan_budget_coherence',
            'title' => 'Plan–budget coherence',
            'status' => $safeStatus,
            'status_label' => match ($safeStatus) {
                'met' => 'Met',
                'missing' => 'Missing',
                default => 'Needs review',
            },
            'score' => max(0, min(100, $coherence['score'])),
            'summary' => $coherence['summary'],
            'evidence' => collect($coherence['evidence'])
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->values()
                ->all(),
            'blocking' => ! $coherence['approval_available'],
            'findings' => $coherence['findings'],
        ];
    }

    /**
     * @param  array<int, mixed>  $drivers
     * @return array<int, array{key:string,category:string,label:string,amount:float,quantity:float,month:int}>
     */
    private function normaliseDrivers(array $drivers): array
    {
        return collect($drivers)
            ->filter(fn (mixed $driver): bool => is_array($driver))
            ->map(function (array $driver): array {
                $category = (string) ($driver['category'] ?? '');
                $label = trim((string) ($driver['label'] ?? ''));
                $amount = max(0.0, (float) ($driver['amount'] ?? 0));

                return [
                    'key' => trim((string) ($driver['key'] ?? '')) ?: 'driver_'.Str::uuid(),
                    'category' => in_array($category, self::CATEGORIES, true) ? $category : self::CATEGORY_REVENUE_FORECAST,
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'quantity' => round(max(1.0, (float) ($driver['quantity'] ?? 1)), 2),
                    'month' => min(36, max(1, (int) ($driver['month'] ?? 1))),
                ];
            })
            ->filter(fn (array $driver): bool => $driver['label'] !== '' && $driver['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{title:string,drivers:list<array{key:string,category:string,label:string,amount:float,quantity:float,month:int,section_title:string}>}>
     */
    private function sections(StrategicBudget $budget): array
    {
        $sections = [];

        foreach ((array) ($budget->business_plan_sections ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) ($section['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $drivers = [];
            foreach ((array) ($section['financial_drivers'] ?? []) as $driver) {
                if (! is_array($driver)) {
                    continue;
                }

                $category = (string) ($driver['category'] ?? '');
                $driverKey = trim((string) ($driver['key'] ?? ''));
                $label = trim((string) ($driver['label'] ?? ''));
                $amount = max(0.0, (float) ($driver['amount'] ?? 0));

                if (! in_array($category, self::CATEGORIES, true) || $driverKey === '' || $label === '' || $amount <= 0) {
                    continue;
                }

                $drivers[] = [
                    'key' => $driverKey,
                    'category' => $category,
                    'label' => $label,
                    'amount' => $amount,
                    'quantity' => max(1.0, (float) ($driver['quantity'] ?? 1)),
                    'month' => max(1, (int) ($driver['month'] ?? 1)),
                    'section_title' => trim((string) ($section['title'] ?? $key)),
                ];
            }

            $sections[$key] = [
                'title' => trim((string) ($section['title'] ?? $key)),
                'drivers' => $drivers,
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, array{title:string,drivers:list<array{key:string,category:string,label:string,amount:float,quantity:float,month:int,section_title:string}>}>  $sections
     * @return list<array{key:string,category:string,label:string,amount:float,quantity:float,month:int,section_title:string}>
     */
    private function drivers(array $sections): array
    {
        $drivers = array_map(
            fn (array $section): array => $section['drivers'],
            array_values($sections),
        );

        return $drivers === [] ? [] : array_merge(...$drivers);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{category:string,label:string,amount:float,quantity:float,month:int,plan_financial_driver_key:string}>
     */
    private function materialRows(array $rows, ?string $category = null): array
    {
        $category ??= '';

        return array_values(array_filter(array_map(
            function (mixed $row) use ($category): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $amount = max(0.0, (float) ($row['amount'] ?? 0));
                $label = trim((string) ($row['label'] ?? ''));

                if ($amount <= 0) {
                    return null;
                }

                return [
                    'category' => $category,
                    'label' => $label,
                    'amount' => $amount,
                    'quantity' => max(1.0, (float) ($row['quantity'] ?? 1)),
                    'month' => max(1, (int) ($row['month'] ?? 1)),
                    'plan_financial_driver_key' => trim((string) ($row['plan_financial_driver_key'] ?? '')),
                ];
            },
            $rows,
        )));
    }

    /** @return array{severity:string,message:string,next_action:string} */
    private function finding(string $severity, string $message, string $nextAction): array
    {
        return [
            'severity' => $severity,
            'message' => $message,
            'next_action' => $nextAction,
        ];
    }

    private function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? 'budget';
    }

    /** @param array{amount:float,quantity:float} $row */
    private function value(array $row): float
    {
        return round($row['amount'] * $row['quantity'], 2);
    }

    private function withinTolerance(float $expected, float $actual): bool
    {
        return abs($expected - $actual) <= max(1.0, $expected * 0.05);
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 0, '.', ',');
    }
}
