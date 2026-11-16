<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

/**
 * How long forecast revenue takes to become cash.
 *
 * Without these the model recognises revenue and never collects it: operating
 * receipts come only from trade items, which are opening debtors and one-offs
 * an advisor types as amounts, so a revenue downside barely moves the cash
 * position and a funding ask sized against that position understates what the
 * business needs. These terms are the cash leg the projection never had.
 *
 * One set for the whole model rather than one per stream. A business that
 * genuinely bills some work on different terms needs per-stream terms, and
 * that is a later change; stating one set is honest about being an assumption
 * and is enough for a downside to reach cash.
 *
 * Absent means absent. A reconstruction that states no terms generates no
 * receipts and reports that it states none - it does not assume revenue is
 * collected the moment it is earned, which would be the most optimistic
 * reading available and the one nobody chose.
 */
final readonly class CollectionTerms implements \JsonSerializable
{
    public function __construct(
        public ?int $debtorDays,
        public ?string $onInvoicePercent,
        public ?string $source,
    ) {
        if ($debtorDays !== null && ($debtorDays < 0 || $debtorDays > 36500)) {
            throw new \InvalidArgumentException('Collection days must be explicitly non-negative calendar days.');
        }
        $percent = DriverNumbers::read($onInvoicePercent);
        if ($percent !== null && ($percent->isNegative() || $percent->isGreaterThan(100))) {
            throw new \InvalidArgumentException('The share collected at invoice is a percentage between nought and one hundred.');
        }
    }

    /**
     * Whether a receipt can be scheduled from these terms at all.
     *
     * Partly stated is not usable: a debtor day count with no invoice share
     * leaves the split unknown, and a share with no day count leaves the
     * balance undated. Either way the schedule reports the gap rather than
     * filling it in.
     */
    public function complete(): bool
    {
        return $this->debtorDays !== null && $this->onInvoicePercent !== null && trim($this->source ?? '') !== '';
    }

    /**
     * The field names behind each gap, unprefixed.
     *
     * The schedule reports a per-stream gap as "{stream}.{period}.{field}" and
     * its caller prefixes the lot with "collection.", so naming the subject
     * here too would report "collection.collection.debtor_days".
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];
        if ($this->debtorDays === null) {
            $missing[] = 'debtor_days';
        }
        if ($this->onInvoicePercent === null) {
            $missing[] = 'on_invoice_percent';
        }
        if (trim($this->source ?? '') === '') {
            $missing[] = 'source';
        }

        return $missing;
    }

    /** @return array{debtor_days:?int,on_invoice_percent:?string,source:?string} */
    public function jsonSerialize(): array
    {
        return ['debtor_days' => $this->debtorDays, 'on_invoice_percent' => $this->onInvoicePercent,
            'source' => $this->source];
    }
}
