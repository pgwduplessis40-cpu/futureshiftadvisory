<?php

declare(strict_types=1);

namespace App\Services\OperationalHealth;

use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Models\User;
use App\Notifications\OperationalHealthAttentionNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class OperationalHealthAlerter
{
    public function notify(OperationalHealthCheckRun $run): int
    {
        if (! (bool) config('operational_health.alerts.enabled', true)) {
            return 0;
        }

        $recipients = $this->recipients();
        if ($recipients->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($this->alertableResults($run) as $result) {
            foreach ($recipients as $recipient) {
                if ($this->hasUnreadNotification($recipient, (string) $result->fingerprint)) {
                    continue;
                }

                $recipient->notify(new OperationalHealthAttentionNotification($run, $result));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients()
    {
        return User::query()
            ->withoutOperationalHealthFixtures()
            ->where(function ($query): void {
                $query->where('user_type', User::TYPE_SUPER_ADMIN)
                    ->orWhere('primary_role', User::TYPE_SUPER_ADMIN);
            })
            ->get();
    }

    /**
     * @return Collection<int, OperationalHealthCheckResult>
     */
    private function alertableResults(OperationalHealthCheckRun $run)
    {
        $threshold = max(1, (int) config('operational_health.alerts.consecutive_failures', 2));
        $statuses = array_map('strval', (array) config('operational_health.alerts.statuses', [
            OperationalHealthCheckResult::STATUS_FAILED,
            OperationalHealthCheckResult::STATUS_WARNING,
            OperationalHealthCheckResult::STATUS_SKIPPED,
        ]));

        return $run->results()
            ->get()
            ->filter(fn (OperationalHealthCheckResult $result): bool => in_array($result->status, $statuses, true)
                && is_string($result->fingerprint)
                && $result->fingerprint !== ''
                && (int) $result->consecutive_failures >= $threshold)
            ->unique('fingerprint')
            ->values();
    }

    private function hasUnreadNotification(User $user, string $fingerprint): bool
    {
        return $user->unreadNotifications()
            ->where('type', 'operational_health.attention')
            ->get()
            ->contains(fn (DatabaseNotification $notification): bool => data_get($notification->data, 'fingerprint') === $fingerprint);
    }
}
