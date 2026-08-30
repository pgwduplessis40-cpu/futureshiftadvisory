<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientStrategicBudgetController extends Controller
{
    public function __construct(
        private readonly StrategicBudgetService $budgets,
    ) {}

    public function approve(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client);
        $payload = $this->budgets->advisorPayload($budget);
        if (! $budget->isUnlocked()) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'A verified P&L or management accounts file must be available before approving the plan and budget.']);
        }
        if (($payload['business_plan_ready'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'The client must complete every plan section before the combined Plan & Budget can be approved.']);
        }
        if (($payload['review_submitted_or_later'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'The client must submit the Business Plan & Budget for advisor review before it can be approved.']);
        }
        if (($payload['assessment_ready_for_approval'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'Run the Business Plan & Budget assessment before approving it.']);
        }

        $this->budgets->approve($budget, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'business-plan-budget-approved');
    }

    public function assess(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client);
        $payload = $this->budgets->advisorPayload($budget);

        if (! $budget->isUnlocked()) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'A verified P&L or management accounts file must be available before running the plan and budget assessment.']);
        }

        if (($payload['business_plan_ready'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'The client must complete every plan section before the combined Plan & Budget can be assessed.']);
        }

        if (($payload['review_submitted_or_later'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'The client must submit the Business Plan & Budget for advisor review before the assessment is run.']);
        }

        $this->budgets->assess($budget, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'business-plan-budget-assessed');
    }

    public function feedback(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'advisor_feedback' => ['required', 'string', 'min:10', 'max:5000'],
            'proposed_reply' => ['required', 'string', 'min:10', 'max:5000'],
            'send_to_client' => ['required', 'boolean'],
        ]);

        $budget = $this->budgets->ensureForClient($client);
        $this->budgets->saveAssessmentFeedback(
            budget: $budget,
            actor: $user,
            advisorFeedback: (string) $validated['advisor_feedback'],
            proposedReply: (string) $validated['proposed_reply'],
            sendToClient: (bool) $validated['send_to_client'],
        );

        return to_route('advisor.clients.show', $client)->with(
            'status',
            (bool) $validated['send_to_client']
                ? 'business-plan-budget-feedback-sent'
                : 'business-plan-budget-feedback-saved',
        );
    }

    public function advisorGoals(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'advisor_goals' => ['array', 'max:8'],
            'advisor_goals.*.title' => ['nullable', 'string', 'max:180'],
            'advisor_goals.*.measure' => ['nullable', 'string', 'max:500'],
        ]);

        $budget = $this->budgets->ensureForClient($client);
        $this->budgets->updateAdvisorGoals($budget, (array) ($validated['advisor_goals'] ?? []), $user);

        return to_route('advisor.clients.show', $client)->with('status', 'strategic-budget-advisor-goals-saved');
    }
}
