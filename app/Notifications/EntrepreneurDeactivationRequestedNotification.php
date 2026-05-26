<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EntrepreneurProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class EntrepreneurDeactivationRequestedNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly EntrepreneurProfile $profile) {}

    public function databaseType(): string
    {
        return 'entrepreneur.deactivation_requested';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Entrepreneur account deactivation requested')
            ->line($this->profile->name.' requested account deactivation.')
            ->line('Review the request before suspending or deactivating access.')
            ->action('Open entrepreneur', route('advisor.entrepreneurs.show', $this->profile));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Deactivation requested',
            'message' => $this->profile->name.' requested account deactivation.',
            'url' => route('advisor.entrepreneurs.show', $this->profile, absolute: false),
            'urgency' => $this->urgency(),
            'entrepreneur_profile_id' => $this->profile->id,
            'entrepreneur_name' => $this->profile->name,
            'user_id' => $this->profile->user_id,
        ];
    }
}
