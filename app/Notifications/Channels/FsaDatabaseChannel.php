<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\Notifications\ChannelResolver;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class FsaDatabaseChannel
{
    public function __construct(private readonly ChannelResolver $resolver) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'notifications')) {
            return;
        }

        $notifiable->notifications()->create([
            'id' => $this->notificationId($notification),
            'type' => $this->notificationType($notification),
            'data' => $this->payload($notifiable, $notification),
            'urgency' => $this->resolver->urgency($notification),
            'channel_decision' => json_encode(
                $this->resolver->decisionFor($notifiable, $notification)->toArray(),
                JSON_THROW_ON_ERROR,
            ),
            'read_at' => null,
        ]);
    }

    private function notificationId(Notification $notification): string
    {
        $id = $notification->id ?? null;

        return is_string($id) && $id !== '' ? $id : (string) Str::uuid();
    }

    private function notificationType(Notification $notification): string
    {
        if (method_exists($notification, 'databaseType')) {
            $type = $notification->databaseType();

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        return $notification::class;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'toDatabase')) {
            $payload = $notification->toDatabase($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            $payload = $notification->toArray($notifiable);
        } else {
            $payload = [];
        }

        return is_array($payload) ? $payload : [];
    }
}
