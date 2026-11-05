<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Support\Methodology\ProvidesMethodology;

final class ActualsRebase implements ProvidesMethodology
{
    public const VERSION = 'phase-2c.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @param list<ActualMonth> $records */
    public function calculate(ForecastCalendar $calendar, string $asOfDate, string $entityId, array $records): ActualsWindow
    {
        ForecastDates::read($asOfDate);
        if (trim($entityId) === '') {
            throw new \InvalidArgumentException('Actuals require an explicit business/entity scope.');
        }
        // The explicit as-of date is end-of-day; only completed calendar months
        // become actuals. No ambient clock or shortened cutoff hides a gap.
        $required = array_values(array_filter($calendar->periods, fn (string $period): bool => ForecastDates::read($period.'-01')->format('Y-m-t') <= $asOfDate));
        $future = array_values(array_diff($calendar->periods, $required));
        $byPeriod = [];
        foreach ($records as $record) {
            if (isset($byPeriod[$record->period]) || ! in_array($record->period, $required, true)) {
                throw new \InvalidArgumentException('Actual periods must be unique, completed and within the declared calendar.');
            }
            $byPeriod[$record->period] = $record;
        }
        ksort($byPeriod);
        $missing = $errors = [];
        $previous = null;
        foreach ($required as $period) {
            $record = $byPeriod[$period] ?? null;
            if ($record === null) {
                $missing[] = $period.'.actual_record';
                $previous = null;

                continue;
            }
            $review = (new ActualMonthReview)->inspect($record, $entityId);
            foreach ($review['missing'] as $field) {
                $missing[] = $period.'.'.$field;
            }
            foreach ($review['errors'] as $field) {
                $errors[] = $period.'.'.$field;
            }
            if ($previous !== null) {
                foreach ($previous->closing->jsonSerialize() as $field => $closing) {
                    if (AccountingNumbers::same($closing, $record->opening->jsonSerialize()[$field]) === false) {
                        $errors[] = $period.'.opening_'.$field.'_does_not_match_prior_close';
                    }
                }
            }
            $previous = $record;
        }
        $status = $required === [] ? 'not_applicable' : AccountingNumbers::status($missing, $errors);
        $usable = $status === 'pass' && $previous !== null;
        $cutoff = $required === [] ? null : $required[array_key_last($required)];

        return new ActualsWindow($status, $asOfDate, $cutoff, array_values($byPeriod), $future,
            $usable ? $previous->closing : null, $usable ? $previous->closingObligations : null,
            CanonicalJson::hash([$calendar, $asOfDate, $entityId, $byPeriod]), $missing, $errors);
    }
}
