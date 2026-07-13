<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\FeeMethod;
use App\Models\Payment;
use App\Services\ServiceActivations\ServiceActivationManager;

final class ApplyProposalPaymentOutcome
{
    public function __construct(private readonly ServiceActivationManager $activations) {}

    /** Applies the once-only commercial consequence of a settled proposal payment. */
    public function settled(Payment $payment): void
    {
        $payment->loadMissing('paymentSchedule.proposal.feeCalculation.integrationScope');
        $proposal = $payment->paymentSchedule?->proposal;
        if ($proposal?->feeCalculation?->method !== FeeMethod::Integration) {
            return;
        }

        $this->activations->activateIntegrationFromProposalPayment($proposal, $payment);
    }
}
