<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Typed price envelope for one acquisition Go/No-Go decision.
 *
 * @phpstan-type DecisionMetadata array{
 *     base_high_nzd:float,
 *     base_high_available:bool,
 *     deal_structure_adjustment_nzd:float,
 *     synergy_adjustment_nzd:float,
 *     risk_adjustment_nzd:float,
 *     holidays_act_liability_nzd:float,
 *     working_capital_adjustment_nzd:float,
 *     walk_away_price_nzd:float,
 *     walk_away_price_available:bool,
 *     asking_price_nzd:float,
 *     asking_price_available:bool,
 *     gap_to_walk_away_nzd:float,
 *     gap_to_walk_away_available:bool,
 *     price_chip_count:int
 * }
 */
final readonly class AcquisitionWalkAwayPrice
{
    /**
     * @param  list<AcquisitionPriceChip>  $priceChips
     */
    public function __construct(
        public ?DdMoneyRange $baseRange,
        public ?float $baseHighNzd,
        public float $dealStructureAdjustmentNzd,
        public float $synergyAdjustmentNzd,
        public float $riskAdjustmentNzd,
        public float $holidaysActLiabilityNzd,
        public float $workingCapitalAdjustmentNzd,
        public ?float $walkAwayPriceNzd,
        public ?float $askingPriceNzd,
        public ?float $gapToWalkAwayNzd,
        public array $priceChips,
        public AcquisitionDealMechanics $dealMechanics,
    ) {}

    /** @return DecisionMetadata */
    public function metadata(): array
    {
        return [
            'base_high_nzd' => $this->baseHighNzd ?? 0.0,
            'base_high_available' => $this->baseHighNzd !== null,
            'deal_structure_adjustment_nzd' => $this->dealStructureAdjustmentNzd,
            'synergy_adjustment_nzd' => $this->synergyAdjustmentNzd,
            'risk_adjustment_nzd' => $this->riskAdjustmentNzd,
            'holidays_act_liability_nzd' => $this->holidaysActLiabilityNzd,
            'working_capital_adjustment_nzd' => $this->workingCapitalAdjustmentNzd,
            'walk_away_price_nzd' => $this->walkAwayPriceNzd ?? 0.0,
            'walk_away_price_available' => $this->walkAwayPriceNzd !== null,
            'asking_price_nzd' => $this->askingPriceNzd ?? 0.0,
            'asking_price_available' => $this->askingPriceNzd !== null,
            'gap_to_walk_away_nzd' => $this->gapToWalkAwayNzd ?? 0.0,
            'gap_to_walk_away_available' => $this->gapToWalkAwayNzd !== null,
            'price_chip_count' => count($this->priceChips),
        ];
    }
}
