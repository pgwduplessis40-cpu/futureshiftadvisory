<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $rawAttachments = collect($message->attachments ?? []);
        $documents = Document::query()
            ->whereKey($rawAttachments
                ->map(fn (mixed $attachment): ?string => $this->attachmentDocumentId($attachment))
                ->filter()
                ->filter(fn (string $id): bool => Str::isUuid($id))
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy(fn (Document $document): string => (string) $document->getKey());

        $attachments = $rawAttachments
            ->map(fn (mixed $attachment): ?array => $this->attachmentPayload($attachment, $viewer, $documents))
            ->filter()
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
            'action_links' => $this->messageActionLinks($message, $viewer),
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];
    }

    /**
     * Action links are stored as semantic targets, never as supplied URLs.
     * Resolve the protected portal route only for the founder who owns the
     * thread, so an advisor's message view cannot expose a misleading link.
     *
     * @return list<array{key:string,title:string,description:string,open_url:string|null,guide_url:string|null}>
     */
    private function messageActionLinks(Message $message, User $viewer): array
    {
        $allowedFocuses = [
            'fixed-costs',
            'forecast-start-month',
            'revenue-forecast',
            'funding-sources',
            'budget-overview',
        ];
        $links = [];

        foreach ($message->action_links ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }

            $key = trim((string) ($link['key'] ?? ''));
            $title = trim((string) ($link['title'] ?? ''));
            $description = trim((string) ($link['description'] ?? ''));
            $requirement = (string) ($link['requirement'] ?? '');
            $focus = (string) ($link['focus'] ?? '');
            if ($key === '' || $title === '' || $description === ''
                || $requirement !== 'financial-assumptions'
                || ! in_array($focus, $allowedFocuses, true)) {
                continue;
            }

            $canOpenPlan = $viewer->user_type === User::TYPE_ENTREPRENEUR;
            $openUrl = $canOpenPlan
                ? route('portal.entrepreneur.plan.show', [
                    'requirement' => $requirement,
                    'focus' => $focus,
                ], absolute: false)
                : null;

            $links[] = [
                'key' => $key,
                'title' => $title,
                'description' => $description,
                'open_url' => $openUrl,
                'guide_url' => $canOpenPlan
                    ? route('portal.entrepreneur.plan.show', [
                        'requirement' => $requirement,
                        'focus' => $focus,
                        'guide' => '1',
                    ], absolute: false)
                    : null,
            ];
        }

        return $links;
    }

    /**
     * @param  Collection<string, Document>  $documents
     * @return array{document_id: string, type?: string, filename?: string|null, mime_type?: string|null, availability:string, availability_message:string, url?: string|null, download_url?: string|null}|null
     */
    private function attachmentPayload(mixed $attachment, User $viewer, Collection $documents): ?array
    {
        $id = $this->attachmentDocumentId($attachment);
        $type = null;

        if (is_array($attachment)) {
            $type = $attachment['type'] ?? null;
        }

        if ($id === null) {
            return null;
        }

        $document = $documents->get($id);
        $payload = ['document_id' => $id];

        if (is_scalar($type) && (string) $type !== '') {
            $payload['type'] = (string) $type;
        }

        if ($document instanceof Document) {
            $payload['filename'] = $document->original_filename;
            $payload['mime_type'] = $document->mime_type;
            $availability = $this->attachmentAvailability($document, $viewer);
            $payload['availability'] = $availability['status'];
            $payload['availability_message'] = $availability['message'];
            $payload['url'] = $availability['url'];
            $payload['download_url'] = $availability['url'] === null
                ? null
                : $availability['url'].'?download=1';
        } else {
            $payload['availability'] = 'unavailable';
            $payload['availability_message'] = 'This older attachment no longer has a downloadable file.';
        }

        return $payload;
    }

    /**
     * @return array{status: 'available'|'scanning'|'blocked'|'unavailable', message: string, url: string|null}
     */
    private function attachmentAvailability(Document $document, User $viewer): array
    {
        if ($document->scanner_result === Document::SCANNER_PENDING) {
            return [
                'status' => 'scanning',
                'message' => 'This attachment is being security checked. It will be available once the scan completes.',
                'url' => null,
            ];
        }

        if ($document->scanner_result === Document::SCANNER_INFECTED) {
            return [
                'status' => 'blocked',
                'message' => 'This attachment was blocked by the security scan.',
                'url' => null,
            ];
        }

        if ($document->scanner_result !== Document::SCANNER_CLEAN) {
            return [
                'status' => 'unavailable',
                'message' => 'This attachment cannot be opened until its security scan is resolved.',
                'url' => null,
            ];
        }

        $url = $this->attachmentUrl($document, $viewer);

        return $url === null
            ? [
                'status' => 'unavailable',
                'message' => 'This attachment is not available to your account.',
                'url' => null,
            ]
            : [
                'status' => 'available',
                'message' => 'Open or download this attachment.',
                'url' => $url,
            ];
    }

    private function attachmentDocumentId(mixed $attachment): ?string
    {
        $id = null;

        if (is_scalar($attachment)) {
            $id = $attachment;
        } elseif (is_array($attachment)) {
            $id = $attachment['document_id'] ?? $attachment['id'] ?? null;
        }

        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        return (string) $id;
    }

    private function attachmentUrl(Document $document, User $viewer): ?string
    {
        if ($document->entrepreneur_profile_id !== null) {
            if ($viewer->user_type === User::TYPE_ENTREPRENEUR) {
                return route('portal.documents.show', $document, absolute: false);
            }

            if (in_array($viewer->user_type, [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADVISOR,
                User::TYPE_JUNIOR_ADVISOR,
                User::TYPE_ENTREPRENEUR_MENTOR,
            ], true)) {
                return route('advisor.entrepreneurs.documents.show', [$document->entrepreneur_profile_id, $document], absolute: false);
            }
        }

        if ($document->client_id !== null) {
            if (in_array($viewer->user_type, [
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_CLIENT_TEAM,
            ], true)) {
                return route('portal.documents.show', $document, absolute: false);
            }

            if (in_array($viewer->user_type, [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADVISOR,
                User::TYPE_JUNIOR_ADVISOR,
            ], true)) {
                return route('advisor.clients.documents.show', [$document->client_id, $document], absolute: false);
            }
        }

        return null;
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
