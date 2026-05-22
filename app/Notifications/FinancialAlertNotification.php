<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\FinancialAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class FinancialAlertNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly FinancialAlert $alert) {}

    public function databaseType(): string
    {
        return 'financial.alert.created';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alert = $this->alert->loadMissing('client');
        $client = $alert->client;

        return (new MailMessage)
            ->subject('Financial health warning')
            ->line('A financial-health monitor detected a material movement in connected accounting data.')
            ->line('Client: '.($client?->legal_name ?? 'Unknown client'))
            ->line('Metric: '.$this->label($alert->metric))
            ->line('Detail: '.$alert->detail)
            ->action('Open client', $client instanceof Client ? route('advisor.clients.show', $client) : route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $alert = $this->alert->loadMissing('client');

        return [
            'title' => 'Financial health warning',
            'message' => ($alert->client?->legal_name ?? 'Client').' has a '.$this->label($alert->metric).' warning.',
            'url' => $alert->client instanceof Client
                ? route('advisor.clients.show', $alert->client, absolute: false)
                : route('dashboard', absolute: false),
            'urgency' => $this->urgency(),
            'financial_alert_id' => $alert->id,
            'client_id' => $alert->client_id,
            'client_name' => $alert->client?->legal_name,
            'category' => $alert->category,
            'severity' => $alert->severity,
            'metric' => $alert->metric,
            'headline' => $alert->headline,
            'detail' => $alert->detail,
            'citation' => $alert->citation,
            'surfaced_at' => $alert->surfaced_at?->toIso8601String(),
        ];
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
