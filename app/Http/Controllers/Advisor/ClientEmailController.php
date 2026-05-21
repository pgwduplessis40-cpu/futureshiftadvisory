<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Communications\EmailFromApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ClientEmailController extends Controller
{
    public function __construct(private readonly EmailFromApp $email) {}

    public function create(Client $client): Response
    {
        Gate::authorize('view', $client);

        return Inertia::render('advisor/clients/Compose', [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
            ],
            'recipients' => $this->recipients($client),
            'storeUrl' => route('advisor.clients.email.store', $client, absolute: false),
            'backUrl' => route('advisor.clients.show', $client, absolute: false),
            'messagesUrl' => route('advisor.clients.messages.index', $client, absolute: false),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'recipient_user_ids' => ['required', 'array', 'min:1'],
            'recipient_user_ids.*' => ['integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:6000'],
            'logical_message_key' => ['nullable', 'string', 'max:120'],
        ]);

        $message = $this->email->send(
            client: $client,
            sender: $user,
            recipientUserIds: $validated['recipient_user_ids'],
            subject: (string) $validated['subject'],
            body: (string) $validated['body'],
            logicalMessageKey: $validated['logical_message_key'] ?? null,
        );

        return to_route('advisor.clients.messages.show', [$client, $message->thread])
            ->with('status', 'email-sent');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recipients(Client $client): array
    {
        return ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->whereHas('user', fn ($query) => $query->whereIn('user_type', [
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_CLIENT_TEAM,
            ]))
            ->with('user.communicationPreference')
            ->get()
            ->map(fn (ClientTeamMember $member): ?array => $member->user instanceof User
                ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->role,
                    'default_selected' => (int) $client->primary_contact_user_id === (int) $member->user->getKey(),
                    'preference_channel' => $member->user->communicationPreference?->channel ?? 'both',
                    'preference_frequency' => $member->user->communicationPreference?->frequency ?? 'immediate',
                ]
                : null)
            ->filter()
            ->values()
            ->all();
    }
}
