<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\Client;
use App\Models\ScreenShareConnection;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class ScreenSharePresence
{
    public function __construct(
        private readonly AdvisorClientAttachment $attachments,
        private readonly ClientPortalContextTokens $portalContexts,
        private readonly RequestContext $context,
    ) {}

    public function registerClient(User $user, string $portalContextToken): ScreenShareConnectionCredentials
    {
        $portalContext = $this->portalContexts->consume($user, $portalContextToken);
        $client = $this->context->withSystemContext(fn (): Client => Client::query()->findOrFail($portalContext->clientId));

        return $this->create($user, $client, ScreenShareConnection::TYPE_CLIENT, $portalContext->routeKey);
    }

    public function registerAdvisor(User $user, Client $client): ScreenShareConnectionCredentials
    {
        abort_unless(in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true), 403);
        abort_unless($this->attachments->resolve($user, $client) instanceof ScreenShareAttachment, 403);

        return $this->create($user, $client, ScreenShareConnection::TYPE_ADVISOR, 'advisor.clients.show');
    }

    /**
     * @return Collection<int, ScreenShareConnection>
     */
    public function activeConnectionsFor(Client $client, User $clientUser): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => ScreenShareConnection::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $clientUser->getKey())
            ->where('participant_type', ScreenShareConnection::TYPE_CLIENT)
            ->where('expires_at', '>', now())
            ->get());
    }

    public function assertConnection(
        User $user,
        string $connectionId,
        string $secret,
        ?string $participantType = null,
    ): ScreenShareConnection {
        return $this->context->withSystemContext(function () use ($user, $connectionId, $secret, $participantType): ScreenShareConnection {
            $connection = ScreenShareConnection::query()
                ->whereKey($connectionId)
                ->where('user_id', $user->getKey())
                ->where('expires_at', '>', now())
                ->first();

            abort_unless($connection instanceof ScreenShareConnection, 403);
            abort_unless($participantType === null || $connection->participant_type === $participantType, 403);
            abort_unless(hash_equals($connection->secret_hash, hash('sha256', $secret)), 403);

            return $connection;
        });
    }

    public function heartbeat(User $user, string $connectionId, string $secret): ScreenShareConnection
    {
        $connection = $this->assertConnection($user, $connectionId, $secret);

        return $this->context->withSystemContext(function () use ($connection): ScreenShareConnection {
            $connection->forceFill([
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($this->ttlSeconds()),
            ])->save();

            return $connection->refresh();
        });
    }

    private function create(
        User $user,
        Client $client,
        string $participantType,
        string $contextKey,
    ): ScreenShareConnectionCredentials {
        $secret = Str::random(64);

        $connection = $this->context->withSystemContext(function () use ($user, $client, $participantType, $contextKey, $secret): ScreenShareConnection {
            return ScreenShareConnection::query()->create([
                'client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
                'participant_type' => $participantType,
                'context_key' => $contextKey,
                'secret_hash' => hash('sha256', $secret),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($this->ttlSeconds()),
            ]);
        });

        return new ScreenShareConnectionCredentials($connection, $secret);
    }

    private function ttlSeconds(): int
    {
        return max(120, (int) config('screen-share.presence_ttl_seconds', 120));
    }
}
