<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class WellbeingCheckinPromptNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly Client $client,
        public readonly string $periodStart,
    ) {}

    public function databaseType(): string
    {
        return 'wellbeing.checkin.prompt';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Optional monthly wellbeing check-in')
            ->line('Your monthly wellbeing pulse is available in the Future Shift Advisory portal.')
            ->line('This check-in is optional and helps your advisor understand how the engagement is feeling.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'client_id' => $this->client->id,
            'client_name' => $this->client->legal_name,
            'period_start' => $this->periodStart,
            'title' => 'Optional monthly wellbeing check-in',
            'message' => 'A short wellbeing pulse is available in your portal.',
            'url' => route('portal.wellbeing.show', absolute: false),
        ];
    }
}
