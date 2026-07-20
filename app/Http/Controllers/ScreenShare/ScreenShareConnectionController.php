<?php

declare(strict_types=1);

namespace App\Http\Controllers\ScreenShare;

use App\Models\Client;
use App\Models\ScreenShareConnection;
use App\Models\User;
use App\Services\ScreenShare\ScreenSharePresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScreenShareConnectionController
{
    public function __construct(private readonly ScreenSharePresence $presence) {}

    public function registerClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'portal_context_token' => ['required', 'string', 'max:4096'],
        ]);

        return response()->json($this->presence
            ->registerClient($this->user($request), $validated['portal_context_token'])
            ->toPayload());
    }

    public function registerAdvisor(Request $request, Client $client): JsonResponse
    {
        return response()->json($this->presence
            ->registerAdvisor($this->user($request), $client)
            ->toPayload());
    }

    public function heartbeat(Request $request, ScreenShareConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
        $updated = $this->presence->heartbeat($this->user($request), (string) $connection->getKey(), $validated['connection_secret']);

        return response()->json(['expires_at' => $updated->expires_at->toIso8601String()]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
