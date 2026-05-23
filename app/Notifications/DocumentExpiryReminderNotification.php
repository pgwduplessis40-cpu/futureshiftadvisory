<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentExpiryReminder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class DocumentExpiryReminderNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly Document $document,
        public readonly DocumentExpiryReminder $reminder,
    ) {}

    public function databaseType(): string
    {
        return 'document.expiry_reminder';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->document->loadMissing('client');
        $client = $document->client;

        $mail = (new MailMessage)
            ->subject('Document expiring soon')
            ->line('A client document is approaching its expiry date.')
            ->line('Client: '.($client?->legal_name ?? 'Unknown client'))
            ->line('Document: '.$document->original_filename)
            ->line('Expires: '.$document->expires_at?->toFormattedDateString());

        $url = $this->urlFor($notifiable, absolute: true);
        if ($url !== null) {
            $mail->action('Open client', $url);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $document = $this->document->loadMissing('client');

        return [
            'title' => 'Document expiring soon',
            'message' => $document->original_filename.' expires on '.$document->expires_at?->toDateString().'.',
            'url' => $this->urlFor($notifiable, absolute: false),
            'document_id' => $document->id,
            'document_name' => $document->original_filename,
            'document_category' => $document->category,
            'client_id' => $document->client_id,
            'client_name' => $document->client?->legal_name,
            'expires_at' => $document->expires_at?->toIso8601String(),
            'reminder_id' => $this->reminder->id,
        ];
    }

    private function urlFor(object $notifiable, bool $absolute): ?string
    {
        $document = $this->document->loadMissing('client');
        $client = $document->client;

        if ($client === null) {
            return null;
        }

        if ($notifiable instanceof User && in_array($notifiable->user_type, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
        ], true)) {
            return route('advisor.clients.show', $client, absolute: $absolute);
        }

        if ($notifiable instanceof User && in_array($notifiable->user_type, [
            User::TYPE_CLIENT_PRIMARY,
            User::TYPE_CLIENT_TEAM,
        ], true)) {
            return route('portal.dashboard', absolute: $absolute);
        }

        return null;
    }
}
