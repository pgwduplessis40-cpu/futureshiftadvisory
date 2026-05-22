<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\RedFlag;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RedFlagController extends Controller
{
    public function acknowledge(Request $request, RedFlag $redFlag, AuditWriter $audit): RedirectResponse
    {
        $redFlag->loadMissing('client');
        Gate::authorize('view', $redFlag->client);

        if ($redFlag->acknowledged_at === null) {
            $before = $redFlag->only(['acknowledged_at', 'acknowledged_by_user_id']);

            $redFlag->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => $request->user()?->getAuthIdentifier(),
            ])->save();

            $audit->record(
                action: 'red_flag.acknowledged',
                subject: $redFlag,
                before: $before,
                after: $redFlag->only(['acknowledged_at', 'acknowledged_by_user_id']),
                actor: $request->user() instanceof User ? $request->user() : null,
            );
        }

        return back()->with('status', 'red-flag-acknowledged');
    }

    public function resolve(Request $request, RedFlag $redFlag, AuditWriter $audit): RedirectResponse
    {
        $redFlag->loadMissing('client');
        Gate::authorize('view', $redFlag->client);

        if ($redFlag->resolved_at === null) {
            $before = $redFlag->only(['resolved_at']);

            $redFlag->forceFill([
                'resolved_at' => now(),
            ])->save();

            $audit->record(
                action: 'red_flag.resolved',
                subject: $redFlag,
                before: $before,
                after: $redFlag->only(['resolved_at']),
                actor: $request->user() instanceof User ? $request->user() : null,
            );
        }

        return back()->with('status', 'red-flag-resolved');
    }
}
