<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

/**
 * An external funding request, and the reasoning that sized it.
 *
 * The amount on its own says nothing a lender can check. SS-03 asks which
 * requirement the request addresses, which scenario that requirement was taken
 * from, what was applied before reaching for external money, and when the
 * money is needed - because a request sized against the base case and one
 * sized against the worst downside are different asks, and neither is wrong
 * until it says which it is.
 *
 * Every field but nothing is nullable, because a request an advisor has only
 * partly established is a real state. An absent metric is nobody having said
 * what the request answers, and it must never read as a cash deficit by
 * default: the two metrics give different amounts off the same scenarios.
 */
final readonly class FundingRequest implements \JsonSerializable
{
    /**
     * The requirements a request can address.
     *
     * A cash deficit is money the business runs out of; a reserve gap is money
     * it holds less of than its own policy requires. Asking for one and
     * reporting the other overstates or understates the need by exactly the
     * difference between them.
     */
    public const METRICS = ['cash_deficit', 'reserve_gap'];

    public function __construct(
        public ?string $amount,
        public ?string $neededOn,
        public ?string $metric,
        public ?string $basisScenario,
        public ?string $nonExternalTotal,
        public ?string $reserveOnlyDisclosure,
    ) {
        DriverNumbers::read($amount);
        DriverNumbers::read($nonExternalTotal);
        ForecastDates::read($neededOn);
        if ($metric !== null && ! in_array($metric, self::METRICS, true)) {
            throw new \InvalidArgumentException('A funding request addresses a cash deficit or a reserve gap; no other requirement is recognised.');
        }
        if ($basisScenario !== null && trim($basisScenario) === '') {
            throw new \InvalidArgumentException('Name the scenario the request is sized against, or leave it unstated.');
        }
    }

    /**
     * Whether anything was asked for at all.
     *
     * A request of zero is a decision not to borrow, which is a different
     * statement from one nobody has made, and the disclosure requirement only
     * reaches the first of them.
     */
    public function isRequested(): bool
    {
        $amount = DriverNumbers::read($this->amount);

        return $amount !== null && $amount->isPositive();
    }

    /** @return array{amount:?string,needed_on:?string,metric:?string,basis_scenario:?string,non_external_total:?string,reserve_only_disclosure:?string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'needed_on' => $this->neededOn, 'metric' => $this->metric,
            'basis_scenario' => $this->basisScenario, 'non_external_total' => $this->nonExternalTotal,
            'reserve_only_disclosure' => $this->reserveOnlyDisclosure];
    }
}
