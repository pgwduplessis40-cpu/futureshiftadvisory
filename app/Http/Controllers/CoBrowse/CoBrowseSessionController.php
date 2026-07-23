<?php

declare(strict_types=1);

namespace App\Http\Controllers\CoBrowse;

use App\Models\Client;
use App\Models\CoBrowseSession;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\CoBrowse\CoBrowseSessions;
use App\Services\CoBrowse\CoBrowseTargetRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CoBrowseSessionController
{
    public function __construct(
        private readonly CoBrowseSessions $sessions,
        private readonly CoBrowseTargetRegistry $targets,
    ) {}

    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $session = $this->sessions->requestForClient(
                $this->user($request),
                $client,
                User::query()->findOrFail($data['client_user_id']),
                $data['advisor_connection_id'],
                $data['advisor_connection_secret'],
            );
        } catch (QueryException $exception) {
            $this->throwOpenSessionMessage($exception);
        }

        return response()->json($this->payload($session), 201);
    }

    public function storeForEntrepreneur(Request $request, EntrepreneurProfile $entrepreneurProfile): JsonResponse
    {
        $data = $this->requestData($request);

        try {
            $session = $this->sessions->requestForEntrepreneur(
                $this->user($request),
                $entrepreneurProfile,
                User::query()->findOrFail($data['client_user_id']),
                $data['advisor_connection_id'],
                $data['advisor_connection_secret'],
            );
        } catch (QueryException $exception) {
            $this->throwOpenSessionMessage($exception);
        }

        return response()->json($this->payload($session), 201);
    }

    public function respond(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,decline'],
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'nonce' => ['required', 'string', 'size:64'],
        ]);
        $session = $this->sessions->respond(
            $this->user($request),
            $session,
            $data['connection_id'],
            $data['connection_secret'],
            $data['nonce'],
            $data['action'] === 'approve',
        );

        return response()->json($this->payload($session));
    }

    public function action(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'type' => ['required', 'in:pointer,clear_pointer,highlight,clear_highlight'],
            'payload' => ['present', 'array'],
        ]);
        $this->sessions->action(
            $this->user($request),
            $session,
            $data['connection_id'],
            $data['connection_secret'],
            $data['type'],
            $data['payload'],
        );

        return response()->json(status: 204);
    }

    public function pendingActions(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json([
            'actions' => $this->sessions->pendingActions(
                $this->user($request),
                $session,
                $data['connection_id'],
                $data['connection_secret'],
                (int) ($data['after_id'] ?? 0),
            ),
        ]);
    }

    public function status(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $this->participantData($request);
        $session = $this->sessions->status($this->user($request), $session, $data['connection_id'], $data['connection_secret']);

        return response()->json($this->payload($session));
    }

    public function heartbeat(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $this->participantData($request);
        $session = $this->sessions->heartbeat($this->user($request), $session, $data['connection_id'], $data['connection_secret']);

        return response()->json($this->payload($session));
    }

    public function end(Request $request, CoBrowseSession $session): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'in:completed_client_ended,completed_advisor_ended,client_revoked,connection_lost'],
        ]);
        $session = $this->sessions->end($this->user($request), $session, $data['connection_id'], $data['connection_secret'], $data['reason']);

        return response()->json($this->payload($session));
    }

    /** @return array{client_user_id:int,advisor_connection_id:string,advisor_connection_secret:string} */
    private function requestData(Request $request): array
    {
        return $request->validate([
            'client_user_id' => ['required', 'integer'],
            'advisor_connection_id' => ['required', 'uuid'],
            'advisor_connection_secret' => ['required', 'string', 'size:64'],
        ]);
    }

    /** @return array{connection_id:string,connection_secret:string} */
    private function participantData(Request $request): array
    {
        return $request->validate([
            'connection_id' => ['required', 'uuid'],
            'connection_secret' => ['required', 'string', 'size:64'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(CoBrowseSession $session): array
    {
        $routeKey = (string) ($session->consent_context['route_key'] ?? '');

        return [
            'id' => (string) $session->getKey(),
            'status' => $session->status,
            'client_response' => $session->client_response,
            'end_reason' => $session->end_reason,
            'expires_at' => $session->expires_at->toIso8601String(),
            'targets' => $this->targets->targetsFor($routeKey),
        ];
    }

    private function throwOpenSessionMessage(QueryException $exception): never
    {
        if (str_contains($exception->getMessage(), 'co_browse_one_open_')) {
            throw ValidationException::withMessages([
                'client_user_id' => 'An open guided-assistance session already exists for this client or advisor.',
            ]);
        }

        throw $exception;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
