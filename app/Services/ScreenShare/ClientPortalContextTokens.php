<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class ClientPortalContextTokens
{
    public function __construct(private readonly RequestContext $context) {}

    public function issue(User $user, Client $client, string $routeKey): string
    {
        $this->assertDirectClientMembership($user, $client);

        return $this->encrypt([
            'user_id' => (string) $user->getKey(),
            'client_id' => (string) $client->getKey(),
            'route_key' => $routeKey,
        ]);
    }

    public function issueForEntrepreneur(User $user, EntrepreneurProfile $profile, string $routeKey): string
    {
        $this->assertEntrepreneurOwnership($user, $profile);

        return $this->encrypt([
            'user_id' => (string) $user->getKey(),
            'entrepreneur_profile_id' => (string) $profile->getKey(),
            'route_key' => $routeKey,
        ]);
    }

    /**
     * @param  array{user_id:string, route_key:string, client_id?:string, entrepreneur_profile_id?:string}  $payload
     */
    private function encrypt(array $payload): string
    {
        return Crypt::encryptString(json_encode([
            ...$payload,
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($this->ttlSeconds())->toIso8601String(),
            'nonce' => Str::random(64),
        ], JSON_THROW_ON_ERROR));
    }

    public function consume(User $user, string $token): ClientPortalContext
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The portal connection context is invalid.');
        }

        abort_unless(is_array($payload), 403);
        abort_unless(
            isset($payload['user_id'], $payload['route_key'], $payload['expires_at'], $payload['nonce'])
            && (string) $payload['user_id'] === (string) $user->getKey()
            && is_string($payload['route_key'])
            && is_string($payload['nonce']),
            403,
        );
        $clientId = $payload['client_id'] ?? null;
        $entrepreneurProfileId = $payload['entrepreneur_profile_id'] ?? null;
        abort_unless(
            (is_string($clientId) && ! is_string($entrepreneurProfileId))
            || (! is_string($clientId) && is_string($entrepreneurProfileId)),
            403,
        );

        abort_if(now()->greaterThanOrEqualTo($payload['expires_at']), 403, 'The portal connection context has expired.');
        abort_unless(Cache::add($this->nonceKey($payload['nonce']), true, now()->addSeconds($this->ttlSeconds())), 403);

        if (is_string($clientId)) {
            $client = $this->context->withSystemContext(fn (): ?Client => Client::query()->find($clientId));
            abort_unless($client instanceof Client, 403);
            $this->assertDirectClientMembership($user, $client);

            return new ClientPortalContext((string) $client->getKey(), $payload['route_key']);
        }

        $profile = $this->context->withSystemContext(fn (): ?EntrepreneurProfile => EntrepreneurProfile::query()->find($entrepreneurProfileId));
        abort_unless($profile instanceof EntrepreneurProfile, 403);
        $this->assertEntrepreneurOwnership($user, $profile);

        return new ClientPortalContext(null, $payload['route_key'], (string) $profile->getKey());
    }

    private function assertDirectClientMembership(User $user, Client $client): void
    {
        $member = $this->context->withSystemContext(fn (): bool => ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->exists());

        abort_unless($member, 403);
    }

    private function assertEntrepreneurOwnership(User $user, EntrepreneurProfile $profile): void
    {
        abort_unless($user->user_type === User::TYPE_ENTREPRENEUR, 403);
        abort_unless((string) $profile->user_id === (string) $user->getKey(), 403);
    }

    private function nonceKey(string $nonce): string
    {
        return 'screen-share:portal-context:'.hash('sha256', $nonce);
    }

    private function ttlSeconds(): int
    {
        return max(30, (int) config('screen-share.portal_context_ttl_seconds', 300));
    }
}
