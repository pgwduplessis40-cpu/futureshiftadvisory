<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IdeaValidationPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class IdeaValidationPurchaseConfirmedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly IdeaValidationPurchase $purchase) {}

    public function databaseType(): string
    {
        return 'idea_validation.purchase_confirmed';
    }

    public function urgency(): string
    {
        return 'urgent';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = data_get($notifiable, 'name');

        return (new MailMessage)
            ->subject('Your Idea Validation access is ready')
            ->greeting('Hello '.(is_string($name) && $name !== '' ? $name : 'there'))
            ->line('Your payment was confirmed. Stripe will also send the card receipt to the email address used at checkout.')
            ->line('Set up your authenticator app and recovery codes, then complete your Idea Validation questions for advisor review.')
            ->action('Set up account security', route('mfa.setup', absolute: true));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Idea Validation access is ready',
            'message' => 'Your payment was confirmed. Set up your authenticator app, then begin Idea Validation.',
            'url' => route('mfa.setup', absolute: false),
            'idea_validation_purchase_id' => $this->purchase->getKey(),
            'service_activation_id' => $this->purchase->service_activation_id,
        ];
    }
}
