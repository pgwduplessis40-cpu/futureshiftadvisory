<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class ActualMonth implements \JsonSerializable
{
    /** @param list<CashMovement>|null $movements
     * @param  array<string,string>|null  $movementAccounts
     * @param  list<TradeItem>|null  $closingObligations
     */
    public function __construct(public string $period, public ?string $state, public ActualSource $source, public ActualProfit $profit,
        public ActualBalances $opening, public ActualBalances $closing, public ?array $movements, public ?array $movementAccounts,
        public ?array $closingObligations, public ?string $preTradingEvidence = null)
    {
        ForecastDates::read($period.'-01');
        if ($state !== null && ! in_array($state, ['trading', 'pre_trading'], true)) {
            throw new \InvalidArgumentException('An actual month must be trading or evidenced pre-trading.');
        }
    }

    /** @return array{period:string,state:?string,source:ActualSource,profit:ActualProfit,opening:ActualBalances,closing:ActualBalances,movements:list<CashMovement>|null,movement_accounts:array<string,string>|null,closing_obligations:list<TradeItem>|null,pre_trading_evidence:?string} */
    public function jsonSerialize(): array
    {
        return ['period' => $this->period, 'state' => $this->state, 'source' => $this->source, 'profit' => $this->profit,
            'opening' => $this->opening, 'closing' => $this->closing, 'movements' => $this->movements,
            'movement_accounts' => $this->movementAccounts, 'closing_obligations' => $this->closingObligations, 'pre_trading_evidence' => $this->preTradingEvidence];
    }
}
