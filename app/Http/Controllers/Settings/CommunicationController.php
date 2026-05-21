<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CommunicationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CommunicationController extends Controller
{
    public function edit(Request $request): Response
    {
        $preference = $request->user()->communicationPreference()->firstOrCreate([], [
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
            'channels' => CommunicationPreference::channels(),
            'frequencies' => CommunicationPreference::frequencies(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::in(CommunicationPreference::channels())],
            'frequency' => ['required', 'string', Rule::in(CommunicationPreference::frequencies())],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $request->user()->communicationPreference()->updateOrCreate([], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Communication preferences updated.')]);

        return to_route('communication.edit');
    }
}
