<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Consent;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Payments\AuthorityCapture;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\ScheduleBuilder;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class SignoffFlow
{
    public function __construct(
        private readonly AuthorityCapture $authorities,
        private readonly ScheduleBuilder $schedules,
        private readonly PdfRenderer $renderer,
        private readonly ProposalBuilder $proposals,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
        private readonly IntegrationActivationResolver $integrations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(Proposal $proposal, string $step, array $payload, User $actor): Proposal
    {
        $step = $this->normaliseStep($step);
        $proposal = $proposal->refresh()->load(['client', 'consents', 'paymentAuthorities', 'signoffSteps']);
        $alreadyCompleted = $this->completedSteps($proposal)->contains($step);

        $this->assertClientOwnsProposal($proposal);
        $this->assertProposalCanEnterSignoff($proposal);
        $this->assertStepOrder($proposal, $step, $alreadyCompleted);

        try {
            return DB::transaction(function () use ($proposal, $step, $payload, $actor): Proposal {
                $stepPayload = match ($step) {
                    ProposalSignoffStep::STEP_REVIEW => $this->review($payload),
                    ProposalSignoffStep::STEP_INSURANCE_CONSENT => $this->consent($proposal, Consent::TYPE_INSURANCE_REFERRAL, $payload, $actor),
                    ProposalSignoffStep::STEP_COACH_CONSENT => $this->consent($proposal, Consent::TYPE_COACH_REFERRAL, $payload, $actor),
                    ProposalSignoffStep::STEP_PAYMENT_METHOD => $this->paymentMethod($payload),
                    ProposalSignoffStep::STEP_AUTHORITY => $this->authority($proposal, $payload, $actor),
                    ProposalSignoffStep::STEP_SIGNATURE => $this->signature($proposal, $payload, $actor),
                    ProposalSignoffStep::STEP_CONFIRMATION => $this->confirmation($proposal),
                    default => throw new InvalidArgumentException("Unsupported sign-off step [{$step}]."),
                };

                $this->recordStep($proposal, $step, $stepPayload, $actor);

                $this->audit->record('proposal.signoff_step_completed', subject: $proposal, actor: $actor, after: [
                    'step' => $step,
                    'status' => $proposal->refresh()->status->value,
                ]);

                return $proposal->refresh()->load(['consents', 'paymentAuthorities', 'signoffSteps']);
            });
        } catch (PaymentGatewayException $e) {
            $this->audit->record('proposal.authority_capture_failed', subject: $proposal, actor: $actor, after: [
                'step' => $step,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Proposal $proposal): array
    {
        $proposal->loadMissing(['signoffSteps', 'paymentAuthorities']);
        $completed = $this->completedSteps($proposal);
        $steps = collect(ProposalSignoffStep::orderedSteps())
            ->map(function (string $step) use ($proposal, $completed): array {
                $record = $proposal->signoffSteps->firstWhere('step', $step);
                $payload = is_array($record?->payload) ? $record->payload : [];

                return [
                    'step' => $step,
                    'label' => str($step)->replace('_', ' ')->title()->toString(),
                    'completed' => $completed->contains($step),
                    'completed_at' => $record?->completed_at?->toIso8601String(),
                    'payload' => $record instanceof ProposalSignoffStep
                        ? $this->clientStepPayload($step, $payload)
                        : [],
                    'url' => route('portal.proposals.signoff.step', [$proposal, $step], absolute: false),
                ];
            })
            ->values()
            ->all();

        return [
            'steps' => $steps,
            'next_step' => $this->nextStep($proposal),
            'active_authority' => $this->activeAuthority($proposal) instanceof PaymentAuthority,
            'authority_requires_token' => $this->authorityRequiresToken($proposal),
            'payment_setup_url' => route('portal.proposals.signoff.payment-setup', $proposal, absolute: false),
        ];
    }

    private function review(array $payload): array
    {
        return [
            'reviewed' => (bool) ($payload['reviewed'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function consent(Proposal $proposal, string $type, array $payload, User $actor): array
    {
        $election = (string) ($payload['election'] ?? Consent::ELECTION_UNDECIDED);

        if (! in_array($election, Consent::elections(), true)) {
            throw new InvalidArgumentException('Unsupported consent election.');
        }

        $consent = Consent::query()->updateOrCreate(
            [
                'proposal_id' => $proposal->getKey(),
                'type' => $type,
            ],
            [
                'client_id' => $proposal->client_id,
                'election' => $election,
                'evidence' => [
                    'source' => 'proposal_signoff',
                    'step' => $type === Consent::TYPE_INSURANCE_REFERRAL
                        ? ProposalSignoffStep::STEP_INSURANCE_CONSENT
                        : ProposalSignoffStep::STEP_COACH_CONSENT,
                    'revocable' => true,
                ],
                'captured_by_user_id' => $actor->getKey(),
                'captured_at' => now(),
            ],
        );

        $this->audit->record('proposal.consent_captured', subject: $consent, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
            'type' => $type,
            'election' => $election,
        ]);

        return [
            'type' => $type,
            'election' => $election,
            'consent_id' => $consent->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function paymentMethod(array $payload): array
    {
        $type = (string) ($payload['type'] ?? PaymentAuthority::TYPE_CARD);
        $gateway = (string) ($payload['gateway'] ?? PaymentAuthority::GATEWAY_STRIPE);
        $collectionDay = $this->validCollectionDay($payload['collection_day'] ?? null);

        if (! in_array($type, PaymentAuthority::types(), true)) {
            throw new InvalidArgumentException('Unsupported payment method type.');
        }

        if (! in_array($gateway, PaymentAuthority::gateways(), true)) {
            throw new InvalidArgumentException('Unsupported payment gateway.');
        }

        return [
            'type' => $type,
            'gateway' => $gateway,
            'collection_day' => $collectionDay,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function authority(Proposal $proposal, array $payload, User $actor): array
    {
        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $type = (string) ($payload['type'] ?? Arr::get($method, 'type', PaymentAuthority::TYPE_CARD));
        $gateway = (string) ($payload['gateway'] ?? Arr::get($method, 'gateway', PaymentAuthority::GATEWAY_STRIPE));
        $collectionDay = $this->validCollectionDay($payload['collection_day'] ?? Arr::get($method, 'collection_day'));
        $payload['collection_day'] = $collectionDay;

        if ($this->integrations->isLive($gateway) && $this->paymentMethodReference($payload) === null) {
            throw new InvalidArgumentException('A payment method reference is required before creating a live payment authority.');
        }

        $authority = $this->authorities->capture($proposal, $type, $gateway, $payload, $actor);

        $this->markAwaitingSignature($proposal);

        return [
            'type' => $type,
            'gateway' => $gateway,
            'collection_day' => $collectionDay,
            'payment_authority_id' => $authority->getKey(),
            'gateway_customer_ref' => $authority->gateway_customer_ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signature(Proposal $proposal, array $payload, User $actor): array
    {
        $authority = $this->activeAuthority($proposal);

        if (! $authority instanceof PaymentAuthority) {
            throw new InvalidArgumentException('A tokenised payment authority is required before signature.');
        }

        $typedName = trim((string) ($payload['signature_name'] ?? $payload['typed_name'] ?? ''));
        if ($typedName === '') {
            throw new InvalidArgumentException('A typed signature name is required.');
        }

        $identityVerification = $this->identityVerificationPayload($payload['identity_verification'] ?? []);
        $this->assertSignatureIdentityVerified($identityVerification);
        $payload['identity_verification'] = $identityVerification;
        $signedAt = now();
        $path = sprintf('proposals/%s/%s/signature-v%s.pdf', $proposal->client_id, Str::uuid(), $proposal->version);

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $actor, $signedAt): void {
            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => $signedAt,
                'signed_by_user_id' => $actor->getKey(),
            ])->save();
        });

        $pdf = $this->renderer->render($this->signedProposalHtml(
            $proposal->refresh()->load(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy']),
            $actor,
            $typedName,
            $payload,
            $signedAt,
        ));
        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Signed proposal evidence could not be stored.');
        }

        $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $actor, $signedAt, $path, $hashEnvelope, $pdf): void {
            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => $signedAt,
                'signed_by_user_id' => $actor->getKey(),
                'signature_evidence_path' => $path,
                'signature_evidence_sha256_envelope' => $hashEnvelope,
                'signature_envelope_meta' => $this->envelope->inspect($hashEnvelope),
                'signature_evidence_byte_size' => strlen($pdf),
            ])->save();
        });

        $schedule = $this->ensurePaymentSchedule($proposal->refresh(), $authority->refresh(), $actor);

        $this->audit->record('proposal.signature_identity_verified', subject: $proposal, actor: $actor, after: [
            'password_verified_at' => $identityVerification['password_verified_at'],
            'mfa_required' => $identityVerification['mfa_required'],
            'mfa_verified_at' => $identityVerification['mfa_verified_at'],
            'mfa_method' => $identityVerification['mfa_method'],
        ]);

        return [
            'signature_name' => $typedName,
            'signed_by_user_id' => $actor->getKey(),
            'signature_evidence_path' => $path,
            'payment_schedule_id' => $schedule?->getKey(),
            'collection_day' => $schedule?->collection_day,
            'identity_verification' => $identityVerification,
        ];
    }

    private function confirmation(Proposal $proposal): array
    {
        if ($proposal->refresh()->status !== ProposalStatus::Signed) {
            throw new InvalidArgumentException('Confirmation requires a signed proposal.');
        }

        return [
            'confirmed' => true,
        ];
    }

    private function markAwaitingSignature(Proposal $proposal): void
    {
        if ($proposal->refresh()->status === ProposalStatus::AwaitingSignature) {
            return;
        }

        Proposal::allowSignoffStatusTransition(function () use ($proposal): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordStep(Proposal $proposal, string $step, array $payload, User $actor): ProposalSignoffStep
    {
        return ProposalSignoffStep::query()->updateOrCreate(
            [
                'proposal_id' => $proposal->getKey(),
                'step' => $step,
            ],
            [
                'client_id' => $proposal->client_id,
                'completed_by_user_id' => $actor->getKey(),
                'completed_at' => now(),
                'payload' => $payload,
            ],
        );
    }

    private function activeAuthority(Proposal $proposal): ?PaymentAuthority
    {
        return PaymentAuthority::query()
            ->where('proposal_id', $proposal->getKey())
            ->where('status', PaymentAuthority::STATUS_ACTIVE)
            ->latest('authorised_at')
            ->first();
    }

    /**
     * @return Collection<int, string>
     */
    private function completedSteps(Proposal $proposal): Collection
    {
        $proposal->loadMissing('signoffSteps');

        return $proposal->signoffSteps->pluck('step')->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPayload(Proposal $proposal, string $step): array
    {
        $proposal->loadMissing('signoffSteps');
        $payload = $proposal->signoffSteps->firstWhere('step', $step)?->payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function clientStepPayload(string $step, array $payload): array
    {
        return match ($step) {
            ProposalSignoffStep::STEP_REVIEW => [
                'reviewed' => (bool) ($payload['reviewed'] ?? true),
            ],
            ProposalSignoffStep::STEP_INSURANCE_CONSENT,
            ProposalSignoffStep::STEP_COACH_CONSENT => [
                'election' => $this->validConsentElection($payload['election'] ?? null),
            ],
            ProposalSignoffStep::STEP_PAYMENT_METHOD => [
                'type' => $this->validPaymentType($payload['type'] ?? null),
                'gateway' => $this->validPaymentGateway($payload['gateway'] ?? null),
                'collection_day' => $this->validCollectionDay($payload['collection_day'] ?? null),
            ],
            ProposalSignoffStep::STEP_AUTHORITY => [
                'type' => $this->validPaymentType($payload['type'] ?? null),
                'gateway' => $this->validPaymentGateway($payload['gateway'] ?? null),
                'collection_day' => $this->validCollectionDay($payload['collection_day'] ?? null),
                'gateway_customer_ref' => is_scalar($payload['gateway_customer_ref'] ?? null)
                    ? (string) $payload['gateway_customer_ref']
                    : null,
            ],
            ProposalSignoffStep::STEP_SIGNATURE => [
                'signature_name' => is_scalar($payload['signature_name'] ?? null)
                    ? trim((string) $payload['signature_name'])
                    : '',
            ],
            ProposalSignoffStep::STEP_CONFIRMATION => [
                'confirmed' => (bool) ($payload['confirmed'] ?? true),
            ],
            default => [],
        };
    }

    private function validConsentElection(mixed $value): string
    {
        return is_string($value) && in_array($value, Consent::elections(), true)
            ? $value
            : Consent::ELECTION_UNDECIDED;
    }

    private function validPaymentType(mixed $value): string
    {
        return is_string($value) && in_array($value, PaymentAuthority::types(), true)
            ? $value
            : PaymentAuthority::TYPE_CARD;
    }

    private function validPaymentGateway(mixed $value): string
    {
        return is_string($value) && in_array($value, PaymentAuthority::gateways(), true)
            ? $value
            : PaymentAuthority::GATEWAY_STRIPE;
    }

    private function validCollectionDay(mixed $value): int
    {
        $day = is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : 1;

        if (! in_array($day, [1, 15], true)) {
            throw new InvalidArgumentException('Payment collection date must be either the 1st or the 15th of the month.');
        }

        return $day;
    }

    private function authorityRequiresToken(Proposal $proposal): bool
    {
        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $gateway = $this->validPaymentGateway($method['gateway'] ?? null);

        return $this->integrations->isLive($gateway);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentMethodReference(array $payload): ?string
    {
        $reference = $payload['payment_method_ref'] ?? $payload['fixture_token'] ?? null;

        if (! is_scalar($reference)) {
            return null;
        }

        $reference = trim((string) $reference);

        return $reference === '' ? null : $reference;
    }

    /**
     * @return array{password_verified_at: string|null, mfa_required: bool, mfa_verified_at: string|null, mfa_method: string|null}
     */
    private function identityVerificationPayload(mixed $payload): array
    {
        $payload = is_array($payload) ? $payload : [];
        $passwordVerifiedAt = $payload['password_verified_at'] ?? null;
        $mfaVerifiedAt = $payload['mfa_verified_at'] ?? null;
        $mfaMethod = $payload['mfa_method'] ?? null;

        return [
            'password_verified_at' => is_string($passwordVerifiedAt) && $passwordVerifiedAt !== ''
                ? $passwordVerifiedAt
                : null,
            'mfa_required' => (bool) ($payload['mfa_required'] ?? false),
            'mfa_verified_at' => is_string($mfaVerifiedAt) && $mfaVerifiedAt !== ''
                ? $mfaVerifiedAt
                : null,
            'mfa_method' => is_string($mfaMethod) && $mfaMethod !== ''
                ? $mfaMethod
                : null,
        ];
    }

    /**
     * @param  array{password_verified_at: string|null, mfa_required: bool, mfa_verified_at: string|null, mfa_method: string|null}  $identityVerification
     */
    private function assertSignatureIdentityVerified(array $identityVerification): void
    {
        if ($identityVerification['password_verified_at'] === null) {
            throw new InvalidArgumentException('Password verification is required before signature.');
        }

        if ($identityVerification['mfa_required'] && $identityVerification['mfa_verified_at'] === null) {
            throw new InvalidArgumentException('MFA verification is required before signature.');
        }
    }

    private function nextStep(Proposal $proposal): ?string
    {
        $completed = $this->completedSteps($proposal);

        foreach (ProposalSignoffStep::orderedSteps() as $step) {
            if (! $completed->contains($step)) {
                return $step;
            }
        }

        return null;
    }

    private function assertStepOrder(Proposal $proposal, string $step, bool $alreadyCompleted): void
    {
        if ($alreadyCompleted) {
            return;
        }

        $ordered = ProposalSignoffStep::orderedSteps();
        $position = array_search($step, $ordered, true);
        $completed = $this->completedSteps($proposal);

        foreach (array_slice($ordered, 0, (int) $position) as $required) {
            if (! $completed->contains($required)) {
                throw new InvalidArgumentException("Step [{$required}] must be completed before [{$step}].");
            }
        }
    }

    private function assertClientOwnsProposal(Proposal $proposal): void
    {
        if ($proposal->client === null) {
            throw new InvalidArgumentException('Proposal must belong to a client.');
        }
    }

    private function assertProposalCanEnterSignoff(Proposal $proposal): void
    {
        if (! in_array($proposal->status, [
            ProposalStatus::Released,
            ProposalStatus::AwaitingSignature,
            ProposalStatus::Signed,
        ], true)) {
            throw new InvalidArgumentException('Only released proposals can enter the sign-off flow.');
        }
    }

    private function normaliseStep(string $step): string
    {
        $step = trim($step);

        if (! in_array($step, ProposalSignoffStep::orderedSteps(), true)) {
            throw new InvalidArgumentException("Unsupported sign-off step [{$step}].");
        }

        return $step;
    }

    private function ensurePaymentSchedule(Proposal $proposal, PaymentAuthority $authority, User $actor): ?PaymentSchedule
    {
        $existing = PaymentSchedule::query()
            ->where('proposal_id', $proposal->getKey())
            ->where('payment_authority_id', $authority->getKey())
            ->whereIn('status', [PaymentSchedule::STATUS_ACTIVE, PaymentSchedule::STATUS_PAUSED])
            ->latest()
            ->first();

        if ($existing instanceof PaymentSchedule) {
            return $existing;
        }

        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $authorityPayload = $this->stepPayload($proposal, ProposalSignoffStep::STEP_AUTHORITY);
        $collectionDay = $this->validCollectionDay($authorityPayload['collection_day'] ?? $method['collection_day'] ?? null);
        $termMonths = $this->proposalTermMonths($proposal);

        return $this->schedules->create($proposal, $authority, [
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'amount' => $this->proposalMonthlyAmount($proposal, $termMonths),
            'currency' => 'NZD',
            'collection_day' => $collectionDay,
        ], $actor);
    }

    private function proposalTermMonths(Proposal $proposal): int
    {
        $months = data_get($proposal->scope, 'term_months')
            ?? data_get($proposal->acceptance_terms, 'term_months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer.months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer_months');

        return max(1, (int) (is_numeric($months) ? $months : 6));
    }

    private function proposalMonthlyAmount(Proposal $proposal, int $termMonths): float
    {
        $monthly = data_get($proposal->feeCalculation?->justification, 'retainer.monthly_fee')
            ?? data_get($proposal->feeCalculation?->justification, 'monthly_retainer_fee')
            ?? data_get($proposal->pv_summary, 'monthly_retainer_fee');

        if (is_numeric($monthly) && (float) $monthly > 0) {
            return round((float) $monthly, 2);
        }

        $total = $proposal->feeCalculation?->suggested_mid ?? data_get($proposal->pv_summary, 'fee_suggested_mid', 0);

        return round(((float) $total) / max(1, $termMonths), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedProposalHtml(Proposal $proposal, User $actor, string $typedName, array $payload, CarbonInterface $signedAt): string
    {
        $proposalHtml = $this->proposals->previewHtml($proposal);
        $certificate = $this->signatureCertificateHtml($proposal, $actor, $typedName, $payload, $signedAt);
        $banner = $this->signatureBannerHtml($proposal, $actor, $signedAt);
        $style = <<<'CSS'
.proposal-signature-stamp {
  border: 1px solid #214f44;
  border-left: 5px solid #214f44;
  color: #17211b;
  font-family: Arial, sans-serif;
  font-size: 11px;
  margin: 0 0 14px;
  padding: 10px 12px;
}
.proposal-signature-certificate {
  background: #fff;
  border: 1px solid #d8e2dc;
  border-left: 5px solid #214f44;
  break-before: page;
  color: #17211b;
  font-family: Arial, sans-serif;
  font-size: 12px;
  line-height: 1.55;
  margin: 0;
  padding: 16px;
}
.proposal-signature-certificate h1 {
  color: #214f44;
  font-size: 20px;
  margin: 0 0 6px;
}
.proposal-signature-certificate h2 {
  color: #214f44;
  font-size: 14px;
  margin: 18px 0 6px;
}
.proposal-signature-certificate dl {
  display: grid;
  grid-template-columns: 170px 1fr;
  margin: 0;
}
.proposal-signature-certificate dt {
  color: #5d6b63;
  font-weight: 700;
  padding: 4px 10px 4px 0;
}
.proposal-signature-certificate dd {
  margin: 0;
  padding: 4px 0;
}
CSS;

        return $this->attachSignatureEvidence($proposalHtml, $banner, $certificate, $style);
    }

    private function signatureBannerHtml(Proposal $proposal, User $actor, CarbonInterface $signedAt): string
    {
        return sprintf(
            '<aside class="proposal-signature-stamp"><strong>Signed proposal v%s</strong><br>Signed at %s by %s &lt;%s&gt;.</aside>',
            $proposal->version,
            $this->escape($signedAt->format('j M Y, g:i A T')),
            $this->escape($actor->name),
            $this->escape($actor->email),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signatureCertificateHtml(Proposal $proposal, User $actor, string $typedName, array $payload, CarbonInterface $signedAt): string
    {
        $identity = is_array($payload['identity_verification'] ?? null) ? $payload['identity_verification'] : [];
        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $authorityPayload = $this->stepPayload($proposal, ProposalSignoffStep::STEP_AUTHORITY);
        $collectionDay = $this->validCollectionDay($authorityPayload['collection_day'] ?? $method['collection_day'] ?? null);
        $termMonths = $this->proposalTermMonths($proposal);
        $monthlyAmount = $this->proposalMonthlyAmount($proposal, $termMonths);

        return sprintf(
            <<<'HTML'
<section class="proposal-signature-certificate">
<h1>Signed proposal certificate</h1>
<p>This certificate records the client acceptance, payment authority, and identity checks attached to the signed proposal.</p>
<h2>Proposal</h2>
<dl>
<dt>Client</dt><dd>%s</dd>
<dt>Proposal</dt><dd>Proposal v%s</dd>
<dt>Total proposal</dt><dd>NZD %s</dd>
<dt>Term</dt><dd>%s months</dd>
<dt>Monthly collection</dt><dd>NZD %s per month</dd>
<dt>GST</dt><dd>Amounts are GST exclusive. GST at 15%% is added to each payment collected.</dd>
<dt>Collection date</dt><dd>%s of each month</dd>
</dl>
<h2>Signature</h2>
<dl>
<dt>Signed at</dt><dd>%s</dd>
<dt>Signed by</dt><dd>%s &lt;%s&gt;</dd>
<dt>Typed signature</dt><dd>%s</dd>
<dt>User ID</dt><dd>%s</dd>
<dt>IP address</dt><dd>%s</dd>
<dt>User agent</dt><dd>%s</dd>
</dl>
<h2>Identity verification</h2>
<dl>
<dt>Password</dt><dd>Verified at %s</dd>
<dt>MFA</dt><dd>%s</dd>
</dl>
<h2>Payment authority</h2>
<p>Authorised using password%s before signature. The payment method is tokenised by the selected gateway; raw card details are not stored by Future Shift Advisory.</p>
</section>
HTML,
            $this->escape($proposal->client?->legal_name ?? 'Client'),
            $proposal->version,
            number_format($proposal->feeCalculation?->suggested_mid ?? 0, 0),
            $termMonths,
            number_format($monthlyAmount, 0),
            $this->ordinal($collectionDay),
            $this->escape($signedAt->format('j M Y, g:i A T')),
            $this->escape($actor->name),
            $this->escape($actor->email),
            $this->escape($typedName),
            $this->escape((string) $actor->getKey()),
            $this->escape((string) ($payload['ip'] ?? '')),
            $this->escape((string) ($payload['user_agent'] ?? '')),
            $this->escape($this->formatEvidenceDate($identity['password_verified_at'] ?? null)),
            $this->escape($this->mfaEvidenceLine($identity)),
            (bool) ($identity['mfa_required'] ?? false) ? ' and MFA' : '',
        );
    }

    private function attachSignatureEvidence(string $html, string $banner, string $certificate, string $style): string
    {
        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', "<style>\n{$style}\n</style>\n</head>", $html);
        } else {
            $html = "<style>\n{$style}\n</style>\n".$html;
        }

        if (preg_match('/<body\b[^>]*>/i', $html) === 1) {
            $html = preg_replace('/(<body\b[^>]*>)/i', '$1'.$banner, $html, 1) ?? $html;
        } else {
            $html = $banner.$html;
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $certificate."\n</body>", $html);
        }

        return $html.$certificate;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function mfaEvidenceLine(array $identity): string
    {
        if (! (bool) ($identity['mfa_required'] ?? false)) {
            return 'Not required in this environment';
        }

        $method = is_scalar($identity['mfa_method'] ?? null) ? (string) $identity['mfa_method'] : 'Authenticator app';

        return 'Verified at '.$this->formatEvidenceDate($identity['mfa_verified_at'] ?? null).' using '.$method;
    }

    private function formatEvidenceDate(mixed $value): string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return 'Recorded';
        }

        try {
            return Carbon::parse((string) $value)->format('j M Y, g:i A T');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function ordinal(int $day): string
    {
        return match ($day) {
            1 => '1st',
            15 => '15th',
            default => (string) $day,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
