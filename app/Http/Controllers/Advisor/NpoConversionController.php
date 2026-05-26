<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\GovernanceReviewConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class NpoConversionController extends Controller
{
    public function reportDelivered(Request $request, NpoEngagement $npoEngagement, GovernanceReviewConversion $conversion): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('update', $npoEngagement->client);

        $validated = $request->validate([
            'report_delivered_at' => ['nullable', 'date'],
        ]);

        $conversion->markReportDelivered(
            $npoEngagement,
            $actor,
            isset($validated['report_delivered_at']) ? Carbon::parse($validated['report_delivered_at']) : null,
        );

        return back()->with('status', 'npo-governance-review-report-delivered');
    }

    public function decline(Request $request, NpoEngagement $npoEngagement, GovernanceReviewConversion $conversion): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('update', $npoEngagement->client);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $conversion->decline($npoEngagement, $actor, $validated['reason']);

        return back()->with('status', 'npo-governance-review-conversion-declined');
    }

    public function convert(Request $request, NpoEngagement $npoEngagement, GovernanceReviewConversion $conversion): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('update', $npoEngagement->client);

        $converted = $conversion->convert($npoEngagement, $actor);

        return to_route('advisor.clients.show', $converted->client_id)
            ->with('status', 'npo-governance-review-converted');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
