<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OffboardingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class OffboardingCompletedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly OffboardingRecord $record) {}

    public function databaseType(): string
    {
        return 'offboarding.completed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $record = $this->record->loadMissing('client');

        return (new MailMessage)
            ->subject('Your Future Shift Advisory offboarding documents are ready')
            ->line('Your advisory engagement has been marked complete.')
            ->line('Client: '.($record->client?->legal_name ?? 'Unknown client'))
            ->line('Your advisor has prepared the Phase 1 offboarding record and will follow up if anything needs clarification.');
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
            'title' => 'Offboarding documents ready',
            'message' => 'Your advisory engagement has been marked complete and the Phase 1 offboarding documents are ready.',
            'url' => route('portal.dashboard', absolute: false),
            'triggered_at' => $record->triggered_at?->toIso8601String(),
        ];
    }
}
