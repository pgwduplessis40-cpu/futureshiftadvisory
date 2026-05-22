<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;
use App\Services\Goals\GoalTracker;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class GoalController extends Controller
{
    public function store(Request $request, Client $client, GoalTracker $goals): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'annual_benefit' => ['nullable', 'numeric', 'min:0'],
            'duration_years' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pv_target' => ['nullable', 'numeric', 'min:0'],
        ]);

        $goals->createGoal($client, $validated, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'goal-created');
    }

    public function milestone(Request $request, Goal $goal, GoalTracker $goals): RedirectResponse
    {
        $goal->loadMissing('client');
        Gate::authorize('view', $goal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'recommendation_ref' => ['nullable', 'string', 'max:255'],
            'annual_impact' => ['nullable', 'numeric', 'min:0'],
            'duration_years' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pv_of_impact' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $goals->createMilestone($goal, $validated, $user);

        return to_route('advisor.clients.show', $goal->client)->with('status', 'milestone-created');
    }

    public function action(Request $request, Milestone $milestone, GoalTracker $goals): RedirectResponse
    {
        $milestone->loadMissing('client');
        Gate::authorize('view', $milestone->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:40'],
        ]);

        $goals->createAction($milestone, $validated, $user);

        return to_route('advisor.clients.show', $milestone->client)->with('status', 'milestone-action-created');
    }

    public function proof(
        Request $request,
        Milestone $milestone,
        GoalTracker $goals,
        SecureFileWriter $files,
    ): RedirectResponse {
        $milestone->loadMissing('client');
        Gate::authorize('view', $milestone->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'proof' => ['required', 'file', 'max:10240'],
            'claim' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = $files->write(
            uploadedFile: $validated['proof'],
            owner: $user,
            category: Document::CATEGORY_OTHER,
            clientId: (string) $milestone->client_id,
        );

        $proof = $goals->completeWithProof($milestone, $document, [
            'claim' => $validated['claim'] ?? null,
        ], $user);

        return to_route('advisor.clients.show', $milestone->client)
            ->with('status', $proof->status === 'verified' ? 'milestone-completed' : 'milestone-proof-flagged');
    }
}
