<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\MessageThread;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Messaging\MessageThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EntrepreneurAdvisoryRequestController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly AdvisoryReadiness $advisoryReadiness,
        private readonly MessageThreadService $messages,
        private readonly AuditWriter $audit,
    ) {}

    public function requestAdvisory(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Advisory conversion is available after a business plan and budget package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        $signal = $this->advisoryReadiness->currentSignalForPlan($plan);

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-not-ready');
        }

        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', EntrepreneurPlanWorkspace::ADVISORY_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        if (! $thread instanceof MessageThread) {
            $message = $this->messages->startEntrepreneurThread(
                profile: $profile,
                sender: $user,
                subject: EntrepreneurPlanWorkspace::ADVISORY_REQUEST_SUBJECT,
                body: sprintf(
                    'I would like to request standard advisory support using my entrepreneur business plan%s.',
                    $plan instanceof BusinessPlan ? ' ('.$plan->title.')' : '',
                ),
            );
            $thread = $message->thread;
        }

        $this->audit->record('entrepreneur.advisory_requested', subject: $profile, actor: $user, after: [
            'business_plan_id' => $plan?->getKey(),
            'advisory_readiness_signal_id' => $signal->getKey(),
            'message_thread_id' => $thread?->getKey(),
        ]);

        return $thread instanceof MessageThread
            ? to_route('portal.messages.show', $thread)->with('status', 'entrepreneur-advisory-requested')
            : to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-requested');
    }
}
