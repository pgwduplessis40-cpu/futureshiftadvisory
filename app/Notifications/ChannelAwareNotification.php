<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Notifications\ChannelResolver;
use Illuminate\Notifications\Notification;

abstract class ChannelAwareNotification extends Notification
{
    public function urgency(): string
    {
        return 'normal';
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return app(ChannelResolver::class)->channelsFor($notifiable, $this);
    }
}
