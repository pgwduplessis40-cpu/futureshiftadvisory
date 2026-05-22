<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\RedFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class RedFlagUrgentNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly RedFlag $redFlag) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'analysis.red_flag.created';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $redFlag = $this->redFlag->loadMissing('client');
        $url = $redFlag->client instanceof Client
            ? route('advisor.clients.show', $redFlag->client)
            : route('dashboard');

        return (new MailMessage)
            ->subject('Urgent: AI red flag surfaced')
            ->line('A critical analysis finding has been promoted to a red flag.')
            ->line('Client: '.($redFlag->client?->legal_name ?? 'Unknown client'))
            ->line('Category: '.$this->label($redFlag->category))
            ->line('Headline: '.$redFlag->headline)
            ->line('Review, acknowledge, and resolve it from the advisor dashboard.')
            ->action('Open client', $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $redFlag = $this->redFlag->loadMissing('client');

        return [
            'title' => 'AI red flag surfaced',
            'message' => ($redFlag->client?->legal_name ?? 'Client').' has a critical '.$this->label($redFlag->category).' red flag.',
            'url' => $redFlag->client instanceof Client
                ? route('advisor.clients.show', $redFlag->client, absolute: false)
                : route('dashboard', absolute: false),
            'urgency' => $this->urgency(),
            'red_flag_id' => $redFlag->id,
            'client_id' => $redFlag->client_id,
            'client_name' => $redFlag->client?->legal_name,
            'analysis_finding_id' => $redFlag->analysis_finding_id,
            'category' => $redFlag->category,
            'severity' => $redFlag->severity,
            'headline' => $redFlag->headline,
            'surfaced_at' => $redFlag->surfaced_at?->toIso8601String(),
        ];
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
