<?php

declare(strict_types=1);

$cloverPath = $argv[1] ?? 'storage/logs/clover.xml';
$overallMinimum = coverageMinimum('COVERAGE_MIN_OVERALL', 80);
$defaultCriticalMinimum = coverageMinimum('COVERAGE_MIN_CRITICAL', 90);
$groupMinimums = [
    'payments' => coverageMinimum('COVERAGE_MIN_PAYMENTS', 65),
    'dates' => coverageMinimum('COVERAGE_MIN_DATES', 75),
    'scoring' => coverageMinimum('COVERAGE_MIN_SCORING', $defaultCriticalMinimum),
    'calculations' => coverageMinimum('COVERAGE_MIN_CALCULATIONS', $defaultCriticalMinimum),
    'reports' => coverageMinimum('COVERAGE_MIN_REPORTS', 87),
];

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Coverage report not found at {$cloverPath}.\n");

    exit(1);
}

$xml = simplexml_load_file($cloverPath);

if (! $xml instanceof SimpleXMLElement) {
    fwrite(STDERR, "Coverage report at {$cloverPath} is not valid XML.\n");

    exit(1);
}

$totals = ['covered' => 0, 'total' => 0];
$groups = [
    'payments' => ['covered' => 0, 'total' => 0],
    'dates' => ['covered' => 0, 'total' => 0],
    'scoring' => ['covered' => 0, 'total' => 0],
    'calculations' => ['covered' => 0, 'total' => 0],
    'reports' => ['covered' => 0, 'total' => 0],
];

$root = str_replace('\\', '/', realpath(__DIR__.'/..') ?: dirname(__DIR__));

foreach ($xml->xpath('//file') ?: [] as $file) {
    $filePath = str_replace('\\', '/', (string) $file['name']);
    $relativePath = str_starts_with($filePath, $root.'/')
        ? substr($filePath, strlen($root) + 1)
        : $filePath;

    $metrics = $file->metrics;

    if (! $metrics instanceof SimpleXMLElement) {
        continue;
    }

    $covered = (int) $metrics['coveredstatements'];
    $total = (int) $metrics['statements'];

    if ($total <= 0) {
        continue;
    }

    $totals['covered'] += $covered;
    $totals['total'] += $total;

    foreach (criticalCoverageGroups($relativePath) as $group) {
        $groups[$group]['covered'] += $covered;
        $groups[$group]['total'] += $total;
    }
}

$failures = [];
$overall = percentage($totals['covered'], $totals['total']);

if ($overall < $overallMinimum) {
    $failures[] = sprintf('Overall coverage %.2f%% is below %.2f%%.', $overall, $overallMinimum);
}

foreach ($groups as $name => $coverage) {
    $minimum = $groupMinimums[$name];

    if ($coverage['total'] === 0) {
        $failures[] = "Critical coverage group [{$name}] matched no executable lines.";

        continue;
    }

    $percentage = percentage($coverage['covered'], $coverage['total']);

    if ($percentage < $minimum) {
        $failures[] = sprintf(
            'Critical coverage group [%s] %.2f%% is below %.2f%%.',
            $name,
            $percentage,
            $minimum,
        );
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

printf(
    "Coverage gates passed: overall %.2f%%, critical groups met configured thresholds.\n",
    $overall,
);

function coverageMinimum(string $name, float $default): float
{
    $value = getenv($name);

    if (! is_string($value) || trim($value) === '') {
        return $default;
    }

    if (! is_numeric($value)) {
        fwrite(STDERR, "Coverage minimum {$name} must be numeric.\n");

        exit(1);
    }

    return (float) $value;
}

function percentage(int $covered, int $total): float
{
    if ($total <= 0) {
        return 0.0;
    }

    return ($covered / $total) * 100;
}

/**
 * @return list<string>
 */
function criticalCoverageGroups(string $relativePath): array
{
    $groups = [];

    if (preg_match('#^app/(Enums|Http|Models|Services|Jobs)/.*Payment#', $relativePath)
        || str_starts_with($relativePath, 'app/Services/Payments/')) {
        $groups[] = 'payments';
    }

    if (preg_match('#^app/(Services|Support)/.*(Calendar|Date|Period|Schedule|Timeline)#', $relativePath)) {
        $groups[] = 'dates';
    }

    if (preg_match('#^app/(Services|Support)/.*(Assessment|Health|Readiness|Score|Scoring)#', $relativePath)) {
        $groups[] = 'scoring';
    }

    if (preg_match('#^app/(Services|Support)/.*(Budget|Calculation|Calculator|Forecast|Pricing|Rate|Valuation)#', $relativePath)) {
        $groups[] = 'calculations';
    }

    if (preg_match('#^app/(Http/Controllers/.*/Report|Jobs/ComposeReport|Models/Report|Services/Reports/)#', $relativePath)) {
        $groups[] = 'reports';
    }

    return $groups;
}
