<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetLinkNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $mailer = (string) config('mail.default', '');
        $message = (new MailMessage)
            ->subject('Reset your Future Shift Advisory password')
            ->greeting('Hello '.$this->displayName($notifiable))
            ->line('We received a request to reset the password for your Future Shift Advisory account.')
            ->action('Reset password', $this->resetUrl($notifiable))
            ->line('This secure reset link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not request this reset, you can ignore this email.');

        return $mailer !== '' ? $message->mailer($mailer) : $message;
    }

    private function displayName(mixed $notifiable): string
    {
        $name = is_object($notifiable) && isset($notifiable->name)
            ? trim((string) $notifiable->name)
            : '';

        return $name !== '' ? $name : 'there';
    }
}
