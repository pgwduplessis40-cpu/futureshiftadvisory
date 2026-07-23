<?php

declare(strict_types=1);

namespace App\Http\Controllers\CoBrowse;

use App\Models\Client;
use App\Models\CoBrowseConnection;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\CoBrowse\CoBrowsePresence;
use App\Services\CoBrowse\CoBrowseSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CoBrowseConnectionController
{
    public function __construct(
        private readonly CoBrowsePresence $presence,
        private readonly CoBrowseSessions $sessions,
    ) {}

    public function registerClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'portal_context_token' => ['required', 'string', 'max:4096'],
        ]);

        return response()->json($this->presence->registerClient($this->user($request), $data['portal_context_token'])->toPayload());
    }

    public function registerAdvisor(Request $request, Client $client): JsonResponse
    {
        return response()->json($this->presence->registerAdvisorForClient($this->user($request), $client)->toPayload());
    }

    public function registerAdvisorForEntrepreneur(Request $request, EntrepreneurProfile $entrepreneurProfile): JsonResponse
    {
        return response()->json($this->presence->registerAdvisorForEntrepreneur($this->user($request), $entrepreneurProfile)->toPayload());
    }

    public function heartbeat(Request $request, CoBrowseConnection $connection): JsonResponse
    {
        $data = $request->validate([
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
        $updated = $this->presence->heartbeat($this->user($request), (string) $connection->getKey(), $data['connection_secret']);

        return response()->json(['expires_at' => $updated->expires_at->toIso8601String()]);
    }

    public function pendingPrompt(Request $request, CoBrowseConnection $connection): JsonResponse
    {
        $data = $request->validate([
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
        $user = $this->user($request);
        $clientConnection = $this->presence->assertConnection(
            $user,
            (string) $connection->getKey(),
            $data['connection_secret'],
            CoBrowseConnection::TYPE_CLIENT,
        );

        return response()->json(['prompt' => $this->sessions->pendingPrompt($user, $clientConnection)]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
