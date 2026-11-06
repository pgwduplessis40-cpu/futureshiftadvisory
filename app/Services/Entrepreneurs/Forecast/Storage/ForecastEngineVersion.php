<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\FundingMeasures;
use App\Services\Entrepreneurs\Forecast\LenderMetrics;
use App\Services\Entrepreneurs\Forecast\RevenueProjection;
use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioCatalogue;
use App\Services\Entrepreneurs\Forecast\ScenarioRunner;

/**
 * The single version a stored model, a snapshot and a financial payload must
 * all agree on.
 *
 * Each engine carries its own slice version. Quoting one of them would let a
 * payload claim a version that says nothing about the engines that actually
 * produced the rest of it, so the model version is the composite, and
 * `components()` keeps the parts visible when one of them moves.
 */
final class ForecastEngineVersion
{
    public const CURRENT = 'fsa-forecast.2d.1';

    /** The slice version of every engine contributing to a stored model.
     * @return array<string,string>
     */
    public static function components(): array
    {
        return [
            'revenue' => RevenueProjection::VERSION,
            'cash' => CashLedger::VERSION,
            'actuals' => ActualsRebase::VERSION,
            'scenarios' => ScenarioApplication::VERSION,
            'scenario_catalogue' => ScenarioCatalogue::VERSION,
            'scenario_runner' => ScenarioRunner::VERSION,
            'metrics' => LenderMetrics::VERSION,
            'funding' => FundingMeasures::VERSION,
        ];
    }

    /** A stored model from an unknown version is not loaded and guessed at. */
    public static function assertReadable(string $version): void
    {
        if ($version !== self::CURRENT) {
            throw new \InvalidArgumentException(sprintf(
                'Stored forecast model version "%s" cannot be read by engine version "%s".',
                $version,
                self::CURRENT,
            ));
        }
    }
}
