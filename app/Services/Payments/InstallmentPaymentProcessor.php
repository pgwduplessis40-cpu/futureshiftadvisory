<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentInstallment;
use App\Models\PaymentSchedule;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class InstallmentPaymentProcessor
{
    public function __construct(
        private readonly Gateway $gateway,
        private readonly ReceiptGenerator $receipts,
        private readonly GstCalculator $gst,
        private readonly AuditWriter $audit,
        private readonly ApplyProposalPaymentOutcome $outcomes,
        private readonly BillingAdjustmentAllocator $adjustments,
        private readonly ClientBillingCode $billingCodes,
    ) {}

    /** @return array{scanned:int,succeeded:int,retrying:int,failed:int,receipts:int} */
    public function processDue(?CarbonInterface $now = null, int $limit = 50, array $chargeMetadata = []): array
    {
        $now ??= now();
        $result = ['scanned' => 0, 'succeeded' => 0, 'retrying' => 0, 'failed' => 0, 'receipts' => 0];

        PaymentInstallment::query()
            ->where('status', PaymentInstallment::STATUS_DUE)
            ->whereDate('due_date', '<=', $now->toDateString())
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('due_date')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (PaymentInstallment $installment) use (&$result, $now, $chargeMetadata): void {
                $outcome = $this->processInstallment($installment, $now, $chargeMetadata);
                if (! $outcome['claimed']) {
                    return;
                }
                $result['scanned']++;
                $result[$outcome['status']]++;
                $result['receipts'] += $outcome['receipt'] ? 1 : 0;
            });

        return $result;
    }

    /** @return array{claimed:bool,status:'succeeded'|'retrying'|'failed',receipt:bool} */
    public function processInstallment(PaymentInstallment $installment, CarbonInterface $now, array $chargeMetadata = []): array
    {
        $claim = $this->claim($installment, $now);
        if (! $claim['claimed']) {
            return ['claimed' => false, 'status' => 'retrying', 'receipt' => false];
        }

        /** @var PaymentInstallment $locked */
        $locked = $claim['installment'];
        /** @var Payment $payment */
        $payment = $claim['payment'];
        /** @var PaymentSchedule $schedule */
        $schedule = $claim['schedule'];

        if ($claim['zero']) {
            return $this->settle($locked, $payment, $schedule, null, $now, true);
        }

        try {
            $billingCode = $schedule->client instanceof Client
                ? $this->billingCodes->shortCode($schedule->client)
                : $this->billingCodes->shortCode((string) $locked->client_id);
            $charge = $this->gateway->charge($claim['authority'], $payment->amount, [
                'currency' => $schedule->currency,
                'idempotency_key' => $payment->idempotency_key,
                'metadata' => [
                    'client_code' => $billingCode,
                    'payment_id' => $payment->getKey(),
                    'payment_installment_id' => $locked->getKey(),
                    'payment_schedule_id' => $schedule->getKey(),
                    'gst_rate_percent' => $this->gst->ratePercent(),
                    'gst_exclusive_amount' => $locked->net_amount,
                    'gst_amount' => $this->gst->gstFromExclusive($locked->net_amount),
                    ...$chargeMetadata,
                ],
            ]);

            if ($charge->status === 'processing') {
                $this->awaitConfirmation($locked, $payment, $charge->gateway, $charge->gatewayRef, $now, 'gateway_processing');

                return ['claimed' => true, 'status' => 'retrying', 'receipt' => false];
            }

            return $this->settle($locked, $payment, $schedule, $charge, $now);
        } catch (DefinitivePaymentDecline $exception) {
            return $this->recordDecline($locked, $payment, $schedule, $exception, $now);
        } catch (Throwable $exception) {
            $this->awaitConfirmation($locked, $payment, $locked->attempted_gateway, null, $now, Str::limit($exception->getMessage(), 500, ''));

            return ['claimed' => true, 'status' => 'retrying', 'receipt' => false];
        }
    }

    /** Moves crashed pre-I/O claims and unresolved provider responses into recovery/manual review. */
    public function sweepStaleProcessing(?CarbonInterface $now = null, int $limit = 50): int
    {
        $now ??= now();
        $swept = 0;
        PaymentInstallment::query()
            ->where('status', PaymentInstallment::STATUS_PROCESSING)
            ->where('processing_started_at', '<=', $now->copy()->subMinutes(5))
            ->orderBy('processing_started_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (PaymentInstallment $installment) use ($now, &$swept): void {
                DB::transaction(function () use ($installment, $now, &$swept): void {
                    $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
                    if ($installment->status !== PaymentInstallment::STATUS_PROCESSING) {
                        return;
                    }
                    $installment->forceFill([
                        'status' => PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION,
                        'next_confirmation_at' => $now,
                    ])->save();
                    $this->audit->record('payment_installment.stale_processing_swept', subject: $installment, after: [
                        'active_payment_id' => $installment->active_payment_id,
                    ]);
                    $swept++;
                });
            });

        return $swept;
    }

    /** Escalates unresolved correlations. Provider lookup is intentionally held until a gateway reference exists. */
    public function escalateExpiredConfirmations(?CarbonInterface $now = null, int $limit = 50): int
    {
        $now ??= now();
        return PaymentInstallment::query()
            ->where('status', PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION)
            ->where('confirmation_deadline', '<=', $now)
            ->limit(max(1, $limit))
            ->update([
                'status' => PaymentInstallment::STATUS_MANUAL_REVIEW,
                'next_confirmation_at' => null,
                'updated_at' => $now,
            ]);
    }

    /** @return array{swept:int,confirmed:int,reopened:int,manual_review:int} */
    public function confirmAmbiguous(?CarbonInterface $now = null, int $limit = 50): array
    {
        $now ??= now();
        $result = ['swept' => $this->sweepStaleProcessing($now, $limit), 'confirmed' => 0, 'reopened' => 0, 'manual_review' => 0];

        PaymentInstallment::query()
            ->where('status', PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION)
            ->where(fn ($query) => $query->whereNull('next_confirmation_at')->orWhere('next_confirmation_at', '<=', $now))
            ->orderBy('next_confirmation_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (PaymentInstallment $installment) use ($now, &$result): void {
                $installment->loadMissing(['activePayment', 'paymentSchedule']);
                $payment = $installment->activePayment;
                if (! $payment instanceof Payment) {
                    $this->markManualReview($installment, $now, 'missing_active_payment');
                    $result['manual_review']++;

                    return;
                }
                if ($installment->confirmation_deadline !== null && $installment->confirmation_deadline->lessThanOrEqualTo($now)) {
                    $this->markManualReview($installment, $now, 'confirmation_deadline_exceeded');
                    $result['manual_review']++;

                    return;
                }

                $lookup = $this->gateway->findCharge($payment);
                if ($lookup->isSucceeded()) {
                    $this->settleFromConfirmation($payment, $lookup->charge, $now);
                    $result['confirmed']++;

                    return;
                }
                if ($lookup->isNotCharged()) {
                    $outcome = $this->declineFromWebhook(
                        $payment,
                        (string) ($payment->gateway ?? $installment->attempted_gateway ?? 'unknown'),
                        (string) ($payment->gateway_ref ?? 'lookup-not-charged'),
                        'Gateway confirmed the charge was not captured.',
                        $now,
                    );
                    if ($outcome !== null && $outcome['status'] === 'failed') {
                        $result['manual_review']++;
                    } else {
                        $result['reopened']++;
                    }

                    return;
                }

                $this->deferConfirmation($installment, $now);
            });

        return $result;
    }

    /** @return array{claimed:bool,status:'succeeded',receipt:bool}|null */
    public function settleFromWebhook(Payment $payment, string $gateway, string $gatewayRef, CarbonInterface $now): ?array
    {
        $payment->loadMissing(['paymentInstallment', 'paymentSchedule']);
        $installment = $payment->paymentInstallment;
        $schedule = $payment->paymentSchedule;
        if (! $installment instanceof PaymentInstallment || ! $schedule instanceof PaymentSchedule) {
            return null;
        }

        return $this->settle($installment, $payment, $schedule, new PaymentChargeResult(
            gateway: $gateway,
            gatewayRef: $gatewayRef,
            status: 'succeeded',
            amount: (string) $payment->amount,
            currency: $payment->currency,
        ), $now);
    }

    private function settleFromConfirmation(Payment $payment, ?PaymentChargeResult $charge, CarbonInterface $now): void
    {
        if (! $charge instanceof PaymentChargeResult) {
            return;
        }
        $payment->loadMissing(['paymentInstallment', 'paymentSchedule']);
        $installment = $payment->paymentInstallment;
        $schedule = $payment->paymentSchedule;
        if ($installment instanceof PaymentInstallment && $schedule instanceof PaymentSchedule) {
            $this->settle($installment, $payment, $schedule, $charge, $now);
        }
    }

    /** @return array{claimed:bool,status:'retrying'|'failed',receipt:bool}|null */
    public function declineFromWebhook(Payment $payment, string $gateway, string $gatewayRef, string $reason, CarbonInterface $now): ?array
    {
        $payment->loadMissing(['paymentInstallment', 'paymentSchedule']);
        $installment = $payment->paymentInstallment;
        $schedule = $payment->paymentSchedule;
        if (! $installment instanceof PaymentInstallment || ! $schedule instanceof PaymentSchedule) {
            return null;
        }

        $payment->forceFill([
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef,
            'failed_reason' => $reason,
        ])->save();

        return $this->recordDecline($installment, $payment, $schedule, new DefinitivePaymentDecline($reason), $now);
    }

    /** @return array{claimed:bool,zero:bool,installment:PaymentInstallment,payment:Payment,schedule:PaymentSchedule,authority:PaymentAuthority} */
    private function claim(PaymentInstallment $installment, CarbonInterface $now): array
    {
        return DB::transaction(function () use ($installment, $now): array {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            $schedule = PaymentSchedule::query()->with(['client', 'paymentAuthority'])->lockForUpdate()->findOrFail($installment->payment_schedule_id);
            $authority = $schedule->paymentAuthority;
            if (! $authority instanceof PaymentAuthority || $authority->status !== PaymentAuthority::STATUS_ACTIVE || $authority->revoked_at !== null) {
                throw new InvalidArgumentException('The installment has no active payment authority.');
            }
            $this->assertTenantLinks($installment, $schedule, $authority);
            if ($installment->status !== PaymentInstallment::STATUS_DUE || ($installment->next_attempt_at !== null && $installment->next_attempt_at->isFuture())) {
                return ['claimed' => false, 'zero' => false, 'installment' => $installment, 'payment' => new Payment, 'schedule' => $schedule, 'authority' => $authority];
            }

            $installment = $this->adjustments->apply($installment);
            $attempt = $installment->attempt_count + 1;
            $zero = (float) $installment->net_amount <= 0.0;
            $payment = Payment::query()->create([
                'client_id' => $installment->client_id,
                'payment_schedule_id' => $schedule->getKey(),
                'payment_installment_id' => $installment->getKey(),
                'payment_authority_id' => $authority->getKey(),
                'amount' => $zero ? '0.00' : $this->gst->grossFromExclusive($installment->net_amount),
                'currency' => $schedule->currency,
                'gateway' => $zero ? 'internal_credit' : null,
                'gateway_ref' => $zero ? 'credit-'.$installment->getKey() : null,
                'idempotency_key' => 'installment-'.$installment->getKey().'-attempt-'.$attempt,
                'status' => $zero ? Payment::STATUS_SUCCEEDED : Payment::STATUS_PENDING,
                'attempt' => $attempt,
                'processed_at' => $zero ? $now : null,
            ]);
            $installment->forceFill([
                'status' => $zero ? PaymentInstallment::STATUS_SETTLED_ZERO : PaymentInstallment::STATUS_PROCESSING,
                'active_payment_id' => $payment->getKey(),
                'attempted_gateway' => $zero ? 'internal_credit' : $this->gateway->preferredGateway(),
                'attempt_count' => $attempt,
                'processing_started_at' => $now,
                'confirmation_deadline' => $now->copy()->addHours(48),
                'next_confirmation_at' => null,
            ])->save();
            $this->audit->record('payment_installment.claimed', subject: $installment, after: [
                'payment_id' => $payment->getKey(),
                'attempt' => $attempt,
                'idempotency_key' => $payment->idempotency_key,
                'zero_settlement' => $zero,
            ]);

            return ['claimed' => true, 'zero' => $zero, 'installment' => $installment->refresh(), 'payment' => $payment, 'schedule' => $schedule, 'authority' => $authority];
        });
    }

    /** @return array{claimed:bool,status:'succeeded',receipt:bool} */
    private function settle(PaymentInstallment $installment, Payment $payment, PaymentSchedule $schedule, ?PaymentChargeResult $charge, CarbonInterface $now, bool $zero = false): array
    {
        return DB::transaction(function () use ($installment, $payment, $schedule, $charge, $now, $zero): array {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            if ($installment->active_payment_id !== $payment->getKey()) {
                return ['claimed' => true, 'status' => 'succeeded', 'receipt' => false];
            }
            $payment->forceFill([
                'gateway' => $charge?->gateway ?? 'internal_credit',
                'gateway_ref' => $charge?->gatewayRef ?? $payment->gateway_ref,
                'failover_from' => $charge?->failoverFrom,
                'status' => Payment::STATUS_SUCCEEDED,
                'failed_reason' => null,
                'processed_at' => $payment->processed_at ?? $now,
            ])->save();
            $installment->forceFill([
                'status' => $zero ? PaymentInstallment::STATUS_SETTLED_ZERO : PaymentInstallment::STATUS_SETTLED,
                'processing_started_at' => null,
                'next_attempt_at' => null,
                'next_confirmation_at' => null,
                'confirmation_deadline' => null,
            ])->save();
            if ($schedule->cadence === PaymentSchedule::CADENCE_ONE_OFF) {
                $schedule->forceFill(['status' => PaymentSchedule::STATUS_COMPLETED])->save();
            }
            $receipt = $this->receipts->create($payment->refresh());
            $this->outcomes->settled($payment->refresh());
            $this->audit->record('payment_installment.settled', subject: $installment, after: [
                'payment_id' => $payment->getKey(),
                'status' => $installment->status,
                'receipt_id' => $receipt->getKey(),
            ]);

            return ['claimed' => true, 'status' => 'succeeded', 'receipt' => true];
        });
    }

    /** @return array{claimed:bool,status:'retrying'|'failed',receipt:bool} */
    private function recordDecline(PaymentInstallment $installment, Payment $payment, PaymentSchedule $schedule, DefinitivePaymentDecline $exception, CarbonInterface $now): array
    {
        return DB::transaction(function () use ($installment, $payment, $schedule, $exception, $now): array {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $terminal = $installment->attempt_count >= $this->maxAttempts();
            $payment->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failed_reason' => Str::limit($exception->getMessage(), 500, ''),
                'processed_at' => $now,
            ])->save();
            $installment->forceFill([
                'status' => $terminal ? PaymentInstallment::STATUS_FAILED : PaymentInstallment::STATUS_DUE,
                'active_payment_id' => null,
                'next_attempt_at' => $terminal ? null : $now->copy()->addMinutes($this->retryDelayMinutes()),
                'processing_started_at' => null,
                'next_confirmation_at' => null,
                'confirmation_deadline' => null,
            ])->save();
            if ($terminal) {
                $schedule->forceFill(['status' => PaymentSchedule::STATUS_PAUSED])->save();
            }
            $this->audit->record('payment_installment.declined', subject: $installment, after: [
                'payment_id' => $payment->getKey(),
                'terminal' => $terminal,
                'attempt_count' => $installment->attempt_count,
            ]);

            return ['claimed' => true, 'status' => $terminal ? 'failed' : 'retrying', 'receipt' => false];
        });
    }

    private function awaitConfirmation(PaymentInstallment $installment, Payment $payment, ?string $gateway, ?string $gatewayRef, CarbonInterface $now, string $reason): void
    {
        DB::transaction(function () use ($installment, $payment, $gateway, $gatewayRef, $now, $reason): void {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($installment->active_payment_id !== $payment->getKey()) {
                return;
            }
            $payment->forceFill([
                'gateway' => $gateway ?? $payment->gateway,
                'gateway_ref' => $gatewayRef ?? $payment->gateway_ref,
                'failed_reason' => $reason,
            ])->save();
            $installment->forceFill([
                'status' => PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION,
                'next_confirmation_at' => $now->copy()->addMinutes(5),
                'processing_started_at' => null,
            ])->save();
            $this->audit->record('payment_installment.awaiting_gateway_confirmation', subject: $installment, after: [
                'payment_id' => $payment->getKey(),
                'gateway' => $payment->gateway,
                'gateway_ref' => $payment->gateway_ref,
                'reason' => $reason,
            ]);
        });
    }

    private function deferConfirmation(PaymentInstallment $installment, CarbonInterface $now): void
    {
        DB::transaction(function () use ($installment, $now): void {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            if ($installment->status !== PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION) {
                return;
            }
            $attempts = $installment->confirmation_attempts + 1;
            $delayMinutes = min(240, 5 * (2 ** min(5, $attempts - 1)));
            $installment->forceFill([
                'confirmation_attempts' => $attempts,
                'next_confirmation_at' => $now->copy()->addMinutes($delayMinutes),
            ])->save();
            $this->audit->record('payment_installment.confirmation_deferred', subject: $installment, after: [
                'confirmation_attempts' => $attempts,
                'next_confirmation_at' => $installment->next_confirmation_at?->toIso8601String(),
            ]);
        });
    }

    private function markManualReview(PaymentInstallment $installment, CarbonInterface $now, string $reason): void
    {
        DB::transaction(function () use ($installment, $now, $reason): void {
            $installment = PaymentInstallment::query()->lockForUpdate()->findOrFail($installment->getKey());
            if (! in_array($installment->status, [PaymentInstallment::STATUS_PROCESSING, PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION], true)) {
                return;
            }
            $installment->forceFill([
                'status' => PaymentInstallment::STATUS_MANUAL_REVIEW,
                'next_confirmation_at' => null,
            ])->save();
            $this->audit->record('payment_installment.manual_review', subject: $installment, after: ['reason' => $reason, 'at' => $now->toIso8601String()]);
        });
    }

    private function assertTenantLinks(PaymentInstallment $installment, PaymentSchedule $schedule, PaymentAuthority $authority): void
    {
        if ((string) $installment->client_id !== (string) $schedule->client_id || (string) $schedule->client_id !== (string) $authority->client_id) {
            throw new InvalidArgumentException('Payment installment, schedule, and authority must belong to the same client.');
        }
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('integrations.payments.max_attempts', 2));
    }

    private function retryDelayMinutes(): int
    {
        return max(1, (int) config('integrations.payments.retry_delay_minutes', 60));
    }
}
