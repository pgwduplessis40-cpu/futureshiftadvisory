<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Events\ScreenSharePrompt;
use App\Models\EntrepreneurProfile;
use App\Models\ScreenShareConnection;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EntrepreneurScreenShareRequests
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ScreenShareAuthorizer $authorizer,
        private readonly EntrepreneurScreenSharePresence $presence,
        private readonly ScreenSharePresence $connections,
        private readonly RequestContext $context,
    ) {}

    public function request(
        User $advisor,
        EntrepreneurProfile $profile,
        User $entrepreneur,
        string $advisorConnectionId,
        string $advisorConnectionSecret,
    ): ScreenShareSession {
        $attachment = $this->authorizer->assertCanRequestForEntrepreneur($advisor, $profile, $entrepreneur);
        $advisorConnection = $this->connections->assertConnection(
            $advisor,
            $advisorConnectionId,
            $advisorConnectionSecret,
            ScreenShareConnection::TYPE_ADVISOR,
        );
        abort_unless((string) $advisorConnection->entrepreneur_profile_id === (string) $profile->getKey(), 403);

        $connections = $this->presence->activeConnectionsFor($profile, $entrepreneur);
        if ($connections->isEmpty()) {
            $this->auditRequestFailure(
                $advisor,
                $profile,
                $entrepreneur,
                $advisorConnection,
                $this->presence->latestConnectionFor($profile, $entrepreneur),
                $this->connections->latestClientParticipantConnectionFor($entrepreneur),
                'no_active_entrepreneur_connection',
            );

            abort(422, $this->offlineMessage('entrepreneur', $entrepreneur));
        }

        [$session, $deliveries] = $this->context->withSystemContext(function () use ($advisor, $advisorConnection, $attachment, $connections, $entrepreneur, $profile): array {
            return DB::transaction(function () use ($advisor, $advisorConnection, $attachment, $connections, $entrepreneur, $profile): array {
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
                        'nonce_encrypted' => Crypt::encryptString($nonce),
                        'context_key' => $connection->context_key,
                        'prompted_at' => $now->toIso8601String(),
                        'expires_at' => $deadline->toIso8601String(),
                    ];
                })->all();

                $session = ScreenShareSession::query()->create([
                    'client_id' => null,
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'client_user_id' => $entrepreneur->getKey(),
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
            'client_user_id' => (string) $entrepreneur->getKey(),
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

    /**
     * @return array{key:string, label:string}
     */
    private function contextCopy(string $key): array
    {
        return match ($key) {
            'portal.entrepreneur.dashboard' => ['key' => $key, 'label' => 'your entrepreneur workspace'],
            default => ['key' => 'portal.generic', 'label' => 'the page you are currently on'],
        };
    }

    private function requestTimeoutSeconds(): int
    {
        return max(15, (int) config('screen-share.request_timeout_seconds', 60));
    }

    private function offlineMessage(string $role, User $user): string
    {
        $name = trim($user->name ?: $user->email ?: 'The selected '.$role);

        return $name.' is not connected to screen support. Ask them to open or refresh the Future Shift portal and keep the tab visible, then request view again.';
    }

    private function auditRequestFailure(
        User $advisor,
        EntrepreneurProfile $profile,
        User $entrepreneur,
        ScreenShareConnection $advisorConnection,
        ?ScreenShareConnection $latestConnection,
        ?ScreenShareConnection $latestUserConnection,
        string $reason,
    ): void {
        $this->audit->record('screen_share.request_failed', subject: $profile, actor: $advisor, after: [
            'reason' => $reason,
            'target_scope' => 'entrepreneur',
            'target_entrepreneur_profile_id' => (string) $profile->getKey(),
            'selected_user_id' => (string) $entrepreneur->getKey(),
            'advisor_connection_id' => (string) $advisorConnection->getKey(),
            'latest_client_connection' => $this->connectionSnapshot($latestConnection),
            'latest_user_connection' => $this->connectionSnapshot($latestUserConnection),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function connectionSnapshot(?ScreenShareConnection $connection): ?array
    {
        if (! $connection instanceof ScreenShareConnection) {
            return null;
        }

        return [
            'connection_id' => (string) $connection->getKey(),
            'client_id' => $connection->client_id === null ? null : (string) $connection->client_id,
            'entrepreneur_profile_id' => $connection->entrepreneur_profile_id === null ? null : (string) $connection->entrepreneur_profile_id,
            'context_key' => $connection->context_key,
            'last_seen_at' => $connection->last_seen_at?->toIso8601String(),
            'expires_at' => $connection->expires_at?->toIso8601String(),
            'expired' => $connection->expires_at?->isPast(),
        ];
    }
}
