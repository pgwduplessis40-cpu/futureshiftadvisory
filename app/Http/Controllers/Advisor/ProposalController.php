<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\FunnelEvent;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Audit\AuditWriter;
use App\Services\Proposals\ProposalBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class ProposalController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        ProposalBuilder $proposals,
        FunnelTracker $funnels,
        StrategicBudgetService $strategicBudgets,
    ): RedirectResponse
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
            'budget_override_category' => ['nullable', 'string', Rule::in([
                'client_urgency',
                'limited_financials',
                'preliminary_budget',
                'advisor_judgement',
                'other',
            ])],
            'budget_override_notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $feeCalculation = FeeCalculation::query()
            ->where('client_id', $client->getKey())
            ->findOrFail($validated['fee_calculation_id']);
        $budget = $strategicBudgets->ensureForClient($client);

        if (! $budget->isApprovedForProposal()) {
            $overrideCategory = trim((string) ($validated['budget_override_category'] ?? ''));
            $overrideNotes = trim((string) ($validated['budget_override_notes'] ?? ''));

            if ($overrideCategory === '' || $overrideNotes === '') {
                return back()->withErrors([
                    'budget_override_category' => 'Choose an override reason before generating a proposal without an approved Business Plan & Budget.',
                    'budget_override_notes' => 'Add acknowledgement notes explaining why the proposal is being generated before Business Plan & Budget approval.',
                ]);
            }
        }

        $scopeSummary = trim((string) ($validated['scope_summary'] ?? ''));
        $scope = $scopeSummary === '' ? [] : ['summary' => $scopeSummary];
        $scope['term_months'] = $this->recommendedTermMonths((float) $feeCalculation->suggested_mid);
        $scope['budget'] = [
            ...$strategicBudgets->proposalGuardPayload($budget),
            'override' => $budget->isApprovedForProposal() ? null : [
                'category' => $validated['budget_override_category'],
                'notes' => $validated['budget_override_notes'],
                'acknowledged_by_user_id' => $user->getKey(),
                'acknowledged_at' => now()->toIso8601String(),
            ],
        ];

        $funnels->enter(FunnelEvent::FLOW_PROPOSAL, 'generate', $client, $user);
        $proposal = $proposals->generate($client, $feeCalculation, [
            'scope' => $scope,
            'consents' => [
                Consent::TYPE_INSURANCE_REFERRAL => $validated['insurance_consent'],
                Consent::TYPE_COACH_REFERRAL => $validated['coach_consent'],
            ],
        ], [
            'created_by_user_id' => $user->getKey(),
        ]);
        $strategicBudgets->markUsedInProposal($budget, $proposal, $user);
        $funnels->complete(FunnelEvent::FLOW_PROPOSAL, 'generate', $client, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'proposal-generated');
    }

    private function recommendedTermMonths(float $amount): int
    {
        return match (true) {
            $amount >= 40000 => 36,
            $amount >= 18000 => 24,
            default => 12,
        };
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

        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Renewed], true)) {
            return back()->withErrors(['proposal' => 'Only draft or renewed proposals can be released.']);
        }

        $funnels->enter(FunnelEvent::FLOW_PROPOSAL, 'release', $proposal->client, $user);
        try {
            $proposals->release($proposal, $user, $validated['expiry_days'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        }
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
