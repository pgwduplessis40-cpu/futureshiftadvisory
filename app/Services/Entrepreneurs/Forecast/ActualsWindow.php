<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class ActualsWindow implements \JsonSerializable
{
    /** @param list<ActualMonth> $actualMonths
     * @param  list<string>  $forecastPeriods
     * @param  list<TradeItem>|null  $openingObligations
     * @param  list<string>  $missing
     * @param  list<string>  $errors
     */
    public function __construct(public string $status, public string $asOfDate, public ?string $cutoff,
        public array $actualMonths, public array $forecastPeriods, public ?ActualBalances $forecastOpening,
        public ?array $openingObligations, public string $historyHash, public array $missing, public array $errors) {}

    /** @param list<string> $periods */
    public function assertForecastPeriods(array $periods): void
    {
        if (! in_array($this->status, ['pass', 'not_applicable'], true)) {
            throw new \LogicException('Resolve the actuals review before applying forecast changes.');
        }
        if (array_diff($periods, $this->forecastPeriods) !== []) {
            throw new \InvalidArgumentException('Actual or out-of-calendar periods cannot be changed by a forecast scenario.');
        }
    }

    /** Already-paid/received cash is in the bank opening and cannot be added again.
     * @return list<CashMovement>|null
     */
    public function outstandingMovements(): ?array
    {
        if ($this->openingObligations === null || $this->cutoff === null) {
            return null;
        }
        $end = ForecastDates::read($this->cutoff.'-01')->format('Y-m-t');
        $movements = [];
        foreach ($this->openingObligations as $item) {
            foreach ($item->settlements ?? [] as $movement) {
                if ($movement->date === null || $movement->date > $end) {
                    $movements[] = $movement;
                }
            }
        }

        return $movements;
    }

    /** @return array{status:string,as_of_date:string,cutoff:?string,actual_months:list<ActualMonth>,forecast_periods:list<string>,forecast_opening:?ActualBalances,opening_obligations:list<TradeItem>|null,history_hash:string,missing:list<string>,errors:list<string>} */
    public function jsonSerialize(): array
    {
        return ['status' => $this->status, 'as_of_date' => $this->asOfDate, 'cutoff' => $this->cutoff,
            'actual_months' => $this->actualMonths, 'forecast_periods' => $this->forecastPeriods,
            'forecast_opening' => $this->forecastOpening, 'opening_obligations' => $this->openingObligations,
            'history_hash' => $this->historyHash, 'missing' => $this->missing, 'errors' => $this->errors];
    }
}
