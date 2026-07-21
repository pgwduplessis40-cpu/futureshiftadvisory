<?php

declare(strict_types=1);

namespace App\Http\Controllers\ScreenShare;

use App\Models\Client;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Services\ScreenShare\ScreenShareIceServers;
use App\Services\ScreenShare\ScreenShareSessions;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ScreenShareSessionController
{
    public function __construct(
        private readonly ScreenShareIceServers $iceServers,
        private readonly ScreenShareSessions $sessions,
    ) {}

    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'client_user_id' => ['required', 'integer'],
            'advisor_connection_id' => ['required', 'uuid'],
            'advisor_connection_secret' => ['required', 'string', 'size:64'],
        ]);

        try {
            $session = $this->sessions->request(
                $this->user($request),
                $client,
                User::query()->findOrFail($data['client_user_id']),
                $data['advisor_connection_id'],
                $data['advisor_connection_secret'],
            );
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'screen_share_one_open_')) {
                throw ValidationException::withMessages([
                    'client_user_id' => 'An open screen-support session already exists for this client or advisor.',
                ]);
            }

            throw $exception;
        }

        return response()->json($this->payload($session), 201);
    }

    public function respond(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,decline'],
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'nonce' => ['required', 'string', 'size:64'],
        ]);
        $session = $this->sessions->respond($this->user($request), $session, $data['connection_id'], $data['connection_secret'], $data['nonce'], $data['action'] === 'approve');

        return response()->json($this->payload($session));
    }

    public function browserPermission(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'granted' => ['required', 'boolean'],
            'display_surface' => ['nullable', 'in:browser,window,monitor'],
        ]);
        $session = $this->sessions->recordBrowserPermission($this->user($request), $session, $data['connection_id'], $data['connection_secret'], (bool) $data['granted'], $data['display_surface'] ?? null);

        return response()->json($this->payload($session));
    }

    public function active(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
        $session = $this->sessions->markActive($this->user($request), $session, $data['connection_id'], $data['connection_secret']);

        return response()->json($this->payload($session));
    }

    public function signal(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'type' => ['required', 'in:offer,answer,candidate'],
            'payload' => ['required', 'array'],
        ]);
        $payload = $this->normalizeSignalPayload($data['type'], $data['payload']);
        abort_if(strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 9_000, 422);
        $this->sessions->signal($this->user($request), $session, $data['connection_id'], $data['connection_secret'], $data['type'], $payload);

        return response()->json(status: 204);
    }

    public function pendingSignals(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json([
            'signals' => $this->sessions->pendingSignals(
                $this->user($request),
                $session,
                $data['connection_id'],
                $data['connection_secret'],
                (int) ($data['after_id'] ?? 0),
            ),
        ]);
    }

    public function iceServers(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);

        return response()->json($this->iceServers->forParticipant(
            $this->user($request),
            $session,
            $data['connection_id'],
            $data['connection_secret'],
        ));
    }

    public function heartbeat(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
        $session = $this->sessions->heartbeat($this->user($request), $session, $data['connection_id'], $data['connection_secret']);

        return response()->json($this->payload($session));
    }

    public function end(Request $request, ScreenShareSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'in:completed_client_ended,completed_advisor_ended,client_navigated_away,connection_lost'],
        ]);
        $session = $this->sessions->end($this->user($request), $session, $data['connection_id'], $data['connection_secret'], $data['reason']);

        return response()->json($this->payload($session));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSignalPayload(string $type, array $payload): array
    {
        if (! in_array($type, ['offer', 'answer'], true)) {
            return $payload;
        }

        abort_unless(($payload['type'] ?? null) === $type, 422, 'The session description type is invalid.');
        abort_unless(is_string($payload['sdp'] ?? null) && $payload['sdp'] !== '', 422, 'A session description is required.');

        $payload['sdp'] = rtrim($payload['sdp'], "\r\n")."\r\n";

        return $payload;
    }

    /** @return array<string, mixed> */
    private function payload(ScreenShareSession $session): array
    {
        return [
            'id' => (string) $session->getKey(),
            'status' => $session->status,
            'client_response' => $session->client_response,
            'end_reason' => $session->end_reason,
            'expires_at' => $session->expires_at->toIso8601String(),
            'display_surface' => $session->display_surface,
        ];
    }
}
