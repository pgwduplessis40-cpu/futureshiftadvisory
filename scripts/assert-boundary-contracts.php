<?php

declare(strict_types=1);

$comparisonRef = $argv[1] ?? 'HEAD';

if (preg_match('/^[A-Za-z0-9._\\/-]+$/', $comparisonRef) !== 1) {
    fwrite(STDERR, "Unsafe comparison revision {$comparisonRef}.\n");

    exit(1);
}

$changed = gitLines(['diff', '--name-only', $comparisonRef, '--', 'app', 'resources/js', 'tests']);
$diff = gitLines(['diff', '--unified=0', $comparisonRef, '--', 'app/Http', 'app/Services', 'resources/js/pages', 'resources/js/components', 'resources/js/hooks', 'resources/js/lib']);
$failures = [];

foreach ($diff as $line) {
    if (! str_starts_with($line, '+') || str_starts_with($line, '+++')) {
        continue;
    }

    if (preg_match('/array<string,\s*mixed>|Record<string,\s*unknown>/', $line) === 1) {
        $failures[] = 'New untyped boundary payload: '.trim(substr($line, 1));
    }
}

$config = require __DIR__.'/../config/production_quality.php';
foreach ($config['monoliths'] as $path => $target) {
    if (! in_array($path, $changed, true)) {
        continue;
    }

    if (! hasChangedContractTest($changed, $target['contract_tests'])) {
        $failures[] = "{$path} changed without a mapped contract test change.";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

echo "Typed-boundary and extraction-contract checks passed.\n";

/** @return list<string> */
function gitLines(array $arguments): array
{
    $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        fwrite(STDERR, "Could not execute git diff.\n");

        exit(1);
    }

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0 || ! is_string($output)) {
        fwrite(STDERR, "git diff failed: {$error}\n");

        exit(1);
    }

    return preg_split('/\R/', rtrim($output)) ?: [];
}

/** @param list<string> $changed @param list<string> $patterns */
function hasChangedContractTest(array $changed, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (str_contains($pattern, '**/*.test.{ts,tsx}')) {
            $prefix = substr($pattern, 0, strpos($pattern, '**'));

            foreach ($changed as $path) {
                if (str_starts_with($path, $prefix) && preg_match('/\.test\.(ts|tsx)$/', $path) === 1) {
                    return true;
                }
            }

            continue;
        }

        $regex = '#^'.str_replace(['**', '*', '.'], ['.*', '[^/]*', '\\.'], $pattern).'$#';

        foreach ($changed as $path) {
            if (preg_match($regex, $path) === 1 || str_starts_with($path, rtrim($pattern, '/'))) {
                return true;
            }
        }
    }

    return false;
}
