<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IdeaValidationPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class IdeaValidationEmailVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly IdeaValidationPurchase $purchase) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $purchase = $this->purchase->loadMissing('user');
        $user = $purchase->user;
        $hash = sha1((string) $user->email);
        $url = URL::temporarySignedRoute(
            'public.validate-idea.purchase.verify',
            now()->addMinutes(60),
            [
                'purchase' => $purchase,
                'hash' => $hash,
            ],
        );

        return (new MailMessage)
            ->subject('Verify your email to continue with Idea Validation')
            ->greeting('Hello '.($user?->name ?: 'there'))
            ->line('Verify this email address before continuing to the secure Idea Validation payment page.')
            ->action('Verify email address', $url)
            ->line('This link expires in 60 minutes. If you did not start this request, you can ignore this email.');
    }
}
