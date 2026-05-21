<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Jobs\VerifyDocumentJob;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class MessageThreadService
{
    public function __construct(
        private readonly SecureFileWriter $writer,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function startClientThread(
        Client $client,
        User $sender,
        string $subject,
        string $body,
        array $attachments = [],
    ): Message {
        $message = DB::transaction(function () use ($client, $sender, $subject, $body, $attachments): Message {
            $thread = MessageThread::query()->create([
                'client_id' => $client->getKey(),
                'created_by_user_id' => $sender->getKey(),
                'subject' => trim($subject),
                'last_activity_at' => now(),
            ]);

            $this->syncClientParticipants($thread, $client, $sender);
            $message = $this->persistMessage($thread, $client, $sender, $body, $attachments);

            $this->auditWriter->record('message_thread.created', subject: $thread, actor: $sender, after: [
                'thread_id' => $thread->id,
                'client_id' => $client->id,
                'subject' => $thread->subject,
            ]);

            return $message;
        });

        $this->notifyRecipients($message);

        return $message->refresh()->loadMissing(['thread.client', 'sender']);
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function sendReply(
        MessageThread $thread,
        User $sender,
        string $body,
        array $attachments = [],
    ): Message {
        $client = $thread->client;
        if (! $client instanceof Client) {
            throw new LogicException('Client message threads must have a client.');
        }

        $message = DB::transaction(function () use ($thread, $client, $sender, $body, $attachments): Message {
            $this->syncClientParticipants($thread, $client, $sender);

            return $this->persistMessage($thread, $client, $sender, $body, $attachments);
        });

        $this->notifyRecipients($message);

        return $message->refresh()->loadMissing(['thread.client', 'sender']);
    }

    public function markRead(MessageThread $thread, User $user): void
    {
        MessageThreadParticipant::query()
            ->where('thread_id', $thread->getKey())
            ->where('user_id', $user->getKey())
            ->update(['last_read_at' => now()]);
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    private function persistMessage(
        MessageThread $thread,
        Client $client,
        User $sender,
        string $body,
        array $attachments,
    ): Message {
        $sentAt = now();
        $attachmentIds = $this->storeAttachments($client, $sender, $thread, $body, $attachments);

        $message = Message::query()->create([
            'thread_id' => $thread->getKey(),
            'sender_user_id' => $sender->getKey(),
            'body' => trim($body),
            'attachments' => $attachmentIds === [] ? null : $attachmentIds,
            'sent_at' => $sentAt,
        ]);

        $thread->forceFill(['last_activity_at' => $sentAt])->save();

        MessageThreadParticipant::query()->updateOrCreate(
            [
                'thread_id' => $thread->getKey(),
                'user_id' => $sender->getKey(),
            ],
            ['last_read_at' => $sentAt],
        );

        $this->auditWriter->record('message.sent', subject: $thread, actor: $sender, after: [
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'client_id' => $client->id,
            'attachment_document_ids' => $attachmentIds,
        ]);

        return $message;
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     * @return array<int, string>
     */
    private function storeAttachments(
        Client $client,
        User $sender,
        MessageThread $thread,
        string $body,
        array $attachments,
    ): array {
        $documentIds = [];

        foreach ($attachments as $attachment) {
            if (! $attachment instanceof UploadedFile) {
                continue;
            }

            $document = $this->writer->write(
                uploadedFile: $attachment,
                owner: $sender,
                category: Document::CATEGORY_MESSAGE_ATTACHMENT,
                clientId: (string) $client->getKey(),
            );

            VerifyDocumentJob::dispatch((string) $document->getKey(), [
                'claims' => [[
                    'source' => 'message_attachment',
                    'claim' => $body === '' ? 'Message attachment' : $body,
                    'question_prompt' => 'Attachment on message thread: '.$thread->subject,
                ]],
            ]);

            $documentIds[] = (string) $document->getKey();
        }

        return $documentIds;
    }

    private function syncClientParticipants(MessageThread $thread, Client $client, User $sender): void
    {
        $this->clientParticipants($client, $sender)
            ->each(function (User $user) use ($thread): void {
                MessageThreadParticipant::query()->firstOrCreate([
                    'thread_id' => $thread->getKey(),
                    'user_id' => $user->getKey(),
                ]);
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function clientParticipants(Client $client, User $sender): Collection
    {
        $userIds = ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($client->primary_contact_user_id !== null) {
            $userIds[] = (int) $client->primary_contact_user_id;
        }

        $userIds[] = (int) $sender->getKey();

        return User::query()
            ->whereKey(array_values(array_unique($userIds)))
            ->get();
    }

    private function notifyRecipients(Message $message): void
    {
        $message = $message->loadMissing(['thread.participants.user', 'thread.client', 'sender']);
        $thread = $message->thread;

        if (! $thread instanceof MessageThread) {
            return;
        }

        $recipients = $thread->participants
            ->map(fn (MessageThreadParticipant $participant): ?User => $participant->user)
            ->filter(fn (?User $user): bool => $user instanceof User && (string) $user->getKey() !== (string) $message->sender_user_id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NewMessageNotification($message));
    }
}
