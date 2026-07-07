<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\GstCalculator;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use LogicException;

final class ProposalSignoffController extends Controller
{
    public function show(
        Request $request,
        Proposal $proposal,
        ClientPortalResolver $clients,
        SignoffFlow $signoff,
        MfaChallenger $mfa,
    ): Response {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);
        $proposal->load(['client', 'feeCalculation', 'consents', 'paymentAuthorities', 'signoffSteps']);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('portal/ProposalSignoff', [
            'proposal' => $this->proposalPayload($proposal),
            'signoff' => [
                ...$signoff->payload($proposal),
                'signature_requires_password' => true,
                'signature_requires_mfa' => $this->paymentAuthorisationRequiresMfa($user, $mfa),
            ],
        ]);
    }

    public function step(
        Request $request,
        Proposal $proposal,
        string $step,
        ClientPortalResolver $clients,
        SignoffFlow $signoff,
        MfaChallenger $mfa,
    ): RedirectResponse {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validateStep($request, $step, $user, $mfa);

        if ($step === ProposalSignoffStep::STEP_SIGNATURE) {
            $validated['ip'] = $request->ip();
            $validated['user_agent'] = $request->userAgent();
        }

        try {
            $signoff->complete($proposal, $step, $validated, $user);
        } catch (PaymentGatewayException|InvalidArgumentException|LogicException $e) {
            return back()->withErrors(['signoff' => $e->getMessage()]);
        }

        return to_route('portal.proposals.signoff.show', $proposal)->with('status', 'proposal-signoff-updated');
    }

    public function paymentSetup(
        Request $request,
        Proposal $proposal,
        ClientPortalResolver $clients,
        StripeClient $stripe,
    ): JsonResponse {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);
        $proposal->load(['client', 'signoffSteps']);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($this->proposalTotalAmount($proposal) <= 0) {
            throw ValidationException::withMessages([
                'payment_method_ref' => 'No payment setup is required for a zero-fee proposal.',
            ]);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(PaymentAuthority::types())],
            'gateway' => ['required', Rule::in(PaymentAuthority::gateways())],
        ]);

        if ($validated['gateway'] !== PaymentAuthority::GATEWAY_STRIPE || $validated['type'] !== PaymentAuthority::TYPE_CARD) {
            throw ValidationException::withMessages([
                'payment_method_ref' => 'Online card setup is currently available for Stripe card payments.',
            ]);
        }

        try {
            $setupIntent = $stripe->createSetupIntent(new PaymentAuthorityRequest(
                clientId: (string) $proposal->client_id,
                proposalId: (string) $proposal->getKey(),
                type: (string) $validated['type'],
                gateway: (string) $validated['gateway'],
                payload: [
                    'customer_email' => $user->email,
                    'customer_name' => $proposal->client?->legal_name ?? $user->name,
                ],
            ));
        } catch (PaymentGatewayException $e) {
            throw ValidationException::withMessages([
                'payment_method_ref' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'publishable_key' => $setupIntent->publishableKey,
            'client_secret' => $setupIntent->clientSecret,
            'setup_intent_ref' => $setupIntent->setupIntentRef,
            'customer_ref' => $setupIntent->customerRef,
        ]);
    }

    public function viewProposal(
        Request $request,
        Proposal $proposal,
        ClientPortalResolver $clients,
        ProposalBuilder $proposals,
        AuditWriter $audit,
    ): HttpResponse {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($proposal->status === ProposalStatus::Signed) {
            return $this->signedEvidenceResponse($proposal, $user, $audit, download: false);
        }

        $html = $proposals->previewHtml($proposal);

        $audit->record('proposal.portal_viewed', subject: $proposal, actor: $user, after: [
            'client_id' => $proposal->client_id,
            'version' => $proposal->version,
            'format' => 'html',
        ]);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function download(
        Request $request,
        Proposal $proposal,
        ClientPortalResolver $clients,
        ProposalBuilder $proposals,
        AuditWriter $audit,
    ): HttpResponse {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($proposal->status === ProposalStatus::Signed) {
            return $this->signedEvidenceResponse($proposal, $user, $audit, download: true);
        }

        $proposal = $proposals->rerenderPdf($proposal);
        $disk = Storage::disk('secure_local');

        $path = $proposal->pdf_path;
        abort_if($path === null || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $audit->record('proposal.portal_downloaded', subject: $proposal, actor: $user, after: [
            'client_id' => $proposal->client_id,
            'version' => $proposal->version,
        ]);

        $filename = Str::slug('proposal-v'.$proposal->version.'-'.$proposal->client?->legal_name).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function signedEvidenceResponse(
        Proposal $proposal,
        User $user,
        AuditWriter $audit,
        bool $download,
    ): HttpResponse {
        $disk = Storage::disk('secure_local');
        $path = is_string($proposal->signature_evidence_path) ? trim($proposal->signature_evidence_path) : '';

        abort_if($path === '' || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $audit->record($download ? 'proposal.portal_signed_downloaded' : 'proposal.portal_signed_viewed', subject: $proposal, actor: $user, after: [
            'client_id' => $proposal->client_id,
            'version' => $proposal->version,
            'signature_evidence_path' => $path,
        ]);

        $filename = Str::slug('signed-proposal-v'.$proposal->version.'-'.$proposal->client?->legal_name).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalPayload(Proposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'version' => $proposal->version,
            'status' => $proposal->status->value,
            'status_label' => str($proposal->status->value)->replace('_', ' ')->title()->toString(),
            'client_name' => $proposal->client?->legal_name,
            'scope_summary' => (string) data_get($proposal->scope, 'summary', ''),
            'suggested_mid' => $proposal->feeCalculation?->suggested_mid,
            'payment_terms' => $this->paymentTermsPayload($proposal),
            'roi_ratio' => $proposal->roi_ratio,
            'view_url' => route('portal.proposals.show', $proposal, absolute: false),
            'download_url' => route('portal.proposals.download', $proposal, absolute: false),
            'released_at' => $proposal->released_at?->toIso8601String(),
            'awaiting_signature_at' => $proposal->awaiting_signature_at?->toIso8601String(),
            'signed_at' => $proposal->signed_at?->toIso8601String(),
            'consents' => $proposal->consents
                ->mapWithKeys(fn (Consent $consent): array => [$consent->type => $consent->election])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentTermsPayload(Proposal $proposal): array
    {
        $gst = app(GstCalculator::class);
        $termMonths = $this->proposalTermMonths($proposal);
        $monthlyAmount = $this->proposalMonthlyAmount($proposal, $termMonths);
        $totalAmount = $this->proposalTotalAmount($proposal, $monthlyAmount, $termMonths);

        return [
            'currency' => 'NZD',
            'cadence' => 'monthly',
            'cadence_label' => 'Monthly',
            'term_months' => $termMonths,
            'monthly_amount' => $monthlyAmount,
            'monthly_amount_including_gst' => round((float) $gst->grossFromExclusive($monthlyAmount), 2),
            'total_amount' => is_numeric($totalAmount) ? round((float) $totalAmount, 2) : null,
            'total_amount_including_gst' => is_numeric($totalAmount) ? round((float) $gst->grossFromExclusive((float) $totalAmount), 2) : null,
            'gst_rate_percent' => $gst->ratePercent(),
            'tax_mode' => 'gst_exclusive',
            'cancellation_notice_days' => $this->positiveInteger(data_get($proposal->acceptance_terms, 'cancellation_notice_days')),
        ];
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

    private function proposalTotalAmount(Proposal $proposal, ?float $monthlyAmount = null, ?int $termMonths = null): float
    {
        $feeAmount = $proposal->feeCalculation?->suggested_mid;

        if (is_numeric($feeAmount)) {
            return round((float) $feeAmount, 2);
        }

        $summaryAmount = data_get($proposal->pv_summary, 'fee_suggested_mid');

        if (is_numeric($summaryAmount)) {
            return round((float) $summaryAmount, 2);
        }

        if ($monthlyAmount !== null && $monthlyAmount > 0) {
            return round($monthlyAmount * max(1, $termMonths ?? $this->proposalTermMonths($proposal)), 2);
        }

        return 0.0;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStep(Request $request, string $step, User $user, MfaChallenger $mfa): array
    {
        return match ($step) {
            ProposalSignoffStep::STEP_REVIEW => $request->validate([
                'reviewed' => ['nullable', 'boolean'],
            ]),
            ProposalSignoffStep::STEP_INSURANCE_CONSENT,
            ProposalSignoffStep::STEP_COACH_CONSENT => $request->validate([
                'election' => ['required', Rule::in(Consent::elections())],
            ]),
            ProposalSignoffStep::STEP_PAYMENT_METHOD => $request->validate([
                'type' => ['required', Rule::in(PaymentAuthority::types())],
                'gateway' => ['required', Rule::in(PaymentAuthority::gateways())],
                'collection_day' => ['required', 'integer', Rule::in([1, 15])],
            ]),
            ProposalSignoffStep::STEP_AUTHORITY => $request->validate([
                'type' => ['nullable', Rule::in(PaymentAuthority::types())],
                'gateway' => ['nullable', Rule::in(PaymentAuthority::gateways())],
                'collection_day' => ['nullable', 'integer', Rule::in([1, 15])],
                'payment_method_ref' => ['nullable', 'string', 'max:255'],
                'setup_intent_ref' => ['nullable', 'string', 'max:255'],
                'customer_ref' => ['nullable', 'string', 'max:255'],
                'fixture_token' => ['nullable', 'string', 'max:255'],
                'fixture_fail' => ['nullable', 'boolean'],
            ]),
            ProposalSignoffStep::STEP_SIGNATURE => $this->validateSignatureStep($request, $user, $mfa),
            ProposalSignoffStep::STEP_CONFIRMATION => $request->validate([
                'confirmed' => ['nullable', 'boolean'],
            ]),
            default => throw new InvalidArgumentException("Unsupported sign-off step [{$step}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSignatureStep(Request $request, User $user, MfaChallenger $mfa): array
    {
        $requiresMfa = $this->paymentAuthorisationRequiresMfa($user, $mfa, enforceEnrollment: true);

        $validated = $request->validate([
            'signature_name' => ['required', 'string', 'max:255'],
            'accepted' => ['accepted'],
            'current_password' => ['required', 'current_password'],
            'mfa_code' => [$requiresMfa ? 'required' : 'nullable', 'string', 'digits:6'],
        ], [
            'current_password.current_password' => 'The password was incorrect.',
            'mfa_code.required' => 'Enter the 6-digit code from your authenticator app before authorising payment.',
            'mfa_code.digits' => 'Enter the 6-digit code from your authenticator app.',
        ]);

        $passwordVerifiedAt = now();
        $mfaVerifiedAt = null;

        if ($requiresMfa) {
            $mfa->verifyTotpCode($request, $user, (string) $validated['mfa_code'], 'mfa_code');
            $mfaVerifiedAt = now();
        }

        return [
            'signature_name' => $validated['signature_name'],
            'accepted' => (bool) $validated['accepted'],
            'identity_verification' => [
                'password_verified_at' => $passwordVerifiedAt->toIso8601String(),
                'mfa_required' => $requiresMfa,
                'mfa_verified_at' => $mfaVerifiedAt?->toIso8601String(),
                'mfa_method' => $requiresMfa ? ($user->mfa_method ?? User::MFA_METHOD_TOTP) : null,
            ],
        ];
    }

    private function paymentAuthorisationRequiresMfa(
        User $user,
        MfaChallenger $mfa,
        bool $enforceEnrollment = false,
    ): bool {
        if (! (bool) config('security.mfa_required', true)) {
            return false;
        }

        if ($mfa->hasCompletedEnrolment($user)) {
            return true;
        }

        if ($enforceEnrollment) {
            throw ValidationException::withMessages([
                'mfa_code' => 'Two-factor authentication must be enabled before authorising payment.',
            ]);
        }

        return false;
    }

    private function assertClientCanAccessProposal(Request $request, Proposal $proposal, ClientPortalResolver $clients): void
    {
        $client = $clients->resolveFor($request);
        abort_unless((string) $proposal->client_id === (string) $client->getKey(), 404);
        abort_unless(in_array($proposal->status, [
            ProposalStatus::Released,
            ProposalStatus::AwaitingSignature,
            ProposalStatus::Signed,
        ], true), 404);

        $proposal->loadMissing('client');
    }
}
