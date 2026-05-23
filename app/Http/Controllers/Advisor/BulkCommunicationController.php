<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\BulkCommunication;
use App\Models\Client;
use App\Models\User;
use App\Services\Communications\BulkCommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BulkCommunicationController extends Controller
{
    public function __construct(private readonly BulkCommunicationService $communications) {}

    public function index(): Response
    {
        return Inertia::render('advisor/bulk-communications/Index', [
            'communications' => BulkCommunication::query()
                ->withCount('recipients')
                ->latest('scheduled_at')
                ->limit(25)
                ->get()
                ->map(fn (BulkCommunication $communication): array => [
                    'id' => $communication->id,
                    'title' => $communication->title,
                    'subject' => $communication->subject,
                    'template_key' => $communication->template_key,
                    'audience_type' => $communication->audience_type,
                    'status' => $communication->status,
                    'scheduled_at' => $communication->scheduled_at?->toIso8601String(),
                    'sent_at' => $communication->sent_at?->toIso8601String(),
                    'metrics' => $communication->metrics ?? [],
                    'recipients_count' => $communication->recipients_count,
                ])
                ->values(),
            'clients' => Client::query()
                ->whereHas('teamMembers.user', fn ($query) => $query->whereIn('user_type', [
                    User::TYPE_CLIENT_PRIMARY,
                    User::TYPE_CLIENT_TEAM,
                ]))
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'trading_name'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->trading_name ?: $client->legal_name,
                ])
                ->values(),
            'templates' => $this->communications->templateOptions(),
            'storeUrl' => route('advisor.bulk-communications.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'template_key' => ['nullable', 'string', Rule::in(array_keys(BulkCommunication::templates()))],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:6000'],
            'audience_type' => ['required', 'string', Rule::in(BulkCommunication::audienceTypes())],
            'selected_client_ids' => ['required_if:audience_type,'.BulkCommunication::AUDIENCE_SELECTED_CLIENTS, 'array'],
            'selected_client_ids.*' => ['uuid', 'exists:clients,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->communications->schedule($validated, $user);

        return to_route('advisor.bulk-communications.index')->with('status', 'bulk-communication-scheduled');
    }
}
