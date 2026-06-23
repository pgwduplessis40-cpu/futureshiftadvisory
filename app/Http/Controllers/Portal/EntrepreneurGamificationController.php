<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Messaging\MessageThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EntrepreneurGamificationController extends Controller
{
    private const DISABLE_REQUEST_SUBJECT = 'Gamification disable request';

    public function seen(Request $request, EntrepreneurMilestones $milestones): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $milestones->markSeen($profile);

        return back()->with('status', 'entrepreneur-gamification-seen');
    }

    public function requestDisable(
        Request $request,
        MessageThreadService $messages,
        AuditWriter $audit,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        if (! $profile->gamification_on) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-gamification-already-disabled');
        }

        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::DISABLE_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        if (! $thread instanceof MessageThread) {
            $message = $messages->startEntrepreneurThread(
                profile: $profile,
                sender: $user,
                subject: self::DISABLE_REQUEST_SUBJECT,
                body: 'I would like to request that gamification be disabled for my entrepreneur portal.',
            );
            $thread = $message->thread;
        }

        $audit->record('gamification.disable_requested', subject: $profile, actor: $user, after: [
            'message_thread_id' => $thread instanceof MessageThread ? $thread->getKey() : null,
        ]);

        return to_route('portal.entrepreneur.plan.show')
            ->with('status', 'entrepreneur-gamification-disable-requested');
    }
}
