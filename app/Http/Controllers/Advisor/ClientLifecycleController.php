<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Clients\LifecycleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ClientLifecycleController extends Controller
{
    public function update(Request $request, Client $client, LifecycleManager $lifecycle): RedirectResponse
    {
        Gate::authorize('update', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $lifecycle->transition(
            client: $client,
            targetStatus: (string) $validated['status'],
            actor: $user,
            reason: $validated['reason'] ?? null,
        );

        return to_route('advisor.clients.show', $client)->with('status', 'client-lifecycle-updated');
    }
}
