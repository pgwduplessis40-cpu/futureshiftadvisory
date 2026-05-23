<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PanelMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class BrokerFspLapsedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly PanelMember $member) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'panel.broker_fsp_lapsed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $member = $this->member->loadMissing('user');

        return (new MailMessage)
            ->subject('Broker FSP registration lapsed')
            ->line('A broker panel member was suspended because their FSP registration is no longer current.')
            ->line('Broker: '.($member->user?->name ?? $member->user?->email ?? 'Unknown broker'))
            ->line('FSP number: '.($member->fsp_number ?? 'Unknown'))
            ->action('Open dashboard', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $member = $this->member->loadMissing('user');

        return [
            'title' => 'Broker FSP registration lapsed',
            'message' => ($member->user?->name ?? $member->user?->email ?? 'A broker').' has been suspended after FSP re-verification.',
            'url' => route('dashboard', absolute: false),
            'urgency' => 'urgent',
            'panel_member_id' => $member->id,
            'broker_user_id' => $member->user_id,
            'fsp_number' => $member->fsp_number,
            'fsp_status' => $member->fsp_status,
        ];
    }
}
