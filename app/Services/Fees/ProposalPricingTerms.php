<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Models\Client;
use App\Models\FeeCalculation;
use App\Models\Proposal;

final class ProposalPricingTerms
{
    public function __construct(private readonly PilotFeeWaiverManager $pilotWaivers) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Client $client, FeeCalculation $feeCalculation): array
    {
        $pilot = $this->pilotWaivers->eligibility($client);
        $globalFreeAccess = (bool) data_get($feeCalculation->justification, 'free_access_mode.active', false);
        $advisoryFeeActive = ! $pilot['eligible'] && ! $globalFreeAccess;
        $listMid = $this->quotedMid($feeCalculation, []);
        $hosting = $this->hostingFromFeeCalculation($feeCalculation);
        $feeActive = ($advisoryFeeActive && $listMid > 0) || $hosting['monthly_fee'] > 0;

        return [
            'fee_active' => $feeActive,
            'payment_required' => $feeActive,
            'advisory_fee_active' => $advisoryFeeActive,
            'currency' => 'NZD',
            'list_fee' => [
                'low' => round((float) $feeCalculation->suggested_low, 2),
                'mid' => $listMid,
                'high' => round((float) $feeCalculation->suggested_high, 2),
            ],
            'payable_fee' => [
                'low' => $advisoryFeeActive ? round((float) $feeCalculation->suggested_low, 2) : 0.0,
                'mid' => $advisoryFeeActive ? $listMid : 0.0,
                'high' => $advisoryFeeActive ? round((float) $feeCalculation->suggested_high, 2) : 0.0,
            ],
            'hosting' => $hosting,
            'treatment' => $pilot['eligible']
                ? 'pilot_fee_waiver'
                : ($globalFreeAccess ? 'global_free_access' : 'standard'),
            'pilot_program_status' => $pilot['program_status'],
            'pilot_waiver_expires_at' => $pilot['eligible'] ? $pilot['expires_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function for(Proposal $proposal): array
    {
        $stored = $proposal->pricing_terms;
        if (is_array($stored) && array_key_exists('fee_active', $stored)) {
            if (is_array(data_get($stored, 'hosting'))) {
                return $stored;
            }

            if ($proposal->status->value !== 'signed') {
                return $this->addDirectHostingCharge($stored, $this->hostingFromProposal($proposal));
            }

            return $stored;
        }

        $mid = $this->quotedMid($proposal->feeCalculation, $proposal->pv_summary);
        $freeAccess = (bool) data_get($proposal->feeCalculation?->justification, 'free_access_mode.active', false);
        $advisoryFeeActive = ! $freeAccess;
        $hosting = $this->hostingFromProposal($proposal);
        $feeActive = ($advisoryFeeActive && $mid > 0) || $hosting['monthly_fee'] > 0;

        return [
            'fee_active' => $feeActive,
            'payment_required' => $feeActive,
            'advisory_fee_active' => $advisoryFeeActive,
            'currency' => 'NZD',
            'list_fee' => [
                'low' => (float) ($proposal->feeCalculation?->suggested_low ?? 0),
                'mid' => $mid,
                'high' => (float) ($proposal->feeCalculation?->suggested_high ?? 0),
            ],
            'payable_fee' => [
                'low' => $advisoryFeeActive ? (float) ($proposal->feeCalculation?->suggested_low ?? 0) : 0.0,
                'mid' => $advisoryFeeActive ? $mid : 0.0,
                'high' => $advisoryFeeActive ? (float) ($proposal->feeCalculation?->suggested_high ?? 0) : 0.0,
            ],
            'hosting' => $hosting,
            'treatment' => $freeAccess ? 'global_free_access' : 'legacy_standard',
        ];
    }

    public function requiresPayment(Proposal $proposal): bool
    {
        $terms = $this->for($proposal);

        return (bool) ($terms['fee_active'] ?? false)
            && (bool) ($terms['payment_required'] ?? false)
            && ($this->payableMid($proposal) > 0 || $this->hostingMonthlyFee($proposal) > 0);
    }

    public function payableMid(Proposal $proposal): float
    {
        return round((float) data_get($this->for($proposal), 'payable_fee.mid', 0), 2);
    }

    public function monthlyAmount(Proposal $proposal, int $termMonths): float
    {
        if (! $this->requiresPayment($proposal)) {
            return 0.0;
        }

        $advisoryMonthly = 0.0;
        if ($this->payableMid($proposal) > 0) {
            $monthly = data_get($proposal->feeCalculation?->justification, 'retainer.monthly_fee')
                ?? data_get($proposal->feeCalculation?->justification, 'monthly_retainer_fee')
                ?? data_get($proposal->pv_summary, 'monthly_retainer_fee');

            $advisoryMonthly = is_numeric($monthly) && (float) $monthly > 0
                ? round((float) $monthly, 2)
                : round($this->payableMid($proposal) / max(1, $termMonths), 2);
        }

        return round($advisoryMonthly + $this->hostingMonthlyFee($proposal), 2);
    }

    public function totalAmount(Proposal $proposal, int $termMonths): float
    {
        return round($this->payableMid($proposal) + ($this->hostingMonthlyFee($proposal) * max(1, $termMonths)), 2);
    }

    public function hostingMonthlyFee(Proposal $proposal): float
    {
        return round((float) data_get($this->for($proposal), 'hosting.monthly_fee', 0), 2);
    }

    public function hasPayableAdvisoryFee(Proposal $proposal): bool
    {
        return $this->payableMid($proposal) > 0;
    }

    public function isPilotWaiver(Proposal $proposal): bool
    {
        return data_get($this->for($proposal), 'treatment') === 'pilot_fee_waiver';
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    private function quotedMid(?FeeCalculation $feeCalculation, ?array $summary): float
    {
        $amount = $feeCalculation?->suggested_mid;

        if (is_numeric($amount)) {
            return round((float) $amount, 2);
        }

        $summaryAmount = data_get($summary, 'fee_suggested_mid');

        return is_numeric($summaryAmount) ? round((float) $summaryAmount, 2) : 0.0;
    }

    /**
     * @return array{enabled:bool,monthly_fee:float,annual_fee:float,currency:string}
     */
    private function hostingFromFeeCalculation(FeeCalculation $feeCalculation): array
    {
        $feeCalculation->loadMissing('integrationScope');

        return $this->normaliseHosting(data_get($feeCalculation->integrationScope?->computed, 'hosting'));
    }

    /**
     * @return array{enabled:bool,monthly_fee:float,annual_fee:float,currency:string}
     */
    private function hostingFromProposal(Proposal $proposal): array
    {
        return $this->normaliseHosting(data_get($proposal->scope, 'integration_quote_pack.hosting'));
    }

    /**
     * @param  array<string, mixed>  $terms
     * @param  array{enabled:bool,monthly_fee:float,annual_fee:float,currency:string}  $hosting
     * @return array<string, mixed>
     */
    private function addDirectHostingCharge(array $terms, array $hosting): array
    {
        if ($hosting['monthly_fee'] <= 0) {
            return $terms;
        }

        $terms['hosting'] = $hosting;
        $terms['fee_active'] = true;
        $terms['payment_required'] = true;

        return $terms;
    }

    /**
     * @return array{enabled:bool,monthly_fee:float,annual_fee:float,currency:string}
     */
    private function normaliseHosting(mixed $hosting): array
    {
        $monthlyFee = is_array($hosting) ? round(max(0.0, (float) ($hosting['monthly_fee'] ?? 0)), 2) : 0.0;
        $enabled = is_array($hosting) && (bool) ($hosting['enabled'] ?? false);

        return [
            'enabled' => $monthlyFee > 0 && $enabled,
            'monthly_fee' => $monthlyFee,
            'annual_fee' => round($monthlyFee * 12, 2),
            'currency' => is_array($hosting) ? (string) ($hosting['currency'] ?? 'NZD') : 'NZD',
        ];
    }
}
