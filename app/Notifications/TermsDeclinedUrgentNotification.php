<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TermsDeclinedUrgentNotification extends Notification
{
    use Queueable;

    public string $urgency = 'urgent';

    public function __construct(
        public readonly User $declinedUser,
        public readonly TermsVersion $termsVersion,
        public readonly TermsAcceptance $acceptance,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Urgent: Terms declined')
            ->line($this->declinedUser->name.' declined the platform terms and has been suspended.')
            ->line('Terms version: '.$this->termsVersion->version)
            ->line('Acceptance record: '.$this->acceptance->id)
            ->line('Review the account before reactivating or contacting the user.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'urgency' => $this->urgency,
            'declined_user_id' => $this->declinedUser->id,
            'declined_user_email' => $this->declinedUser->email,
            'terms_version_id' => $this->termsVersion->id,
            'terms_version' => $this->termsVersion->version,
            'terms_acceptance_id' => $this->acceptance->id,
        ];
    }
}
