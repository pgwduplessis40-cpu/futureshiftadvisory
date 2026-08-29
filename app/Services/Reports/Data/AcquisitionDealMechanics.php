<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Typed New Zealand transaction mechanics shown alongside the decision price.
 */
final readonly class AcquisitionDealMechanics
{
    public function __construct(
        public string $gstZeroRatingStatus,
        public float $gstCashExposureNzd,
        public string $workingCapitalPeg,
        public string $vendorFinance,
        public string $earnout,
    ) {}

    /**
     * @return array{gst_zero_rating_status:string,gst_cash_exposure_nzd:float,working_capital_peg:string,vendor_finance:string,earnout:string}
     */
    public function metadata(): array
    {
        return [
            'gst_zero_rating_status' => $this->gstZeroRatingStatus,
            'gst_cash_exposure_nzd' => $this->gstCashExposureNzd,
            'working_capital_peg' => $this->workingCapitalPeg,
            'vendor_finance' => $this->vendorFinance,
            'earnout' => $this->earnout,
        ];
    }
}
