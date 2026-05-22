<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\IndustryBriefing;
use App\Models\PreMeetingBrief;
use App\Models\User;
use App\Services\Reports\IndustryBriefingGenerator;
use App\Services\Reports\PreMeetingBriefGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BriefingController extends Controller
{
    public function reviewIndustry(Request $request, IndustryBriefing $industryBriefing, IndustryBriefingGenerator $briefings): RedirectResponse
    {
        $industryBriefing->loadMissing('client');
        Gate::authorize('view', $industryBriefing->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $briefings->reviewAndSend($industryBriefing, $user);

        return to_route('advisor.clients.show', $industryBriefing->client)->with('status', 'industry-briefing-sent');
    }

    public function reviewPreMeeting(Request $request, PreMeetingBrief $preMeetingBrief, PreMeetingBriefGenerator $briefs): RedirectResponse
    {
        $preMeetingBrief->loadMissing('client');
        Gate::authorize('view', $preMeetingBrief->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $briefs->reviewAndSend($preMeetingBrief, $user);

        return to_route('advisor.clients.show', $preMeetingBrief->client)->with('status', 'pre-meeting-brief-sent');
    }
}
