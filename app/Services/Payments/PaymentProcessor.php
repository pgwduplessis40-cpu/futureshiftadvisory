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
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PaymentProcessor
{
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
        return DB::transaction(function () use ($schedule, $now, $chargeMetadata, $actor, $reactivatePausedOnSuccess): array {
            $schedule = $schedule->refresh()->loadMissing(['paymentAuthority', 'proposal', 'client']);
            $wasPaused = $schedule->status === PaymentSchedule::STATUS_PAUSED;
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

            try {
                $charge = $this->gateway->charge($schedule->paymentAuthority, $schedule->amount, [
                    'currency' => $schedule->currency,
                    'idempotency_key' => 'payment-'.$payment->getKey().'-attempt-'.$attempt,
                    'metadata' => [
                        'payment_id' => $payment->getKey(),
                        'payment_schedule_id' => $schedule->getKey(),
                        ...$chargeMetadata,
                    ],
                ], $actor);

                $payment->forceFill([
                    'gateway' => $charge->gateway,
                    'gateway_ref' => $charge->gatewayRef,
                    'status' => Payment::STATUS_SUCCEEDED,
                    'failover_from' => $charge->failoverFrom,
                    'failed_reason' => null,
                    'processed_at' => $now,
                ])->save();

                $this->advanceSchedule($schedule, $now);
                if ($reactivatePausedOnSuccess && $wasPaused && $schedule->cadence !== PaymentSchedule::CADENCE_ONE_OFF) {
                    $schedule->forceFill([
                        'status' => PaymentSchedule::STATUS_ACTIVE,
                    ])->save();
                }

                $receipt = $this->receipts->create($payment->refresh());
                $this->audit->record('payment.succeeded', subject: $payment, after: [
                    'payment_schedule_id' => $schedule->getKey(),
                    'gateway' => $charge->gateway,
                    'gateway_ref' => $charge->gatewayRef,
                    'failover_from' => $charge->failoverFrom,
                    'receipt_id' => $receipt->getKey(),
                ], actor: $actor);

                return ['status' => 'succeeded', 'receipt' => true];
            } catch (PaymentGatewayException $e) {
                $status = $attempt < $this->maxAttempts()
                    ? Payment::STATUS_RETRYING
                    : Payment::STATUS_FAILED;

                $payment->forceFill([
                    'status' => $status,
                    'failed_reason' => Str::limit($e->getMessage(), 500, ''),
                    'processed_at' => $now,
                ])->save();

                $this->scheduleRetryOrPause($schedule, $now, $status);
                $this->audit->record('payment.failed', subject: $payment, after: [
                    'payment_schedule_id' => $schedule->getKey(),
                    'status' => $status,
                    'attempt' => $attempt,
                    'failed_reason' => $payment->failed_reason,
                ], actor: $actor);
                $this->notifyFailure($payment->refresh()->loadMissing('client'));

                return ['status' => $status === Payment::STATUS_RETRYING ? 'retrying' : 'failed', 'receipt' => false];
            }
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

        $nextRunAt = $schedule->next_run_at?->copy() ?? $now->copy();

        do {
            $nextRunAt = $nextRunAt->addMonthNoOverflow();
        } while ($nextRunAt <= $now);

        $schedule->forceFill([
            'next_run_at' => $nextRunAt,
        ])->save();
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
