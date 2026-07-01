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
        if (! $budget->isUnlocked()) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'A P&L or management accounts file must be uploaded before approving the plan and budget.']);
        }
        if (($this->budgets->advisorPayload($budget)['business_plan_ready'] ?? false) !== true) {
            return to_route('advisor.clients.show', $client)
                ->withErrors(['strategic_budget' => 'The client must complete every plan section before the combined Plan & Budget can be approved.']);
        }

        $this->budgets->approve($budget, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'business-plan-budget-approved');
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
