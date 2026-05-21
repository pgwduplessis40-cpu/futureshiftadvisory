<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class ClientLifecycleNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly Client $client,
        public readonly ClientStatus $previousStatus,
        public readonly ClientStatus $status,
        public readonly ?string $reason = null,
    ) {}

    public function databaseType(): string
    {
        return 'client.lifecycle.changed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Client lifecycle status updated')
            ->line('Client: '.$this->client->legal_name)
            ->line('Status: '.$this->previousStatus->label().' to '.$this->status->label());

        if (is_string($this->reason) && $this->reason !== '') {
            $message->line('Reason: '.$this->reason);
        }

        if ($this->status === ClientStatus::SUSPENDED) {
            $message->line('Client portal access is suspended until the client is restored.');
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'client_id' => $this->client->id,
            'client_name' => $this->client->legal_name,
            'previous_status' => $this->previousStatus->value,
            'previous_status_label' => $this->previousStatus->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'title' => 'Client status updated',
            'message' => $this->client->legal_name.' is now '.$this->status->label().'.',
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): ?string
    {
        if ($notifiable instanceof User && in_array($notifiable->user_type, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
        ], true)) {
            return route('advisor.clients.show', $this->client, absolute: false);
        }

        return $this->status === ClientStatus::SUSPENDED
            ? null
            : route('portal.dashboard', absolute: false);
    }
}
