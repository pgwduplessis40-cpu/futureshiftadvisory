<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PaymentController extends Controller
{
    public function retry(Request $request, string $payment, PaymentProcessor $processor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $payment = Payment::query()
            ->with(['client', 'paymentSchedule.paymentAuthority'])
            ->findOrFail($payment);

        $this->authorizePaymentScope($user, $payment);

        $client = $payment->client;
        abort_unless($client instanceof Client, 404);
        Gate::authorize('update', $client);

        $schedule = $payment->paymentSchedule;
        abort_unless($schedule instanceof PaymentSchedule, 422, 'Payment schedule is missing.');

        $latest = $this->latestPaymentFor($schedule);
        abort_unless($latest instanceof Payment && (string) $latest->getKey() === (string) $payment->getKey(), 422, 'Only the latest schedule payment can be retried.');
        abort_unless(in_array($latest->status, [Payment::STATUS_FAILED, Payment::STATUS_RETRYING], true), 422, 'Only failed or retrying payments can be retried.');

        try {
            $processor->retrySchedule($schedule, actor: $user);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('status', 'payment-retry-requested');
    }

    private function latestPaymentFor(PaymentSchedule $schedule): ?Payment
    {
        return Payment::query()
            ->where('payment_schedule_id', $schedule->getKey())
            ->orderByDesc('attempt')
            ->latest()
            ->first();
    }

    private function authorizePaymentScope(User $user, Payment $payment): void
    {
        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return;
        }

        abort_unless(in_array((string) $payment->client_id, $user->accessibleClientIds(), true), 404);
    }
}
