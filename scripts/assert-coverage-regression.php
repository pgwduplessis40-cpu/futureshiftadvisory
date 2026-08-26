<?php

declare(strict_types=1);

$currentPath = $argv[1] ?? 'storage/logs/coverage-summary.json';
$baselinePath = $argv[2] ?? 'quality/coverage-baseline.json';
$current = decode(readFileOrFail($currentPath), $currentPath);
$baseline = decode(readFileOrFail($baselinePath), $baselinePath);
$failures = [];

foreach (['overall', 'modules', 'critical_paths'] as $group) {
    $expected = $group === 'overall'
        ? ['overall' => $baseline['overall'] ?? null]
        : $baseline[$group] ?? [];

    foreach ($expected as $name => $baselinePercentage) {
        $actual = $group === 'overall'
            ? $current['overall']['percentage'] ?? null
            : $current[$group][$name]['percentage'] ?? null;

        if (! is_numeric($actual)) {
            $failures[] = "Coverage group {$group}.{$name} is missing from the current report.";

            continue;
        }

        if ((float) $actual + 0.0001 < (float) $baselinePercentage) {
            $failures[] = sprintf(
                'Coverage regression for %s.%s: %.2f%% is below main baseline %.2f%%.',
                $group,
                $name,
                (float) $actual,
                (float) $baselinePercentage,
            );
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

echo "Coverage did not regress from the committed main baseline.\n";

function readFileOrFail(string $path): string
{
    $contents = @file_get_contents($path);

    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}.\n");

        exit(1);
    }

    return $contents;
}

/** @return array<string,mixed> */
function decode(string $contents, string $path): array
{
    try {
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Could not decode {$path}: {$exception->getMessage()}.\n");

        exit(1);
    }

    if (! is_array($decoded)) {
        fwrite(STDERR, "{$path} must contain a JSON object.\n");

        exit(1);
    }

    return $decoded;
}
