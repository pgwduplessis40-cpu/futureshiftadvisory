<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EngagementType;
use App\Http\Controllers\Concerns\BuildsMessagePayloads;
use App\Http\Controllers\Controller;
use App\Models\Client;
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

final class ClientMessageController extends Controller
{
    use BuildsMessagePayloads;

    public function __construct(private readonly MessageThreadService $messages) {}

    public function index(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);
        $viewer = $this->viewer($request);
        $threads = $this->clientMessageThreads($client);
        $selectedThread = $threads->first();

        if ($selectedThread instanceof MessageThread) {
            $this->messages->markRead($selectedThread, $viewer);
        }

        return Inertia::render('advisor/clients/messages/Index', $this->pagePayload($client, $viewer, $threads, $selectedThread));
    }

    public function show(Request $request, Client $client, MessageThread $messageThread): Response
    {
        Gate::authorize('view', $client);
        $this->assertClientThread($client, $messageThread);
        $viewer = $this->viewer($request);
        $this->messages->markRead($messageThread, $viewer);

        return Inertia::render('advisor/clients/messages/Index', $this->pagePayload(
            client: $client,
            viewer: $viewer,
            threads: $this->clientMessageThreads($client),
            selectedThread: $messageThread,
        ));
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);
        $viewer = $this->viewer($request);
        $validated = $this->validatedMessage($request, requireSubject: true);

        $message = $this->messages->startClientThread(
            client: $client,
            sender: $viewer,
            subject: (string) $validated['subject'],
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('advisor.clients.messages.show', [$client, $message->thread])
            ->with('status', 'message-sent');
    }

    public function reply(Request $request, Client $client, MessageThread $messageThread): RedirectResponse
    {
        Gate::authorize('view', $client);
        $this->assertClientThread($client, $messageThread);
        $viewer = $this->viewer($request);
        $validated = $this->validatedMessage($request, requireSubject: false);

        $message = $this->messages->sendReply(
            thread: $messageThread->loadMissing('client'),
            sender: $viewer,
            body: (string) $validated['body'],
            attachments: $this->uploadedAttachments($request),
        );

        return to_route('advisor.clients.messages.show', [$client, $message->thread])
            ->with('status', 'message-sent');
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return array<string, mixed>
     */
    private function pagePayload(Client $client, User $viewer, $threads, ?MessageThread $selectedThread): array
    {
        return [
            'client' => $this->clientPayload($client),
            'threads' => $threads
                ->map(fn (MessageThread $thread): array => $this->messageThreadSummary(
                    thread: $thread,
                    viewer: $viewer,
                    url: route('advisor.clients.messages.show', [$client, $thread], absolute: false),
                ))
                ->values()
                ->all(),
            'selectedThread' => $selectedThread instanceof MessageThread
                ? $this->selectedMessageThread(
                    thread: $selectedThread,
                    viewer: $viewer,
                    replyUrl: route('advisor.clients.messages.reply', [$client, $selectedThread], absolute: false),
                )
                : null,
            'createUrl' => route('advisor.clients.messages.store', $client, absolute: false),
            'indexUrl' => route('advisor.clients.messages.index', $client, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type_label' => $engagementType->label(),
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
            'attachments.*' => ['file', 'max:20480'],
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

    private function assertClientThread(Client $client, MessageThread $thread): void
    {
        abort_unless((string) $thread->client_id === (string) $client->getKey(), 404);
    }
}
