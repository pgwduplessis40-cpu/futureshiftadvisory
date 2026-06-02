<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class MessageInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $this->viewer($request);
        $threads = $this->visibleThreads($viewer);
        $latestMessages = $this->latestMessages($threads);
        $unreadCounts = $this->unreadCounts($threads, $viewer);
        $threadPayloads = $threads
            ->map(fn (MessageThread $thread): ?array => $this->threadPayload($thread, $latestMessages, $unreadCounts))
            ->filter()
            ->values();

        return Inertia::render('advisor/messages/Index', [
            'threads' => $threadPayloads->all(),
            'counts' => [
                'all' => $threadPayloads->count(),
                'client' => $threadPayloads->where('kind', 'client')->count(),
                'entrepreneur' => $threadPayloads->where('kind', 'entrepreneur')->count(),
            ],
        ]);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return Collection<int, MessageThread>
     */
    private function visibleThreads(User $viewer): Collection
    {
        return $this->clientThreads($viewer)
            ->merge($this->entrepreneurThreads($viewer))
            ->sortByDesc(fn (MessageThread $thread): int => $thread->last_activity_at?->getTimestamp() ?? $thread->created_at?->getTimestamp() ?? 0)
            ->values()
            ->take(100);
    }

    /**
     * @return Collection<int, MessageThread>
     */
    private function clientThreads(User $viewer): Collection
    {
        if (! $viewer->can(Permission::CLIENTS_VIEW->value)) {
            return collect();
        }

        $query = MessageThread::query()
            ->whereNotNull('client_id')
            ->with('client')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at');

        if ($viewer->fsaRole() !== User::TYPE_SUPER_ADMIN) {
            $clientIds = $viewer->accessibleClientIds();
            if ($clientIds === []) {
                return collect();
            }

            $query->whereIn('client_id', $clientIds);
        }

        return $query->limit(75)->get();
    }

    /**
     * @return Collection<int, MessageThread>
     */
    private function entrepreneurThreads(User $viewer): Collection
    {
        if (! $viewer->can(Permission::ENTREPRENEURS_VIEW->value)) {
            return collect();
        }

        $query = MessageThread::query()
            ->whereNotNull('entrepreneur_profile_id')
            ->with('entrepreneurProfile')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at');

        if ($viewer->fsaRole() !== User::TYPE_SUPER_ADMIN) {
            $query->whereHas(
                'entrepreneurProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('assigned_advisor_id', $viewer->getKey()),
            );
        }

        return $query->limit(75)->get();
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return Collection<string, Message>
     */
    private function latestMessages(Collection $threads): Collection
    {
        $threadIds = $threads->pluck('id')->all();
        if ($threadIds === []) {
            return collect();
        }

        return Message::query()
            ->whereIn('thread_id', $threadIds)
            ->with('sender')
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('thread_id')
            ->keyBy('thread_id');
    }

    /**
     * @param  Collection<int, MessageThread>  $threads
     * @return array<string, int>
     */
    private function unreadCounts(Collection $threads, User $viewer): array
    {
        $threadIds = $threads->pluck('id')->all();
        if ($threadIds === []) {
            return [];
        }

        $participants = MessageThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', $viewer->getKey())
            ->get()
            ->keyBy('thread_id');

        return $threads
            ->mapWithKeys(fn (MessageThread $thread): array => [
                (string) $thread->getKey() => $this->unreadCount(
                    thread: $thread,
                    viewer: $viewer,
                    participant: $participants->get((string) $thread->getKey()),
                ),
            ])
            ->all();
    }

    private function unreadCount(MessageThread $thread, User $viewer, ?MessageThreadParticipant $participant): int
    {
        if (! $participant instanceof MessageThreadParticipant) {
            return 0;
        }

        $query = Message::query()
            ->where('thread_id', $thread->getKey())
            ->where('sender_user_id', '!=', $viewer->getKey());

        if ($participant->last_read_at !== null) {
            $query->where('sent_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }

    /**
     * @param  Collection<string, Message>  $latestMessages
     * @param  array<string, int>  $unreadCounts
     * @return array<string, mixed>|null
     */
    private function threadPayload(MessageThread $thread, Collection $latestMessages, array $unreadCounts): ?array
    {
        $latestMessage = $latestMessages->get((string) $thread->getKey());
        $activityAt = $thread->last_activity_at ?? $latestMessage?->sent_at ?? $thread->created_at;

        if ($thread->client_id !== null && $thread->client !== null) {
            return [
                'id' => $thread->id,
                'kind' => 'client',
                'kind_label' => 'Client',
                'subject' => $thread->subject,
                'context_name' => $thread->client->legal_name,
                'context_detail' => $thread->client->trading_name,
                'latest_sender_name' => $latestMessage?->sender?->name,
                'latest_excerpt' => $latestMessage instanceof Message ? Str::limit($latestMessage->body, 180) : null,
                'last_activity_at' => $activityAt?->toIso8601String(),
                'messages_count' => (int) ($thread->messages_count ?? 0),
                'unread_count' => $unreadCounts[(string) $thread->getKey()] ?? 0,
                'url' => route('advisor.clients.messages.show', [$thread->client, $thread], absolute: false),
            ];
        }

        $profile = $thread->entrepreneurProfile;
        if ($thread->entrepreneur_profile_id !== null && $profile instanceof EntrepreneurProfile) {
            return [
                'id' => $thread->id,
                'kind' => 'entrepreneur',
                'kind_label' => 'Entrepreneur',
                'subject' => $thread->subject,
                'context_name' => $profile->name,
                'context_detail' => $profile->email,
                'latest_sender_name' => $latestMessage?->sender?->name,
                'latest_excerpt' => $latestMessage instanceof Message ? Str::limit($latestMessage->body, 180) : null,
                'last_activity_at' => $activityAt?->toIso8601String(),
                'messages_count' => (int) ($thread->messages_count ?? 0),
                'unread_count' => $unreadCounts[(string) $thread->getKey()] ?? 0,
                'url' => route('advisor.entrepreneurs.messages.show', [$profile, $thread], absolute: false),
            ];
        }

        return null;
    }
}
