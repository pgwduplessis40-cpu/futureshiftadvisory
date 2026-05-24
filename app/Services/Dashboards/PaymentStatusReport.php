<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class PaymentStatusReport
{
    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forClientIds(?array $clientIds, int $limit = 8): array
    {
        if ($clientIds === []) {
            return $this->empty();
        }

        $query = $this->latestPaymentQuery()
            ->with(['client', 'paymentSchedule.paymentAuthority'])
            ->whereIn('payments.status', [Payment::STATUS_FAILED, Payment::STATUS_RETRYING]);

        if (is_array($clientIds)) {
            $query->whereIn('payments.client_id', $clientIds);
        }

        $payments = $query
            ->orderByRaw(
                'case payments.status when ? then 0 when ? then 1 else 2 end',
                [Payment::STATUS_FAILED, Payment::STATUS_RETRYING],
            )
            ->orderByDesc('payments.processed_at')
            ->orderByDesc('payments.created_at')
            ->limit(max(1, $limit))
            ->get();

        return [
            'summary' => [
                'failed' => $payments->where('status', Payment::STATUS_FAILED)->count(),
                'retrying' => $payments->where('status', Payment::STATUS_RETRYING)->count(),
                'retryable' => $payments->filter(fn (Payment $payment): bool => $this->manualRetryAvailable($payment, true))->count(),
            ],
            'items' => $payments
                ->map(fn (Payment $payment): array => $this->paymentPayload($payment, true))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forClient(Client $client, int $limit = 20): array
    {
        $payments = Payment::query()
            ->with(['client', 'paymentSchedule.paymentAuthority'])
            ->where('client_id', $client->getKey())
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();

        $latestPaymentIds = $this->latestPaymentIdsForSchedules($payments->pluck('payment_schedule_id')->all());

        return $payments
            ->map(fn (Payment $payment): array => $this->paymentPayload(
                $payment,
                ($latestPaymentIds[(string) $payment->payment_schedule_id] ?? null) === (string) $payment->getKey(),
            ))
            ->values()
            ->all();
    }

    /**
     * @return Builder<Payment>
     */
    private function latestPaymentQuery(): Builder
    {
        $latestAttempts = Payment::query()
            ->select('payment_schedule_id', DB::raw('max(attempt) as latest_attempt'))
            ->groupBy('payment_schedule_id');

        return Payment::query()
            ->select('payments.*')
            ->joinSub($latestAttempts, 'latest_payments', function (JoinClause $join): void {
                $join
                    ->on('payments.payment_schedule_id', '=', 'latest_payments.payment_schedule_id')
                    ->on('payments.attempt', '=', 'latest_payments.latest_attempt');
            });
    }

    /**
     * @param  array<int, mixed>  $scheduleIds
     * @return array<string, string>
     */
    private function latestPaymentIdsForSchedules(array $scheduleIds): array
    {
        $ids = collect($scheduleIds)
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->latestPaymentQuery()
            ->whereIn('payments.payment_schedule_id', $ids->all())
            ->pluck('payments.id', 'payments.payment_schedule_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'summary' => [
                'failed' => 0,
                'retrying' => 0,
                'retryable' => 0,
            ],
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(Payment $payment, bool $isLatestForSchedule): array
    {
        $payment->loadMissing(['client', 'paymentSchedule.paymentAuthority']);
        $schedule = $payment->paymentSchedule;
        $client = $payment->client;
        $clientId = (string) $payment->client_id;

        return [
            'id' => (string) $payment->getKey(),
            'client_id' => $clientId,
            'client_name' => $client?->legal_name,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'processed_at' => $payment->processed_at?->toIso8601String(),
            'failed_reason' => $payment->failed_reason,
            'attempt' => (int) $payment->attempt,
            'automatic_next_retry_at' => $this->automaticNextRetryAt($payment, $schedule),
            'manual_retry_available' => $this->manualRetryAvailable($payment, $isLatestForSchedule),
            'retry_url' => route('advisor.payments.retry', $payment->getKey(), absolute: false),
            'drill_url' => route('advisor.clients.show', [
                'client' => $clientId,
                'focus' => 'payments',
                'highlight' => $payment->getKey(),
            ], absolute: false),
            'contact_url' => route('advisor.clients.compose', $clientId, absolute: false),
        ];
    }

    private function automaticNextRetryAt(Payment $payment, ?PaymentSchedule $schedule): ?string
    {
        if (! $schedule instanceof PaymentSchedule) {
            return null;
        }

        if ($schedule->status !== PaymentSchedule::STATUS_ACTIVE) {
            return null;
        }

        if (! in_array($payment->status, [Payment::STATUS_FAILED, Payment::STATUS_RETRYING], true)) {
            return null;
        }

        $nextRunAt = $schedule->next_run_at;

        if (! $nextRunAt instanceof CarbonInterface || $nextRunAt->isPast()) {
            return null;
        }

        return $nextRunAt->toIso8601String();
    }

    private function manualRetryAvailable(Payment $payment, bool $isLatestForSchedule): bool
    {
        $schedule = $payment->paymentSchedule;
        $authority = $schedule?->paymentAuthority;

        return $isLatestForSchedule
            && in_array($payment->status, [Payment::STATUS_FAILED, Payment::STATUS_RETRYING], true)
            && $schedule instanceof PaymentSchedule
            && in_array($schedule->status, [PaymentSchedule::STATUS_ACTIVE, PaymentSchedule::STATUS_PAUSED], true)
            && $authority instanceof PaymentAuthority
            && $authority->status === PaymentAuthority::STATUS_ACTIVE
            && $authority->revoked_at === null;
    }
}
