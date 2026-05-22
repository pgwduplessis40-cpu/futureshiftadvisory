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
use App\Services\Proposals\ProposalBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
}
