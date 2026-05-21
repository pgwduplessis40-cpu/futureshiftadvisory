<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;

final class AnalyticalFramework
{
    /**
     * @return array<int, AnalysisLens>
     */
    public function lenses(): array
    {
        return AnalysisLens::cases();
    }

    /**
     * @return array<int, string>
     */
    public function values(): array
    {
        return AnalysisLens::values();
    }

    /**
     * @param  array<int, AnalysisLens|string>  $lenses
     * @return array<int, string>
     */
    public function normalise(array $lenses): array
    {
        $values = [];

        foreach ($lenses as $lens) {
            $value = $lens instanceof AnalysisLens ? $lens->value : $lens;

            if (AnalysisLens::tryFrom($value) !== null) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }
}
