<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\ScreenShareConnection;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Support\RequestContext;

final class ScreenShareIceServers
{
    public function __construct(
        private readonly ScreenSharePresence $presence,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array<int, array<string, string|array<int, string>>>
     */
    public function forParticipant(
        User $user,
        ScreenShareSession $session,
        string $connectionId,
        string $connectionSecret,
    ): array {
        $connection = $this->presence->assertConnection($user, $connectionId, $connectionSecret);

        $this->context->withSystemContext(function () use ($connection, $session, $user): void {
            $locked = ScreenShareSession::query()->findOrFail($session->getKey());
            abort_unless(in_array($locked->status, [
                ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER,
                ScreenShareSession::STATUS_ACTIVE,
            ], true), 409);
            abort_unless(
                ((string) $locked->advisor_id === (string) $user->getKey()
                    && (string) $locked->advisor_connection_id === (string) $connection->getKey())
                || ((string) $locked->client_user_id === (string) $user->getKey()
                    && (string) $locked->client_connection_id === (string) $connection->getKey()),
                403,
            );
        });

        $turnUrls = $this->urls('turn_urls');
        $turnSecret = (string) config('screen-share.turn_shared_secret');
        abort_unless($turnUrls !== [] && $turnSecret !== '', 503, 'Screen support relay is unavailable.');

        $expiresAt = now()->addSeconds(max(60, (int) config('screen-share.turn_ttl_seconds', 600)));
        $username = $expiresAt->getTimestamp().':screen-share:'.(string) $session->getKey();
        $credential = base64_encode(hash_hmac('sha1', $username, $turnSecret, true));
        $servers = [];
        $stunUrls = $this->urls('stun_urls');

        if ($stunUrls !== []) {
            $servers[] = ['urls' => $stunUrls];
        }

        $servers[] = [
            'urls' => $turnUrls,
            'username' => $username,
            'credential' => $credential,
        ];

        return $servers;
    }

    /**
     * @return array<int, string>
     */
    private function urls(string $key): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', (string) config('screen-share.'.$key, ''))),
        ));
    }
}
