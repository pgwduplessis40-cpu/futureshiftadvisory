<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

/**
 * The two helpers the split CI jobs stand on.
 *
 * Both have a failure mode that passes. A sharding scheme that drops a file
 * runs less than it claims and reports green; a coverage merge that sums
 * instead of unioning reports more covered statements than exist and pushes a
 * file past the threshold it was meant to be held to. Neither announces
 * itself, so both are pinned here.
 */
final class CiShardingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3).'/scripts/phpunit-shard.php';
        require_once dirname(__DIR__, 3).'/scripts/merge-clover.php';
    }

    /**
     * The property the whole split depends on: together the shards are the
     * suite, whatever the shard count.
     */
    public function test_the_shards_partition_every_test_file_exactly_once(): void
    {
        $files = [];
        for ($i = 0; $i < 37; $i++) {
            $files[] = 'tests/Feature/Case'.$i.'Test.php';
        }
        // Deliberately not pre-sorted: the partition has to hold for whatever
        // order the files arrive in, because that is what a runner hands it.
        $expected = $files;
        sort($expected);

        foreach ([1, 2, 3, 4, 8, 37] as $count) {
            $seen = [];
            for ($index = 0; $index < $count; $index++) {
                foreach (phpunitShardSlice($files, $index, $count) as $file) {
                    self::assertNotContains($file, $seen, $file.' runs in more than one shard.');
                    $seen[] = $file;
                }
            }
            sort($seen);
            self::assertSame($expected, $seen, 'Shard count '.$count.' does not run the whole suite.');
        }
    }

    public function test_a_shard_outside_its_count_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        phpunitShardSlice(['tests/Unit/OneTest.php'], 2, 2);
    }

    /** The assignment must not move between jobs, or two runners sharing a
     * shard index would run different slices and the suite would be partly
     * unrun while both reported success.
     */
    public function test_the_assignment_does_not_depend_on_the_order_files_are_discovered(): void
    {
        $files = ['tests/Unit/BTest.php', 'tests/Unit/ATest.php', 'tests/Feature/CTest.php'];
        $reversed = array_reverse($files);

        self::assertSame(
            phpunitShardSlice($files, 0, 2),
            phpunitShardSlice($reversed, 0, 2),
            'A shard is only stable if it is taken from a sorted list.',
        );
    }

    /** The discovery has to see the real suite; a glob that silently matched
     * nothing would shard an empty list and pass.
     */
    public function test_the_real_suite_is_discovered(): void
    {
        $files = phpunitShardCandidates(dirname(__DIR__, 3), ['tests/Unit', 'tests/Feature']);

        self::assertGreaterThan(300, count($files));
        self::assertContains('tests/Unit/Infrastructure/CiShardingTest.php', $files);
        foreach ($files as $file) {
            self::assertStringEndsWith('Test.php', $file);
        }
    }

    /**
     * A statement two shards both ran is one covered statement.
     *
     * Summing would report two, which is how a merged report ends up claiming
     * more coverage than a file has statements.
     */
    public function test_coverage_seen_by_two_shards_is_counted_once(): void
    {
        $first = $this->clover(['/app/Thing.php' => [1 => 1, 2 => 1, 3 => 0]]);
        $second = $this->clover(['/app/Thing.php' => [1 => 1, 2 => 0, 3 => 0]]);

        $merged = cloverStatements([$first, $second]);

        self::assertSame([1 => 1, 2 => 1, 3 => 0], $merged['/app/Thing.php']);

        $document = cloverDocument($merged);
        $xml = simplexml_load_string((string) $document->saveXML());
        self::assertNotFalse($xml);
        $metrics = $xml->xpath('//file/metrics')[0];
        self::assertSame('3', (string) $metrics['statements']);
        self::assertSame('2', (string) $metrics['coveredstatements'], 'A line both shards ran was counted twice.');
    }

    /** A line only the second shard reached is covered in the merge; that is
     * the whole reason the reports are combined rather than one being used.
     */
    public function test_a_line_only_one_shard_reached_is_covered(): void
    {
        $merged = cloverStatements([
            $this->clover(['/app/Thing.php' => [1 => 0, 2 => 0]]),
            $this->clover(['/app/Thing.php' => [1 => 0, 2 => 4]]),
        ]);

        self::assertSame([1 => 0, 2 => 4], $merged['/app/Thing.php']);
    }

    public function test_files_only_one_shard_saw_are_kept(): void
    {
        $merged = cloverStatements([
            $this->clover(['/app/One.php' => [1 => 1]]),
            $this->clover(['/app/Two.php' => [1 => 1]]),
        ]);

        self::assertSame(['/app/One.php', '/app/Two.php'], array_keys($merged));
    }

    /**
     * The merged document has to be readable by the gate that consumes it,
     * which reads `//file` and its statement metrics.
     */
    public function test_the_merged_document_carries_the_metrics_the_gate_reads(): void
    {
        $document = cloverDocument(['/app/One.php' => [1 => 1, 2 => 0], '/app/Two.php' => [5 => 3]]);
        $xml = simplexml_load_string((string) $document->saveXML());

        self::assertNotFalse($xml);
        self::assertCount(2, $xml->xpath('//file'));
        $project = $xml->xpath('/coverage/project/metrics')[0];
        self::assertSame('3', (string) $project['statements']);
        self::assertSame('2', (string) $project['coveredstatements']);
    }

    /** @param array<string,array<int,int>> $files */
    private function clover(array $files): string
    {
        $lines = '';
        foreach ($files as $name => $counts) {
            $lines .= '<file name="'.$name.'">';
            foreach ($counts as $number => $count) {
                $lines .= '<line num="'.$number.'" type="stmt" count="'.$count.'"/>';
            }
            $lines .= '</file>';
        }
        $path = tempnam(sys_get_temp_dir(), 'clover').'.xml';
        file_put_contents($path, '<?xml version="1.0" encoding="UTF-8"?><coverage><project>'.$lines.'</project></coverage>');

        return $path;
    }
}
