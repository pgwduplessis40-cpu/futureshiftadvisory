<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PaymentRefund;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\LifecycleManager;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\GstCalculator;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRefundRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IdeaValidationCancellation
{
    public const SUSPENSION_REASON = 'idea_validation_cancelled';

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly GstCalculator $gst,
        private readonly LifecycleManager $lifecycle,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return array{eligible:bool,reason:string|null,refund_amount:string|null,currency:string|null,submitted_at:string|null}|null
     */
    public function summaryFor(User $user): ?array
    {
        $profile = $this->profileFor($user);
        if (! $profile instanceof EntrepreneurProfile) {
            return null;
        }

        $activation = $this->activationFor($profile);
        if (! $activation instanceof ServiceActivation) {
            return null;
        }

        return $this->summary($profile, $activation);
    }

    public function cancel(User $user): PaymentRefund
    {
        $prepared = DB::transaction(function () use ($user): array {
            $profile = EntrepreneurProfile::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();
            if (! $profile instanceof EntrepreneurProfile) {
                throw ValidationException::withMessages(['cancellation' => 'Idea Validation cancellation is not available for this account.']);
            }

            $activation = ServiceActivation::query()
                ->where('related_entrepreneur_profile_id', $profile->getKey())
                ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
                ->where('status', ServiceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if (! $activation instanceof ServiceActivation) {
                throw ValidationException::withMessages(['cancellation' => 'There is no active paid Idea Validation service to cancel.']);
            }

            $summary = $this->summary($profile, $activation);
            if (! $summary['eligible']) {
                throw ValidationException::withMessages(['cancellation' => $this->ineligibleMessage($summary['reason'])]);
            }

            $existing = PaymentRefund::query()
                ->where('service_activation_id', $activation->getKey())
                ->lockForUpdate()
                ->first();
            if ($existing instanceof PaymentRefund && $existing->status === PaymentRefund::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['cancellation' => 'This Idea Validation has already been cancelled and refunded.']);
            }
            if ($existing instanceof PaymentRefund && $existing->status === PaymentRefund::STATUS_FAILED) {
                throw ValidationException::withMessages(['cancellation' => 'The refund could not be completed automatically. Please contact Future Shift Advisory for help.']);
            }

            $refund = $existing instanceof PaymentRefund
                ? $existing
                : PaymentRefund::query()->create([
                    'client_id' => $activation->client_id,
                    'service_activation_id' => $activation->getKey(),
                    'requested_by_user_id' => $user->getKey(),
                    'gateway' => 'stripe',
                    'payment_reference' => $activation->payment_reference,
                    'amount' => $summary['refund_amount'],
                    'currency' => $summary['currency'],
                    'status' => PaymentRefund::STATUS_PROCESSING,
                    'idempotency_key' => 'idea-validation-cancellation-'.$activation->getKey(),
                    'metadata' => [
                        'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                        'refund_policy' => 'before_idea_validation_submission',
                    ],
                ]);

            $activation->forceFill([
                'metadata' => [
                    ...(array) ($activation->metadata ?? []),
                    'idea_validation_cancellation' => [
                        'status' => PaymentRefund::STATUS_PROCESSING,
                        'requested_at' => now()->toIso8601String(),
                        'refund_id' => $refund->getKey(),
                    ],
                ],
            ])->save();

            return [$refund->refresh(), $activation->refresh(), $profile->refresh()];
        });

        /** @var array{0: PaymentRefund, 1: ServiceActivation, 2: EntrepreneurProfile} $prepared */
        [$refund, $activation, $profile] = $prepared;

        try {
            $result = $this->stripe->refund(new PaymentRefundRequest(
                paymentReference: $refund->payment_reference,
                idempotencyKey: $refund->idempotency_key,
                amount: $refund->amount,
                currency: $refund->currency,
                metadata: [
                    'payment_refund_id' => $refund->getKey(),
                    'service_activation_id' => $activation->getKey(),
                    'entrepreneur_profile_id' => $profile->getKey(),
                ],
            ));
        } catch (PaymentGatewayException $exception) {
            DB::transaction(function () use ($refund, $activation, $exception): void {
                $refund->forceFill([
                    'status' => PaymentRefund::STATUS_FAILED,
                    'failure_reason' => Str::limit($exception->getMessage(), 500, ''),
                ])->save();

                $activation->forceFill([
                    'metadata' => [
                        ...(array) ($activation->metadata ?? []),
                        'idea_validation_cancellation' => [
                            'status' => PaymentRefund::STATUS_FAILED,
                            'refund_id' => $refund->getKey(),
                        ],
                    ],
                ])->save();
            });

            throw ValidationException::withMessages([
                'cancellation' => 'Your refund could not be started. Your account remains active; please try again later or contact Future Shift Advisory.',
            ]);
        }

        $refund = DB::transaction(function () use ($refund, $activation, $user, $result): PaymentRefund {
            $refund = PaymentRefund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            $activation = ServiceActivation::query()->whereKey($activation->getKey())->lockForUpdate()->firstOrFail();

            $refund->forceFill([
                'gateway' => $result->gateway,
                'gateway_ref' => $result->gatewayRef,
                'status' => PaymentRefund::STATUS_ACCEPTED,
                'failure_reason' => null,
                'processed_at' => now(),
                'metadata' => [
                    ...(array) ($refund->metadata ?? []),
                    ...$result->metadata,
                    'provider_status' => $result->status,
                ],
            ])->save();

            $activation->forceFill([
                'status' => ServiceActivation::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'metadata' => [
                    ...(array) ($activation->metadata ?? []),
                    'idea_validation_cancellation' => [
                        'status' => PaymentRefund::STATUS_ACCEPTED,
                        'refunded_at' => now()->toIso8601String(),
                        'refund_id' => $refund->getKey(),
                        'refund_reference' => $refund->gateway_ref,
                    ],
                ],
            ])->save();

            $user->forceFill([
                'suspended_at' => now(),
                'suspended_reason' => self::SUSPENSION_REASON,
            ])->save();

            $this->audit->record('entrepreneur.idea_validation_cancelled', subject: $activation, actor: $user, after: [
                'entrepreneur_profile_id' => $activation->related_entrepreneur_profile_id,
                'payment_refund_id' => $refund->getKey(),
                'refund_amount' => $refund->amount,
                'currency' => $refund->currency,
                'refund_reference' => $refund->gateway_ref,
                'account_deactivated' => true,
            ]);

            return $refund->refresh();
        });

        $client = Client::query()->whereKey($activation->client_id)->first();
        if ($client instanceof Client && $client->status !== ClientStatus::SUSPENDED) {
            $this->lifecycle->suspend($client, $user, 'Idea Validation cancelled by the client after Stripe accepted the refund.');
        }

        return $refund;
    }

    private function profileFor(User $user): ?EntrepreneurProfile
    {
        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->first();
    }

    private function activationFor(EntrepreneurProfile $profile): ?ServiceActivation
    {
        return ServiceActivation::query()
            ->where('related_entrepreneur_profile_id', $profile->getKey())
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->latest()
            ->first();
    }

    /**
     * @return array{eligible:bool,reason:string|null,refund_amount:string|null,currency:string|null,submitted_at:string|null}
     */
    private function summary(EntrepreneurProfile $profile, ServiceActivation $activation): array
    {
        $access = ServiceRatePackage::accessFor(
            ServiceRatePackage::SERVICE_ENTREPRENEUR,
            (string) data_get($activation->selected_package_snapshot, 'package_scope', ''),
        );
        $submitted = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->oldest('evaluated_at')
            ->first();
        $fee = data_get($activation->selected_package_snapshot, 'fixed_fee');
        $hasOtherActiveService = ServiceActivation::query()
            ->where('client_id', $activation->client_id)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('id', '!=', $activation->getKey())
            ->exists();
        $reason = match (true) {
            ! $access['includes_idea_validation'] || $access['includes_plan_budget'] => 'not_idea_validation_only',
            $activation->payment_status !== ServiceActivation::PAYMENT_PAID => 'payment_not_settled',
            ! is_numeric($fee) || (float) $fee <= 0 => 'refund_amount_unavailable',
            ! is_string($activation->payment_reference) || trim($activation->payment_reference) === '' => 'payment_reference_unavailable',
            $submitted instanceof IdeaValidation => 'submitted',
            $hasOtherActiveService => 'other_active_service',
            default => null,
        };

        return [
            'eligible' => $reason === null,
            'reason' => $reason,
            'refund_amount' => is_numeric($fee) && (float) $fee > 0 ? $this->gst->grossFromExclusive($fee) : null,
            'currency' => (string) data_get($activation->selected_package_snapshot, 'currency', 'NZD'),
            'submitted_at' => $submitted?->evaluated_at?->toIso8601String(),
        ];
    }

    private function ineligibleMessage(?string $reason): string
    {
        return match ($reason) {
            'submitted' => 'Cancellation is not available after Idea Validation has been submitted for advisor review.',
            'other_active_service' => 'This account has another active FSA service. Please contact Future Shift Advisory to arrange service changes.',
            default => 'Cancellation and refund are not available for this Idea Validation service.',
        };
    }
}
