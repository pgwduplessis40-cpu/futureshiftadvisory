<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceActivation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class ServiceActivationRequestedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly ServiceActivation $activation) {}

    public function databaseType(): string
    {
        return 'service_activation.requested';
    }

    public function urgency(): string
    {
        return 'urgent';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activation = $this->activation->loadMissing('client', 'requestedBy');

        return (new MailMessage)
            ->subject('Client requested a new service workspace')
            ->line(($activation->client?->legal_name ?? 'A client').' requested: '.$activation->clientLabel().'.')
            ->line('Requested by: '.($activation->requestedBy?->name ?? 'Client portal user'))
            ->line('Select the active Admin Service Rate package before the client can accept the workspace fee.')
            ->action('Review request', route('advisor.service-activations.show', $activation, absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $activation = $this->activation->loadMissing('client', 'requestedBy');

        return [
            'service_activation_id' => $activation->id,
            'client_id' => $activation->client_id,
            'client_name' => $activation->client?->legal_name,
            'service_type' => $activation->service_type,
            'requested_by_user_id' => $activation->requested_by_user_id,
            'title' => 'Service workspace requested',
            'message' => ($activation->client?->legal_name ?? 'A client').' requested '.$activation->clientLabel().'. Select the package/scope/pricing from Admin Service Rates.',
            'url' => route('advisor.service-activations.show', $activation, absolute: false),
        ];
    }
}
