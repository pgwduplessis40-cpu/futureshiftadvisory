<?php

declare(strict_types=1);

/**
 * Writes a PHPUnit configuration covering one deterministic slice of the suite.
 *
 * The suite is the long pole in CI and a single runner has to walk all of it.
 * Splitting it across runners needs each slice to be stable between jobs and,
 * far more importantly, needs the slices together to be the whole suite. A
 * sharding scheme that quietly drops a file does not fail; it passes, having
 * run less than it claimed, which is the worst outcome a test split can have.
 * So the assignment is index modulo count over a sorted list - exhaustive and
 * non-overlapping by construction - and `PhpunitShardTest` holds it to that.
 *
 * The configuration is produced by loading phpunit.xml and replacing only its
 * testsuites, so bootstrap, coverage source, environment and ini settings stay
 * whatever the real file says rather than being copied here to drift.
 *
 * Usage: php scripts/phpunit-shard.php <index> <count> [output] [config]
 * The output path must sit beside phpunit.xml, because PHPUnit resolves every
 * relative path in a configuration against that file's own directory.
 */

/**
 * Every test file the suite contains, sorted for a stable assignment.
 *
 * @param  list<string>  $directories
 * @return list<string>
 */
function phpunitShardCandidates(string $root, array $directories): array
{
    $files = [];
    foreach ($directories as $directory) {
        $absolute = $root.'/'.$directory;
        if (! is_dir($absolute)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if (! $entry instanceof SplFileInfo || ! $entry->isFile()) {
                continue;
            }
            if (! str_ends_with($entry->getFilename(), 'Test.php')) {
                continue;
            }
            $files[] = $directory.'/'.str_replace('\\', '/', substr($entry->getPathname(), strlen($absolute) + 1));
        }
    }
    sort($files);

    return array_values(array_unique($files));
}

/**
 * The slice one shard runs.
 *
 * Index modulo count over a sorted list, so the shards partition the suite
 * exactly: every file lands in one, none lands in two, and the assignment does
 * not move between jobs that see the same files.
 *
 * @param  list<string>  $files
 * @return list<string>
 */
function phpunitShardSlice(array $files, int $index, int $count): array
{
    if ($count < 1 || $index < 0 || $index >= $count) {
        throw new InvalidArgumentException('A shard index must fall inside its count.');
    }
    // Sorted here rather than trusted from the caller. Directory iteration
    // order is not guaranteed to match between runners, and two jobs holding
    // the same shard index over differently ordered lists would run
    // overlapping slices, leave the remainder unrun, and all report success.
    sort($files);
    $slice = [];
    foreach (array_values($files) as $position => $file) {
        if ($position % $count === $index) {
            $slice[] = $file;
        }
    }

    return $slice;
}

/**
 * The supplied configuration with its testsuites replaced by one naming
 * exactly the files this shard runs.
 *
 * @param  list<string>  $files
 */
function phpunitShardConfiguration(string $configPath, array $files): DOMDocument
{
    $document = new DOMDocument;
    $document->preserveWhiteSpace = false;
    $document->formatOutput = true;
    if ($document->load($configPath) === false || $document->documentElement === null) {
        throw new RuntimeException('Could not read the PHPUnit configuration at '.$configPath.'.');
    }

    $suites = $document->createElement('testsuites');
    $suite = $document->createElement('testsuite');
    $suite->setAttribute('name', 'shard');
    foreach ($files as $file) {
        $suite->appendChild($document->createElement('file', $file));
    }
    $suites->appendChild($suite);

    $existing = $document->documentElement->getElementsByTagName('testsuites')->item(0);
    if ($existing === null) {
        throw new RuntimeException('The PHPUnit configuration states no testsuites to replace.');
    }
    $document->documentElement->replaceChild($suites, $existing);

    return $document;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $index = (int) ($argv[1] ?? '0');
    $count = (int) ($argv[2] ?? '1');
    $root = dirname(__DIR__);
    $outputPath = $argv[3] ?? $root.'/phpunit.shard.xml';
    $configPath = $argv[4] ?? $root.'/phpunit.xml';

    if ($count < 1 || $index < 0 || $index >= $count) {
        fwrite(STDERR, "A shard index must fall inside its count.\n");

        exit(1);
    }
    if (realpath(dirname($outputPath)) !== realpath($root)) {
        // PHPUnit resolves every relative path in a configuration against the
        // configuration's own directory, so a shard file written elsewhere
        // would look for the bootstrap and the tests in the wrong place.
        fwrite(STDERR, "The shard configuration must be written beside phpunit.xml.\n");

        exit(1);
    }

    $files = phpunitShardCandidates($root, ['tests/Unit', 'tests/Feature']);
    if ($files === []) {
        fwrite(STDERR, "No test files were found to shard.\n");

        exit(1);
    }

    $slice = phpunitShardSlice($files, $index, $count);
    if ($slice === []) {
        // More shards than files means a runner with nothing to do, which is a
        // misconfiguration rather than a pass.
        fwrite(STDERR, sprintf("Shard %d of %d selected no files from %d.\n", $index + 1, $count, count($files)));

        exit(1);
    }

    phpunitShardConfiguration($configPath, $slice)->save($outputPath);

    // A manifest per shard, so the aggregate job can prove the shards it
    // received add up to the suite. The slices themselves are exhaustive by
    // construction, but the shard count lives in the workflow in two places -
    // the matrix list and the count passed here - and a disagreement between
    // them would leave a slice unrun while every job still reported success.
    // That is the one failure this split must never have, so it is checked
    // from the numbers rather than trusted to the YAML.
    file_put_contents($root.'/storage/logs/shard.json', json_encode([
        'index' => $index,
        'count' => $count,
        'files' => count($slice),
        'total' => count($files),
    ], JSON_THROW_ON_ERROR)."\n");

    printf("Shard %d of %d runs %d of %d test files.\n", $index + 1, $count, count($slice), count($files));
}
