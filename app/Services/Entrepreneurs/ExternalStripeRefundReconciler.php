<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\ClientStatus;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PaymentRefund;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Accounting\IdeaValidationPaymentLedger;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\LifecycleManager;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\GstCalculator;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRefundResult;
use App\Support\RequestContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A deliberately staff-mediated recovery for refunds processed directly in
 * Stripe. It never creates a Stripe refund: the supplied refund reference is
 * read from Stripe and must match the original payment before access changes.
 */
final class ExternalStripeRefundReconciler
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly GstCalculator $gst,
        private readonly LifecycleManager $lifecycle,
        private readonly AuditWriter $audit,
        private readonly IdeaValidationPaymentLedger $accountingLedger,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return Collection<int, array{id:string,customer_name:string,customer_email:string,currency:string,amount:float,payment_reference:string}>
     */
    public function candidates(): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => ServiceActivation::query()
            ->with(['entrepreneurProfile.user', 'paymentRefund'])
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->whereIn('status', [ServiceActivation::STATUS_ACTIVE, ServiceActivation::STATUS_CANCELLED])
            ->where('payment_status', ServiceActivation::PAYMENT_PAID)
            ->whereNotNull('payment_reference')
            ->orderBy('payment_completed_at')
            ->get()
            ->map(fn (ServiceActivation $activation): ?array => $this->candidate($activation))
            ->filter()
            ->values());
    }

    public function reconcile(
        ServiceActivation $activation,
        User $actor,
        string $refundReference,
        string $reason,
    ): PaymentRefund {
        try {
            $lookup = $this->stripe->findRefund($refundReference);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages([
                'refund_reference' => Str::limit($exception->getMessage(), 500, ''),
            ]);
        }

        if (! $lookup->isSucceeded() || ! $lookup->refund instanceof PaymentRefundResult) {
            throw ValidationException::withMessages([
                'refund_reference' => 'Stripe does not verify this refund as succeeded. No account access has changed.',
            ]);
        }

        [$refund, $client, , $newlyReconciled] = $this->context->withSystemContext(function () use ($activation, $actor, $lookup, $reason): array {
            return DB::transaction(function () use ($activation, $actor, $lookup, $reason): array {
                $activation = ServiceActivation::query()
                    ->whereKey($activation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $profile = EntrepreneurProfile::query()
                    ->whereKey($activation->related_entrepreneur_profile_id)
                    ->lockForUpdate()
                    ->first();
                $client = Client::query()->whereKey($activation->client_id)->lockForUpdate()->first();
                $user = $profile instanceof EntrepreneurProfile
                    ? User::query()->whereKey($profile->user_id)->lockForUpdate()->first()
                    : null;
                if (! $profile instanceof EntrepreneurProfile || ! $client instanceof Client || ! $user instanceof User) {
                    throw ValidationException::withMessages([
                        'refund_reference' => 'This service no longer has the client account required for a safe refund reconciliation.',
                    ]);
                }

                $this->assertEligible($activation, $profile);
                $result = $lookup->refund;
                if (! $result instanceof PaymentRefundResult) {
                    throw ValidationException::withMessages([
                        'refund_reference' => 'Stripe does not verify this refund as succeeded. No account access has changed.',
                    ]);
                }
                $this->assertMatches($activation, $result);

                $refund = PaymentRefund::query()
                    ->where('service_activation_id', $activation->getKey())
                    ->lockForUpdate()
                    ->first();
                $alreadyReconciled = $refund instanceof PaymentRefund
                    && $refund->status === PaymentRefund::STATUS_ACCEPTED
                    && $refund->gateway_ref === $result->gatewayRef;

                if ($refund instanceof PaymentRefund
                    && $refund->status === PaymentRefund::STATUS_ACCEPTED
                    && $refund->gateway_ref !== null
                    && $refund->gateway_ref !== $result->gatewayRef
                    && ! $this->isSimulated($refund)) {
                    throw ValidationException::withMessages([
                        'refund_reference' => 'This account is already reconciled to a different accepted Stripe refund.',
                    ]);
                }

                $refund ??= new PaymentRefund;
                if (! $alreadyReconciled) {
                    $metadata = (array) ($refund->metadata ?? []);
                    unset($metadata['fixture']);

                    $refund->forceFill([
                        'client_id' => $activation->client_id,
                        'service_activation_id' => $activation->getKey(),
                        'requested_by_user_id' => $user->getKey(),
                        'gateway' => 'stripe',
                        'payment_reference' => $activation->payment_reference,
                        'gateway_ref' => $result->gatewayRef,
                        'amount' => $this->money($result->amount),
                        'currency' => strtoupper($result->currency),
                        'status' => PaymentRefund::STATUS_ACCEPTED,
                        'idempotency_key' => $refund->exists
                            ? $refund->idempotency_key
                            : 'external-stripe-refund-'.$activation->getKey(),
                        'failure_reason' => null,
                        'processed_at' => now(),
                        'metadata' => [
                            ...$metadata,
                            ...$result->metadata,
                            'provider_status' => $result->status,
                            'external_reconciliation' => [
                                'reconciled_at' => now()->toIso8601String(),
                                'reconciled_by_user_id' => $actor->getKey(),
                                'reason' => $reason,
                            ],
                        ],
                    ])->save();
                }

                $activation->forceFill([
                    'status' => ServiceActivation::STATUS_CANCELLED,
                    'cancelled_at' => $activation->cancelled_at ?? now(),
                    'metadata' => [
                        ...(array) ($activation->metadata ?? []),
                        'idea_validation_cancellation' => [
                            'status' => PaymentRefund::STATUS_ACCEPTED,
                            'refunded_at' => ($refund->processed_at ?? now())->toIso8601String(),
                            'refund_id' => $refund->getKey(),
                            'refund_reference' => $result->gatewayRef,
                            'origin' => 'external_stripe_refund_reconciliation',
                        ],
                    ],
                ])->save();
                $profile->forceFill([
                    'stage' => EntrepreneurStage::CANCELLED,
                    'suspended_from_stage' => null,
                ])->save();
                $user->forceFill([
                    'suspended_at' => $user->suspended_at ?? now(),
                    'suspended_reason' => IdeaValidationCancellation::SUSPENSION_REASON,
                ])->save();

                if (! $alreadyReconciled) {
                    $this->audit->record('entrepreneur.idea_validation_external_refund_reconciled', subject: $activation, actor: $actor, after: [
                        'entrepreneur_profile_id' => $profile->getKey(),
                        'payment_refund_id' => $refund->getKey(),
                        'refund_amount' => $refund->amount,
                        'currency' => $refund->currency,
                        'refund_reference' => $refund->gateway_ref,
                        'reason' => $reason,
                        'account_deactivated' => true,
                        'entrepreneur_stage' => EntrepreneurStage::CANCELLED->value,
                    ]);
                }

                return [$refund->refresh(), $client->refresh(), $user->refresh(), ! $alreadyReconciled];
            });
        });

        if ($client->status !== ClientStatus::SUSPENDED) {
            $this->lifecycle->suspend(
                $client,
                $actor,
                'Idea Validation refund was verified directly with Stripe during controlled reconciliation.',
            );
        }
        if ($newlyReconciled) {
            $this->accountingLedger->recordAcceptedRefund($refund, $actor);
        }

        return $refund;
    }

    /** @return array{id:string,customer_name:string,customer_email:string,currency:string,amount:float,payment_reference:string}|null */
    private function candidate(ServiceActivation $activation): ?array
    {
        $profile = $activation->entrepreneurProfile;
        $user = $profile?->user;
        $refund = $activation->paymentRefund;
        if (! $profile instanceof EntrepreneurProfile
            || ! $user instanceof User
            || ! $refund instanceof PaymentRefund
            || ! $this->requiresExternalRecovery($refund)
            || ! $this->eligible($activation, $profile)) {
            return null;
        }

        return [
            'id' => (string) $activation->getKey(),
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'currency' => $this->currency($activation),
            'amount' => (float) $this->refundAmount($activation),
            'payment_reference' => (string) $activation->payment_reference,
        ];
    }

    private function requiresExternalRecovery(PaymentRefund $refund): bool
    {
        return in_array($refund->status, [PaymentRefund::STATUS_PROCESSING, PaymentRefund::STATUS_FAILED], true)
            || ($refund->status === PaymentRefund::STATUS_ACCEPTED && $this->isSimulated($refund));
    }

    private function assertEligible(ServiceActivation $activation, EntrepreneurProfile $profile): void
    {
        if (! $this->eligible($activation, $profile)) {
            throw ValidationException::withMessages([
                'refund_reference' => 'This account is not eligible for an Idea Validation refund reconciliation.',
            ]);
        }
    }

    private function eligible(ServiceActivation $activation, EntrepreneurProfile $profile): bool
    {
        $access = ServiceRatePackage::accessFor(
            ServiceRatePackage::SERVICE_ENTREPRENEUR,
            (string) data_get($activation->selected_package_snapshot, 'package_scope', ''),
        );
        $hasSubmittedIdea = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->exists();
        $hasOtherActiveService = ServiceActivation::query()
            ->where('client_id', $activation->client_id)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('id', '!=', $activation->getKey())
            ->exists();

        return $activation->service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            && in_array($activation->status, [ServiceActivation::STATUS_ACTIVE, ServiceActivation::STATUS_CANCELLED], true)
            && $activation->payment_status === ServiceActivation::PAYMENT_PAID
            && is_string($activation->payment_reference)
            && trim($activation->payment_reference) !== ''
            && (bool) $access['includes_idea_validation']
            && ! (bool) $access['includes_plan_budget']
            && ! $hasSubmittedIdea
            && ! $hasOtherActiveService
            && $this->refundAmount($activation) !== null;
    }

    private function assertMatches(ServiceActivation $activation, PaymentRefundResult $refund): void
    {
        $paymentReferences = array_filter([
            data_get($refund->metadata, 'payment_intent'),
            data_get($refund->metadata, 'charge'),
        ], 'is_string');
        if ($refund->gateway !== 'stripe'
            || ! str_starts_with($refund->gatewayRef, 're_')
            || $refund->status !== 'succeeded'
            || ! in_array($activation->payment_reference, $paymentReferences, true)
            || strtoupper($refund->currency) !== $this->currency($activation)
            || $this->cents($this->money($refund->amount)) !== $this->cents((string) $this->refundAmount($activation))) {
            throw ValidationException::withMessages([
                'refund_reference' => 'The verified Stripe refund does not match the original Idea Validation payment. No account access has changed.',
            ]);
        }
    }

    private function isSimulated(PaymentRefund $refund): bool
    {
        return (bool) data_get($refund->metadata, 'fixture', false)
            || str_starts_with((string) $refund->gateway_ref, 're_stripe_');
    }

    private function refundAmount(ServiceActivation $activation): ?string
    {
        $fee = data_get($activation->selected_package_snapshot, 'fixed_fee');

        return is_numeric($fee) && (float) $fee > 0
            ? $this->gst->grossFromExclusive($fee)
            : null;
    }

    private function currency(ServiceActivation $activation): string
    {
        return strtoupper((string) data_get($activation->selected_package_snapshot, 'currency', 'NZD'));
    }

    private function money(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
