<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

final class NewMessageNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly Message $message) {}

    public function databaseType(): string
    {
        return 'message.new';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message->loadMissing(['sender', 'thread.client', 'thread.entrepreneurProfile']);
        $thread = $message->thread;
        $client = $thread?->client;
        $profile = $thread?->entrepreneurProfile;
        $mail = (new MailMessage)
            ->subject('New message: '.$thread?->subject)
            ->line('A new message is available in Future Shift Advisory.')
            ->line('Conversation: '.($client?->legal_name ?? $profile?->name ?? 'Portal conversation'))
            ->line('From: '.($message->sender?->name ?? 'Future Shift Advisory'))
            ->line(Str::limit($message->body, 220));

        $url = $this->urlFor($notifiable, absolute: true);
        if ($url !== null) {
            $mail->action('Open message', $url);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->message->loadMissing(['sender', 'thread.client', 'thread.entrepreneurProfile']);
        $thread = $message->thread;
        $client = $thread?->client;
        $profile = $thread?->entrepreneurProfile;

        return [
            'message_id' => $message->id,
            'thread_id' => $message->thread_id,
            'client_id' => $client?->id,
            'client_name' => $client?->legal_name,
            'entrepreneur_profile_id' => $profile?->id,
            'entrepreneur_name' => $profile?->name,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->name,
            'title' => 'New message',
            'message' => Str::limit($message->body, 180),
            'url' => $this->urlFor($notifiable, absolute: false),
        ];
    }

    private function urlFor(object $notifiable, bool $absolute): ?string
    {
        $message = $this->message->loadMissing(['thread.client', 'thread.entrepreneurProfile']);
        $thread = $message->thread;
        $client = $thread?->client;
        $profile = $thread?->entrepreneurProfile;

        if ($thread === null) {
            return null;
        }

        if ($client !== null) {
            if ($notifiable instanceof User && in_array($notifiable->user_type, [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADVISOR,
                User::TYPE_JUNIOR_ADVISOR,
            ], true)) {
                return route('advisor.clients.messages.show', [$client, $thread], absolute: $absolute);
            }

            if ($notifiable instanceof User && in_array($notifiable->user_type, [
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_CLIENT_TEAM,
            ], true)) {
                return route('portal.messages.show', $thread, absolute: $absolute);
            }
        }

        if ($profile !== null) {
            if ($notifiable instanceof User && in_array($notifiable->user_type, [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADVISOR,
                User::TYPE_JUNIOR_ADVISOR,
                User::TYPE_ENTREPRENEUR_MENTOR,
            ], true)) {
                return route('advisor.entrepreneurs.show', $profile, absolute: $absolute);
            }

            if ($notifiable instanceof User && $notifiable->user_type === User::TYPE_ENTREPRENEUR) {
                return route('portal.messages.show', $thread, absolute: $absolute);
            }
        }

        return null;
    }
}
