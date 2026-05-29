<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\NpoEngagementSubType;
use App\Http\Controllers\Controller;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\GovernanceReviewAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class NpoGovernanceReviewController extends Controller
{
    public function run(Request $request, NpoEngagement $npoEngagement, GovernanceReviewAnalyzer $analyzer): RedirectResponse
    {
        $npoEngagement->loadMissing('client');
        Gate::authorize('view', $npoEngagement->client);
        abort_unless($npoEngagement->sub_type === NpoEngagementSubType::GovernanceReview, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $analyzer->run($npoEngagement, $user);

        return back()->with('status', 'npo-governance-review-analysis-generated');
    }

    public function review(
        Request $request,
        GovernanceReviewFinding $governanceReviewFinding,
        GovernanceReviewAnalyzer $analyzer,
    ): RedirectResponse {
        $governanceReviewFinding->loadMissing('client');
        Gate::authorize('view', $governanceReviewFinding->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'advisor_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $analyzer->review($governanceReviewFinding, $user, $validated['advisor_notes'] ?? null);

        return back()->with('status', 'npo-governance-review-finding-reviewed');
    }
}
