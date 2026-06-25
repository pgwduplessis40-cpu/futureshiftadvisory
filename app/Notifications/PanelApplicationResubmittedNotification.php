<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PanelMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class PanelApplicationResubmittedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly PanelMember $member) {}

    public function databaseType(): string
    {
        return 'panel.application_resubmitted';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $member = $this->member->loadMissing('user');

        return (new MailMessage)
            ->subject('Panel application resubmitted')
            ->line(($member->user?->name ?? 'A panel applicant').' has updated and resubmitted their panel application.')
            ->line('Panel type: '.ucfirst($member->panel_type))
            ->action('Review application', route('admin.panel-members.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $member = $this->member->loadMissing('user');
        $application = is_array($member->application) ? $member->application : [];
        $company = is_string($application['company'] ?? null) ? $application['company'] : null;

        return [
            'title' => 'Panel application resubmitted',
            'message' => ($company ?? $member->user?->name ?? 'A panel applicant').' has updated and resubmitted their '.str_replace('_', ' ', $member->panel_type).' application.',
            'url' => route('admin.panel-members.index', absolute: false),
            'urgency' => $this->urgency(),
            'panel_member_id' => $member->id,
            'panel_type' => $member->panel_type,
            'status' => $member->status,
            'applicant_user_id' => $member->user_id,
        ];
    }
}
