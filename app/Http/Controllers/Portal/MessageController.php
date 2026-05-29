<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\BuildsMessagePayloads;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Messaging\MessageThreadService;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class MessageController extends Controller
{
    use BuildsMessagePayloads;

    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly MessageThreadService $messages,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $this->viewer($request);
        $profile = $this->entrepreneurProfileFor($viewer);
        if ($profile instanceof EntrepreneurProfile) {
            $threads = $this->entrepreneurMessageThreads($profile);
            $selectedThread = $threads->first();

            if ($selectedThread instanceof MessageThread) {
                $this->messages->markRead($selectedThread, $viewer);
            }

            return Inertia::render('portal/messages/Index', $this->entrepreneurPagePayload($profile, $viewer, $threads, $selectedThread));
        }

        $client = $this->clients->resolveFor($request);
        $threads = $this->clientMessageThreads($client);
        $selectedThread = $threads->first();

        if ($selectedThread instanceof MessageThread) {
            $this->messages->markRead($selectedThread, $viewer);
        }

        return Inertia::render('portal/messages/Index', $this->pagePayload($client, $viewer, $threads, $selectedThread));
    }

    public function show(Request $request, MessageThread $messageThread): Response
    {
        $viewer = $this->viewer($request);
        $profile = $this->entrepreneurProfileFor($viewer);
        if ($profile instanceof EntrepreneurProfile) {
            $this->assertEntrepreneurThread($profile, $messageThread);
            $this->messages->markRead($messageThread, $viewer);

            return Inertia::render('portal/messages/Index', $this->entrepreneurPagePayload(
                profile: $profile,
                viewer: $viewer,
                threads: $this->entrepreneurMessageThreads($profile),
                selectedThread: $messageThread,
            ));
        }

        $client = $this->clients->resolveFor($request);
        $this->assertClientThread($client, $messageThread);
        $this->messages->markRead($messageThread, $viewer);

        return Inertia::render('portal/messages/Index', $this->pagePayload(
            client: $client,
            viewer: $viewer,
            threads: $this->clientMessageThreads($client),
            selectedThread: $messageThread,
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = $this->viewer($request);
        $profile = $this->entrepreneurProfileFor($viewer);
        if ($profile instanceof EntrepreneurProfile) {
            $validated = $this->validatedMessage($request, requireSubject: true);

            $message = $this->messages->startEntrepreneurThread(
                profile: $profile,
                sender: $viewer,
                subject: (string) $validated['subject'],
                body: (string) $validated['body'],
                attachments: $this->uploadedAttachments($request),
            );

            return to_route('portal.messages.show', $message->thread)
                ->with('status', 'message-sent');
        }

        $client = $this->clients->resolveFor($request);
        $validated = $this->validatedMessage($request, requireSubject: true);

        $message = $this->messages->startClientThread(
            client: $client,
            sender: $viewer,
            subject: (string) $validated['subject'],
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('portal.messages.show', $message->thread)
            ->with('status', 'message-sent');
    }

    public function reply(Request $request, MessageThread $messageThread): RedirectResponse
    {
        $viewer = $this->viewer($request);
        $profile = $this->entrepreneurProfileFor($viewer);
        if ($profile instanceof EntrepreneurProfile) {
            $this->assertEntrepreneurThread($profile, $messageThread);
            $validated = $this->validatedMessage($request, requireSubject: false);

            $message = $this->messages->sendEntrepreneurReply(
                thread: $messageThread->loadMissing('entrepreneurProfile'),
                sender: $viewer,
                body: (string) $validated['body'],
                attachments: $this->uploadedAttachments($request),
            );

            return to_route('portal.messages.show', $message->thread)
                ->with('status', 'message-sent');
        }

        $client = $this->clients->resolveFor($request);
        $this->assertClientThread($client, $messageThread);
        $validated = $this->validatedMessage($request, requireSubject: false);

        $message = $this->messages->sendReply(
            thread: $messageThread->loadMissing('client'),
            sender: $viewer,
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('portal.messages.show', $message->thread)
            ->with('status', 'message-sent');
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return array<string, mixed>
     */
    private function pagePayload(Client $client, User $viewer, $threads, ?MessageThread $selectedThread): array
    {
        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
            ],
            'threads' => $threads
                ->map(fn (MessageThread $thread): array => $this->messageThreadSummary(
                    thread: $thread,
                    viewer: $viewer,
                    url: route('portal.messages.show', $thread, absolute: false),
                ))
                ->values()
                ->all(),
            'selectedThread' => $selectedThread instanceof MessageThread
                ? $this->selectedMessageThread(
                    thread: $selectedThread,
                    viewer: $viewer,
                    replyUrl: route('portal.messages.reply', $selectedThread, absolute: false),
                )
                : null,
            'createUrl' => route('portal.messages.store', absolute: false),
            'indexUrl' => route('portal.messages.index', absolute: false),
            'backHref' => route('portal.dashboard', absolute: false),
            'backLabel' => 'Dashboard',
        ];
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return array<string, mixed>
     */
    private function entrepreneurPagePayload(EntrepreneurProfile $profile, User $viewer, $threads, ?MessageThread $selectedThread): array
    {
        return [
            'client' => [
                'id' => $profile->id,
                'legal_name' => $profile->name,
                'trading_name' => null,
                'engagement_type_label' => 'Entrepreneur portal',
            ],
            'threads' => $threads
                ->map(fn (MessageThread $thread): array => $this->messageThreadSummary(
                    thread: $thread,
                    viewer: $viewer,
                    url: route('portal.messages.show', $thread, absolute: false),
                ))
                ->values()
                ->all(),
            'selectedThread' => $selectedThread instanceof MessageThread
                ? $this->selectedMessageThread(
                    thread: $selectedThread,
                    viewer: $viewer,
                    replyUrl: route('portal.messages.reply', $selectedThread, absolute: false),
                )
                : null,
            'createUrl' => route('portal.messages.store', absolute: false),
            'indexUrl' => route('portal.messages.index', absolute: false),
            'backHref' => route('portal.entrepreneur.dashboard', absolute: false),
            'backLabel' => 'Dashboard',
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

    private function entrepreneurProfileFor(User $user): ?EntrepreneurProfile
    {
        if ($user->user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    private function assertClientThread(Client $client, MessageThread $thread): void
    {
        abort_unless((string) $thread->client_id === (string) $client->getKey(), 404);
    }

    private function assertEntrepreneurThread(EntrepreneurProfile $profile, MessageThread $thread): void
    {
        abort_unless((string) $thread->entrepreneur_profile_id === (string) $profile->getKey(), 404);
    }
}
