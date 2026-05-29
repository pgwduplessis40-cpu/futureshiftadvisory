<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Concerns\BuildsMessagePayloads;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Messaging\MessageThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurMessageController extends Controller
{
    use BuildsMessagePayloads;

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
                ? $this->selectedMessageThread(
                    thread: $selectedThread,
                    viewer: $viewer,
                    replyUrl: route('advisor.entrepreneurs.messages.reply', [$profile, $selectedThread], absolute: false),
                )
                : null,
            'createUrl' => route('advisor.entrepreneurs.messages.store', $profile, absolute: false),
            'indexUrl' => route('advisor.entrepreneurs.messages.index', $profile, absolute: false),
        ];
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
