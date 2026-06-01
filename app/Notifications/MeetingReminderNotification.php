<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class MeetingReminderNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly Meeting $meeting) {}

    public function databaseType(): string
    {
        return 'meeting.reminder';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing('client');

        return (new MailMessage)
            ->subject('Upcoming meeting')
            ->line($meeting->title)
            ->line('Client: '.($meeting->client?->legal_name ?? 'Client'))
            ->line('Scheduled: '.($meeting->scheduled_at?->toDayDateTimeString() ?? 'Scheduled soon'))
            ->action('Open calendar', route('advisor.calendar.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $meeting = $this->meeting->loadMissing('client');

        return [
            'title' => 'Upcoming meeting',
            'message' => $meeting->title,
            'url' => route('advisor.calendar.index', absolute: false),
            'meeting_id' => $meeting->id,
            'client_id' => $meeting->client_id,
            'client_name' => $meeting->client?->legal_name,
            'meeting_at' => $meeting->scheduled_at?->toIso8601String(),
        ];
    }
}
