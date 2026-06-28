<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\ClientTeamMember;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PaymentProcessor implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['payments.retry_policy'];
    }

    public function __construct(
        private readonly Gateway $gateway,
        private readonly ReceiptGenerator $receipts,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $chargeMetadata
     * @return array{scanned:int, succeeded:int, retrying:int, failed:int, receipts:int}
     */
    public function processDue(?CarbonInterface $now = null, int $limit = 50, array $chargeMetadata = []): array
    {
        $now ??= now();
        $result = [
            'scanned' => 0,
            'succeeded' => 0,
            'retrying' => 0,
            'failed' => 0,
            'receipts' => 0,
        ];

        PaymentSchedule::query()
            ->with(['paymentAuthority', 'proposal', 'client'])
            ->where('status', PaymentSchedule::STATUS_ACTIVE)
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (PaymentSchedule $schedule) use (&$result, $now, $chargeMetadata): void {
                $result['scanned']++;
                $outcome = $this->processSchedule($schedule, $now, $chargeMetadata);
                $result[$outcome['status']]++;
                $result['receipts'] += $outcome['receipt'] ? 1 : 0;
            });

        return $result;
    }

    /**
     * A manual retry is a single advisor-requested override attempt. It does
     * not loop and it does not apply the automatic max-attempt cap.
     *
     * @param  array<string, mixed>  $chargeMetadata
     * @return array{status:'succeeded'|'retrying'|'failed', receipt:bool}
     */
    public function retrySchedule(
        PaymentSchedule $schedule,
        ?CarbonInterface $now = null,
        ?User $actor = null,
        array $chargeMetadata = [],
    ): array {
        $now ??= now();
        $schedule = $schedule->refresh()->loadMissing(['paymentAuthority', 'proposal', 'client']);

        $this->assertManualRetryableSchedule($schedule);
        $this->audit->record('payment.retry_requested', subject: $schedule, actor: $actor, after: [
            'payment_schedule_id' => $schedule->getKey(),
            'status' => $schedule->status,
        ]);

        return $this->processSchedule($schedule, $now, $chargeMetadata, $actor, reactivatePausedOnSuccess: true);
    }

    /**
     * @param  array<string, mixed>  $chargeMetadata
     * @return array{status:'succeeded'|'retrying'|'failed', receipt:bool}
     */
    private function processSchedule(
        PaymentSchedule $schedule,
        CarbonInterface $now,
        array $chargeMetadata,
        ?User $actor = null,
        bool $reactivatePausedOnSuccess = false,
    ): array {
        $attempt = $this->openPaymentAttempt($schedule);
        $schedule = $attempt['schedule'];
        $payment = $attempt['payment'];

        try {
            $charge = $this->gateway->charge($schedule->paymentAuthority, $schedule->amount, [
                'currency' => $schedule->currency,
                'idempotency_key' => 'payment-'.$payment->getKey().'-attempt-'.$attempt['number'],
                'metadata' => [
                    'payment_id' => $payment->getKey(),
                    'payment_schedule_id' => $schedule->getKey(),
                    ...$chargeMetadata,
                ],
            ], $actor);

            return $this->recordSuccessfulAttempt(
                payment: $payment,
                schedule: $schedule,
                charge: $charge,
                now: $now,
                actor: $actor,
                reactivatePausedOnSuccess: $reactivatePausedOnSuccess,
                wasPaused: $attempt['was_paused'],
            );
        } catch (PaymentGatewayException $e) {
            return $this->recordFailedAttempt(
                payment: $payment,
                schedule: $schedule,
                exception: $e,
                now: $now,
                actor: $actor,
            );
        }
    }

    /**
     * @return array{schedule: PaymentSchedule, payment: Payment, number: int, was_paused: bool}
     */
    private function openPaymentAttempt(PaymentSchedule $schedule): array
    {
        return DB::transaction(function () use ($schedule): array {
            $schedule = PaymentSchedule::query()
                ->with(['paymentAuthority', 'proposal', 'client'])
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = ((int) Payment::query()
                ->where('payment_schedule_id', $schedule->getKey())
                ->max('attempt')) + 1;

            $payment = Payment::query()->create([
                'client_id' => $schedule->client_id,
                'payment_schedule_id' => $schedule->getKey(),
                'payment_authority_id' => $schedule->payment_authority_id,
                'amount' => $schedule->amount,
                'currency' => $schedule->currency,
                'status' => Payment::STATUS_PENDING,
                'attempt' => $attempt,
            ]);

            return [
                'schedule' => $schedule,
                'payment' => $payment,
                'number' => $attempt,
                'was_paused' => $schedule->status === PaymentSchedule::STATUS_PAUSED,
            ];
        });
    }

    /**
     * @return array{status:'succeeded', receipt:bool}
     */
    private function recordSuccessfulAttempt(
        Payment $payment,
        PaymentSchedule $schedule,
        PaymentChargeResult $charge,
        CarbonInterface $now,
        ?User $actor,
        bool $reactivatePausedOnSuccess,
        bool $wasPaused,
    ): array {
        return DB::transaction(function () use ($payment, $schedule, $charge, $now, $actor, $reactivatePausedOnSuccess, $wasPaused): array {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $schedule = PaymentSchedule::query()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $wasSucceeded = $payment->status === Payment::STATUS_SUCCEEDED;

            $payment->forceFill([
                'gateway' => $charge->gateway,
                'gateway_ref' => $charge->gatewayRef,
                'status' => Payment::STATUS_SUCCEEDED,
                'failover_from' => $charge->failoverFrom,
                'failed_reason' => null,
                'processed_at' => $payment->processed_at ?? $now,
            ])->save();

            if (! $wasSucceeded) {
                $this->advanceSchedule($schedule, $now);

                if ($reactivatePausedOnSuccess && $wasPaused && $schedule->cadence !== PaymentSchedule::CADENCE_ONE_OFF) {
                    $schedule->forceFill([
                        'status' => PaymentSchedule::STATUS_ACTIVE,
                    ])->save();
                }
            }

            $receipt = $this->receipts->create($payment->refresh());
            $this->audit->record('payment.succeeded', subject: $payment, after: [
                'payment_schedule_id' => $schedule->getKey(),
                'gateway' => $charge->gateway,
                'gateway_ref' => $charge->gatewayRef,
                'failover_from' => $charge->failoverFrom,
                'receipt_id' => $receipt->getKey(),
                'webhook_reconciled_first' => $wasSucceeded,
            ], actor: $actor);

            return ['status' => 'succeeded', 'receipt' => true];
        });
    }

    /**
     * @return array{status:'succeeded'|'retrying'|'failed', receipt:bool}
     */
    private function recordFailedAttempt(
        Payment $payment,
        PaymentSchedule $schedule,
        PaymentGatewayException $exception,
        CarbonInterface $now,
        ?User $actor,
    ): array {
        return DB::transaction(function () use ($payment, $schedule, $exception, $now, $actor): array {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $schedule = PaymentSchedule::query()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === Payment::STATUS_SUCCEEDED) {
                $receipt = $this->receipts->create($payment->refresh());

                return ['status' => 'succeeded', 'receipt' => true];
            }

            $status = $payment->attempt < $this->maxAttempts()
                ? Payment::STATUS_RETRYING
                : Payment::STATUS_FAILED;

            $payment->forceFill([
                'status' => $status,
                'failed_reason' => Str::limit($exception->getMessage(), 500, ''),
                'processed_at' => $now,
            ])->save();

            $this->scheduleRetryOrPause($schedule, $now, $status);
            $this->audit->record('payment.failed', subject: $payment, after: [
                'payment_schedule_id' => $schedule->getKey(),
                'status' => $status,
                'attempt' => $payment->attempt,
                'failed_reason' => $payment->failed_reason,
            ], actor: $actor);
            $this->notifyFailure($payment->refresh()->loadMissing('client'));

            return ['status' => $status === Payment::STATUS_RETRYING ? 'retrying' : 'failed', 'receipt' => false];
        });
    }

    private function advanceSchedule(PaymentSchedule $schedule, CarbonInterface $now): void
    {
        if ($schedule->cadence === PaymentSchedule::CADENCE_ONE_OFF) {
            $schedule->forceFill([
                'status' => PaymentSchedule::STATUS_COMPLETED,
            ])->save();

            return;
        }

        $nextRunAt = $this->nextRetainerRunAt($schedule, $now);

        $schedule->forceFill([
            'next_run_at' => $nextRunAt,
        ])->save();
    }

    private function nextRetainerRunAt(PaymentSchedule $schedule, CarbonInterface $now): CarbonInterface
    {
        $collectionDay = $schedule->collection_day;

        if (in_array($collectionDay, [1, 15], true)) {
            $candidate = $now->copy()
                ->startOfDay()
                ->setDay($collectionDay);

            while ($candidate <= $now) {
                $candidate = $candidate->addMonthNoOverflow()->setDay($collectionDay);
            }

            return $candidate;
        }

        $nextRunAt = $schedule->next_run_at?->copy() ?? $now->copy();

        do {
            $nextRunAt = $nextRunAt->addMonthNoOverflow();
        } while ($nextRunAt <= $now);

        return $nextRunAt;
    }

    private function scheduleRetryOrPause(PaymentSchedule $schedule, CarbonInterface $now, string $paymentStatus): void
    {
        if ($paymentStatus === Payment::STATUS_RETRYING) {
            $schedule->forceFill([
                'next_run_at' => $now->copy()->addMinutes($this->retryDelayMinutes()),
            ])->save();

            return;
        }

        $schedule->forceFill([
            'status' => PaymentSchedule::STATUS_PAUSED,
        ])->save();
    }

    private function notifyFailure(Payment $payment): void
    {
        $client = $payment->client;
        $recipients = collect();

        $advisorIds = ClientTeamMember::query()
            ->where('client_id', $payment->client_id)
            ->whereIn('role', ['lead_advisor', 'advisor'])
            ->pluck('user_id');

        if ($advisorIds->isNotEmpty()) {
            $recipients = $recipients->merge(User::query()->whereIn('id', $advisorIds)->get());
        }

        if ($client?->primary_contact_user_id !== null) {
            $clientContact = User::query()->whereKey($client->primary_contact_user_id)->first();

            if ($clientContact instanceof User) {
                $recipients->push($clientContact);
            }
        }

        $contactIds = ClientTeamMember::query()
            ->where('client_id', $payment->client_id)
            ->where('role', 'primary_contact')
            ->pluck('user_id');

        if ($contactIds->isNotEmpty()) {
            $recipients = $recipients->merge(User::query()->whereIn('id', $contactIds)->get());
        }

        $recipients = $recipients
            ->unique(fn (User $user): int|string => $user->getKey())
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PaymentFailedNotification($payment));
    }

    private function assertManualRetryableSchedule(PaymentSchedule $schedule): void
    {
        if (! in_array($schedule->status, [PaymentSchedule::STATUS_ACTIVE, PaymentSchedule::STATUS_PAUSED], true)) {
            throw new InvalidArgumentException('Payment schedule is not eligible for manual retry.');
        }

        $authority = $schedule->paymentAuthority;

        if (! $authority instanceof PaymentAuthority || $authority->status !== PaymentAuthority::STATUS_ACTIVE || $authority->revoked_at !== null) {
            throw new InvalidArgumentException('Payment authority is not eligible for manual retry.');
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
