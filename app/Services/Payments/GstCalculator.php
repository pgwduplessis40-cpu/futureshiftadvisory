<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Support\Methodology\ProvidesMethodology;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class GstCalculator implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['payments.gst'];
    }

    private const FALLBACK_RATE_PERCENT = 15.0;

    public function __construct(private readonly RequestContext $requestContext) {}

    public function ratePercent(): float
    {
        return max(0.0, round($this->implementedRatePercent() ?? self::FALLBACK_RATE_PERCENT, 2));
    }

    public function grossFromExclusive(int|float|string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('GST calculation amount must be numeric.');
        }

        $exclusive = (float) $amount;

        if ($exclusive < 0) {
            throw new InvalidArgumentException('GST calculation amount must not be negative.');
        }

        return number_format($exclusive * (1 + ($this->ratePercent() / 100)), 2, '.', '');
    }

    public function gstFromExclusive(int|float|string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('GST calculation amount must be numeric.');
        }

        $exclusive = (float) $amount;

        if ($exclusive < 0) {
            throw new InvalidArgumentException('GST calculation amount must not be negative.');
        }

        return number_format($exclusive * ($this->ratePercent() / 100), 2, '.', '');
    }

    private function implementedRatePercent(): ?float
    {
        if (! Schema::hasTable('reference_data_entries') || ! Schema::hasTable('learning_updates')) {
            return null;
        }

        return $this->requestContext->withSystemContext(function (): ?float {
            $entry = ReferenceDataEntry::query()
                ->where('dataset', ReferenceDataEntry::DATASET_GST_RATE)
                ->whereHas('learningUpdate', fn ($query) => $query->where('status', LearningUpdate::STATUS_IMPLEMENTED))
                ->latest('as_at')
                ->latest()
                ->first();

            if (! $entry instanceof ReferenceDataEntry) {
                return null;
            }

            $rate = data_get($entry->payload, 'rate_percent');

            return is_numeric($rate) ? (float) $rate : null;
        });
    }
}
