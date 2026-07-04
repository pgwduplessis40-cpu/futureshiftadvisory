<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class MassAssignmentSourceGuardTest extends TestCase
{
    public function test_request_all_is_not_passed_directly_to_mass_assignment_methods(): void
    {
        $violations = [];

        foreach ($this->phpSourceFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (! preg_match_all(
                '/(?:create|update|fill|forceFill)\s*\(\s*\$[A-Za-z_][A-Za-z0-9_]*->all\s*\(/',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE,
            )) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $violations[] = sprintf(
                    '%s:%d uses [%s]; validate and shape attributes before mass assignment.',
                    $this->relativePath($file),
                    $this->lineNumber($contents, (int) $offset),
                    trim((string) $match),
                );
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return iterable<int, SplFileInfo>
     */
    private function phpSourceFiles(): iterable
    {
        $root = base_path('app');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            yield $file;
        }
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()));
    }

    private function lineNumber(string $contents, int $offset): int
    {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }
}
