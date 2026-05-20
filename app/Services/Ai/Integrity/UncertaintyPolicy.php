<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Services\Ai\Contracts\Uncertainty;

final class UncertaintyPolicy
{
    public function derive(?string $dataQuality, ?float $confidence): Uncertainty
    {
        $quality = strtolower((string) $dataQuality);

        if ($quality === 'insufficient' || $quality === 'low') {
            return Uncertainty::High;
        }

        if ($confidence === null) {
            return Uncertainty::High;
        }

        if ($confidence < 0.4) {
            return Uncertainty::High;
        }

        if ($confidence < 0.7) {
            return Uncertainty::Medium;
        }

        if ($confidence < 0.9 || $quality === 'medium') {
            return Uncertainty::Low;
        }

        return Uncertainty::None;
    }
}
