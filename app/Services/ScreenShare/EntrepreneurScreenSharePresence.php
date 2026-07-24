<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\EntrepreneurProfile;
use App\Models\ScreenShareConnection;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class EntrepreneurScreenSharePresence
{
    public function __construct(
        private readonly ClientPortalContextTokens $portalContexts,
        private readonly ScreenShareAuthorizer $authorizer,
        private readonly RequestContext $context,
    ) {}

    public function registerPortalParticipant(
        User $user,
        string $portalContextToken,
    ): ScreenShareConnectionCredentials {
        $portalContext = $this->portalContexts->consume($user, $portalContextToken);
        abort_unless($portalContext->entrepreneurProfileId !== null, 403);
        $profile = $this->context->withSystemContext(
            fn (): EntrepreneurProfile => EntrepreneurProfile::query()->findOrFail($portalContext->entrepreneurProfileId),
        );

        return $this->create(
            $user,
            $profile,
            ScreenShareConnection::TYPE_CLIENT,
            $portalContext->routeKey,
        );
    }

    public function registerAdvisor(
        User $advisor,
        EntrepreneurProfile $profile,
    ): ScreenShareConnectionCredentials {
        $entrepreneur = $this->context->withSystemContext(
            fn (): User => User::query()->findOrFail($profile->user_id),
        );
        $this->authorizer->assertCanRequestForEntrepreneur($advisor, $profile, $entrepreneur);

        return $this->create(
            $advisor,
            $profile,
            ScreenShareConnection::TYPE_ADVISOR,
            'advisor.entrepreneurs.show',
        );
    }

    /**
     * @return Collection<int, ScreenShareConnection>
     */
    public function activeConnectionsFor(
        EntrepreneurProfile $profile,
        User $entrepreneur,
    ): Collection {
        return $this->context->withSystemContext(fn (): Collection => ScreenShareConnection::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('user_id', $entrepreneur->getKey())
            ->where('participant_type', ScreenShareConnection::TYPE_CLIENT)
            ->where('expires_at', '>', now())
            ->get());
    }

    private function create(
        User $user,
        EntrepreneurProfile $profile,
        string $participantType,
        string $contextKey,
    ): ScreenShareConnectionCredentials {
        $secret = Str::random(64);

        $connection = $this->context->withSystemContext(
            fn (): ScreenShareConnection => ScreenShareConnection::query()->create([
                'client_id' => null,
                'entrepreneur_profile_id' => $profile->getKey(),
                'user_id' => $user->getKey(),
                'participant_type' => $participantType,
                'context_key' => $contextKey,
                'secret_hash' => hash('sha256', $secret),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($this->ttlSeconds()),
            ]),
        );

        return new ScreenShareConnectionCredentials($connection, $secret);
    }

    private function ttlSeconds(): int
    {
        return max(120, (int) config('screen-share.presence_ttl_seconds', 120));
    }
}
