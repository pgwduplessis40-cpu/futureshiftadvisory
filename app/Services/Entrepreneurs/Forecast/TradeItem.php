<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class TradeItem implements \JsonSerializable
{
    /** @param list<CashMovement>|null $settlements */
    public function __construct(public string $id, public string $source, public string $kind, public ?string $raisedOn, public ?string $amount, public ?array $settlements)
    {
        if (trim($id) === '' || trim($source) === '' || ! in_array($kind, ['receivable', 'payable'], true)) {
            throw new \InvalidArgumentException('A trade item needs an identity, source and receivable/payable kind.');
        }
        ForecastDates::read($raisedOn);
        DriverNumbers::read($amount);
        foreach ($settlements ?? [] as $settlement) {
            if ($settlement->direction !== ($kind === 'receivable' ? 'in' : 'out')) {
                throw new \InvalidArgumentException('Settlement direction does not match its trade item.');
            }
        }
    }

    /** @return array{id:string,source:string,kind:string,raised_on:?string,amount:?string,settlements:list<CashMovement>|null} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'kind' => $this->kind, 'raised_on' => $this->raisedOn,
            'amount' => $this->amount, 'settlements' => $this->settlements];
    }
}
