<?php

declare(strict_types=1);

$currentPath = $argv[1] ?? 'storage/logs/coverage-summary.json';
$baselinePath = $argv[2] ?? 'quality/coverage-baseline.json';
$comparisonRef = $argv[3] ?? null;
$current = decode(readFileOrFail($currentPath), $currentPath);
$baselineContents = readFileOrFail($baselinePath);
$baseline = decode($baselineContents, $baselinePath);
$comparisonContents = $comparisonRef === null ? null : readRevisionOrNull($comparisonRef, $baselinePath);
$comparisonBaseline = $comparisonContents === null ? null : decode($comparisonContents, "{$comparisonRef}:{$baselinePath}");
$isVerifiedBootstrap = verifiedBootstrap($comparisonRef, $comparisonBaseline, $baselineContents);
$failures = [];

if ($comparisonRef !== null && $comparisonBaseline === null && ! $isVerifiedBootstrap) {
    $failures[] = 'Coverage baseline is missing from the comparison revision and is not the verified initial bootstrap.';
}

if ($comparisonBaseline !== null) {
    $failures = [...$failures, ...baselineRatchetFailures($baseline, $comparisonBaseline)];
}

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

echo $isVerifiedBootstrap
    ? "Coverage baseline bootstrap is verified and current coverage meets it.\n"
    : "Coverage did not regress from the committed main baseline.\n";

/**
 * A baseline cannot be reduced or have groups removed in a pull request. The
 * candidate is still used for the coverage comparison, so a PR may only raise
 * its own baseline when its report proves the higher values.
 *
 * @param  array<string,mixed>  $candidate
 * @param  array<string,mixed>  $main
 * @return list<string>
 */
function baselineRatchetFailures(array $candidate, array $main): array
{
    $failures = [];

    foreach (['overall', 'modules', 'critical_paths'] as $group) {
        $mainValues = $group === 'overall' ? ['overall' => $main['overall'] ?? null] : $main[$group] ?? null;
        $candidateValues = $group === 'overall' ? ['overall' => $candidate['overall'] ?? null] : $candidate[$group] ?? null;

        if (! is_array($mainValues) || ! is_array($candidateValues)) {
            $failures[] = "Coverage baseline group {$group} must remain an object.";

            continue;
        }

        foreach ($mainValues as $name => $mainValue) {
            $candidateValue = $candidateValues[$name] ?? null;
            if (! is_numeric($mainValue) || ! is_numeric($candidateValue)) {
                $failures[] = "Coverage baseline group {$group}.{$name} must remain numeric.";

                continue;
            }

            if ((float) $candidateValue + 0.0001 < (float) $mainValue) {
                $failures[] = sprintf(
                    'Coverage baseline for %s.%s was lowered from %.2f%% to %.2f%%.',
                    $group,
                    $name,
                    (float) $mainValue,
                    (float) $candidateValue,
                );
            }
        }
    }

    return $failures;
}

/**
 * The first baseline is an audited snapshot taken while the policy is added.
 * Its manifest binds the exception to this precise predecessor and file hash;
 * all later pull requests must compare directly with their base branch.
 *
 * @param  array<string,mixed>|null  $comparisonBaseline
 */
function verifiedBootstrap(?string $comparisonRef, ?array $comparisonBaseline, string $baselineContents): bool
{
    if ($comparisonRef === null || $comparisonBaseline !== null) {
        return false;
    }

    $manifestPath = 'quality/coverage-baseline-bootstrap.json';
    $manifestContents = @file_get_contents($manifestPath);
    if ($manifestContents === false) {
        return false;
    }

    try {
        /** @var array<string,mixed> $manifest */
        $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    $sourceRevision = $manifest['source_revision'] ?? null;
    $baselineHash = $manifest['baseline_sha256'] ?? null;

    return is_string($sourceRevision)
        && is_string($baselineHash)
        && revisionSha($comparisonRef) === $sourceRevision
        && hash_equals($baselineHash, normalisedHash($baselineContents));
}

function readFileOrFail(string $path): string
{
    $contents = @file_get_contents($path);

    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}.\n");

        exit(1);
    }

    return $contents;
}

function readRevisionOrNull(string $revision, string $path): ?string
{
    if (preg_match('/^[A-Za-z0-9._\\/-]+$/', $revision) !== 1) {
        return null;
    }

    $process = proc_open(['git', 'show', "{$revision}:{$path}"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        return null;
    }

    $contents = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 && is_string($contents) ? $contents : null;
}

function revisionSha(string $revision): ?string
{
    $process = proc_open(['git', 'rev-parse', '--verify', "{$revision}^{commit}"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        return null;
    }

    $sha = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 && is_string($sha) ? trim($sha) : null;
}

function normalisedHash(string $contents): string
{
    return hash('sha256', str_replace("\r\n", "\n", $contents));
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
