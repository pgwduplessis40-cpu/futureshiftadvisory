<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RatingFramework;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FoundingRatingFrameworkValuesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $current = RatingFramework::query()
                ->with('criteria')
                ->where('status', RatingFramework::STATUS_PUBLISHED)
                ->whereNull('industry_variant')
                ->latest('version')
                ->firstOrFail();

            if ($this->matchesFoundingRubric($current)) {
                return;
            }

            $next = RatingFramework::query()->create([
                'version' => ((int) RatingFramework::query()->whereNull('industry_variant')->max('version')) + 1,
                'status' => RatingFramework::STATUS_PUBLISHED,
                'industry_variant' => null,
                'production_ready' => true,
                'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
                'supersedes_framework_id' => $current->getKey(),
                'published_at' => now(),
            ]);

            foreach (self::values() as $criterion) {
                $next->criteria()->create([
                    'number' => $criterion['number'],
                    'name' => RatingFramework::FOUNDING_CRITERIA[$criterion['number']],
                    'weight' => $criterion['weight'],
                    'descriptors' => $criterion['descriptors'],
                    'industry_variants' => $criterion['industry_variants'] ?? [],
                    'is_placeholder' => false,
                ]);
            }
        });
    }

    /**
     * @return array<int, array{number:int,weight:float,descriptors:array<string, string>,industry_variants?:array<string, mixed>}>
     */
    public static function values(): array
    {
        $weights = [
            1 => 8.0,
            2 => 7.0,
            3 => 8.0,
            4 => 8.0,
            5 => 9.0,
            6 => 8.0,
            7 => 8.0,
            8 => 7.0,
            9 => 9.0,
            10 => 8.0,
            11 => 8.0,
            12 => 12.0,
        ];

        return array_map(
            static fn (int $number): array => [
                'number' => $number,
                'weight' => $weights[$number],
                'descriptors' => self::descriptors(RatingFramework::FOUNDING_CRITERIA[$number]),
                'industry_variants' => [],
            ],
            array_keys(RatingFramework::FOUNDING_CRITERIA),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function descriptors(string $name): array
    {
        return [
            'exceptional' => "{$name} is specific, evidence-backed, internally consistent, and ready for advisor-supported execution.",
            'strong' => "{$name} is clear and mostly evidenced, with only minor advisor follow-up required.",
            'developing' => "{$name} is directionally useful but has material gaps or assumptions to test before launch.",
            'needs_work' => "{$name} is too vague, unsupported, or inconsistent to rely on for launch decisions.",
        ];
    }

    private function matchesFoundingRubric(RatingFramework $framework): bool
    {
        if (! $framework->production_ready) {
            return false;
        }

        $expected = collect(self::values())->keyBy('number');

        if ($framework->criteria->count() !== $expected->count()) {
            return false;
        }

        foreach ($framework->criteria as $criterion) {
            $value = $expected->get($criterion->number);

            if (! is_array($value)) {
                return false;
            }

            if ($criterion->is_placeholder) {
                return false;
            }

            if ((string) $criterion->name !== RatingFramework::FOUNDING_CRITERIA[$criterion->number]) {
                return false;
            }

            if (abs(((float) $criterion->weight) - ((float) $value['weight'])) > 0.001) {
                return false;
            }
        }

        return true;
    }
}
