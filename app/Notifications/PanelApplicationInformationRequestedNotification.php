<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PanelMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class PanelApplicationInformationRequestedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly PanelMember $member,
        public readonly string $reason,
    ) {}

    public function databaseType(): string
    {
        return 'panel.application_information_requested';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Panel application needs more information')
            ->line('Future Shift Advisory needs updated information before your panel application can be approved.')
            ->line($this->reason)
            ->action('Update application', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Panel application needs more information',
            'message' => $this->reason,
            'url' => route('dashboard', absolute: false),
            'urgency' => $this->urgency(),
            'panel_member_id' => $this->member->id,
            'panel_type' => $this->member->panel_type,
            'status' => $this->member->status,
        ];
    }
}
