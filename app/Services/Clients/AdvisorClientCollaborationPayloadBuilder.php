<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;

/**
 * Typed API contract for the advisor client-screen collaboration panels.
 * Browser clients receive endpoint templates and participant identities only;
 * credentials and signalling payloads remain server-owned.
 *
 * @phpstan-type CollaborationParticipant array{id:string,name:string}
 * @phpstan-type ScreenSharePayload array{
 *     connection_url:string,
 *     connection_heartbeat_url:string,
 *     request_url:string,
 *     ice_servers_url:string,
 *     active_url:string,
 *     signal_url:string,
 *     pending_signals_url:string,
 *     heartbeat_url:string,
 *     end_url:string,
 *     heartbeat_seconds:int,
 *     participants:list<CollaborationParticipant>
 * }
 * @phpstan-type CoBrowsePayload array{
 *     connection_url:string,
 *     connection_heartbeat_url:string,
 *     request_url:string,
 *     status_url:string,
 *     heartbeat_url:string,
 *     end_url:string,
 *     action_url:string,
 *     heartbeat_seconds:int,
 *     participants:list<CollaborationParticipant>
 * }
 */
final class AdvisorClientCollaborationPayloadBuilder
{
    /** @return ScreenSharePayload */
    public function screenShare(Client $client): array
    {
        return [
            'connection_url' => route('advisor.clients.screen-share.connections.store', $client, absolute: false),
            'connection_heartbeat_url' => route('screen-share.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'request_url' => route('advisor.clients.screen-share.sessions.store', $client, absolute: false),
            'ice_servers_url' => route('screen-share.sessions.ice-servers', ['session' => '__session__'], absolute: false),
            'active_url' => route('screen-share.sessions.active', ['session' => '__session__'], absolute: false),
            'signal_url' => route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false),
            'pending_signals_url' => route('screen-share.sessions.pending-signals', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('screen-share.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('screen-share.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('screen-share.heartbeat_interval_seconds', 10)),
            'participants' => $this->participants($client),
        ];
    }

    /** @return CoBrowsePayload|null */
    public function coBrowse(Client $client): ?array
    {
        if (! (bool) config('co-browse.enabled')) {
            return null;
        }

        return [
            'connection_url' => route('advisor.clients.co-browse.connections.store', $client, absolute: false),
            'connection_heartbeat_url' => route('co-browse.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'request_url' => route('advisor.clients.co-browse.sessions.store', $client, absolute: false),
            'status_url' => route('co-browse.sessions.status', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('co-browse.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('co-browse.sessions.end', ['session' => '__session__'], absolute: false),
            'action_url' => route('co-browse.sessions.actions.store', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('co-browse.heartbeat_interval_seconds', 10)),
            'participants' => $this->participants($client),
        ];
    }

    /** @return list<CollaborationParticipant> */
    private function participants(Client $client): array
    {
        return $client->teamMembers()
            ->with('user')
            ->get()
            ->map(fn (ClientTeamMember $member): ?array => $member->user instanceof User
                && in_array($member->user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
                ? ['id' => (string) $member->user->getKey(), 'name' => $member->user->name]
                : null)
            ->filter()
            ->values()
            ->all();
    }
}
