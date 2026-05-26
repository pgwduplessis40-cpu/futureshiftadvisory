<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

trait BuildsMessagePayloads
{
    /**
     * @return Collection<int, MessageThread>
     */
    private function clientMessageThreads(Client $client): Collection
    {
        return MessageThread::query()
            ->where('client_id', $client->getKey())
            ->with('participants')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, MessageThread>
     */
    private function entrepreneurMessageThreads(EntrepreneurProfile $profile): Collection
    {
        return MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->with('participants')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageThreadSummary(MessageThread $thread, User $viewer, string $url): array
    {
        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'last_activity_at' => $thread->last_activity_at?->toIso8601String(),
            'messages_count' => (int) ($thread->messages_count ?? 0),
            'unread_count' => $this->unreadCount($thread, $viewer),
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedMessageThread(MessageThread $thread, User $viewer, string $replyUrl): array
    {
        $thread->loadMissing(['messages.sender', 'participants.user']);

        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'last_activity_at' => $thread->last_activity_at?->toIso8601String(),
            'reply_url' => $replyUrl,
            'participants' => $thread->participants
                ->map(fn (MessageThreadParticipant $participant): ?array => $participant->user instanceof User
                    ? [
                        'id' => $participant->user->id,
                        'name' => $participant->user->name,
                        'user_type' => $participant->user->user_type,
                    ]
                    : null)
                ->filter()
                ->values()
                ->all(),
            'messages' => $thread->messages
                ->sortBy('sent_at')
                ->map(fn (Message $message): array => $this->messagePayload($message, $viewer))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(Message $message, User $viewer): array
    {
        $attachments = collect($message->attachments ?? [])
            ->map(fn (mixed $documentId): array => ['document_id' => (string) $documentId])
            ->values()
            ->all();

        return [
            'id' => $message->id,
            'body' => $message->body,
            'channel' => $message->channel ?? Message::CHANNEL_IN_APP,
            'delivery_state' => $message->delivery_state ?? Message::DELIVERY_SENT,
            'email_subject' => $message->email_subject,
            'email_recipients' => $message->email_recipients ?? [],
            'channel_decision' => $message->channel_decision ?? null,
            'sender_name' => $message->sender?->name ?? 'Future Shift Advisory',
            'sender_user_id' => $message->sender_user_id,
            'sender_user_type' => $message->sender?->user_type,
            'mine' => (string) $message->sender_user_id === (string) $viewer->getKey(),
            'attachments' => $attachments,
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];
    }

    private function unreadCount(MessageThread $thread, User $viewer): int
    {
        $participant = MessageThreadParticipant::query()
            ->where('thread_id', $thread->getKey())
            ->where('user_id', $viewer->getKey())
            ->first();

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
}
