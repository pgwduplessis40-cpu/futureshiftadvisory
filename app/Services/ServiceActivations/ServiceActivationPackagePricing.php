<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Services\Fees\PilotFeeWaiverManager;
use App\Services\Fees\ServiceRateManager;

/**
 * @phpstan-import-type InviteOfferSnapshot from ServiceRatePackage
 *
 * @phpstan-type PaymentSplit array{deposit_percent:float,card_deposit_amount:float|null,bank_transfer_amount:float|null,requires_bank_transfer:bool}
 * @phpstan-type PaymentSnapshot array{billing_model?:string,fixed_fee?:float|null,deposit_percent?:float,payment_split?:array{deposit_percent?:float,card_deposit_amount?:float|null,bank_transfer_amount?:float|null,requires_bank_transfer?:bool},...}
 */
final class ServiceActivationPackagePricing
{
    public function __construct(
        private readonly ServiceRateManager $serviceRates,
        private readonly PilotFeeWaiverManager $pilotWaivers,
    ) {}

    /**
     * @return InviteOfferSnapshot
     */
    public function packageSnapshotForActivation(ServiceRatePackage $package, ?Client $client = null): array
    {
        $snapshot = $package->snapshot();
        $pilot = $client instanceof Client ? $this->pilotWaivers->eligibility($client) : null;

        if (is_array($pilot) && $pilot['eligible']) {
            // The waiver overlays fee fields and retains the package identity and scope.
            /** @var InviteOfferSnapshot $waived */
            $waived = $this->pilotWaivers->waivedPackageSnapshot($snapshot, $pilot);

            return $waived;
        }

        if (! $this->serviceRates->freeAccessModeActive()) {
            return $snapshot;
        }

        return [
            ...$snapshot,
            'fixed_fee' => 0.0,
            'deposit_percent' => 100.0,
            'payment_split' => [
                'deposit_percent' => 100.0,
                'card_deposit_amount' => 0.0,
                'bank_transfer_amount' => 0.0,
                'requires_bank_transfer' => false,
            ],
            'free_access_mode' => [
                'active' => true,
                'reason' => 'Admin service rates are inactive; package payment is not required until rates are activated.',
                'nominal_fixed_fee' => $snapshot['fixed_fee'] ?? null,
                'stripe_required' => false,
            ],
        ];
    }

    /**
     * @param  PaymentSnapshot  $snapshot
     */
    public function packagePaymentStatus(array $snapshot): string
    {
        if (! $this->packageRequiresPayment($snapshot)) {
            return ServiceActivation::PAYMENT_NOT_REQUIRED;
        }

        return $this->paymentSplitForSnapshot($snapshot)['requires_bank_transfer'] === true
            ? ServiceActivation::PAYMENT_DEPOSIT_PENDING
            : ServiceActivation::PAYMENT_PENDING;
    }

    /**
     * @param  PaymentSnapshot  $snapshot
     */
    public function packageRequiresPayment(array $snapshot): bool
    {
        return (string) ($snapshot['billing_model'] ?? ServiceRatePackage::BILLING_FIXED_FEE) === ServiceRatePackage::BILLING_FIXED_FEE
            && (float) ($snapshot['fixed_fee'] ?? 0) > 0;
    }

    /**
     * @param  PaymentSnapshot  $snapshot
     * @return array{deposit_percent:float,card_deposit_amount:float|null,bank_transfer_amount:float|null,requires_bank_transfer:bool}
     */
    public function paymentSplitForSnapshot(array $snapshot): array
    {
        $paymentSplit = $snapshot['payment_split'] ?? null;

        if (is_array($paymentSplit)) {
            return [
                'deposit_percent' => (float) ($paymentSplit['deposit_percent'] ?? $snapshot['deposit_percent'] ?? 100),
                'card_deposit_amount' => isset($paymentSplit['card_deposit_amount'])
                    ? (float) $paymentSplit['card_deposit_amount']
                    : null,
                'bank_transfer_amount' => isset($paymentSplit['bank_transfer_amount'])
                    ? (float) $paymentSplit['bank_transfer_amount']
                    : null,
                'requires_bank_transfer' => (bool) ($paymentSplit['requires_bank_transfer'] ?? false),
            ];
        }

        $fixedFee = isset($snapshot['fixed_fee']) ? (float) $snapshot['fixed_fee'] : null;
        if ($fixedFee === null) {
            return [
                'deposit_percent' => 100.0,
                'card_deposit_amount' => null,
                'bank_transfer_amount' => null,
                'requires_bank_transfer' => false,
            ];
        }

        $depositPercent = min(max((float) ($snapshot['deposit_percent'] ?? 100), 0.0), 100.0);
        $cardDeposit = round($fixedFee * ($depositPercent / 100), 2);
        $bankTransfer = round(max($fixedFee - $cardDeposit, 0), 2);

        return [
            'deposit_percent' => $depositPercent,
            'card_deposit_amount' => $cardDeposit,
            'bank_transfer_amount' => $bankTransfer,
            'requires_bank_transfer' => $bankTransfer > 0,
        ];
    }
}
