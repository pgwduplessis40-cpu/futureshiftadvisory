<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class NotificationCenter
{
    /**
     * @return array{unread:int, urgent:int, latest:array<int, array<string, mixed>>, index_url:string, mark_all_read_url:string}
     */
    public function summary(User $user, int $limit = 5): array
    {
        return [
            ...$this->counts($user),
            'latest' => $user->notifications()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => $this->item($notification))
                ->values()
                ->all(),
            'index_url' => route('notifications.index', absolute: false),
            'mark_all_read_url' => route('notifications.mark-all-read', absolute: false),
        ];
    }

    /**
     * @return array{unread:int, urgent:int}
     */
    public function counts(User $user): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'urgent' => $user->unreadNotifications()
                ->where('urgency', 'urgent')
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user, int $limit = 50): array
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->item($notification))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function item(DatabaseNotification $notification): array
    {
        $data = $this->decode($notification->data);
        $decision = $this->decode($notification->channel_decision ?? null);
        $title = Arr::get($data, 'title');
        $message = Arr::get($data, 'message');
        $url = Arr::get($data, 'url');

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => is_string($title) && $title !== ''
                ? $title
                : $this->fallbackTitle((string) $notification->type),
            'message' => is_string($message) ? $message : null,
            'url' => is_string($url) && $url !== '' ? $url : null,
            'urgency' => $notification->urgency ?? 'normal',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'channel_decision' => $decision,
            'mark_read_url' => route('notifications.mark-read', $notification->id, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function fallbackTitle(string $type): string
    {
        $short = Str::afterLast($type, '\\');

        return Str::headline(Str::replace('.', ' ', $short));
    }
}
