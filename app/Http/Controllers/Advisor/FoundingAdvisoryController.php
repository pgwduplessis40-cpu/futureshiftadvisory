<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\FoundingAdvisoryEngagement;
use App\Models\FoundingRoadmapVersion;
use App\Models\User;
use App\Services\Entrepreneurs\FoundingAdvisoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class FoundingAdvisoryController extends Controller
{
    public function replan(
        Request $request,
        FoundingAdvisoryEngagement $foundingAdvisoryEngagement,
        FoundingAdvisoryService $foundingAdvisory,
    ): RedirectResponse {
        $foundingAdvisoryEngagement->loadMissing('client');
        Gate::authorize('view', $foundingAdvisoryEngagement->client);

        $advisor = $request->user();
        abort_unless($advisor instanceof User, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'sales_pipeline' => ['nullable', 'string', 'max:2000'],
            'cash_funding' => ['nullable', 'string', 'max:2000'],
            'delivery_capacity' => ['nullable', 'string', 'max:2000'],
            'changed_assumptions' => ['nullable', 'string', 'max:2000'],
            'risks' => ['nullable', 'string', 'max:2000'],
            'advisor_decisions' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $foundingAdvisory->draftReplan($foundingAdvisoryEngagement, $validated, $advisor);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['founding_advisory' => $exception->getMessage()]);
        }

        return to_route('advisor.clients.show', $foundingAdvisoryEngagement->client)
            ->with('status', 'founding-roadmap-replan-generated');
    }

    public function publish(
        Request $request,
        FoundingAdvisoryEngagement $foundingAdvisoryEngagement,
        FoundingRoadmapVersion $foundingRoadmapVersion,
        FoundingAdvisoryService $foundingAdvisory,
    ): RedirectResponse {
        abort_unless(
            (string) $foundingRoadmapVersion->founding_advisory_engagement_id === (string) $foundingAdvisoryEngagement->getKey(),
            404,
        );
        $foundingAdvisoryEngagement->loadMissing('client');
        Gate::authorize('view', $foundingAdvisoryEngagement->client);

        $advisor = $request->user();
        abort_unless($advisor instanceof User, 403);

        try {
            $foundingAdvisory->publish($foundingRoadmapVersion, $advisor);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['founding_advisory' => $exception->getMessage()]);
        }

        return to_route('advisor.clients.show', $foundingAdvisoryEngagement->client)
            ->with('status', 'founding-roadmap-published');
    }
}
