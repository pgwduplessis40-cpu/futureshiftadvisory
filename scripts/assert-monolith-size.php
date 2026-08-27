<?php

declare(strict_types=1);

$production = in_array('--production', $argv, true);
$comparisonRef = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--production') {
        continue;
    }

    if ($comparisonRef !== null || preg_match('/^[A-Za-z0-9._\\/-]+$/', $argument) !== 1) {
        fwrite(STDERR, "Usage: assert-monolith-size.php [--production] [comparison-ref].\n");

        exit(1);
    }

    $comparisonRef = $argument;
}

$config = require __DIR__.'/../config/production_quality.php';
$failures = [];
$summary = [];

if ($comparisonRef !== null) {
    $comparisonConfig = configAtRevision($comparisonRef);

    if ($comparisonConfig !== null) {
        enforceNoLimitIncreases($config, $comparisonConfig, $failures);
    }
}

foreach ($config['monoliths'] as $path => $target) {
    $contents = @file($path, FILE_IGNORE_NEW_LINES);

    if ($contents === false) {
        $failures[] = "Monolith target {$path} is missing.";

        continue;
    }

    $lines = count($contents);
    $limit = $production ? $target['production_limit'] : $target['ceiling'];
    $summary[$path] = [
        'lines' => $lines,
        'limit' => $limit,
        'production_limit' => $target['production_limit'],
        'meets_current_gate' => $lines <= $limit,
    ];

    if ($lines > $limit) {
        $mode = $production ? 'production limit' : 'ratcheted no-growth ceiling';
        $failures[] = "{$path} has {$lines} lines, above its {$mode} of {$limit}.";
    }
}

writeSummary('### Structural-boundary size gate', $summary);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

echo $production
    ? "All target files meet their production size limits.\n"
    : "All target files meet their no-growth ceilings.\n";

/** @param array<string,mixed> $current @param array<string,mixed> $comparison @param list<string> $failures */
function enforceNoLimitIncreases(array $current, array $comparison, array &$failures): void
{
    $oldTargets = $comparison['monoliths'] ?? [];
    if (! is_array($oldTargets)) {
        $failures[] = 'Comparison production-quality config has no monolith targets.';

        return;
    }

    foreach ($oldTargets as $path => $oldTarget) {
        if (! array_key_exists($path, $current['monoliths'])) {
            $failures[] = "Monolith target {$path} was removed from the release-control registry.";
        }
    }

    foreach ($current['monoliths'] as $path => $target) {
        $oldTarget = $oldTargets[$path] ?? null;
        if (! is_array($oldTarget)) {
            continue;
        }

        foreach (['ceiling', 'production_limit'] as $limit) {
            if (! isset($target[$limit], $oldTarget[$limit])) {
                $failures[] = "Monolith target {$path} lacks {$limit}.";

                continue;
            }

            if ((int) $target[$limit] > (int) $oldTarget[$limit]) {
                $failures[] = "{$path} {$limit} increased from {$oldTarget[$limit]} to {$target[$limit]}.";
            }
        }
    }
}

/** @return array<string,mixed>|null */
function configAtRevision(string $revision): ?array
{
    $process = proc_open(
        ['git', 'show', "{$revision}:config/production_quality.php"],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        fwrite(STDERR, "Could not read production-quality config from {$revision}.\n");

        exit(1);
    }

    $contents = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0 || ! is_string($contents)) {
        if (str_contains($error, 'does not exist') || str_contains($error, 'not in ')) {
            return null;
        }

        fwrite(STDERR, "Could not read production-quality config from {$revision}: {$error}\n");

        exit(1);
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'production-quality-config-');
    if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
        fwrite(STDERR, "Could not create a comparison production-quality config.\n");

        exit(1);
    }

    try {
        $comparison = require $temporaryPath;
    } finally {
        @unlink($temporaryPath);
    }

    if (! is_array($comparison)) {
        fwrite(STDERR, "Comparison production-quality config from {$revision} is invalid.\n");

        exit(1);
    }

    return $comparison;
}

/** @param array<string,mixed> $summary */
function writeSummary(string $title, array $summary): void
{
    $path = getenv('GITHUB_STEP_SUMMARY');

    if (! is_string($path) || $path === '') {
        return;
    }

    file_put_contents($path, $title."\n\n```json\n".json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n```\n");
}
