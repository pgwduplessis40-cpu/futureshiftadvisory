<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PaymentAuthority;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class PaymentGatewayFailureNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly PaymentAuthority $authority,
        public readonly string $primaryGateway,
        public readonly string $secondaryGateway,
    ) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'payment.gateway.failure';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $authority = $this->authority->loadMissing('client');

        return (new MailMessage)
            ->subject('Payment gateway failure')
            ->line('Both payment gateways failed for a charge attempt.')
            ->line('Client: '.($authority->client?->legal_name ?? 'Unknown client'))
            ->line('Primary: '.$this->primaryGateway)
            ->line('Secondary: '.$this->secondaryGateway)
            ->action('Open client', $authority->client ? route('advisor.clients.show', $authority->client) : route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $authority = $this->authority->loadMissing('client');

        return [
            'title' => 'Payment gateway failure',
            'message' => 'Both payment gateways failed for '.($authority->client?->legal_name ?? 'a client').'.',
            'url' => $authority->client ? route('advisor.clients.show', $authority->client, absolute: false) : route('dashboard', absolute: false),
            'urgency' => 'urgent',
            'payment_authority_id' => $authority->id,
            'client_id' => $authority->client_id,
            'client_name' => $authority->client?->legal_name,
            'primary_gateway' => $this->primaryGateway,
            'secondary_gateway' => $this->secondaryGateway,
            'surfaced_at' => now()->toIso8601String(),
        ];
    }
}
