<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\IdeaValidationPurchaseAdvisorNotification;
use App\Notifications\IdeaValidationPurchaseConfirmedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\LifecycleManager;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\GstCalculator;
use App\Services\Payments\IdeaValidationPaymentIntent;
use App\Services\Payments\IdeaValidationPaymentIntentRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\ReceiptGenerator;
use App\Services\Terms\SignedAcceptancePdf;
use App\Services\Terms\TermsAcceptanceGate;
use App\Support\RequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Owns the anonymous-to-paid Idea Validation journey.
 *
 * Stripe is the payment system of record. This service only receives the
 * PaymentIntent reference and checks Stripe server-side (or a signed Stripe
 * webhook) before it creates portal access and a receipt.
 */
final class IdeaValidationCheckout
{
    public function __construct(
        private readonly EntrepreneurServiceOffer $offers,
        private readonly TermsAcceptanceGate $terms,
        private readonly SignedAcceptancePdf $signedTerms,
        private readonly StripeClient $stripe,
        private readonly GstCalculator $gst,
        private readonly ReceiptGenerator $receipts,
        private readonly LifecycleManager $lifecycle,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  array{name:string,email:string,password:string,terms_version_id:string}  $input
     */
    public function register(Request $request, array $input): IdeaValidationPurchase
    {
        return $this->context->withSystemContext(function () use ($request, $input): IdeaValidationPurchase {
            return DB::transaction(function () use ($request, $input): IdeaValidationPurchase {
                $email = Str::lower(trim($input['email']));
                $existing = User::query()->whereRaw('lower(trim(email)) = ?', [$email])->lockForUpdate()->first();
                if ($existing instanceof User) {
                    throw ValidationException::withMessages([
                        'email' => 'An account already exists for this email address. Sign in to continue your Idea Validation purchase.',
                    ]);
                }

                $terms = $this->terms->latestPublishedVersion(withClauses: true);
                if (! $terms instanceof TermsVersion || (string) $terms->getKey() !== $input['terms_version_id']) {
                    throw ValidationException::withMessages([
                        'terms_version_id' => 'The Terms and Privacy Policy has changed or is not published. Review the current policy before continuing.',
                    ]);
                }

                $advisor = $this->defaultAdvisor();
                if (! $advisor instanceof User) {
                    throw ValidationException::withMessages([
                        'checkout' => 'Idea Validation checkout is temporarily unavailable because no advisor is available to receive new clients.',
                    ]);
                }

                $name = trim($input['name']);
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $input['password'],
                    'user_type' => User::TYPE_ENTREPRENEUR,
                    'primary_role' => User::TYPE_ENTREPRENEUR,
                    'last_password_set_at' => now(),
                ]);

                if (Role::query()->where('name', User::TYPE_ENTREPRENEUR)->where('guard_name', 'web')->exists()) {
                    $user->assignRole(User::TYPE_ENTREPRENEUR);
                }

                $client = Client::query()->create([
                    'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
                    'status' => ClientStatus::PAUSED,
                    'legal_name' => $name,
                    'trading_name' => null,
                    'data_quality' => Client::DATA_QUALITY_LOW,
                    'registry_sources' => [
                        'source' => 'idea_validation_self_service',
                        'source_label' => 'Created while an Idea Validation customer verifies email and completes payment.',
                    ],
                    'created_by_user_id' => $advisor->getKey(),
                    'primary_contact_user_id' => $user->getKey(),
                ]);

                $acceptedAt = now();
                $artifact = $this->signedTerms->create($terms, $user, $request, $acceptedAt);
                TermsAcceptance::query()->create([
                    'user_id' => $user->getKey(),
                    'terms_version_id' => $terms->getKey(),
                    'accepted_at' => $acceptedAt,
                    'signed_pdf_path' => $artifact->path,
                    'signed_pdf_sha256_envelope' => $artifact->sha256Envelope,
                    'signed_pdf_envelope_meta' => $artifact->envelopeMeta,
                    'signed_pdf_byte_size' => $artifact->byteSize,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                $purchase = IdeaValidationPurchase::query()->create([
                    'user_id' => $user->getKey(),
                    'client_id' => $client->getKey(),
                    'advisor_id' => $advisor->getKey(),
                    'terms_version_id' => $terms->getKey(),
                    'status' => IdeaValidationPurchase::STATUS_EMAIL_VERIFICATION_PENDING,
                    'metadata' => [
                        'source' => 'public_validate_idea',
                        'terms_version' => $terms->version,
                    ],
                ]);

                $this->audit->record('idea_validation.purchase_registered', subject: $purchase, actor: $user, after: [
                    'client_id' => $client->getKey(),
                    'advisor_id' => $advisor->getKey(),
                    'terms_version_id' => $terms->getKey(),
                    'email_verified' => false,
                ]);

                return $purchase->refresh()->load('user');
            });
        });
    }

    public function markEmailVerified(IdeaValidationPurchase $purchase, string $hash): IdeaValidationPurchase
    {
        return $this->context->withSystemContext(function () use ($purchase, $hash): IdeaValidationPurchase {
            return DB::transaction(function () use ($purchase, $hash): IdeaValidationPurchase {
                $purchase = IdeaValidationPurchase::query()
                    ->with('user')
                    ->whereKey($purchase->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $user = $purchase->user;

                abort_unless($user instanceof User && hash_equals(sha1($user->email), $hash), 403);

                $verifiedAt = now();
                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => $verifiedAt])->save();
                }

                if ($purchase->email_verified_at === null) {
                    $purchase->forceFill([
                        'email_verified_at' => $verifiedAt,
                        'status' => IdeaValidationPurchase::STATUS_PAYMENT_PENDING,
                    ])->save();
                    $this->audit->record('idea_validation.purchase_email_verified', subject: $purchase, actor: $user, after: [
                        'email_verified_at' => $verifiedAt->toIso8601String(),
                    ]);
                }

                return $purchase->refresh()->load('user');
            });
        });
    }

    public function purchaseFor(User $user): ?IdeaValidationPurchase
    {
        return $this->context->withSystemContext(fn (): ?IdeaValidationPurchase => IdeaValidationPurchase::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->first());
    }

    public function beginPayment(User $user, IdeaValidationPurchase $purchase): IdeaValidationPaymentIntent
    {
        [$purchase, $payment] = $this->context->withSystemContext(function () use ($user, $purchase): array {
            return DB::transaction(function () use ($user, $purchase): array {
                $purchase = $this->lockedOwnedPurchase($user, $purchase);
                if ($purchase->paid_at !== null || $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
                    throw ValidationException::withMessages(['checkout' => 'This Idea Validation purchase is already paid.']);
                }
                if ($purchase->email_verified_at === null || $user->email_verified_at === null) {
                    throw ValidationException::withMessages(['checkout' => 'Verify your email address before opening secure checkout.']);
                }

                $package = $this->ideaValidationPackage();
                $snapshot = $package->snapshot();
                $split = $package->paymentSplit();
                if ($split['requires_bank_transfer'] || $split['deposit_percent'] !== 100.0) {
                    throw ValidationException::withMessages([
                        'checkout' => 'Configure the Idea Validation Service Rate as a full card payment before enabling self-service checkout.',
                    ]);
                }

                $exclusive = number_format((float) $package->fixed_fee, 2, '.', '');
                $gst = $this->gst->gstFromExclusive($exclusive);
                $gross = $this->gst->grossFromExclusive($exclusive);
                $currency = strtoupper((string) ($package->currency ?: 'NZD'));

                $payment = $purchase->payment_id !== null
                    ? Payment::query()->whereKey($purchase->payment_id)->lockForUpdate()->first()
                    : null;

                if (! $payment instanceof Payment) {
                    $payment = Payment::query()->create([
                        'client_id' => $purchase->client_id,
                        'payment_schedule_id' => null,
                        'amount' => $gross,
                        'currency' => $currency,
                        'gateway' => 'stripe',
                        'idempotency_key' => 'idea-validation-'.$purchase->getKey(),
                        'status' => Payment::STATUS_PENDING,
                        'attempt' => 1,
                    ]);
                }

                $purchase->forceFill([
                    'service_rate_package_id' => $package->getKey(),
                    'payment_id' => $payment->getKey(),
                    'status' => IdeaValidationPurchase::STATUS_PAYMENT_PROCESSING,
                    'amount_ex_gst' => $exclusive,
                    'gst_amount' => $gst,
                    'amount_including_gst' => $gross,
                    'currency' => $currency,
                    'package_snapshot' => $snapshot,
                ])->save();

                return [$purchase->refresh(), $payment->refresh()];
            });
        });

        try {
            $intent = $this->stripe->createIdeaValidationPaymentIntent(new IdeaValidationPaymentIntentRequest(
                purchaseId: (string) $purchase->getKey(),
                paymentId: (string) $payment->getKey(),
                clientId: (string) $purchase->client_id,
                customerEmail: $user->email,
                customerName: $user->name,
                amount: (string) $payment->amount,
                currency: $payment->currency,
                idempotencyKey: (string) $payment->idempotency_key,
            ));
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }

        $this->context->withSystemContext(function () use ($purchase, $payment, $intent): void {
            DB::transaction(function () use ($purchase, $payment, $intent): void {
                $lockedPurchase = IdeaValidationPurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

                $lockedPayment->forceFill([
                    'gateway' => 'stripe',
                    'gateway_ref' => $intent->paymentIntentRef,
                ])->save();
                $lockedPurchase->forceFill([
                    'stripe_payment_intent_ref' => $intent->paymentIntentRef,
                    'payment_intent_created_at' => $lockedPurchase->payment_intent_created_at ?? now(),
                    'metadata' => [
                        ...(array) ($lockedPurchase->metadata ?? []),
                        'stripe_fixture' => $intent->fixture,
                    ],
                ])->save();
            });
        });

        return $intent;
    }

    /**
     * The browser callback is only a convenience for immediate UI feedback.
     * It rechecks the PaymentIntent through Stripe; the signed webhook calls
     * the same settlement method if the browser leaves before this request.
     */
    public function confirmPayment(User $user, IdeaValidationPurchase $purchase, string $paymentIntentRef): ?IdeaValidationPurchase
    {
        $purchase = $this->context->withSystemContext(function () use ($user, $purchase): IdeaValidationPurchase {
            return DB::transaction(fn (): IdeaValidationPurchase => $this->lockedOwnedPurchase($user, $purchase));
        });
        if ($purchase->paid_at !== null) {
            return $purchase;
        }

        abort_unless(hash_equals((string) $purchase->stripe_payment_intent_ref, $paymentIntentRef), 403);
        abort_unless($purchase->payment_id !== null, 422);

        $payment = $this->context->withSystemContext(fn (): Payment => Payment::query()->whereKey($purchase->payment_id)->firstOrFail());
        $lookup = $this->stripe->findCharge($paymentIntentRef, (string) $payment->idempotency_key, (string) $payment->getKey());
        if (! $lookup->isSucceeded() || ! $lookup->charge instanceof PaymentChargeResult) {
            return null;
        }

        return $this->settlePayment($payment, $lookup->charge, now());
    }

    public function confirmFixturePayment(User $user, IdeaValidationPurchase $purchase): IdeaValidationPurchase
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $purchase = $this->context->withSystemContext(function () use ($user, $purchase): IdeaValidationPurchase {
            return DB::transaction(fn (): IdeaValidationPurchase => $this->lockedOwnedPurchase($user, $purchase));
        });
        abort_unless((bool) data_get($purchase->metadata, 'stripe_fixture', false), 404);
        abort_unless($purchase->payment_id !== null && $purchase->stripe_payment_intent_ref !== null, 422);

        $payment = $this->context->withSystemContext(fn (): Payment => Payment::query()->whereKey($purchase->payment_id)->firstOrFail());

        return $this->settlePayment($payment, new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: $purchase->stripe_payment_intent_ref,
            status: 'succeeded',
            amount: (string) $payment->amount,
            currency: $payment->currency,
            metadata: ['fixture' => true],
        ), now());
    }

    public function settleFromWebhook(Payment $payment, string $gatewayRef, \DateTimeInterface $processedAt): ?IdeaValidationPurchase
    {
        $purchase = $this->context->withSystemContext(fn (): ?IdeaValidationPurchase => IdeaValidationPurchase::query()
            ->where('payment_id', $payment->getKey())
            ->first());
        if (! $purchase instanceof IdeaValidationPurchase) {
            return null;
        }

        return $this->settlePayment($payment, new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: $gatewayRef,
            status: 'succeeded',
            amount: (string) $payment->amount,
            currency: $payment->currency,
        ), $processedAt);
    }

    private function settlePayment(Payment $payment, PaymentChargeResult $charge, \DateTimeInterface $processedAt): IdeaValidationPurchase
    {
        $result = $this->context->withSystemContext(function () use ($payment, $charge, $processedAt): array {
            return DB::transaction(function () use ($payment, $charge, $processedAt): array {
                $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $purchase = IdeaValidationPurchase::query()
                    ->with(['user', 'client', 'termsVersion', 'advisor'])
                    ->where('payment_id', $payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertChargeMatches($payment, $purchase, $charge);
                $wasPaid = $purchase->paid_at !== null || $purchase->status === IdeaValidationPurchase::STATUS_PAID;

                $payment->forceFill([
                    'gateway' => 'stripe',
                    'gateway_ref' => $charge->gatewayRef,
                    'status' => Payment::STATUS_SUCCEEDED,
                    'failed_reason' => null,
                    'processed_at' => $payment->processed_at ?? $processedAt,
                ])->save();

                $receipt = $this->receipts->create($payment->refresh());
                if ($wasPaid) {
                    return [$purchase->refresh(), false, $receipt->getKey()];
                }

                $user = $purchase->user;
                $client = $purchase->client;
                if (! $user instanceof User || ! $client instanceof Client) {
                    throw new \LogicException('The paid Idea Validation purchase is missing its customer record.');
                }

                $advisor = $purchase->advisor ?? $this->defaultAdvisor();
                if (! $advisor instanceof User) {
                    throw new \LogicException('A paid Idea Validation purchase cannot be activated without an advisor.');
                }

                $profile = EntrepreneurProfile::query()->updateOrCreate(
                    ['user_id' => $user->getKey()],
                    [
                        'client_id' => $client->getKey(),
                        'assigned_advisor_id' => $advisor->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                        'stage' => EntrepreneurStage::IDEA_VALIDATION,
                        'concept_summary' => 'Self-service Idea Validation purchase. The client will complete the validation questions before advisor review.',
                        'gamification_on' => true,
                    ],
                );

                $modules = ['portal', EngagementType::ENTREPRENEUR_MODULE->value];
                ClientTeamMember::query()->updateOrCreate(
                    ['client_id' => $client->getKey(), 'user_id' => $user->getKey()],
                    ['role' => 'primary_contact', 'granted_modules' => $modules],
                );
                ClientTeamMember::query()->updateOrCreate(
                    ['client_id' => $client->getKey(), 'user_id' => $advisor->getKey()],
                    ['role' => 'lead_advisor', 'granted_modules' => $modules],
                );

                $activation = ServiceActivation::query()->create([
                    'client_id' => $client->getKey(),
                    'requested_by_user_id' => $user->getKey(),
                    'advisor_id' => $advisor->getKey(),
                    'approved_by_user_id' => $advisor->getKey(),
                    'service_rate_package_id' => $purchase->service_rate_package_id,
                    'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                    'client_label' => 'Idea Validation',
                    'status' => ServiceActivation::STATUS_ACTIVE,
                    'selected_package_snapshot' => $purchase->package_snapshot,
                    'payment_status' => ServiceActivation::PAYMENT_PAID,
                    'payment_completed_at' => $processedAt,
                    'payment_completed_by_user_id' => null,
                    'payment_reference' => $charge->gatewayRef,
                    'accepted_by_user_id' => $user->getKey(),
                    'accepted_at' => $purchase->created_at,
                    'acceptance_text' => 'The client accepted the published Future Shift Advisory Terms and Privacy Policy before purchasing Idea Validation.',
                    'terms_reference' => [
                        'terms_version_id' => $purchase->terms_version_id,
                        'terms_version' => $purchase->termsVersion?->version,
                        'accepted_at' => $purchase->created_at?->toIso8601String(),
                    ],
                    'related_entrepreneur_profile_id' => $profile->getKey(),
                    'metadata' => [
                        'source' => 'public_idea_validation_checkout',
                        'idea_validation_purchase_id' => $purchase->getKey(),
                        'payment_id' => $payment->getKey(),
                        'amount_ex_gst' => $purchase->amount_ex_gst,
                        'gst_amount' => $purchase->gst_amount,
                        'amount_including_gst' => $purchase->amount_including_gst,
                    ],
                ]);

                if ($client->status !== ClientStatus::ACTIVE) {
                    $this->lifecycle->transition($client, ClientStatus::ACTIVE, $user, 'Idea Validation payment confirmed.', false);
                }

                $purchase->forceFill([
                    'advisor_id' => $advisor->getKey(),
                    'service_activation_id' => $activation->getKey(),
                    'status' => IdeaValidationPurchase::STATUS_PAID,
                    'paid_at' => $processedAt,
                ])->save();

                $this->audit->record('idea_validation.purchase_paid', subject: $purchase, actor: $user, after: [
                    'payment_id' => $payment->getKey(),
                    'payment_reference' => $charge->gatewayRef,
                    'receipt_id' => $receipt->getKey(),
                    'service_activation_id' => $activation->getKey(),
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'advisor_id' => $advisor->getKey(),
                ]);

                return [$purchase->refresh()->load(['user', 'advisor', 'client']), true, $receipt->getKey()];
            });
        });

        /** @var array{0: IdeaValidationPurchase, 1: bool, 2: string} $result */
        [$purchase, $shouldNotify] = $result;
        if ($shouldNotify) {
            $this->notifyPurchaseConfirmed($purchase);
        }

        return $purchase;
    }

    private function notifyPurchaseConfirmed(IdeaValidationPurchase $purchase): void
    {
        try {
            $purchase->loadMissing(['user', 'advisor', 'client']);
            if ($purchase->user instanceof User) {
                $purchase->user->notify(new IdeaValidationPurchaseConfirmedNotification($purchase));
            }
            if ($purchase->advisor instanceof User) {
                $purchase->advisor->notify(new IdeaValidationPurchaseAdvisorNotification($purchase));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function lockedOwnedPurchase(User $user, IdeaValidationPurchase $purchase): IdeaValidationPurchase
    {
        $locked = IdeaValidationPurchase::query()
            ->whereKey($purchase->getKey())
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
        abort_unless($locked instanceof IdeaValidationPurchase, 404);

        return $locked;
    }

    private function ideaValidationPackage(): ServiceRatePackage
    {
        $now = now();
        $package = ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->where('package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)
            ->where('billing_model', ServiceRatePackage::BILLING_FIXED_FEE)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->whereNotNull('fixed_fee')
            ->where('fixed_fee', '>', 0)
            ->latest('effective_from')
            ->first();
        if (! $package instanceof ServiceRatePackage) {
            throw ValidationException::withMessages([
                'checkout' => 'Idea Validation checkout is temporarily unavailable because a current Service Rate could not be found.',
            ]);
        }

        return $package;
    }

    private function defaultAdvisor(): ?User
    {
        return User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->oldest()
            ->first();
    }

    private function assertChargeMatches(Payment $payment, IdeaValidationPurchase $purchase, PaymentChargeResult $charge): void
    {
        if ($charge->gateway !== 'stripe'
            || ! hash_equals((string) $purchase->stripe_payment_intent_ref, $charge->gatewayRef)
            || strtoupper($charge->currency) !== strtoupper($payment->currency)
            || number_format((float) $charge->amount, 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')) {
            throw new \LogicException('Stripe payment confirmation does not match the Idea Validation purchase.');
        }
    }
}
