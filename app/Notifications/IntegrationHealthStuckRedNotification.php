<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IntegrationHealthAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class IntegrationHealthStuckRedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly IntegrationHealthAlert $alert) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'integration.health.stuck_red';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Urgent: integration health red for 30+ minutes')
            ->line('An integration has remained in red health for more than 30 minutes.')
            ->line('Service: '.$this->alert->service)
            ->line('Stuck since: '.$this->alert->stuck_started_at?->toIso8601String())
            ->action('Open API health dashboard', route('admin.integration-health.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Integration stuck red',
            'message' => "{$this->alert->service} has been red for more than 30 minutes.",
            'url' => route('admin.integration-health.index', absolute: false),
            'integration_health_alert_id' => $this->alert->id,
            'service' => $this->alert->service,
            'stuck_started_at' => $this->alert->stuck_started_at?->toIso8601String(),
            'last_red_window_end' => $this->alert->last_red_window_end?->toIso8601String(),
        ];
    }
}
