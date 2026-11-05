<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class ActualProfit implements \JsonSerializable
{
    public function __construct(public ?string $revenue, public ?string $operatingExpenses, public ?string $depreciation,
        public ?string $interest, public ?string $otherIncome, public ?string $profitBeforeTax, public ?string $incomeTaxExpense)
    {
        foreach ($this->jsonSerialize() as $amount) {
            AccountingNumbers::signed($amount);
        }
    }

    /** @return array{revenue:?string,operating_expenses:?string,depreciation:?string,interest:?string,other_income:?string,profit_before_tax:?string,income_tax_expense:?string} */
    public function jsonSerialize(): array
    {
        return ['revenue' => $this->revenue, 'operating_expenses' => $this->operatingExpenses, 'depreciation' => $this->depreciation,
            'interest' => $this->interest, 'other_income' => $this->otherIncome, 'profit_before_tax' => $this->profitBeforeTax, 'income_tax_expense' => $this->incomeTaxExpense];
    }
}
