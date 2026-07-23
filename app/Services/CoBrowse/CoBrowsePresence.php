<?php

declare(strict_types=1);

namespace App\Services\CoBrowse;

use App\Models\Client;
use App\Models\CoBrowseConnection;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\ScreenShare\ClientPortalContextTokens;
use App\Services\ScreenShare\ScreenShareAuthorizer;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class CoBrowsePresence
{
    public function __construct(
        private readonly ClientPortalContextTokens $portalContexts,
        private readonly ScreenShareAuthorizer $authorizer,
        private readonly CoBrowseTargetRegistry $targets,
        private readonly RequestContext $context,
    ) {}

    public function registerClient(User $user, string $portalContextToken): CoBrowseConnectionCredentials
    {
        $this->assertEnabled();
        $portalContext = $this->portalContexts->consume($user, $portalContextToken);

        if ($portalContext->clientId !== null) {
            $client = $this->context->withSystemContext(
                fn (): Client => Client::query()->findOrFail($portalContext->clientId),
            );
            $this->authorizer->assertClientMembership($user, $client);

            return $this->create($user, $client, null, CoBrowseConnection::TYPE_CLIENT, $portalContext->routeKey);
        }

        abort_unless($portalContext->entrepreneurProfileId !== null, 403);
        $profile = $this->context->withSystemContext(
            fn (): EntrepreneurProfile => EntrepreneurProfile::query()->findOrFail($portalContext->entrepreneurProfileId),
        );
        abort_unless($user->user_type === User::TYPE_ENTREPRENEUR, 403);
        abort_unless((string) $profile->user_id === (string) $user->getKey(), 403);

        return $this->create($user, null, $profile, CoBrowseConnection::TYPE_CLIENT, $portalContext->routeKey);
    }

    public function registerAdvisorForClient(User $advisor, Client $client): CoBrowseConnectionCredentials
    {
        $this->assertEnabled();
        $placeholder = $this->context->withSystemContext(fn (): ?User => $client->teamMembers()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('user_type', [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM]))
            ->first()
            ?->user);
        abort_unless($placeholder instanceof User, 403);
        $this->authorizer->assertCanRequest($advisor, $client, $placeholder);

        return $this->create($advisor, $client, null, CoBrowseConnection::TYPE_ADVISOR, 'advisor.clients.show');
    }

    public function registerAdvisorForEntrepreneur(User $advisor, EntrepreneurProfile $profile): CoBrowseConnectionCredentials
    {
        $this->assertEnabled();
        $entrepreneur = $this->context->withSystemContext(fn (): ?User => User::query()->find($profile->user_id));
        abort_unless($entrepreneur instanceof User, 403);
        $this->authorizer->assertCanRequestForEntrepreneur($advisor, $profile, $entrepreneur);

        return $this->create($advisor, null, $profile, CoBrowseConnection::TYPE_ADVISOR, 'advisor.entrepreneurs.show');
    }

    /** @return Collection<int, CoBrowseConnection> */
    public function activeClientConnections(Client $client, User $clientUser): Collection
    {
        return $this->connectionsFor('client_id', (string) $client->getKey(), $clientUser);
    }

    /** @return Collection<int, CoBrowseConnection> */
    public function activeEntrepreneurConnections(EntrepreneurProfile $profile, User $entrepreneur): Collection
    {
        return $this->connectionsFor('entrepreneur_profile_id', (string) $profile->getKey(), $entrepreneur);
    }

    public function assertConnection(
        User $user,
        string $connectionId,
        string $secret,
        ?string $participantType = null,
    ): CoBrowseConnection {
        return $this->context->withSystemContext(function () use ($user, $connectionId, $secret, $participantType): CoBrowseConnection {
            $connection = CoBrowseConnection::query()
                ->whereKey($connectionId)
                ->where('user_id', $user->getKey())
                ->where('expires_at', '>', now())
                ->first();

            abort_unless($connection instanceof CoBrowseConnection, 403);
            abort_unless($participantType === null || $connection->participant_type === $participantType, 403);
            abort_unless(hash_equals($connection->secret_hash, hash('sha256', $secret)), 403);

            return $connection;
        });
    }

    public function heartbeat(User $user, string $connectionId, string $secret): CoBrowseConnection
    {
        $connection = $this->assertConnection($user, $connectionId, $secret);

        return $this->context->withSystemContext(function () use ($connection): CoBrowseConnection {
            $connection->forceFill([
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($this->ttlSeconds()),
            ])->save();

            return $connection->refresh();
        });
    }

    private function create(
        User $user,
        ?Client $client,
        ?EntrepreneurProfile $profile,
        string $participantType,
        string $contextKey,
    ): CoBrowseConnectionCredentials {
        if ($participantType === CoBrowseConnection::TYPE_CLIENT) {
            abort_unless($this->targets->targetsFor($contextKey) !== [], 403, 'Guided assistance is unavailable on this page.');
        }
        $secret = Str::random(64);

        $connection = $this->context->withSystemContext(fn (): CoBrowseConnection => CoBrowseConnection::query()->create([
            'client_id' => $client?->getKey(),
            'entrepreneur_profile_id' => $profile?->getKey(),
            'user_id' => $user->getKey(),
            'participant_type' => $participantType,
            'context_key' => $contextKey,
            'secret_hash' => hash('sha256', $secret),
            'last_seen_at' => now(),
            'expires_at' => now()->addSeconds($this->ttlSeconds()),
        ]));

        return new CoBrowseConnectionCredentials($connection, $secret);
    }

    /** @return Collection<int, CoBrowseConnection> */
    private function connectionsFor(string $scopeColumn, string $scopeId, User $user): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => CoBrowseConnection::query()
            ->where($scopeColumn, $scopeId)
            ->where('user_id', $user->getKey())
            ->where('participant_type', CoBrowseConnection::TYPE_CLIENT)
            ->where('expires_at', '>', now())
            ->get()
            ->filter(fn (CoBrowseConnection $connection): bool => $this->targets->targetsFor($connection->context_key) !== []
            )
            ->values());
    }

    private function ttlSeconds(): int
    {
        return max(20, (int) config('co-browse.presence_ttl_seconds', 45));
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('co-browse.enabled'), 404);
    }
}
