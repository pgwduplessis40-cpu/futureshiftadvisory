<?php

declare(strict_types=1);

$cloverPath = $argv[1] ?? 'storage/logs/clover.xml';
$summaryPath = $argv[2] ?? 'storage/logs/coverage-summary.json';
$overallMinimum = coverageMinimum('COVERAGE_MIN_OVERALL', 80);
$moduleMinimum = coverageMinimum('COVERAGE_MIN_MODULE', 80);
$defaultCriticalMinimum = coverageMinimum('COVERAGE_MIN_CRITICAL', 90);
$criticalMinimums = [
    'payments' => coverageMinimum('COVERAGE_MIN_PAYMENTS', $defaultCriticalMinimum),
    'dates' => coverageMinimum('COVERAGE_MIN_DATES', $defaultCriticalMinimum),
    'scoring' => coverageMinimum('COVERAGE_MIN_SCORING', $defaultCriticalMinimum),
    'calculations' => coverageMinimum('COVERAGE_MIN_CALCULATIONS', $defaultCriticalMinimum),
    'reports' => coverageMinimum('COVERAGE_MIN_REPORTS', $defaultCriticalMinimum),
    'client_screen' => coverageMinimum('COVERAGE_MIN_CLIENT_SCREEN', $defaultCriticalMinimum),
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

$totals = emptyCoverage();
$modules = [];
$criticalGroups = array_fill_keys(array_keys($criticalMinimums), emptyCoverage());
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

    $coverage = [
        'covered' => (int) $metrics['coveredstatements'],
        'total' => (int) $metrics['statements'],
    ];

    if ($coverage['total'] <= 0) {
        continue;
    }

    addCoverage($totals, $coverage);

    $module = applicationModule($relativePath);
    if ($module !== null) {
        $modules[$module] ??= emptyCoverage();
        addCoverage($modules[$module], $coverage);
    }

    foreach (criticalCoverageGroups($relativePath) as $group) {
        addCoverage($criticalGroups[$group], $coverage);
    }
}

ksort($modules);
$failures = [];
$overall = percentage($totals['covered'], $totals['total']);

if ($overall < $overallMinimum) {
    $failures[] = sprintf('Overall coverage %.2f%% is below %.2f%%.', $overall, $overallMinimum);
}

$moduleSummary = [];
foreach ($modules as $name => $coverage) {
    $percentage = percentage($coverage['covered'], $coverage['total']);
    $moduleSummary[$name] = coverageSummary($coverage, $moduleMinimum);

    if ($percentage < $moduleMinimum) {
        $failures[] = sprintf('Module [%s] %.2f%% is below %.2f%%.', $name, $percentage, $moduleMinimum);
    }
}

$criticalSummary = [];
foreach ($criticalGroups as $name => $coverage) {
    $minimum = $criticalMinimums[$name];
    $criticalSummary[$name] = coverageSummary($coverage, $minimum);

    if ($coverage['total'] === 0) {
        $failures[] = "Critical coverage group [{$name}] matched no executable lines.";

        continue;
    }

    if ($criticalSummary[$name]['percentage'] < $minimum) {
        $failures[] = sprintf(
            'Critical coverage group [%s] %.2f%% is below %.2f%%.',
            $name,
            $criticalSummary[$name]['percentage'],
            $minimum,
        );
    }
}

writeCoverageSummary($summaryPath, [
    'overall' => coverageSummary($totals, $overallMinimum),
    'modules' => $moduleSummary,
    'critical_paths' => $criticalSummary,
    'failures' => $failures,
]);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

printf(
    "Coverage gates passed: overall %.2f%%, every application module met %.2f%%, and critical paths met their configured thresholds.\n",
    $overall,
    $moduleMinimum,
);

/**
 * @return array{covered:int, total:int}
 */
function emptyCoverage(): array
{
    return ['covered' => 0, 'total' => 0];
}

/**
 * @param  array{covered:int, total:int}  $target
 * @param  array{covered:int, total:int}  $coverage
 */
function addCoverage(array &$target, array $coverage): void
{
    $target['covered'] += $coverage['covered'];
    $target['total'] += $coverage['total'];
}

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
 * @param  array{covered:int, total:int}  $coverage
 * @return array{covered:int, total:int, percentage:float, minimum:float, meets_minimum:bool}
 */
function coverageSummary(array $coverage, float $minimum): array
{
    $percentage = percentage($coverage['covered'], $coverage['total']);

    return [
        ...$coverage,
        'percentage' => round($percentage, 2),
        'minimum' => $minimum,
        'meets_minimum' => $coverage['total'] > 0 && $percentage >= $minimum,
    ];
}

function applicationModule(string $relativePath): ?string
{
    if (preg_match('#^app/([^/]+)/#', $relativePath, $matches) === 1) {
        return 'app/'.$matches[1];
    }

    return str_starts_with($relativePath, 'app/') ? 'app/root' : null;
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

    if (preg_match('#^app/(Actions|Services|Support)/.*(Calendar|Date|Period|Schedule|Timeline)#', $relativePath)) {
        $groups[] = 'dates';
    }

    if (preg_match('#^app/(Actions|Services|Support)/.*(Assessment|Health|Readiness|Score|Scoring)#', $relativePath)) {
        $groups[] = 'scoring';
    }

    if (preg_match('#^app/(Actions|Services|Support)/.*(Budget|Calculation|Calculator|Forecast|Pricing|Rate|Valuation)#', $relativePath)) {
        $groups[] = 'calculations';
    }

    if (preg_match('#^app/(Http/Controllers/.*/Report|Jobs/ComposeReport|Models/Report|Services/Reports/)#', $relativePath)
        || preg_match('#^app/Services/Entrepreneurs/(BusinessPlanExecutiveSummary|BusinessPlanPreviewRenderer|BudgetPackBuilder|FunderReadyBusinessPlanBuilder)\.php$#', $relativePath)) {
        $groups[] = 'reports';
    }

    if (preg_match('#^app/(Events|Http/Controllers|Models|Services)/.*(CoBrowse|ScreenShare)#', $relativePath)) {
        $groups[] = 'client_screen';
    }

    return $groups;
}

/**
 * @param  array<string, mixed>  $summary
 */
function writeCoverageSummary(string $path, array $summary): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create coverage summary directory {$directory}.\n");

        exit(1);
    }

    $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents($path, $json.PHP_EOL) === false) {
        fwrite(STDERR, "Could not write coverage summary to {$path}.\n");

        exit(1);
    }
}
