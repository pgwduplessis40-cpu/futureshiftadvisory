<?php

declare(strict_types=1);

namespace App\Services\CoBrowse;

use App\Events\CoBrowseActionDispatched;
use App\Events\CoBrowsePrompt;
use App\Events\CoBrowseSessionUpdated;
use App\Models\Client;
use App\Models\CoBrowseAction;
use App\Models\CoBrowseConnection;
use App\Models\CoBrowseSession;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\ScreenShare\ScreenShareAttachment;
use App\Services\ScreenShare\ScreenShareAuthorizer;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class CoBrowseSessions
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ScreenShareAuthorizer $authorizer,
        private readonly CoBrowsePresence $presence,
        private readonly CoBrowseTargetRegistry $targets,
        private readonly RequestContext $context,
    ) {}

    public function requestForClient(
        User $advisor,
        Client $client,
        User $clientUser,
        string $advisorConnectionId,
        string $advisorConnectionSecret,
    ): CoBrowseSession {
        $attachment = $this->authorizer->assertCanRequest($advisor, $client, $clientUser);
        $advisorConnection = $this->presence->assertConnection(
            $advisor,
            $advisorConnectionId,
            $advisorConnectionSecret,
            CoBrowseConnection::TYPE_ADVISOR,
        );
        abort_unless((string) $advisorConnection->client_id === (string) $client->getKey(), 403);
        $connections = $this->presence->activeClientConnections($client, $clientUser);

        return $this->request(
            $advisor,
            $clientUser,
            $advisorConnection,
            $connections,
            $attachment,
            $client,
            null,
        );
    }

    public function requestForEntrepreneur(
        User $advisor,
        EntrepreneurProfile $profile,
        User $entrepreneur,
        string $advisorConnectionId,
        string $advisorConnectionSecret,
    ): CoBrowseSession {
        $attachment = $this->authorizer->assertCanRequestForEntrepreneur($advisor, $profile, $entrepreneur);
        $advisorConnection = $this->presence->assertConnection(
            $advisor,
            $advisorConnectionId,
            $advisorConnectionSecret,
            CoBrowseConnection::TYPE_ADVISOR,
        );
        abort_unless((string) $advisorConnection->entrepreneur_profile_id === (string) $profile->getKey(), 403);
        $connections = $this->presence->activeEntrepreneurConnections($profile, $entrepreneur);

        return $this->request(
            $advisor,
            $entrepreneur,
            $advisorConnection,
            $connections,
            $attachment,
            null,
            $profile,
        );
    }

    public function respond(
        User $clientUser,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
        string $nonce,
        bool $approved,
    ): CoBrowseSession {
        $connection = $this->presence->assertConnection(
            $clientUser,
            $connectionId,
            $connectionSecret,
            CoBrowseConnection::TYPE_CLIENT,
        );
        $updated = $this->context->withSystemContext(function () use ($clientUser, $session, $connection, $nonce, $approved): CoBrowseSession {
            return DB::transaction(function () use ($clientUser, $session, $connection, $nonce, $approved): CoBrowseSession {
                $locked = CoBrowseSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                $this->assertClientConnectionForSession($locked, $clientUser, $connection);

                if ($locked->status !== CoBrowseSession::STATUS_REQUESTED) {
                    return $locked;
                }

                abort_if($locked->expires_at === null || $locked->expires_at->isPast(), 409, 'The guidance request has expired.');
                $prompt = collect($locked->prompted_connections)->first(
                    fn (array $item): bool => (string) $item['connection_id'] === (string) $connection->getKey(),
                );
                abort_unless(is_array($prompt) && hash_equals($prompt['nonce_hash'], hash('sha256', $nonce)), 403);
                abort_if(now()->greaterThanOrEqualTo($prompt['expires_at']), 409, 'The guidance request has expired.');

                $locked->client_connection_id = $connection->getKey();
                $locked->client_response = $approved ? 'approved' : 'declined';
                $locked->client_response_at = now();
                $locked->consent_context = [
                    'route_key' => $connection->context_key,
                    'approved_at' => now()->toIso8601String(),
                    'capabilities' => ['highlight', 'pointer'],
                ];

                if ($approved) {
                    $locked->status = CoBrowseSession::STATUS_ACTIVE;
                    $locked->session_started_at = now();
                    $locked->last_heartbeat_at = now();
                    $locked->expires_at = now()->addMinutes($this->maxDurationMinutes());
                } else {
                    $this->endLocked($locked, 'declined');
                }

                $locked->save();

                return $locked->refresh();
            });
        });

        $this->audit->record(
            $approved ? 'co_browse.client_approved' : 'co_browse.client_declined',
            subject: $updated,
            actor: $clientUser,
            after: ['route_key' => $connection->context_key],
        );
        $this->broadcastUpdate($updated);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function action(
        User $advisor,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
        string $type,
        array $payload,
    ): CoBrowseAction {
        $connection = $this->presence->assertConnection(
            $advisor,
            $connectionId,
            $connectionSecret,
            CoBrowseConnection::TYPE_ADVISOR,
        );
        $this->assertActionPayload($type, $payload);

        $action = $this->context->withSystemContext(function () use ($advisor, $session, $connection, $type, $payload): CoBrowseAction {
            return DB::transaction(function () use ($advisor, $session, $connection, $type, $payload): CoBrowseAction {
                $locked = CoBrowseSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                $this->assertAdvisorConnectionForSession($locked, $advisor, $connection);
                abort_unless($locked->status === CoBrowseSession::STATUS_ACTIVE && $locked->expires_at?->isFuture(), 409);
                $this->assertStillAuthorized($locked);
                abort_unless($locked->client_connection_id !== null, 409);

                if ($type === 'highlight') {
                    $routeKey = (string) ($locked->consent_context['route_key'] ?? '');
                    $this->targets->assertKnown($routeKey, (string) $payload['target']);
                }

                if ($type === 'pointer') {
                    $this->rateLimitPointer($locked);
                }

                $action = CoBrowseAction::query()->create([
                    'session_id' => $locked->getKey(),
                    'recipient_connection_id' => $locked->client_connection_id,
                    'sender_connection_id' => $connection->getKey(),
                    'type' => $type,
                    'payload' => $payload,
                    'expires_at' => $locked->expires_at,
                ]);
                $locked->increment('actions_count');

                return $action;
            });
        });

        $this->audit->record('co_browse.'.$type, subject: $session, actor: $advisor, after: [
            'action_id' => $action->getKey(),
            'target' => $type === 'highlight' ? $payload['target'] : null,
        ]);
        CoBrowseActionDispatched::dispatch((string) $action->recipient_connection_id, $action);

        return $action;
    }

    /**
     * @return array<int, array{id:int,type:string,payload:array<string,mixed>}>
     */
    public function pendingActions(
        User $clientUser,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
        int $afterId,
    ): array {
        $connection = $this->presence->assertConnection(
            $clientUser,
            $connectionId,
            $connectionSecret,
            CoBrowseConnection::TYPE_CLIENT,
        );

        return $this->context->withSystemContext(function () use ($clientUser, $session, $connection, $afterId): array {
            $locked = CoBrowseSession::query()->findOrFail($session->getKey());
            $this->assertClientConnectionForSession($locked, $clientUser, $connection);
            abort_unless($locked->status === CoBrowseSession::STATUS_ACTIVE, 409);

            return CoBrowseAction::query()
                ->where('session_id', $locked->getKey())
                ->where('recipient_connection_id', $connection->getKey())
                ->where('id', '>', max(0, $afterId))
                ->where('expires_at', '>', now())
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->map(fn (CoBrowseAction $action): array => [
                    'id' => $action->getKey(),
                    'type' => $action->type,
                    'payload' => $action->payload,
                ])
                ->all();
        });
    }

    /**
     * @return array{session_id:string,nonce:string,advisor_name:string,expires_at:string,context:array{key:string,label:string}}|null
     */
    public function pendingPrompt(User $clientUser, CoBrowseConnection $connection): ?array
    {
        return $this->context->withSystemContext(function () use ($clientUser, $connection): ?array {
            $session = CoBrowseSession::query()
                ->where('client_user_id', $clientUser->getKey())
                ->where('status', CoBrowseSession::STATUS_REQUESTED)
                ->where('expires_at', '>', now())
                ->latest('requested_at')
                ->first();

            if (! $session instanceof CoBrowseSession) {
                return null;
            }

            $this->assertClientConnectionForSession($session, $clientUser, $connection);
            $prompt = collect($session->prompted_connections)->first(
                fn (array $item): bool => (string) ($item['connection_id'] ?? '') === (string) $connection->getKey(),
            );

            if (! is_array($prompt) || ! isset($prompt['expires_at'], $prompt['nonce_encrypted']) || now()->greaterThanOrEqualTo($prompt['expires_at'])) {
                return null;
            }

            $advisor = User::query()->find($session->advisor_id);

            return [
                'session_id' => (string) $session->getKey(),
                'nonce' => Crypt::decryptString((string) $prompt['nonce_encrypted']),
                'advisor_name' => $advisor?->name ?? 'Your advisor',
                'expires_at' => (string) $prompt['expires_at'],
                'context' => $this->contextCopy($connection->context_key),
            ];
        });
    }

    public function status(
        User $user,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
    ): CoBrowseSession {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        return $this->context->withSystemContext(function () use ($user, $session, $connection): CoBrowseSession {
            $locked = CoBrowseSession::query()->findOrFail($session->getKey());

            if ($connection->participant_type === CoBrowseConnection::TYPE_ADVISOR) {
                $this->assertAdvisorConnectionForSession($locked, $user, $connection);
            } else {
                $this->assertClientConnectionForSession($locked, $user, $connection);
            }

            return $locked;
        });
    }

    public function heartbeat(
        User $user,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
    ): CoBrowseSession {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        return $this->context->withSystemContext(function () use ($user, $session, $connection): CoBrowseSession {
            $locked = CoBrowseSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);
            abort_unless($locked->status === CoBrowseSession::STATUS_ACTIVE, 409);
            $locked->last_heartbeat_at = now();
            $locked->save();

            return $locked->refresh();
        });
    }

    public function end(
        User $user,
        CoBrowseSession $session,
        string $connectionId,
        string $connectionSecret,
        string $reason,
    ): CoBrowseSession {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);
        $updated = $this->context->withSystemContext(function () use ($user, $session, $connection, $reason): CoBrowseSession {
            return DB::transaction(function () use ($user, $session, $connection, $reason): CoBrowseSession {
                $locked = CoBrowseSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($this->isBoundParticipant($locked, $user, $connection), 403);
                if ($locked->status !== CoBrowseSession::STATUS_ENDED) {
                    $this->endLocked($locked, $reason);
                    $locked->save();
                }

                return $locked->refresh();
            });
        });

        $this->audit->record('co_browse.ended', subject: $updated, actor: $user, after: ['end_reason' => $updated->end_reason]);
        $this->broadcastUpdate($updated);

        return $updated;
    }

    /** @return Collection<int, CoBrowseSession> */
    public function expireDueSessions(): Collection
    {
        $expired = $this->context->withSystemContext(function (): Collection {
            return DB::transaction(function (): Collection {
                $sessions = CoBrowseSession::query()
                    ->where('status', '!=', CoBrowseSession::STATUS_ENDED)
                    ->where(function ($query): void {
                        $query->where('expires_at', '<=', now())
                            ->orWhere(function ($active): void {
                                $active->where('status', CoBrowseSession::STATUS_ACTIVE)
                                    ->where('last_heartbeat_at', '<=', now()->subSeconds($this->presenceTtlSeconds()));
                            });
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($sessions as $session) {
                    $reason = $session->expires_at !== null && $session->expires_at->isPast()
                        ? ($session->status === CoBrowseSession::STATUS_REQUESTED ? 'request_timed_out' : 'max_duration_reached')
                        : 'connection_lost';
                    $this->endLocked($session, $reason);
                    $session->save();
                }

                return $sessions;
            });
        });

        foreach ($expired as $session) {
            $this->audit->record('co_browse.expired', subject: $session, after: ['end_reason' => $session->end_reason]);
            $this->broadcastUpdate($session);
        }

        return $expired;
    }

    private function request(
        User $advisor,
        User $clientUser,
        CoBrowseConnection $advisorConnection,
        Collection $connections,
        ScreenShareAttachment $attachment,
        ?Client $client,
        ?EntrepreneurProfile $profile,
    ): CoBrowseSession {
        abort_unless((bool) config('co-browse.enabled'), 404);
        abort_if($connections->isEmpty(), 422, 'The selected client is not currently on a supported Future Shift Advisory page.');

        [$session, $deliveries] = $this->context->withSystemContext(function () use ($advisor, $clientUser, $advisorConnection, $connections, $attachment, $client, $profile): array {
            return DB::transaction(function () use ($advisor, $clientUser, $advisorConnection, $connections, $attachment, $client, $profile): array {
                $now = now();
                $deadline = $now->copy()->addSeconds($this->requestTimeoutSeconds());
                $deliveries = [];
                $prompts = $connections->map(function (CoBrowseConnection $connection) use (&$deliveries, $deadline, $now): array {
                    $nonce = Str::random(64);
                    $deliveries[] = ['connection' => $connection, 'nonce' => $nonce];

                    return [
                        'connection_id' => (string) $connection->getKey(),
                        'nonce_hash' => hash('sha256', $nonce),
                        'nonce_encrypted' => Crypt::encryptString($nonce),
                        'context_key' => $connection->context_key,
                        'prompted_at' => $now->toIso8601String(),
                        'expires_at' => $deadline->toIso8601String(),
                    ];
                })->all();
                $session = CoBrowseSession::query()->create([
                    'client_id' => $client?->getKey(),
                    'entrepreneur_profile_id' => $profile?->getKey(),
                    'client_user_id' => $clientUser->getKey(),
                    'advisor_id' => $advisor->getKey(),
                    'advisor_connection_id' => $advisorConnection->getKey(),
                    'status' => CoBrowseSession::STATUS_REQUESTED,
                    'requested_at' => $now,
                    'expires_at' => $deadline,
                    'authorization_basis' => $attachment->auditPayload(),
                    'prompted_connections' => $prompts,
                ]);

                return [$session, $deliveries];
            });
        });

        $this->audit->record('co_browse.requested', subject: $session, actor: $advisor, after: [
            'client_user_id' => (string) $clientUser->getKey(),
            'authorization_basis' => $session->authorization_basis,
        ]);
        foreach ($deliveries as $delivery) {
            /** @var CoBrowseConnection $connection */
            $connection = $delivery['connection'];
            CoBrowsePrompt::dispatch(
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

    private function assertActionPayload(string $type, array $payload): void
    {
        abort_unless(in_array($type, ['pointer', 'clear_pointer', 'highlight', 'clear_highlight'], true), 422);
        if ($type === 'pointer') {
            abort_unless(isset($payload['x'], $payload['y']) && is_numeric($payload['x']) && is_numeric($payload['y']), 422);
            abort_unless((float) $payload['x'] >= 0 && (float) $payload['x'] <= 1, 422);
            abort_unless((float) $payload['y'] >= 0 && (float) $payload['y'] <= 1, 422);
        }
        if ($type === 'highlight') {
            abort_unless(isset($payload['target']) && is_string($payload['target']) && strlen($payload['target']) <= 120, 422);
        }
        abort_unless(count($payload) <= 2, 422);
    }

    private function assertStillAuthorized(CoBrowseSession $session): void
    {
        $advisor = User::query()->findOrFail($session->advisor_id);
        $clientUser = User::query()->findOrFail($session->client_user_id);
        if ($session->entrepreneur_profile_id !== null) {
            $profile = EntrepreneurProfile::query()->findOrFail($session->entrepreneur_profile_id);
            $this->authorizer->assertCanRequestForEntrepreneur($advisor, $profile, $clientUser);

            return;
        }

        $client = Client::query()->findOrFail($session->client_id);
        $this->authorizer->assertCanRequest($advisor, $client, $clientUser);
    }

    private function assertClientConnectionForSession(CoBrowseSession $session, User $user, CoBrowseConnection $connection): void
    {
        abort_unless((string) $session->client_user_id === (string) $user->getKey(), 403);
        abort_unless($connection->participant_type === CoBrowseConnection::TYPE_CLIENT, 403);
        abort_unless(
            (string) $session->client_id === (string) $connection->client_id
            && (string) $session->entrepreneur_profile_id === (string) $connection->entrepreneur_profile_id,
            403,
        );
    }

    private function assertAdvisorConnectionForSession(CoBrowseSession $session, User $user, CoBrowseConnection $connection): void
    {
        abort_unless((string) $session->advisor_id === (string) $user->getKey(), 403);
        abort_unless((string) $session->advisor_connection_id === (string) $connection->getKey(), 403);
        abort_unless(
            (string) $session->client_id === (string) $connection->client_id
            && (string) $session->entrepreneur_profile_id === (string) $connection->entrepreneur_profile_id,
            403,
        );
    }

    private function isBoundParticipant(CoBrowseSession $session, User $user, CoBrowseConnection $connection): bool
    {
        return (
            (string) $session->advisor_id === (string) $user->getKey()
            && (string) $session->advisor_connection_id === (string) $connection->getKey()
        ) || (
            (string) $session->client_user_id === (string) $user->getKey()
            && (string) $session->client_connection_id === (string) $connection->getKey()
        );
    }

    private function endLocked(CoBrowseSession $session, string $reason): void
    {
        $session->status = CoBrowseSession::STATUS_ENDED;
        $session->end_reason = $reason;
        $session->session_ended_at = now();
    }

    private function broadcastUpdate(CoBrowseSession $session): void
    {
        $connectionIds = collect($session->prompted_connections)
            ->pluck('connection_id')
            ->push($session->advisor_connection_id)
            ->filter()
            ->unique();

        foreach ($connectionIds as $connectionId) {
            CoBrowseSessionUpdated::dispatch((string) $connectionId, $session);
        }
    }

    /** @return array{key:string,label:string} */
    private function contextCopy(string $key): array
    {
        return match ($key) {
            'portal.dashboard' => ['key' => $key, 'label' => 'your client dashboard'],
            'portal.entrepreneur.dashboard' => ['key' => $key, 'label' => 'your entrepreneur workspace'],
            default => ['key' => 'portal.generic', 'label' => 'the Future Shift Advisory page you are on'],
        };
    }

    private function rateLimitPointer(CoBrowseSession $session): void
    {
        $key = 'co-browse:pointer:'.$session->getKey();
        abort_if(RateLimiter::tooManyAttempts($key, max(1, (int) config('co-browse.actions_per_second', 5))), 429);
        RateLimiter::hit($key, 1);
    }

    private function requestTimeoutSeconds(): int
    {
        return max(15, (int) config('co-browse.request_timeout_seconds', 60));
    }

    private function maxDurationMinutes(): int
    {
        return max(1, (int) config('co-browse.max_duration_minutes', 20));
    }

    private function presenceTtlSeconds(): int
    {
        return max(20, (int) config('co-browse.presence_ttl_seconds', 45));
    }
}
