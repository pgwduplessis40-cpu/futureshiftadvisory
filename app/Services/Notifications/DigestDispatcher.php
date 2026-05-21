<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Mail\NotificationDigestMail;
use App\Models\CommunicationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class DigestDispatcher
{
    public function dispatch(string $frequency): int
    {
        if (! in_array($frequency, [
            CommunicationPreference::FREQUENCY_DAILY,
            CommunicationPreference::FREQUENCY_WEEKLY,
        ], true)) {
            return 0;
        }

        $pending = DB::table('notifications')
            ->where('created_at', '<=', now())
            ->orderBy('created_at')
            ->get()
            ->filter(fn (object $row): bool => $this->isPendingForFrequency($row, $frequency))
            ->groupBy(fn (object $row): string => $row->notifiable_type.'|'.$row->notifiable_id);

        $sent = 0;
        foreach ($pending as $rows) {
            $user = $this->resolveUser($rows->first());
            if (! $user instanceof User) {
                continue;
            }

            $items = $rows
                ->map(fn (object $row): array => [
                    'id' => (string) $row->id,
                    'type' => (string) $row->type,
                    'data' => $this->decodeJson($row->data),
                    'created_at' => (string) $row->created_at,
                ])
                ->values()
                ->all();

            Mail::to($user)->send(new NotificationDigestMail($user, $frequency, $items));

            $this->markSent($rows, $frequency);
            $sent += count($items);
        }

        return $sent;
    }

    private function isPendingForFrequency(object $row, string $frequency): bool
    {
        $decision = $this->decodeJson($row->channel_decision ?? null);

        return ($decision['email_deferred'] ?? false) === true
            && ($decision['frequency'] ?? null) === $frequency
            && ! isset($decision['digest_sent_at']);
    }

    private function resolveUser(object $row): ?User
    {
        $type = (string) $row->notifiable_type;
        if (! in_array($type, [User::class, 'user'], true)) {
            return null;
        }

        return User::query()->find($row->notifiable_id);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function markSent(Collection $rows, string $frequency): void
    {
        $sentAt = now()->toIso8601String();
        foreach ($rows as $row) {
            $decision = $this->decodeJson($row->channel_decision ?? null);
            $decision['digest_sent_at'] = $sentAt;
            $decision['digest_frequency'] = $frequency;

            DB::table('notifications')
                ->where('id', $row->id)
                ->update(['channel_decision' => json_encode($decision, JSON_THROW_ON_ERROR)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
