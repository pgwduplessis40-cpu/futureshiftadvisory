<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Requested against effective shock for one scenario.
 *
 * A requested volume factor of 0.8 rarely produces exactly a 20% revenue
 * reduction: capacity limits, pricing and excluded streams all intervene. Both
 * figures are reported with the reason they differ, so the difference reads as
 * a modelled consequence rather than an arithmetic error.
 */
final readonly class ShockReport implements \JsonSerializable
{
    /** @param list<string> $reasons */
    public function __construct(
        public string $scenarioId,
        public string $shockType,
        public string $requestedFactor,
        public string $baselineAmount,
        public string $shockedAmount,
        public array $reasons = [],
    ) {
        foreach ([$baselineAmount, $shockedAmount] as $value) {
            DriverNumbers::read($value);
        }
    }

    /** Effective factor as shocked over baseline, or null when the baseline is
     * zero and no proportion is defined. A zero baseline is not a 100%
     * reduction; it is an undefined proportion and says so.
     */
    public function effectiveFactor(): ?string
    {
        $baseline = BigDecimal::of($this->baselineAmount);
        if ($baseline->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($this->shockedAmount)->dividedBy($baseline, 6, RoundingMode::HalfUp);
    }

    /** Effective change as a signed amount: negative is a reduction. */
    public function effectiveChange(): string
    {
        return DriverNumbers::money(
            BigDecimal::of($this->shockedAmount)->minus(BigDecimal::of($this->baselineAmount))
        );
    }

    /** True when the effective factor differs from the requested one, so the
     * reasons list must be populated for the result to be readable.
     */
    public function divergedFromRequest(): bool
    {
        $effective = $this->effectiveFactor();
        if ($effective === null) {
            return true;
        }

        return BigDecimal::of($effective)->toScale(6, RoundingMode::HalfUp)
            ->isEqualTo(BigDecimal::of($this->requestedFactor)->toScale(6, RoundingMode::HalfUp)) === false;
    }

    /** @return array{scenario_id:string,shock_type:string,requested_factor:string,baseline_amount:string,shocked_amount:string,effective_factor:?string,effective_change:string,diverged:bool,reasons:list<string>} */
    public function jsonSerialize(): array
    {
        return ['scenario_id' => $this->scenarioId, 'shock_type' => $this->shockType,
            'requested_factor' => $this->requestedFactor, 'baseline_amount' => $this->baselineAmount,
            'shocked_amount' => $this->shockedAmount, 'effective_factor' => $this->effectiveFactor(),
            'effective_change' => $this->effectiveChange(), 'diverged' => $this->divergedFromRequest(),
            'reasons' => $this->reasons];
    }
}
