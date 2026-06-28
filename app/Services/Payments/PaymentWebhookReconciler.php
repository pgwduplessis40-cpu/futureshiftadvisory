<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\PaymentWebhookEvent;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentWebhookReconciler
{
    public function __construct(
        private readonly ReceiptGenerator $receipts,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleStripe(array $payload): PaymentWebhookEvent
    {
        return $this->context->withSystemContext(function () use ($payload): PaymentWebhookEvent {
            return DB::transaction(fn (): PaymentWebhookEvent => $this->recordAndProcess(
                gateway: 'stripe',
                payload: $payload,
            ));
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordAndProcess(string $gateway, array $payload): PaymentWebhookEvent
    {
        $eventId = $this->eventId($payload);
        $eventType = $this->scalarString($payload['type'] ?? null) ?? 'unknown';
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $event = PaymentWebhookEvent::query()
            ->where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->lockForUpdate()
            ->first();

        if ($event instanceof PaymentWebhookEvent) {
            if ($event->processed_at !== null) {
                $this->audit->record('payment.webhook_duplicate', subject: $event->payment, after: [
                    'gateway' => $gateway,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'status' => $event->status,
                ]);
            }

            return $event;
        }

        $inserted = PaymentWebhookEvent::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'gateway' => $gateway,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
            'payload_hash' => $payloadHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            $event = PaymentWebhookEvent::query()
                ->where('gateway', $gateway)
                ->where('event_id', $eventId)
                ->firstOrFail();

            if ($event->processed_at !== null) {
                $this->audit->record('payment.webhook_duplicate', subject: $event->payment, after: [
                    'gateway' => $gateway,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'status' => $event->status,
                ]);
            }

            return $event;
        }

        $event = PaymentWebhookEvent::query()
            ->where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->firstOrFail();

        $object = data_get($payload, 'data.object');
        if (! is_array($object)) {
            return $this->ignore($event, 'missing_event_object');
        }

        return match ($eventType) {
            'payment_intent.succeeded' => $this->reconcileSucceededPaymentIntent($event, $payload, $object),
            'payment_intent.payment_failed' => $this->reconcileFailedPaymentIntent($event, $payload, $object),
            default => $this->ignore($event, 'unsupported_event_type'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $intent
     */
    private function reconcileSucceededPaymentIntent(PaymentWebhookEvent $event, array $payload, array $intent): PaymentWebhookEvent
    {
        $payment = $this->matchingPayment($intent);

        if (! $payment instanceof Payment) {
            return $this->ignore($event, 'no_matching_payment');
        }

        $mismatch = $this->paymentMismatchReason($payment, $intent);
        if ($mismatch !== null) {
            return $this->fail($event, $payment, $mismatch);
        }

        $processedAt = $this->eventTimestamp($payload);
        $wasSucceeded = $payment->status === Payment::STATUS_SUCCEEDED;

        $payment->forceFill([
            'gateway' => 'stripe',
            'gateway_ref' => $this->scalarString($intent['id'] ?? null) ?? $payment->gateway_ref,
            'status' => Payment::STATUS_SUCCEEDED,
            'failed_reason' => null,
            'processed_at' => $payment->processed_at ?? $processedAt,
        ])->save();

        if (! $wasSucceeded) {
            $this->advanceSchedule($payment->paymentSchedule, $processedAt);
        }

        $receipt = $this->receipts->create($payment->refresh());

        $this->audit->record('payment.webhook_reconciled', subject: $payment, after: [
            'gateway' => 'stripe',
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'status' => Payment::STATUS_SUCCEEDED,
            'receipt_id' => $receipt->getKey(),
        ]);

        return $this->finish($event, PaymentWebhookEvent::STATUS_PROCESSED, $payment);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $intent
     */
    private function reconcileFailedPaymentIntent(PaymentWebhookEvent $event, array $payload, array $intent): PaymentWebhookEvent
    {
        $payment = $this->matchingPayment($intent);

        if (! $payment instanceof Payment) {
            return $this->ignore($event, 'no_matching_payment');
        }

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return $this->ignore($event, 'payment_already_succeeded', $payment);
        }

        $processedAt = $this->eventTimestamp($payload);
        $status = $payment->attempt < $this->maxAttempts()
            ? Payment::STATUS_RETRYING
            : Payment::STATUS_FAILED;
        $reason = $this->failureReason($intent);

        $payment->forceFill([
            'gateway' => 'stripe',
            'gateway_ref' => $this->scalarString($intent['id'] ?? null) ?? $payment->gateway_ref,
            'status' => $status,
            'failed_reason' => Str::limit($reason, 500, ''),
            'processed_at' => $processedAt,
        ])->save();

        $this->scheduleRetryOrPause($payment->paymentSchedule, $processedAt, $status);

        $this->audit->record('payment.webhook_reconciled', subject: $payment, after: [
            'gateway' => 'stripe',
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'status' => $status,
            'failed_reason' => $payment->failed_reason,
        ]);

        return $this->finish($event, PaymentWebhookEvent::STATUS_PROCESSED, $payment);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function matchingPayment(array $intent): ?Payment
    {
        $paymentId = $this->scalarString(data_get($intent, 'metadata.payment_id'));

        if ($paymentId !== null && Str::isUuid($paymentId)) {
            $payment = $this->paymentById($paymentId);

            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        $gatewayRef = $this->scalarString($intent['id'] ?? null);

        if ($gatewayRef !== null) {
            $payment = Payment::query()
                ->with('paymentSchedule')
                ->where('gateway', 'stripe')
                ->where('gateway_ref', $gatewayRef)
                ->first();

            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        return null;
    }

    private function paymentById(string $paymentId): ?Payment
    {
        $attempts = app()->runningUnitTests() ? 1 : 10;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $payment = Payment::query()
                ->with('paymentSchedule')
                ->whereKey($paymentId)
                ->first();

            if ($payment instanceof Payment) {
                return $payment;
            }

            if ($attempt < $attempts) {
                usleep(250_000);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function paymentMismatchReason(Payment $payment, array $intent): ?string
    {
        $currency = $this->scalarString($intent['currency'] ?? null);

        if ($currency !== null && strtoupper($currency) !== strtoupper($payment->currency)) {
            return 'currency_mismatch';
        }

        $amountCents = $intent['amount_received'] ?? $intent['amount'] ?? null;

        if (is_numeric($amountCents)) {
            $expectedCents = (int) round(((float) $payment->amount) * 100);

            if ((int) $amountCents !== $expectedCents) {
                return 'amount_mismatch';
            }
        }

        return null;
    }

    private function advanceSchedule(?PaymentSchedule $schedule, Carbon $now): void
    {
        if (! $schedule instanceof PaymentSchedule) {
            return;
        }

        if ($schedule->cadence === PaymentSchedule::CADENCE_ONE_OFF) {
            $schedule->forceFill([
                'status' => PaymentSchedule::STATUS_COMPLETED,
            ])->save();

            return;
        }

        $nextRunAt = $this->nextRetainerRunAt($schedule, $now);

        $schedule->forceFill([
            'status' => PaymentSchedule::STATUS_ACTIVE,
            'next_run_at' => $nextRunAt,
        ])->save();
    }

    private function nextRetainerRunAt(PaymentSchedule $schedule, Carbon $now): Carbon
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

    private function scheduleRetryOrPause(?PaymentSchedule $schedule, Carbon $now, string $paymentStatus): void
    {
        if (! $schedule instanceof PaymentSchedule) {
            return;
        }

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

    private function finish(PaymentWebhookEvent $event, string $status, ?Payment $payment = null, ?string $reason = null): PaymentWebhookEvent
    {
        $event->forceFill([
            'status' => $status,
            'payment_id' => $payment?->getKey(),
            'client_id' => $payment?->client_id,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ])->save();

        return $event->refresh();
    }

    private function ignore(PaymentWebhookEvent $event, string $reason, ?Payment $payment = null): PaymentWebhookEvent
    {
        $this->audit->record('payment.webhook_ignored', subject: $payment, after: [
            'gateway' => $event->gateway,
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'reason' => $reason,
        ]);

        return $this->finish($event, PaymentWebhookEvent::STATUS_IGNORED, $payment, $reason);
    }

    private function fail(PaymentWebhookEvent $event, Payment $payment, string $reason): PaymentWebhookEvent
    {
        $this->audit->record('payment.webhook_failed', subject: $payment, after: [
            'gateway' => $event->gateway,
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'reason' => $reason,
        ]);

        return $this->finish($event, PaymentWebhookEvent::STATUS_FAILED, $payment, $reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): string
    {
        $eventId = $this->scalarString($payload['id'] ?? null);

        if ($eventId !== null) {
            return $eventId;
        }

        return 'sha256:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventTimestamp(array $payload): Carbon
    {
        $created = $payload['created'] ?? null;

        if (is_numeric($created)) {
            return Carbon::createFromTimestamp((int) $created);
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function failureReason(array $intent): string
    {
        $message = $this->scalarString(data_get($intent, 'last_payment_error.message'))
            ?? $this->scalarString(data_get($intent, 'last_payment_error.code'))
            ?? $this->scalarString(data_get($intent, 'status'));

        return $message !== null && $message !== ''
            ? $message
            : 'Stripe payment intent failed.';
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
