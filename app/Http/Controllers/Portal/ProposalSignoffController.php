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
use App\Services\Payments\PaymentGatewayException;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use LogicException;

final class ProposalSignoffController extends Controller
{
    public function show(Request $request, Proposal $proposal, ClientPortalResolver $clients, SignoffFlow $signoff): Response
    {
        $this->assertClientCanAccessProposal($request, $proposal, $clients);
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
        $this->assertClientCanAccessProposal($request, $proposal, $clients);

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
