<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\BillingAdjustment;
use App\Models\BillingAdjustmentApplication;
use App\Models\PaymentInstallment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BillingAdjustmentAllocator
{
    /**
     * Applies the client's available integration credits to one installment.
     * The ledger, not a mutable remaining balance, is authoritative.
     */
    public function apply(PaymentInstallment $installment): PaymentInstallment
    {
        return DB::transaction(function () use ($installment): PaymentInstallment {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            $installment->loadMissing('paymentSchedule');
            $proposalId = $installment->paymentSchedule?->proposal_id;
            $remaining = max(0.0, round((float) $installment->base_amount - (float) $installment->credit_applied, 2));

            if ($remaining === 0.0) {
                return $installment->refresh();
            }

            $adjustments = BillingAdjustment::query()
                ->where('client_id', $installment->client_id)
                ->where('status', BillingAdjustment::STATUS_AVAILABLE)
                ->where('currency', 'NZD')
                ->where(function ($query) use ($proposalId): void {
                    $query->whereNull('applied_to_proposal_id');
                    if ($proposalId !== null) {
                        $query->orWhere('applied_to_proposal_id', $proposalId);
                    }
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($adjustments as $adjustment) {
                if ($remaining <= 0.0) {
                    break;
                }

                $applied = (float) BillingAdjustmentApplication::query()
                    ->where('adjustment_id', $adjustment->getKey())
                    ->sum('amount_applied');
                $available = max(0.0, round((float) $adjustment->amount - $applied, 2));

                if ($available <= 0.0) {
                    $adjustment->forceFill(['status' => BillingAdjustment::STATUS_APPLIED, 'applied_at' => now()])->save();
                    continue;
                }

                $amount = min($remaining, $available);
                BillingAdjustmentApplication::query()->create([
                    'client_id' => $installment->client_id,
                    'adjustment_id' => $adjustment->getKey(),
                    'payment_installment_id' => $installment->getKey(),
                    'amount_applied' => $amount,
                ]);

                if ($adjustment->applied_to_proposal_id === null && $proposalId !== null) {
                    $adjustment->forceFill(['applied_to_proposal_id' => $proposalId])->save();
                }

                $remaining = round($remaining - $amount, 2);
                if ($remaining <= 0.0 || round($available - $amount, 2) <= 0.0) {
                    $adjustment->forceFill([
                        'status' => BillingAdjustment::STATUS_APPLIED,
                        'applied_at' => now(),
                    ])->save();
                }
            }

            $creditApplied = round((float) $installment->base_amount - $remaining, 2);
            $installment->forceFill([
                'credit_applied' => $creditApplied,
                'net_amount' => $remaining,
            ])->save();

            return $installment->refresh();
        });
    }

    public function assertAdjustmentClient(BillingAdjustment $adjustment, PaymentInstallment $installment): void
    {
        if ((string) $adjustment->client_id !== (string) $installment->client_id) {
            throw new InvalidArgumentException('Billing adjustments may only be applied within the same client.');
        }
    }
}
