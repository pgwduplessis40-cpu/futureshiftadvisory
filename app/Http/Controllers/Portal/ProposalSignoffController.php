<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Proposals\SignoffFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use LogicException;

final class ProposalSignoffController extends Controller
{
    public function show(Request $request, Proposal $proposal, ClientPortalResolver $clients, SignoffFlow $signoff): Response
    {
        $client = $clients->resolveFor($request);
        abort_unless((string) $proposal->client_id === (string) $client->getKey(), 404);

        $proposal->load(['client', 'feeCalculation', 'consents', 'paymentAuthorities', 'signoffSteps']);

        return Inertia::render('portal/ProposalSignoff', [
            'proposal' => $this->proposalPayload($proposal),
            'signoff' => $signoff->payload($proposal),
        ]);
    }

    public function step(
        Request $request,
        Proposal $proposal,
        string $step,
        ClientPortalResolver $clients,
        SignoffFlow $signoff,
    ): RedirectResponse {
        $client = $clients->resolveFor($request);
        abort_unless((string) $proposal->client_id === (string) $client->getKey(), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validateStep($request, $step);

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
            'roi_ratio' => $proposal->roi_ratio,
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
    private function validateStep(Request $request, string $step): array
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
            ]),
            ProposalSignoffStep::STEP_AUTHORITY => $request->validate([
                'type' => ['nullable', Rule::in(PaymentAuthority::types())],
                'gateway' => ['nullable', Rule::in(PaymentAuthority::gateways())],
                'fixture_token' => ['nullable', 'string', 'max:255'],
                'fixture_fail' => ['nullable', 'boolean'],
            ]),
            ProposalSignoffStep::STEP_SIGNATURE => $request->validate([
                'signature_name' => ['required', 'string', 'max:255'],
                'accepted' => ['accepted'],
            ]),
            ProposalSignoffStep::STEP_CONFIRMATION => $request->validate([
                'confirmed' => ['nullable', 'boolean'],
            ]),
            default => throw new InvalidArgumentException("Unsupported sign-off step [{$step}]."),
        };
    }
}
