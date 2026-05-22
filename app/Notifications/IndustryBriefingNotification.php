<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IndustryBriefing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

final class IndustryBriefingNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly IndustryBriefing $briefing) {}

    public function databaseType(): string
    {
        return 'industry_briefing.sent';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $briefing = $this->briefing->loadMissing('client');

        return (new MailMessage)
            ->subject('Monthly industry briefing')
            ->line('Your advisor has reviewed the latest industry briefing.')
            ->line('Client: '.($briefing->client?->legal_name ?? 'Client'))
            ->line(Str::limit($briefing->body, 220))
            ->action('Open portal', route('portal.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $briefing = $this->briefing->loadMissing('client');

        return [
            'title' => 'Monthly industry briefing',
            'message' => Str::limit($briefing->body, 180),
            'url' => route('portal.dashboard', absolute: false),
            'industry_briefing_id' => $briefing->id,
            'client_id' => $briefing->client_id,
            'client_name' => $briefing->client?->legal_name,
            'period' => $briefing->period?->toDateString(),
        ];
    }
}
