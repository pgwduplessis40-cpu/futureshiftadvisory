<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class ActualBalances implements \JsonSerializable
{
    public function __construct(public ?string $bankCash, public ?string $restrictedCash, public ?string $receivables,
        public ?string $payables, public ?string $customerAdvances, public ?string $supplierPrepayments)
    {
        AccountingNumbers::signed($bankCash);
        foreach ([$restrictedCash, $receivables, $payables, $customerAdvances, $supplierPrepayments] as $amount) {
            DriverNumbers::read($amount);
        }
    }

    /** @return array{bank_cash:?string,restricted_cash:?string,receivables:?string,payables:?string,customer_advances:?string,supplier_prepayments:?string} */
    public function jsonSerialize(): array
    {
        return ['bank_cash' => $this->bankCash, 'restricted_cash' => $this->restrictedCash, 'receivables' => $this->receivables,
            'payables' => $this->payables, 'customer_advances' => $this->customerAdvances, 'supplier_prepayments' => $this->supplierPrepayments];
    }
}
