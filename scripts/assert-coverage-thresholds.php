<?php

declare(strict_types=1);

$cloverPath = $argv[1] ?? 'storage/logs/clover.xml';
$summaryPath = $argv[2] ?? 'storage/logs/coverage-summary.json';
$overallMinimum = coverageMinimum('COVERAGE_MIN_OVERALL', 85);
$moduleMinimum = coverageMinimum('COVERAGE_MIN_MODULE', 80);
$defaultCriticalMinimum = coverageMinimum('COVERAGE_MIN_CRITICAL', 90);
$criticalManifest = require __DIR__.'/../quality/coverage-critical-paths.php';

if (! is_array($criticalManifest) || $criticalManifest === []) {
    fwrite(STDERR, "Critical coverage manifest is missing or invalid.\n");

    exit(1);
}

$criticalMinimums = [];
foreach ($criticalManifest as $name => $definition) {
    if (! is_string($name) || ! is_array($definition)
        || ! is_array($definition['paths'] ?? null)
        || ! is_array($definition['prefixes'] ?? null)
    ) {
        fwrite(STDERR, "Critical coverage manifest entry is invalid.\n");

        exit(1);
    }

    $criticalMinimums[$name] = coverageMinimum(
        'COVERAGE_MIN_'.strtoupper($name),
        $defaultCriticalMinimum,
    );
}

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
$criticalFiles = array_fill_keys(array_keys($criticalMinimums), []);
$coveredFiles = [];
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

    $coveredFiles[$relativePath] = true;
    addCoverage($totals, $coverage);

    $module = applicationModule($relativePath);
    if ($module !== null) {
        $modules[$module] ??= emptyCoverage();
        addCoverage($modules[$module], $coverage);
    }

    foreach (criticalCoverageGroups($relativePath, $criticalManifest) as $group) {
        addCoverage($criticalGroups[$group], $coverage);
        $criticalFiles[$group][] = $relativePath;
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
    $definition = $criticalManifest[$name];
    $matchedFiles = array_values(array_unique($criticalFiles[$name]));
    sort($matchedFiles);
    $missingFiles = array_values(array_filter(
        $definition['paths'],
        static fn (mixed $path): bool => is_string($path) && ! array_key_exists($path, $coveredFiles),
    ));
    sort($missingFiles);
    $criticalSummary[$name] = [
        ...coverageSummary($coverage, $minimum),
        'matched_files' => $matchedFiles,
        'required_paths' => $definition['paths'],
        'missing_paths' => $missingFiles,
    ];

    if ($missingFiles !== []) {
        $failures[] = sprintf(
            'Critical coverage group [%s] did not report required files: %s.',
            $name,
            implode(', ', $missingFiles),
        );
    }

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
function criticalCoverageGroups(string $relativePath, array $manifest): array
{
    $groups = [];

    foreach ($manifest as $name => $definition) {
        if (! is_string($name) || ! is_array($definition)) {
            continue;
        }

        $paths = $definition['paths'] ?? [];
        $prefixes = $definition['prefixes'] ?? [];
        if (in_array($relativePath, $paths, true)) {
            $groups[] = $name;

            continue;
        }

        foreach ($prefixes as $prefix) {
            if (is_string($prefix) && str_starts_with($relativePath, $prefix)) {
                $groups[] = $name;

                break;
            }
        }
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
