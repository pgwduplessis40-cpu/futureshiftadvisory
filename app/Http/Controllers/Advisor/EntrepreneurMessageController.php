<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\BuildsMessagePayloads;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Messaging\MessageThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurMessageController extends Controller
{
    use BuildsMessagePayloads;

    private const GAMIFICATION_DISABLE_REQUEST_SUBJECT = 'Gamification disable request';

    public function __construct(private readonly MessageThreadService $messages) {}

    public function index(Request $request, EntrepreneurProfile $entrepreneurProfile): Response
    {
        Gate::authorize('view', $entrepreneurProfile);

        $viewer = $this->viewer($request);
        $threads = $this->entrepreneurMessageThreads($entrepreneurProfile);
        $selectedThread = $threads->first();

        if ($selectedThread instanceof MessageThread) {
            $this->messages->markRead($selectedThread, $viewer);
        }

        return Inertia::render(
            'advisor/entrepreneurs/messages/Index',
            $this->pagePayload($entrepreneurProfile, $viewer, $threads, $selectedThread),
        );
    }

    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile, MessageThread $messageThread): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertEntrepreneurThread($entrepreneurProfile, $messageThread);

        $viewer = $this->viewer($request);
        $this->messages->markRead($messageThread, $viewer);

        return Inertia::render(
            'advisor/entrepreneurs/messages/Index',
            $this->pagePayload(
                profile: $entrepreneurProfile,
                viewer: $viewer,
                threads: $this->entrepreneurMessageThreads($entrepreneurProfile),
                selectedThread: $messageThread,
            ),
        );
    }

    public function store(Request $request, EntrepreneurProfile $entrepreneurProfile): RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $viewer = $this->viewer($request);
        $validated = $this->validatedMessage($request, requireSubject: true);

        $message = $this->messages->startEntrepreneurThread(
            profile: $entrepreneurProfile,
            sender: $viewer,
            subject: (string) $validated['subject'],
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('advisor.entrepreneurs.messages.show', [$entrepreneurProfile, $message->thread])
            ->with('status', 'message-sent');
    }

    public function reply(Request $request, EntrepreneurProfile $entrepreneurProfile, MessageThread $messageThread): RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertEntrepreneurThread($entrepreneurProfile, $messageThread);

        $viewer = $this->viewer($request);
        $validated = $this->validatedMessage($request, requireSubject: false);

        $message = $this->messages->sendEntrepreneurReply(
            thread: $messageThread->loadMissing('entrepreneurProfile'),
            sender: $viewer,
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('advisor.entrepreneurs.messages.show', [$entrepreneurProfile, $message->thread])
            ->with('status', 'message-sent');
    }

    public function disableGamification(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        MessageThread $messageThread,
        AuditWriter $audit,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertEntrepreneurThread($entrepreneurProfile, $messageThread);
        abort_unless($messageThread->subject === self::GAMIFICATION_DISABLE_REQUEST_SUBJECT, 404);

        $advisor = $this->viewer($request);
        abort_unless($advisor->can(Permission::ENTREPRENEURS_ASSESS->value), 403);

        if (! $entrepreneurProfile->gamification_on) {
            return to_route('advisor.entrepreneurs.messages.show', [$entrepreneurProfile, $messageThread])
                ->with('status', 'entrepreneur-gamification-already-disabled');
        }

        DB::transaction(function () use ($entrepreneurProfile, $advisor, $audit, $messageThread): void {
            $before = (bool) $entrepreneurProfile->gamification_on;

            $entrepreneurProfile->forceFill([
                'gamification_on' => false,
                'current_streak' => 0,
                'last_active_at' => null,
            ])->save();

            $audit->record('gamification.disabled', subject: $entrepreneurProfile, actor: $advisor, before: [
                'gamification_on' => $before,
            ], after: [
                'gamification_on' => false,
                'entrepreneur_profile_id' => $entrepreneurProfile->getKey(),
                'message_thread_id' => $messageThread->getKey(),
                'source' => 'disable_request_thread',
            ]);
        });

        $this->messages->sendEntrepreneurReply(
            thread: $messageThread->loadMissing('entrepreneurProfile'),
            sender: $advisor,
            body: 'Gamification has been disabled for your entrepreneur portal.',
        );

        return to_route('advisor.entrepreneurs.messages.show', [$entrepreneurProfile, $messageThread])
            ->with('status', 'entrepreneur-gamification-disabled');
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return array<string, mixed>
     */
    private function pagePayload(EntrepreneurProfile $profile, User $viewer, Collection $threads, ?MessageThread $selectedThread): array
    {
        return [
            'client' => $this->entrepreneurPayload($profile),
            'threads' => $threads
                ->map(fn (MessageThread $thread): array => $this->messageThreadSummary(
                    thread: $thread,
                    viewer: $viewer,
                    url: route('advisor.entrepreneurs.messages.show', [$profile, $thread], absolute: false),
                ))
                ->values()
                ->all(),
            'selectedThread' => $selectedThread instanceof MessageThread
                ? $this->selectedThreadPayload($profile, $viewer, $selectedThread)
                : null,
            'createUrl' => route('advisor.entrepreneurs.messages.store', $profile, absolute: false),
            'indexUrl' => route('advisor.entrepreneurs.messages.index', $profile, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedThreadPayload(EntrepreneurProfile $profile, User $viewer, MessageThread $thread): array
    {
        return [
            ...$this->selectedMessageThread(
                thread: $thread,
                viewer: $viewer,
                replyUrl: route('advisor.entrepreneurs.messages.reply', [$profile, $thread], absolute: false),
            ),
            'actions' => $this->threadActions($profile, $viewer, $thread),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function threadActions(EntrepreneurProfile $profile, User $viewer, MessageThread $thread): array
    {
        if (
            $thread->subject !== self::GAMIFICATION_DISABLE_REQUEST_SUBJECT
            || ! $viewer->can(Permission::ENTREPRENEURS_ASSESS->value)
        ) {
            return [];
        }

        if (! $profile->gamification_on) {
            return [[
                'id' => 'gamification-disabled',
                'label' => 'Gamification disabled',
                'method' => 'patch',
                'url' => route('advisor.entrepreneurs.messages.gamification.disable', [$profile, $thread], absolute: false),
                'variant' => 'secondary',
                'disabled' => true,
            ]];
        }

        return [[
            'id' => 'disable-gamification',
            'label' => 'Disable gamification',
            'method' => 'patch',
            'url' => route('advisor.entrepreneurs.messages.gamification.disable', [$profile, $thread], absolute: false),
            'variant' => 'outline',
            'disabled' => false,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function entrepreneurPayload(EntrepreneurProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'legal_name' => $profile->name,
            'trading_name' => $profile->email,
            'engagement_type_label' => 'Entrepreneur portal',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMessage(Request $request, bool $requireSubject): array
    {
        return $request->validate([
            'subject' => [$requireSubject ? 'required' : 'nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:6000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx'],
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function assertEntrepreneurThread(EntrepreneurProfile $profile, MessageThread $thread): void
    {
        abort_unless((string) $thread->entrepreneur_profile_id === (string) $profile->getKey(), 404);
    }
}
