<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\FeeMethod;
use App\Models\PaymentInstallment;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final class InstallmentScheduleBuilder
{
    public function __construct(
        private readonly BillingAdjustmentAllocator $adjustments,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Integration scopes use installments as their single billing clock. The
     * initial implementation creates the first payable installment; recurring
     * schedules receive their next installment after a settlement.
     */
    public function ensureFirstForIntegrationProposal(PaymentSchedule $schedule, Proposal $proposal, ?User $actor = null): ?PaymentInstallment
    {
        $proposal->loadMissing('feeCalculation');
        if ($proposal->feeCalculation?->method !== FeeMethod::Integration) {
            return null;
        }

        return DB::transaction(function () use ($schedule, $proposal, $actor): PaymentInstallment {
            $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $existing = PaymentInstallment::query()
                ->where('payment_schedule_id', $schedule->getKey())
                ->where('sequence', 1)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PaymentInstallment) {
                return $existing;
            }

            $installment = PaymentInstallment::query()->create([
                'client_id' => $schedule->client_id,
                'payment_schedule_id' => $schedule->getKey(),
                'sequence' => 1,
                'due_date' => $schedule->next_run_at?->toDateString() ?? now()->toDateString(),
                'base_amount' => $schedule->amount,
                'credit_applied' => 0,
                'net_amount' => $schedule->amount,
                'status' => PaymentInstallment::STATUS_DUE,
                'next_attempt_at' => $schedule->next_run_at,
            ]);
            $installment = $this->adjustments->apply($installment);

            $this->audit->record('payment_installment.created', subject: $installment, actor: $actor, after: [
                'proposal_id' => $proposal->getKey(),
                'payment_schedule_id' => $schedule->getKey(),
                'sequence' => 1,
                'base_amount' => $installment->base_amount,
                'credit_applied' => $installment->credit_applied,
                'net_amount' => $installment->net_amount,
            ]);

            return $installment->refresh();
        });
    }
}
