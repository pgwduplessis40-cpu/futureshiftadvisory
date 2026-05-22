<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PreMeetingBrief;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

final class PreMeetingBriefNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly PreMeetingBrief $brief) {}

    public function databaseType(): string
    {
        return 'pre_meeting_brief.sent';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brief = $this->brief->loadMissing(['client', 'meeting']);

        return (new MailMessage)
            ->subject('Reviewed pre-meeting brief')
            ->line('A reviewed pre-meeting brief is ready.')
            ->line('Client: '.($brief->client?->legal_name ?? 'Client'))
            ->line('Meeting: '.($brief->meeting?->title ?? 'Meeting'))
            ->line(Str::limit($brief->body, 220))
            ->action('Open client', $brief->client ? route('advisor.clients.show', $brief->client) : route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $brief = $this->brief->loadMissing(['client', 'meeting']);

        return [
            'title' => 'Reviewed pre-meeting brief',
            'message' => Str::limit($brief->body, 180),
            'url' => $brief->client ? route('advisor.clients.show', $brief->client, absolute: false) : route('dashboard', absolute: false),
            'pre_meeting_brief_id' => $brief->id,
            'meeting_id' => $brief->meeting_id,
            'client_id' => $brief->client_id,
            'client_name' => $brief->client?->legal_name,
            'meeting_at' => $brief->meeting_at?->toIso8601String(),
        ];
    }
}
