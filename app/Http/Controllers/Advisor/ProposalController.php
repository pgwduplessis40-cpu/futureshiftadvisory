<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\FunnelEvent;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Services\Audit\AuditWriter;
use App\Services\Proposals\ProposalBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProposalController extends Controller
{
    public function store(Request $request, Client $client, ProposalBuilder $proposals, FunnelTracker $funnels): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'fee_calculation_id' => [
                'required',
                'uuid',
                Rule::exists('fee_calculations', 'id')->where('client_id', $client->getKey()),
            ],
            'scope_summary' => ['nullable', 'string', 'max:2000'],
            'insurance_consent' => ['required', Rule::in(Consent::elections())],
            'coach_consent' => ['required', Rule::in(Consent::elections())],
        ]);

        $feeCalculation = FeeCalculation::query()
            ->where('client_id', $client->getKey())
            ->findOrFail($validated['fee_calculation_id']);

        $scopeSummary = trim((string) ($validated['scope_summary'] ?? ''));

        $funnels->enter(FunnelEvent::FLOW_PROPOSAL, 'generate', $client, $user);
        $proposals->generate($client, $feeCalculation, [
            'scope' => $scopeSummary === '' ? [] : ['summary' => $scopeSummary],
            'consents' => [
                Consent::TYPE_INSURANCE_REFERRAL => $validated['insurance_consent'],
                Consent::TYPE_COACH_REFERRAL => $validated['coach_consent'],
            ],
        ], [
            'created_by_user_id' => $user->getKey(),
        ]);
        $funnels->complete(FunnelEvent::FLOW_PROPOSAL, 'generate', $client, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'proposal-generated');
    }

    public function release(Request $request, Proposal $proposal, ProposalBuilder $proposals, FunnelTracker $funnels): RedirectResponse
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $funnels->enter(FunnelEvent::FLOW_PROPOSAL, 'release', $proposal->client, $user);
        $proposals->release($proposal, $user, $validated['expiry_days'] ?? null);
        $funnels->complete(FunnelEvent::FLOW_PROPOSAL, 'release', $proposal->client, $user);

        return to_route('advisor.clients.show', $proposal->client)->with('status', 'proposal-released');
    }

    public function recall(Request $request, Proposal $proposal, ProposalBuilder $proposals): RedirectResponse
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $proposals->recall($proposal, $user);

        return to_route('advisor.clients.show', $proposal->client)->with('status', 'proposal-recalled');
    }

    public function renew(Request $request, Proposal $proposal, ProposalBuilder $proposals): RedirectResponse
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $renewed = $proposals->renew($proposal, $user);

        return to_route('advisor.clients.show', $renewed->client)->with('status', 'proposal-renewed');
    }

    public function show(Request $request, Proposal $proposal, ProposalBuilder $proposals, AuditWriter $audit): Response
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $html = $proposals->previewHtml($proposal);

        $audit->record('proposal.viewed', subject: $proposal, actor: $user, after: [
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

    public function download(Request $request, Proposal $proposal, ProposalBuilder $proposals, AuditWriter $audit): Response
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $proposal = $proposals->rerenderPdf($proposal);
        $disk = Storage::disk('secure_local');

        $path = $proposal->pdf_path;
        abort_if($path === null || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $audit->record('proposal.downloaded', subject: $proposal, actor: $user, after: [
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
}
