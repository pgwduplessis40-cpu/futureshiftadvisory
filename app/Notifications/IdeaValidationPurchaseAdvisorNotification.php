<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IdeaValidationPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class IdeaValidationPurchaseAdvisorNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly IdeaValidationPurchase $purchase) {}

    public function databaseType(): string
    {
        return 'idea_validation.purchase_received';
    }

    public function urgency(): string
    {
        return 'urgent';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $purchase = $this->purchase->loadMissing('client', 'user');

        return (new MailMessage)
            ->subject('New paid Idea Validation client')
            ->line(($purchase->user->name ?? 'A new client').' purchased Idea Validation.')
            ->line('They have been assigned to you and can now complete their validation questions.')
            ->action('Open client', route('advisor.clients.show', $purchase->client, absolute: true));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $purchase = $this->purchase->loadMissing('client', 'user');

        return [
            'title' => 'New paid Idea Validation client',
            'message' => ($purchase->user->name ?? 'A new client').' purchased Idea Validation and is assigned to you.',
            'url' => route('advisor.clients.show', $purchase->client, absolute: false),
            'idea_validation_purchase_id' => $purchase->getKey(),
            'client_id' => $purchase->client_id,
            'client_name' => $purchase->client?->legal_name,
        ];
    }
}
