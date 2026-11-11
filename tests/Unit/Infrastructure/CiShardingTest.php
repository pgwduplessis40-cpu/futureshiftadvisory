<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

/**
 * The sharding the split CI jobs stand on.
 *
 * Its failure mode passes: a scheme that drops a file runs less than it
 * claimed and still reports green, so nothing downstream notices that part of
 * the suite stopped being tested. That property is pinned here rather than
 * trusted to the workflow.
 */
final class CiShardingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3).'/scripts/phpunit-shard.php';
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
}
