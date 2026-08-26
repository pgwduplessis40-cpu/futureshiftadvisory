<?php

declare(strict_types=1);

/**
 * Fails when PHPStan baseline debt grows and publishes its true occurrence
 * count. One baseline entry can suppress more than one finding, so counting
 * `message:` lines is deliberately insufficient.
 */
$baselinePath = $argv[1] ?? 'phpstan-baseline.neon';
$comparisonRef = $argv[2] ?? null;
$maximum = (int) (getenv('PHPSTAN_BASELINE_MAX') ?: 1839);

$current = baselineStats(readFileOrFail($baselinePath));
$comparisonContents = $comparisonRef === null ? null : readRevisionOrFail($comparisonRef, $baselinePath);
$comparison = $comparisonContents === null ? null : baselineStats($comparisonContents);
$failures = [];
$newOrExpandedEntries = 0;
$isVerifiedBootstrap = verifiedBootstrap($comparisonRef, $comparison, $current, readFileOrFail($baselinePath));

if ($current['findings'] > $maximum) {
    $failures[] = "PHPStan baseline has {$current['findings']} findings; the ratcheted maximum is {$maximum}.";
}

if ($comparison !== null && $current['findings'] > $comparison['findings'] && ! $isVerifiedBootstrap) {
    $failures[] = "PHPStan baseline grew from {$comparison['findings']} to {$current['findings']} findings.";
}

if ($comparisonContents !== null) {
    $newOrExpandedEntries = newOrExpandedEntryCount(
        baselineEntryCounts(readFileOrFail($baselinePath)),
        baselineEntryCounts($comparisonContents),
    );

    if ($newOrExpandedEntries > 0 && ! $isVerifiedBootstrap) {
        $failures[] = "PHPStan baseline contains {$newOrExpandedEntries} new or expanded suppression entries.";
    }
}

$summary = [
    'entries' => $current['entries'],
    'findings' => $current['findings'],
    'ratcheted_maximum' => $maximum,
    'under_1000' => $current['findings'] < 1000,
    'production_ready' => $current['findings'] === 0,
    'comparison_findings' => $comparison['findings'] ?? null,
    'new_or_expanded_entries' => $newOrExpandedEntries,
    'verified_bootstrap' => $isVerifiedBootstrap,
];

writeSummary('### PHPStan level-6 baseline', $summary);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

printf("PHPStan baseline: %d findings across %d entries.\n", $current['findings'], $current['entries']);

/**
 * Permits only the first level-6 normalization from the verified predecessor
 * revision. The committed baseline hash makes this exception non-reusable for
 * a later suppression change.
 *
 * @param  array{entries:int,findings:int}|null  $comparison
 * @param  array{entries:int,findings:int}  $current
 */
function verifiedBootstrap(?string $comparisonRef, ?array $comparison, array $current, string $baselineContents): bool
{
    if ($comparisonRef === null || $comparison === null || $current['findings'] <= $comparison['findings']) {
        return false;
    }

    $path = 'quality/phpstan-level6-bootstrap.json';
    $contents = @file_get_contents($path);
    if ($contents === false) {
        return false;
    }

    try {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    $sourceRevision = $manifest['source_revision'] ?? null;
    $previousFindings = $manifest['previous_findings'] ?? null;
    $legacyFindings = $manifest['legacy_level6_findings'] ?? null;
    $removedStaleSuppressions = $manifest['removed_stale_suppressions'] ?? null;
    $expectedFindings = $manifest['expected_findings'] ?? null;
    $baselineHash = $manifest['baseline_sha256'] ?? null;

    if (! is_string($sourceRevision) || ! is_int($previousFindings) || ! is_int($legacyFindings)
        || ! is_int($removedStaleSuppressions) || ! is_int($expectedFindings) || ! is_string($baselineHash)) {
        return false;
    }

    return revisionSha($comparisonRef) === $sourceRevision
        && $comparison['findings'] === $previousFindings
        && $current['findings'] === $expectedFindings
        && $expectedFindings - ($previousFindings - $removedStaleSuppressions) === $legacyFindings
        && hash_equals($baselineHash, hash('sha256', str_replace("\r\n", "\n", $baselineContents)));
}

/** @return array{entries:int,findings:int} */
function baselineStats(string $contents): array
{
    $blocks = baselineBlocks($contents);
    $entries = 0;
    $findings = 0;

    foreach ($blocks as $block) {
        if (preg_match('/^\s*message:\s*/m', $block) !== 1) {
            continue;
        }

        $entries++;
        $findings += preg_match('/^\s*count:\s*(\d+)\s*$/m', $block, $count) === 1
            ? (int) $count[1]
            : 1;
    }

    return [
        'entries' => $entries,
        'findings' => $findings,
    ];
}

/** @return list<string> */
function baselineBlocks(string $contents): array
{
    return array_values(array_filter(
        preg_split('/^\s*-\s*$/m', $contents) ?: [],
        static fn (string $block): bool => preg_match('/^\s*message:\s*/m', $block) === 1,
    ));
}

/** @return array<string, int> */
function baselineEntryCounts(string $contents): array
{
    $counts = [];

    foreach (baselineBlocks($contents) as $block) {
        $occurrences = preg_match('/^\s*count:\s*(\d+)\s*$/m', $block, $count) === 1
            ? (int) $count[1]
            : 1;
        $normalised = preg_replace('/^\s*count:\s*\d+\s*$/m', 'count:', str_replace("\r\n", "\n", $block));
        if (! is_string($normalised)) {
            continue;
        }

        $normalised = trim($normalised);
        $key = hash('sha256', $normalised);
        $counts[$key] = ($counts[$key] ?? 0) + $occurrences;
    }

    return $counts;
}

/** @param array<string, int> $current @param array<string, int> $comparison */
function newOrExpandedEntryCount(array $current, array $comparison): int
{
    $count = 0;

    foreach ($current as $entry => $occurrences) {
        $count += max(0, $occurrences - ($comparison[$entry] ?? 0));
    }

    return $count;
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

function readRevisionOrFail(string $revision, string $path): string
{
    if (preg_match('/^[A-Za-z0-9._\\/-]+$/', $revision) !== 1) {
        fwrite(STDERR, "Unsafe comparison revision {$revision}.\n");

        exit(1);
    }

    $process = proc_open(['git', 'show', "{$revision}:{$path}"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        fwrite(STDERR, "Could not read baseline from {$revision}.\n");

        exit(1);
    }

    $contents = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0 || ! is_string($contents)) {
        fwrite(STDERR, "Could not read {$path} from {$revision}: {$error}\n");

        exit(1);
    }

    return $contents;
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

/** @param array<string,mixed> $summary */
function writeSummary(string $title, array $summary): void
{
    $path = getenv('GITHUB_STEP_SUMMARY');

    if (! is_string($path) || $path === '') {
        return;
    }

    file_put_contents($path, $title."\n\n```json\n".json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n```\n");
}
