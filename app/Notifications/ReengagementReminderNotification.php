<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OffboardingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class ReengagementReminderNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly OffboardingRecord $record) {}

    public function databaseType(): string
    {
        return 'offboarding.reengagement_due';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $record = $this->record->loadMissing('client');

        return (new MailMessage)
            ->subject('Re-engagement reminder due')
            ->line('A 90-day re-engagement reminder is due for an offboarded client.')
            ->line('Client: '.($record->client?->legal_name ?? 'Unknown client'))
            ->line('Review the client profile and decide whether outreach is appropriate.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $record = $this->record->loadMissing('client');

        return [
            'offboarding_record_id' => $record->id,
            'client_id' => $record->client_id,
            'client_name' => $record->client?->legal_name,
            'title' => 'Re-engagement reminder due',
            'message' => 'The post-offboarding re-engagement reminder is due.',
            'url' => $record->client
                ? route('advisor.clients.show', $record->client, absolute: false)
                : route('advisor.clients.index', absolute: false),
            'reengagement_due' => $record->reengagement_due?->toIso8601String(),
        ];
    }
}
