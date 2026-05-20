<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class TermsVersionSeeder extends Seeder
{
    /**
     * @var array<int, int>
     */
    private const DEFAULT_MATERIAL_CLAUSES = [1, 5, 6, 10, 12];

    public function run(): void
    {
        $terms = TermsVersion::query()->updateOrCreate(
            ['version' => '1'],
            [
                'title' => 'Future Shift Advisory Terms and Conditions',
                'material' => true,
                'notice_period_days' => 30,
                'reviewer_reference' => null,
            ],
        );

        foreach ($this->clauses() as $clause) {
            $terms->clauses()->updateOrCreate(
                ['clause_number' => $clause['clause_number']],
                [
                    'title' => $clause['title'],
                    'body' => $clause['body'],
                    'material' => in_array($clause['clause_number'], self::DEFAULT_MATERIAL_CLAUSES, true),
                ],
            );
        }
    }

    /**
     * @return array<int, array{clause_number: int, title: string, body: string}>
     */
    private function clauses(): array
    {
        $path = base_path('docs/legal/terms-v1.md');
        $markdown = File::get($path);
        $lines = preg_split('/\R/u', $markdown) ?: [];

        $clauses = [];
        $current = null;
        $body = [];

        foreach ($lines as $line) {
            if (preg_match('/^## Clause\s+(\d+)\s+(.+)$/u', $line, $matches) === 1) {
                if (is_array($current)) {
                    $clauses[] = $this->withBody($current, $body);
                }

                $current = [
                    'clause_number' => (int) $matches[1],
                    'title' => trim((string) preg_replace('/^[^\pL\pN]+/u', '', $matches[2])),
                ];
                $body = [];

                continue;
            }

            if (is_array($current) && trim($line) === '---') {
                $clauses[] = $this->withBody($current, $body);
                $current = null;
                $body = [];

                break;
            }

            if (is_array($current)) {
                $body[] = $line;
            }
        }

        if (is_array($current)) {
            $clauses[] = $this->withBody($current, $body);
        }

        if (count($clauses) !== 14) {
            throw new RuntimeException("Expected 14 terms clauses in [{$path}], found ".count($clauses).'.');
        }

        return $clauses;
    }

    /**
     * @param  array{clause_number: int, title: string}  $clause
     * @param  array<int, string>  $body
     * @return array{clause_number: int, title: string, body: string}
     */
    private function withBody(array $clause, array $body): array
    {
        return [
            'clause_number' => $clause['clause_number'],
            'title' => $clause['title'],
            'body' => trim(implode("\n", $body)),
        ];
    }
}
