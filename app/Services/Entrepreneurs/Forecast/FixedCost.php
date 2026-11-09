<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

/**
 * One recurring overhead the business carries regardless of what it sells.
 *
 * A fixed cost is a rate charged a number of times a year, not a dated
 * invoice. Trade items already hold individual payables; this holds the
 * arrangement behind them, so that the invoices and the monthly expense are
 * both derived from one stated cadence rather than typed twice and left to
 * disagree.
 *
 * Every field but the identity and the record is nullable, because a cost an
 * advisor has only partly established is a real state. An absent cadence is
 * nobody having said how often the charge falls, and it must never read as a
 * monthly one.
 */
final readonly class FixedCost implements \JsonSerializable
{
    /**
     * Cadences a fixed cost can carry.
     *
     * Each one has to be a cadence the recurrence can actually generate and a
     * cadence the release gate can annualise, and only these four are both.
     * `once` is excluded because a single charge is a trade item rather than a
     * fixed cost, and fortnightly because the recurrence does not generate it.
     */
    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'annual'];

    public function __construct(
        public string $id,
        public string $source,
        public ?string $rate,
        public ?string $quantity,
        public ?string $frequency,
        public ?string $firstInvoiceOn,
        public ?string $lastInvoiceOn,
        public ?int $creditorDays,
        public ?bool $reviewed,
    ) {
        if (trim($id) === '' || trim($source) === '') {
            throw new \InvalidArgumentException('Each fixed cost needs an identity and the record it comes from.');
        }
        DriverNumbers::read($rate);
        DriverNumbers::read($quantity);
        ForecastDates::read($firstInvoiceOn);
        ForecastDates::read($lastInvoiceOn);
        if ($frequency !== null && ! in_array($frequency, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('A fixed cost recurs weekly, monthly, quarterly or annually; no other cadence is recognised.');
        }
        // An invoice window that closes before it opens is refused here for
        // the same reason a tax period ending before it starts is: it is not
        // a finding about the forecast, it is a record that describes nothing.
        if ($firstInvoiceOn !== null && $lastInvoiceOn !== null && $lastInvoiceOn < $firstInvoiceOn) {
            throw new \InvalidArgumentException('A fixed cost cannot stop being invoiced before it starts.');
        }
        if ($creditorDays !== null && ($creditorDays < 0 || $creditorDays > 36500)) {
            throw new \InvalidArgumentException('Payment days must be explicitly non-negative calendar days.');
        }
    }

    /** @return array{id:string,source:string,rate:?string,quantity:?string,frequency:?string,first_invoice_on:?string,last_invoice_on:?string,creditor_days:?int,reviewed:?bool} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'rate' => $this->rate, 'quantity' => $this->quantity,
            'frequency' => $this->frequency, 'first_invoice_on' => $this->firstInvoiceOn,
            'last_invoice_on' => $this->lastInvoiceOn, 'creditor_days' => $this->creditorDays, 'reviewed' => $this->reviewed];
    }
}
