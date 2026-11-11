<?php

declare(strict_types=1);

/**
 * Combines the Clover reports produced by the sharded test jobs into one.
 *
 * Coverage from a split suite has to be unioned, never summed. A statement
 * exercised by two shards is one covered statement, and adding the two would
 * report more covered statements than the file contains - which inflates the
 * percentage the threshold gate reads and can push a file past 100%. So this
 * works from the per-line records: a statement is covered when any shard saw
 * it run, and each file's metrics are recomputed from that union rather than
 * carried over from whichever report happened to be read last.
 *
 * Usage: php scripts/merge-clover.php <output> <input...>
 */

/**
 * Per-file statement coverage, unioned across every supplied report.
 *
 * @param  list<string>  $paths
 * @return array<string,array<int,int>> File name to line number to hit count.
 */
function cloverStatements(array $paths): array
{
    $files = [];
    foreach ($paths as $path) {
        $xml = simplexml_load_file($path);
        if ($xml === false) {
            throw new RuntimeException('Could not read the Clover report at '.$path.'.');
        }
        foreach ($xml->xpath('//file') ?: [] as $file) {
            $name = (string) $file['name'];
            if ($name === '') {
                continue;
            }
            $files[$name] ??= [];
            foreach ($file->line as $line) {
                if ((string) $line['type'] !== 'stmt') {
                    continue;
                }
                $number = (int) $line['num'];
                $count = (int) $line['count'];
                // The union: a statement any shard ran is covered, and the
                // highest count seen is kept so the merged report still
                // distinguishes an unexercised line from a run one.
                $files[$name][$number] = max($files[$name][$number] ?? 0, $count);
            }
        }
    }

    return $files;
}

/**
 * A Clover document stating the unioned coverage.
 *
 * Only the records the coverage gate reads are reconstructed: each file's
 * statement lines and the statement metrics computed from them, plus the
 * project totals. A merged report is evidence for the threshold check, not a
 * substitute for a single run's full report.
 *
 * @param  array<string,array<int,int>>  $files
 */
function cloverDocument(array $files): DOMDocument
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->formatOutput = true;
    $coverage = $document->createElement('coverage');
    $coverage->setAttribute('generated', (string) time());
    $project = $document->createElement('project');
    $project->setAttribute('timestamp', (string) time());

    $statements = 0;
    $covered = 0;
    ksort($files);
    foreach ($files as $name => $lines) {
        ksort($lines);
        $element = $document->createElement('file');
        $element->setAttribute('name', $name);
        $fileCovered = 0;
        foreach ($lines as $number => $count) {
            $line = $document->createElement('line');
            $line->setAttribute('num', (string) $number);
            $line->setAttribute('type', 'stmt');
            $line->setAttribute('count', (string) $count);
            $element->appendChild($line);
            if ($count > 0) {
                $fileCovered++;
            }
        }
        $metrics = $document->createElement('metrics');
        $metrics->setAttribute('statements', (string) count($lines));
        $metrics->setAttribute('coveredstatements', (string) $fileCovered);
        $element->appendChild($metrics);
        $project->appendChild($element);
        $statements += count($lines);
        $covered += $fileCovered;
    }

    $totals = $document->createElement('metrics');
    $totals->setAttribute('statements', (string) $statements);
    $totals->setAttribute('coveredstatements', (string) $covered);
    $project->appendChild($totals);
    $coverage->appendChild($project);
    $document->appendChild($coverage);

    return $document;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $outputPath = $argv[1] ?? '';
    $inputPaths = array_slice($argv, 2);

    if ($outputPath === '' || $inputPaths === []) {
        fwrite(STDERR, "Usage: php scripts/merge-clover.php <output> <input...>\n");

        exit(1);
    }
    foreach ($inputPaths as $path) {
        if (! is_file($path) || filesize($path) === 0) {
            // A shard whose report is missing covered nothing that can be
            // proven, and merging the rest would report a coverage figure for
            // a suite that did not finish.
            fwrite(STDERR, "Shard coverage report not found at {$path}.\n");

            exit(1);
        }
    }

    $files = cloverStatements($inputPaths);
    if ($files === []) {
        fwrite(STDERR, "The shard coverage reports name no files.\n");

        exit(1);
    }

    cloverDocument($files)->save($outputPath);
    printf("Merged %d shard reports covering %d files into %s.\n", count($inputPaths), count($files), $outputPath);
}
