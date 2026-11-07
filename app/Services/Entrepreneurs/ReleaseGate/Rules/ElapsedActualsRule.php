<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class ElapsedActualsRule implements Rule
{
    public function id(): string
    {
        return 'MI-07';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $asOf = $context->facts->one('actuals.as_of');
        $start = $context->facts->one('model.start_date');
        if ($asOf === null || $start === null || ! FactValidation::date($asOf->value) || ! FactValidation::date($start->value) || ! $asOf->confirmed) {
            return [Subcheck::missing('snapshot', 'the confirmed release snapshot date and the model start date')];
        }
        $elapsed = $this->elapsedMonths((string) $start->value, (string) $asOf->value);
        if ($elapsed < 1) {
            return [Checks::result('snapshot', true,
                'No reporting month has closed between '.$start->value.' and the '.$asOf->value.' release snapshot.', [$start, $asOf])];
        }
        $checks = [];
        $lastReconciled = null;
        $lastClosing = null;
        for ($month = 1; $month <= $elapsed; $month++) {
            $period = 'month:'.$month;
            $status = $context->facts->one('actuals.status', period: $period);
            $label = $context->facts->one('period.label', period: $period);
            if (! is_string($status?->value) || ! in_array($status->value, ['reconciled', 'pre_trading'], true) || ! $status->confirmed) {
                $checks[] = Subcheck::missing('elapsed:'.$month, 'a reconciled or explicitly pre-trading record for the closed month '.$period);

                continue;
            }
            $checks[] = ! is_string($label?->value)
                ? Subcheck::missing('label:'.$month, 'a label distinguishing actual from forecast for '.$period)
                : Checks::result('label:'.$month, $label->value === 'actual',
                    $period.' has closed and is labelled "'.$label->value.'".', [$label, $status]);
            if ($status->value === 'pre_trading') {
                // Pre-trading is a legitimate answer for an elapsed month, but
                // it is an assertion with a source, not an empty row.
                $checks[] = Checks::result('elapsed:'.$month, $status->sources !== [],
                    $period.' is established as pre-trading rather than left unreconciled.', [$status]);

                continue;
            }
            $closing = $context->facts->one('actuals.closing_cash', period: $period);
            if ($closing?->number() === null) {
                $checks[] = Subcheck::missing('elapsed:'.$month, 'the reconciled closing cash balance for '.$period);

                continue;
            }
            // "Reconciled" is a claim about an outside record, so the balance
            // carries its own source rather than inheriting the status flag's.
            $checks[] = Checks::result('elapsed:'.$month, $closing->confirmed && $closing->sources !== [],
                $period.' is reconciled to a closing balance of '.$closing->value.' against a named record.', [$status, $closing]);
            $lastReconciled = $month;
            $lastClosing = $closing;
        }
        $checks[] = $this->handover($context, $lastReconciled, $lastClosing);

        return $checks;
    }

    private function handover(DocumentContext $context, ?int $lastReconciled, ?Fact $lastClosing): Subcheck
    {
        if ($lastReconciled === null || $lastClosing === null) {
            return Subcheck::missing('handover', 'at least one reconciled actual month before the forecast resumes');
        }
        $period = 'month:'.($lastReconciled + 1);
        $opening = $context->facts->one('forecast.opening_cash', period: $period);
        $label = $context->facts->one('period.label', period: $period);
        if ($opening?->number() === null || ! is_string($label?->value)) {
            return Subcheck::missing('handover', 'the opening cash and label of the first forecast month '.$period);
        }
        if ($label->value !== 'forecast') {
            return Subcheck::missing('handover', 'a forecast-labelled month immediately after the last reconciled actual '.$period);
        }

        return Checks::result('handover', Checks::close($lastClosing->number(), $opening->number()),
            'Forecast cash in '.$period.' opens at '.$opening->value.' from a reconciled closing balance of '.$lastClosing->value.'.',
            [$lastClosing, $opening]);
    }

    /** Whole reporting months closed between the model start and the snapshot. */
    private function elapsedMonths(string $start, string $asOf): int
    {
        [$startYear, $startMonth] = array_map(intval(...), explode('-', $start));
        [$asOfYear, $asOfMonth] = array_map(intval(...), explode('-', $asOf));

        // The snapshot month is still open, so it is not counted.
        return max(0, ($asOfYear - $startYear) * 12 + ($asOfMonth - $startMonth));
    }
}
