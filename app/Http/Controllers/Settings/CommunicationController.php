<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CommunicationPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CommunicationController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $canChooseChannel = $user->user_type !== User::TYPE_ENTREPRENEUR;
        $preference = $user->communicationPreference()->firstOrCreate([], [
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        return Inertia::render('settings/communication', [
            'preference' => [
                'channel' => $preference->channel,
                'frequency' => $preference->frequency,
                'timezone' => $preference->timezone,
            ],
            'canChooseChannel' => $canChooseChannel,
            'channels' => CommunicationPreference::channels(),
            'frequencies' => CommunicationPreference::frequencies(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $canChooseChannel = $user->user_type !== User::TYPE_ENTREPRENEUR;

        $validated = $request->validate([
            'channel' => [$canChooseChannel ? 'required' : 'exclude', 'string', Rule::in(CommunicationPreference::channels())],
            'frequency' => ['required', 'string', Rule::in(CommunicationPreference::frequencies())],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $user->communicationPreference()->updateOrCreate([], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Communication preferences updated.')]);

        return to_route('communication.edit');
    }
}
