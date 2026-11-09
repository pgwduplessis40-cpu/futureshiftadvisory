<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

/**
 * One named source of funding behind the forecast.
 *
 * Opening cash answers what the business already holds. This answers what it
 * has arranged to receive, and the two are kept apart deliberately: adding a
 * source that is already sitting in the bank balance counts the same money
 * twice, which is the first thing a funding reconciliation looks for.
 *
 * Every field but the identity and the record is nullable, because an
 * arrangement an advisor has only partly established is a real state. An
 * absent commitment is nobody having said whether the money is committed, and
 * it must never read as an uncommitted one.
 */
final readonly class FundingSource implements \JsonSerializable
{
    /** Commitments the engine recognises. Anything else is a word nobody can act on. */
    public const COMMITMENTS = ['committed', 'indicative'];

    /**
     * Restrictions the engine recognises.
     *
     * Restricted money exists and is real, but it cannot be spent on whatever
     * the forecast needs, so it is recorded as the restricted money it is
     * rather than quietly counted as available.
     */
    public const RESTRICTIONS = ['unrestricted', 'restricted'];

    public function __construct(
        public string $id,
        public string $source,
        public ?string $identity,
        public ?string $type,
        public ?string $amount,
        public ?string $commitment,
        public ?string $availableFrom,
        public ?string $drawnOn,
        public ?string $restriction,
        public ?bool $verified,
    ) {
        if (trim($id) === '' || trim($source) === '') {
            throw new \InvalidArgumentException('Each funding source needs an identity and the record it comes from.');
        }
        DriverNumbers::read($amount);
        ForecastDates::read($availableFrom);
        ForecastDates::read($drawnOn);
        if ($commitment !== null && ! in_array($commitment, self::COMMITMENTS, true)) {
            throw new \InvalidArgumentException('Funding is committed or indicative; no other commitment is recognised.');
        }
        if ($restriction !== null && ! in_array($restriction, self::RESTRICTIONS, true)) {
            throw new \InvalidArgumentException('Funding is unrestricted or restricted; no other restriction is recognised.');
        }
        // Money cannot be drawn before it is there to draw. The pair is
        // refused here for the same reason a tax period is refused when it
        // ends before it starts: it is not a finding about the forecast, it
        // is a record that does not describe anything.
        if ($availableFrom !== null && $drawnOn !== null && $drawnOn < $availableFrom) {
            throw new \InvalidArgumentException('Funding cannot be drawn before the date it becomes available.');
        }
    }

    /**
     * The dated financing inflow this source puts into the cash ledger.
     *
     * None, until both the amount and the date it is drawn are known. A draw
     * with one of them missing is an arrangement still being established, and
     * placing it in the ledger with a guessed figure or date would put cash in
     * a month nobody has said it arrives in.
     */
    public function draw(int $sequence): ?CashMovement
    {
        return $this->amount === null || $this->drawnOn === null ? null : new CashMovement(
            $this->id.'.draw', $this->source, $this->drawnOn, $this->amount, 'in', 'funding', $sequence, $this->verified,
        );
    }

    /** @return array{id:string,source:string,identity:?string,type:?string,amount:?string,commitment:?string,available_from:?string,drawn_on:?string,restriction:?string,verified:?bool} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'identity' => $this->identity, 'type' => $this->type,
            'amount' => $this->amount, 'commitment' => $this->commitment, 'available_from' => $this->availableFrom,
            'drawn_on' => $this->drawnOn, 'restriction' => $this->restriction, 'verified' => $this->verified];
    }
}
