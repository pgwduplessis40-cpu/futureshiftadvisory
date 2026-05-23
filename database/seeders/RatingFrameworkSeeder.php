<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RatingFramework;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RatingFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $framework = RatingFramework::query()->firstOrCreate(
                [
                    'version' => 1,
                    'industry_variant' => null,
                ],
                [
                    'status' => RatingFramework::STATUS_PUBLISHED,
                    'production_ready' => false,
                    'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
                    'published_at' => now(),
                ],
            );

            if ($framework->criteria()->exists()) {
                return;
            }

            foreach (RatingFramework::FOUNDING_CRITERIA as $number => $name) {
                $framework->criteria()->create([
                    'number' => $number,
                    'name' => $name,
                    'weight' => round(100 / count(RatingFramework::FOUNDING_CRITERIA), 3),
                    'descriptors' => $this->placeholderDescriptors($name),
                    'industry_variants' => [],
                    'is_placeholder' => true,
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function placeholderDescriptors(string $name): array
    {
        return [
            'exceptional' => "Placeholder descriptor for exceptional {$name}.",
            'strong' => "Placeholder descriptor for strong {$name}.",
            'developing' => "Placeholder descriptor for developing {$name}.",
            'needs_work' => "Placeholder descriptor for needs-work {$name}.",
        ];
    }
}
