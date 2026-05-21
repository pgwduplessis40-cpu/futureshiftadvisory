<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Notifications\ClientLifecycleNotification;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class LifecycleManager
{
    private static bool $allowStatusMutation = false;

    public function __construct(private readonly AuditWriter $auditWriter) {}

    public static function statusMutationIsAllowed(): bool
    {
        return self::$allowStatusMutation;
    }

    public function restore(Client $client, User $actor, ?string $reason = null): Client
    {
        return $this->transition($client, ClientStatus::ACTIVE, $actor, $reason);
    }

    public function pause(Client $client, User $actor, ?string $reason = null): Client
    {
        return $this->transition($client, ClientStatus::PAUSED, $actor, $reason);
    }

    public function suspend(Client $client, User $actor, ?string $reason = null): Client
    {
        return $this->transition($client, ClientStatus::SUSPENDED, $actor, $reason);
    }

    public function offboard(Client $client, User $actor, ?string $reason = null, bool $sendNotifications = true): Client
    {
        return $this->transition($client, ClientStatus::OFFBOARDED, $actor, $reason, $sendNotifications);
    }

    public function transition(
        Client $client,
        ClientStatus|string $targetStatus,
        User $actor,
        ?string $reason = null,
        bool $sendNotifications = true,
    ): Client {
        $targetStatus = $targetStatus instanceof ClientStatus
            ? $targetStatus
            : ClientStatus::from($targetStatus);

        $client->refresh();
        $client->loadMissing(['primaryContact', 'teamMembers.user']);
        $previousStatus = $this->statusOf($client);

        if ($previousStatus === $targetStatus) {
            return $client;
        }

        DB::transaction(function () use ($client, $targetStatus, $actor, $reason, $previousStatus): void {
            $this->withAllowedStatusMutation(function () use ($client, $targetStatus): void {
                $client->forceFill([
                    'status' => $targetStatus->value,
                ])->save();
            });

            $this->auditWriter->record('client.lifecycle.transitioned', subject: $client, actor: $actor, before: [
                'status' => $previousStatus->value,
            ], after: [
                'status' => $targetStatus->value,
                'reason' => $reason,
                'portal_access_revoked' => $targetStatus === ClientStatus::SUSPENDED,
            ]);
        });

        $client->refresh()->loadMissing(['primaryContact', 'teamMembers.user']);

        if ($sendNotifications) {
            $this->notify($client, $previousStatus, $targetStatus, $actor, $reason);
        }

        return $client;
    }

    private function statusOf(Client $client): ClientStatus
    {
        return $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value));
    }

    private function withAllowedStatusMutation(callable $callback): mixed
    {
        $previous = self::$allowStatusMutation;
        self::$allowStatusMutation = true;

        try {
            return $callback();
        } finally {
            self::$allowStatusMutation = $previous;
        }
    }

    private function notify(
        Client $client,
        ClientStatus $previousStatus,
        ClientStatus $targetStatus,
        User $actor,
        ?string $reason,
    ): void {
        $recipients = $this->notificationRecipients($client)
            ->reject(fn (User $user): bool => (string) $user->getKey() === (string) $actor->getKey())
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients->all(),
            new ClientLifecycleNotification($client, $previousStatus, $targetStatus, $reason),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function notificationRecipients(Client $client): Collection
    {
        return $client->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->push($client->primaryContact)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }
}
