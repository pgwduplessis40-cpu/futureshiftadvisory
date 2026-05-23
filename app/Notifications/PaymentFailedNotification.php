<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class PaymentFailedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'payment.failed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing('client');
        $client = $payment->client;

        return (new MailMessage)
            ->subject('Payment failed')
            ->line('A scheduled payment attempt failed.')
            ->line('Client: '.($client?->legal_name ?? 'Unknown client'))
            ->line('Amount: '.$payment->currency.' '.$payment->amount)
            ->line('Attempt: '.$payment->attempt)
            ->action('Open dashboard', $notifiable instanceof User && $notifiable->user_type === User::TYPE_CLIENT_PRIMARY
                ? route('portal.dashboard')
                : ($client ? route('advisor.clients.show', $client) : route('dashboard')));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $payment = $this->payment->loadMissing('client');
        $client = $payment->client;

        return [
            'title' => 'Payment failed',
            'message' => 'A scheduled payment failed for '.($client?->legal_name ?? 'a client').'.',
            'url' => $notifiable instanceof User && $notifiable->user_type === User::TYPE_CLIENT_PRIMARY
                ? route('portal.dashboard', absolute: false)
                : ($client ? route('advisor.clients.show', $client, absolute: false) : route('dashboard', absolute: false)),
            'urgency' => 'urgent',
            'payment_id' => $payment->id,
            'payment_schedule_id' => $payment->payment_schedule_id,
            'client_id' => $payment->client_id,
            'client_name' => $client?->legal_name,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'attempt' => $payment->attempt,
            'failed_reason' => $payment->failed_reason,
        ];
    }
}
