<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProspectLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class ProspectLeadReceivedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly ProspectLead $lead) {}

    public function databaseType(): string
    {
        return 'prospect.lead.received';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New website prospect lead')
            ->line('A new prospect lead has arrived from the website integration.')
            ->line('Name: '.$this->lead->name)
            ->line('Email: '.$this->lead->email)
            ->line('Source: '.$this->lead->source)
            ->action('Open prospect inbox', route('advisor.prospects.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New prospect lead',
            'message' => trim($this->lead->name.' from '.($this->lead->company ?: $this->lead->email)),
            'url' => route('advisor.prospects.index', absolute: false),
            'prospect_lead_id' => $this->lead->id,
            'name' => $this->lead->name,
            'email' => $this->lead->email,
            'company' => $this->lead->company,
            'source' => $this->lead->source,
            'received_at' => now()->toIso8601String(),
        ];
    }
}
