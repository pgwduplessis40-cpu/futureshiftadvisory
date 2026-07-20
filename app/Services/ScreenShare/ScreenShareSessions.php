<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Events\ScreenSharePrompt;
use App\Events\ScreenShareSessionUpdated;
use App\Events\ScreenShareSignal;
use App\Jobs\EndScreenShareSessionIfDisconnected;
use App\Models\Client;
use App\Models\ScreenShareConnection;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ScreenShareSessions
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ScreenShareAuthorizer $authorizer,
        private readonly ScreenSharePresence $presence,
        private readonly RequestContext $context,
    ) {}

    public function request(
        User $advisor,
        Client $client,
        User $clientUser,
        string $advisorConnectionId,
        string $advisorConnectionSecret,
    ): ScreenShareSession {
        $attachment = $this->authorizer->assertCanRequest($advisor, $client, $clientUser);
        $advisorConnection = $this->presence->assertConnection(
            $advisor,
            $advisorConnectionId,
            $advisorConnectionSecret,
            ScreenShareConnection::TYPE_ADVISOR,
        );
        abort_unless((string) $advisorConnection->client_id === (string) $client->getKey(), 403);

        $connections = $this->presence->activeConnectionsFor($client, $clientUser);
        abort_if($connections->isEmpty(), 422, 'The selected client is not currently online.');

        [$session, $deliveries] = $this->context->withSystemContext(function () use ($advisor, $advisorConnection, $attachment, $client, $clientUser, $connections): array {
            return DB::transaction(function () use ($advisor, $advisorConnection, $attachment, $client, $clientUser, $connections): array {
                $now = now();
                $deadline = $now->copy()->addSeconds($this->requestTimeoutSeconds());
                $deliveries = [];
                $prompts = $connections->map(function (ScreenShareConnection $connection) use (&$deliveries, $deadline, $now): array {
                    $nonce = Str::random(64);
                    $deliveries[] = [
                        'connection' => $connection,
                        'nonce' => $nonce,
                    ];

                    return [
                        'connection_id' => (string) $connection->getKey(),
                        'nonce_hash' => hash('sha256', $nonce),
                        'context_key' => $connection->context_key,
                        'prompted_at' => $now->toIso8601String(),
                        'expires_at' => $deadline->toIso8601String(),
                    ];
                })->all();

                $session = ScreenShareSession::query()->create([
                    'client_id' => $client->getKey(),
                    'client_user_id' => $clientUser->getKey(),
                    'advisor_id' => $advisor->getKey(),
                    'advisor_connection_id' => $advisorConnection->getKey(),
                    'status' => ScreenShareSession::STATUS_REQUESTED,
                    'requested_at' => $now,
                    'expires_at' => $deadline,
                    'authorization_basis' => $attachment->auditPayload(),
                    'prompted_connections' => $prompts,
                ]);

                return [$session, $deliveries];
            });
        });

        $this->audit->record('screen_share.requested', subject: $session, actor: $advisor, after: [
            'client_user_id' => (string) $clientUser->getKey(),
            'authorization_basis' => $session->authorization_basis,
        ]);

        foreach ($deliveries as $delivery) {
            /** @var ScreenShareConnection $connection */
            $connection = $delivery['connection'];
            ScreenSharePrompt::dispatch(
                (string) $connection->getKey(),
                (string) $session->getKey(),
                $delivery['nonce'],
                $advisor->name,
                $session->expires_at->toIso8601String(),
                $this->contextCopy($connection->context_key),
            );
        }

        return $session;
    }

    public function respond(
        User $clientUser,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
        string $nonce,
        bool $approved,
    ): ScreenShareSession {
        $connection = $this->presence->assertConnection(
            $clientUser,
            $connectionId,
            $connectionSecret,
            ScreenShareConnection::TYPE_CLIENT,
        );

        $result = $this->context->withSystemContext(function () use ($approved, $clientUser, $connection, $nonce, $session): array {
            return DB::transaction(function () use ($approved, $clientUser, $connection, $nonce, $session): array {
                $locked = ScreenShareSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

                abort_unless((string) $locked->client_user_id === (string) $clientUser->getKey(), 403);
                abort_unless((string) $locked->client_id === (string) $connection->client_id, 403);

                if ($locked->status !== ScreenShareSession::STATUS_REQUESTED) {
                    return [$locked, false];
                }

                $prompts = collect($locked->prompted_connections ?? []);
                $promptIndex = $prompts->search(fn (mixed $prompt): bool => is_array($prompt)
                    && ($prompt['connection_id'] ?? null) === (string) $connection->getKey());
                abort_if($promptIndex === false, 403);

                $prompt = $prompts->get($promptIndex);
                abort_unless(is_array($prompt), 403);
                abort_unless(! isset($prompt['consumed_at']), 403);
                abort_unless(
                    is_string($prompt['nonce_hash'] ?? null)
                    && hash_equals($prompt['nonce_hash'], hash('sha256', $nonce)),
                    403,
                );
                abort_if(now()->greaterThanOrEqualTo($prompt['expires_at'] ?? now()), 403);

                $attachment = $this->authorizer->assertStillAuthorized($locked);
                $this->assertAttachmentSnapshot($locked, $attachment);

                $prompt['consumed_at'] = now()->toIso8601String();
                $prompts->put($promptIndex, $prompt);
                $locked->prompted_connections = $prompts->values()->all();
                $locked->client_response = $approved ? 'approved' : 'declined';
                $locked->client_response_at = now();
                $locked->consent_context = [
                    'route_key' => $connection->context_key,
                    'bound_at' => $connection->created_at?->toIso8601String(),
                ];

                if ($approved) {
                    $locked->status = ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER;
                    $locked->client_connection_id = $connection->getKey();
                    $locked->picker_deadline_at = now()->addSeconds($this->pickerTimeoutSeconds());
                    $locked->expires_at = $locked->picker_deadline_at;
                } else {
                    $this->endLocked($locked, 'declined');
                }

                $locked->save();

                return [$locked->refresh(), true];
            });
        });

        [$updated, $won] = $result;
        if (! $won) {
            return $updated;
        }

        $this->audit->record(
            $approved ? 'screen_share.client_approved' : 'screen_share.client_declined',
            subject: $updated,
            actor: $clientUser,
            after: [
                'consent_context' => $updated->consent_context,
            ],
        );
        $this->broadcastUpdate($updated);

        return $updated;
    }

    public function recordBrowserPermission(
        User $clientUser,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
        bool $granted,
        ?string $displaySurface,
    ): ScreenShareSession {
        $connection = $this->presence->assertConnection($clientUser, $connectionId, $connectionSecret, ScreenShareConnection::TYPE_CLIENT);

        $updated = $this->context->withSystemContext(function () use ($clientUser, $connection, $displaySurface, $granted, $session): ScreenShareSession {
            return DB::transaction(function () use ($clientUser, $connection, $displaySurface, $granted, $session): ScreenShareSession {
                $locked = ScreenShareSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER, 409);
                abort_unless((string) $locked->client_user_id === (string) $clientUser->getKey(), 403);
                abort_unless((string) $locked->client_connection_id === (string) $connection->getKey(), 403);

                $locked->browser_permission_granted = $granted;
                $locked->display_surface = in_array($displaySurface, ['browser', 'window', 'monitor'], true) ? $displaySurface : null;

                if (! $granted) {
                    $this->endLocked($locked, 'browser_permission_denied');
                }

                $locked->save();

                return $locked->refresh();
            });
        });

        $this->audit->record(
            $granted ? 'screen_share.browser_permission_granted' : 'screen_share.browser_permission_denied',
            subject: $updated,
            actor: $clientUser,
            after: ['display_surface' => $updated->display_surface],
        );
        $this->broadcastUpdate($updated);

        return $updated;
    }

    public function markActive(
        User $user,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
    ): ScreenShareSession {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        $updated = $this->context->withSystemContext(function () use ($connection, $session, $user): ScreenShareSession {
            return DB::transaction(function () use ($connection, $session, $user): ScreenShareSession {
                $locked = ScreenShareSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);

                if ($locked->status === ScreenShareSession::STATUS_ACTIVE) {
                    return $locked;
                }

                abort_unless(
                    $locked->status === ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER
                    && $locked->browser_permission_granted,
                    409,
                );

                $locked->status = ScreenShareSession::STATUS_ACTIVE;
                $locked->session_started_at = now();
                $locked->last_heartbeat_at = now();
                $locked->expires_at = now()->addSeconds($this->maxDurationSeconds());
                $locked->save();

                return $locked->refresh();
            });
        });

        $this->audit->record('screen_share.started', subject: $updated, actor: $user);
        $this->broadcastUpdate($updated);
        $this->scheduleDisconnectCheck($updated, $connection);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signal(
        User $user,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
        string $type,
        array $payload,
    ): void {
        abort_unless(in_array($type, ['offer', 'answer', 'candidate'], true), 422);
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        $target = $this->context->withSystemContext(function () use ($connection, $session, $user): ?string {
            $locked = ScreenShareSession::query()->findOrFail($session->getKey());
            abort_unless(in_array($locked->status, [
                ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER,
                ScreenShareSession::STATUS_ACTIVE,
            ], true), 409);
            abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);

            return (string) $locked->advisor_connection_id === (string) $connection->getKey()
                ? $locked->client_connection_id
                : $locked->advisor_connection_id;
        });

        abort_unless(is_string($target) && $target !== '', 409);
        ScreenShareSignal::dispatch($target, (string) $session->getKey(), (string) $connection->getKey(), $type, $payload);
    }

    public function heartbeat(
        User $user,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
    ): ScreenShareSession {
        $connection = $this->presence->heartbeat($user, $connectionId, $connectionSecret);

        $updated = $this->context->withSystemContext(function () use ($connection, $session, $user): ScreenShareSession {
            $locked = ScreenShareSession::query()->findOrFail($session->getKey());
            abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);

            if ($locked->status === ScreenShareSession::STATUS_ACTIVE) {
                $locked->forceFill(['last_heartbeat_at' => now()])->save();
            }

            return $locked->refresh();
        });

        if ($updated->status === ScreenShareSession::STATUS_ACTIVE) {
            $this->scheduleDisconnectCheck($updated, $connection);
        }

        return $updated;
    }

    public function end(
        User $user,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
        string $reason,
    ): ScreenShareSession {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        $updated = $this->context->withSystemContext(function () use ($connection, $reason, $session, $user): ScreenShareSession {
            return DB::transaction(function () use ($connection, $reason, $session, $user): ScreenShareSession {
                $locked = ScreenShareSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);

                if (! $locked->isTerminal()) {
                    $this->endLocked($locked, $reason);
                    $locked->save();
                }

                return $locked->refresh();
            });
        });

        $this->audit->record('screen_share.ended', subject: $updated, actor: $user, after: [
            'end_reason' => $updated->end_reason,
        ]);
        $this->broadcastUpdate($updated);

        return $updated;
    }

    public function endIfConnectionNotReconnected(string $sessionId, string $connectionId): ?ScreenShareSession
    {
        $updated = $this->context->withSystemContext(function () use ($connectionId, $sessionId): ?ScreenShareSession {
            return DB::transaction(function () use ($connectionId, $sessionId): ?ScreenShareSession {
                $session = ScreenShareSession::query()->whereKey($sessionId)->lockForUpdate()->first();

                if (! $session instanceof ScreenShareSession || $session->status !== ScreenShareSession::STATUS_ACTIVE) {
                    return null;
                }

                if (! in_array((string) $connectionId, [
                    (string) $session->advisor_connection_id,
                    (string) $session->client_connection_id,
                ], true)) {
                    return null;
                }

                $connection = ScreenShareConnection::query()->find($connectionId);
                if ($connection instanceof ScreenShareConnection
                    && $connection->last_seen_at?->greaterThan(now()->subSeconds($this->reconnectGraceSeconds()))) {
                    return null;
                }

                $this->endLocked($session, 'connection_lost');
                $session->save();

                return $session->refresh();
            });
        });

        if ($updated instanceof ScreenShareSession) {
            $this->audit->record('screen_share.connection_lost', subject: $updated, after: [
                'end_reason' => $updated->end_reason,
            ]);
            $this->broadcastUpdate($updated);
        }

        return $updated;
    }

    /**
     * @return Collection<int, ScreenShareSession>
     */
    public function expireDueSessions(): Collection
    {
        $sessions = $this->context->withSystemContext(function (): Collection {
            return DB::transaction(function (): Collection {
                $sessions = ScreenShareSession::query()
                    ->where('status', '!=', ScreenShareSession::STATUS_ENDED)
                    ->where(function ($query): void {
                        $query->where('expires_at', '<=', now())
                            ->orWhere(function ($active): void {
                                $active->where('status', ScreenShareSession::STATUS_ACTIVE)
                                    ->where('last_heartbeat_at', '<=', now()->subSeconds($this->reconnectGraceSeconds()));
                            });
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($sessions as $session) {
                    $reason = $session->status === ScreenShareSession::STATUS_ACTIVE
                        && $session->last_heartbeat_at?->lessThanOrEqualTo(now()->subSeconds($this->reconnectGraceSeconds()))
                        ? 'connection_lost'
                        : match ($session->status) {
                            ScreenShareSession::STATUS_REQUESTED => 'request_timed_out',
                            ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER => 'browser_permission_timed_out',
                            default => 'max_duration_reached',
                        };
                    $this->endLocked($session, $reason);
                    $session->save();
                }

                return $sessions;
            });
        });

        foreach ($sessions as $session) {
            $this->audit->record('screen_share.expired', subject: $session, after: [
                'end_reason' => $session->end_reason,
            ]);
            $this->broadcastUpdate($session);
        }

        return $sessions;
    }

    private function broadcastUpdate(ScreenShareSession $session): void
    {
        foreach (array_filter([$session->advisor_connection_id, $session->client_connection_id]) as $connectionId) {
            ScreenShareSessionUpdated::dispatch((string) $connectionId, $session);
        }
    }

    private function endLocked(ScreenShareSession $session, string $reason): void
    {
        $session->status = ScreenShareSession::STATUS_ENDED;
        $session->end_reason = $reason;
        $session->session_ended_at = now();
        $session->expires_at = now();
        $session->duration_seconds = $session->session_started_at?->diffInSeconds($session->session_ended_at);
    }

    private function isBoundParticipant(ScreenShareSession $session, User $user, ScreenShareConnection $connection): bool
    {
        return ((string) $session->advisor_id === (string) $user->getKey()
                && (string) $session->advisor_connection_id === (string) $connection->getKey())
            || ((string) $session->client_user_id === (string) $user->getKey()
                && (string) $session->client_connection_id === (string) $connection->getKey());
    }

    private function assertAttachmentSnapshot(ScreenShareSession $session, ScreenShareAttachment $attachment): void
    {
        $snapshot = $session->authorization_basis ?? [];
        abort_unless(($snapshot['path'] ?? null) === $attachment->basis, 403);
        abort_unless(
            $attachment->basis !== 'advisor_team'
            || ($snapshot['advisor_team_id'] ?? null) === $attachment->advisorTeamId,
            403,
        );
    }

    /**
     * @return array{key:string, label:string}
     */
    private function contextCopy(string $key): array
    {
        return match ($key) {
            'portal.dd.questionnaire' => ['key' => $key, 'label' => 'your due diligence questionnaire'],
            default => ['key' => 'portal.generic', 'label' => 'the page you are currently on'],
        };
    }

    private function requestTimeoutSeconds(): int
    {
        return max(15, (int) config('screen-share.request_timeout_seconds', 60));
    }

    private function pickerTimeoutSeconds(): int
    {
        return max(30, (int) config('screen-share.picker_timeout_seconds', 90));
    }

    private function maxDurationSeconds(): int
    {
        return max(1, (int) config('screen-share.max_duration_minutes', 30)) * 60;
    }

    private function reconnectGraceSeconds(): int
    {
        return max(5, (int) config('screen-share.reconnect_grace_seconds', 15));
    }

    private function scheduleDisconnectCheck(ScreenShareSession $session, ScreenShareConnection $connection): void
    {
        EndScreenShareSessionIfDisconnected::dispatch(
            (string) $session->getKey(),
            (string) $connection->getKey(),
        )->delay(now()->addSeconds($this->reconnectGraceSeconds()))->onQueue('realtime');
    }
}
