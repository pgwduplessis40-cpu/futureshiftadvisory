<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Meetings\MeetingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MeetingController extends Controller
{
    public function store(Request $request, Client $client, MeetingManager $meetings): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:255'],
            'attendees' => ['nullable', 'string', 'max:1000'],
        ]);

        $meetings->create($client, $user, $validated);

        return to_route('advisor.clients.show', $client)->with('status', 'meeting-created');
    }
}
