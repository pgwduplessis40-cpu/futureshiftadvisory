<?php

declare(strict_types=1);

namespace App\Http\Controllers\ScreenShare;

use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\ScreenShare\EntrepreneurScreenSharePresence;
use App\Services\ScreenShare\EntrepreneurScreenShareRequests;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class EntrepreneurScreenShareController
{
    public function __construct(
        private readonly EntrepreneurScreenSharePresence $presence,
        private readonly EntrepreneurScreenShareRequests $requests,
    ) {}

    public function registerPortalParticipant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'portal_context_token' => ['required', 'string', 'max:4096'],
        ]);

        return response()->json($this->presence
            ->registerPortalParticipant($this->user($request), $validated['portal_context_token'])
            ->toPayload());
    }

    public function registerAdvisor(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
    ): JsonResponse {
        return response()->json($this->presence
            ->registerAdvisor($this->user($request), $entrepreneurProfile)
            ->toPayload());
    }

    public function store(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
    ): JsonResponse {
        $data = $request->validate([
            'client_user_id' => ['required', 'integer'],
            'advisor_connection_id' => ['required', 'uuid'],
            'advisor_connection_secret' => ['required', 'string', 'size:64'],
        ]);

        try {
            $session = $this->requests->request(
                $this->user($request),
                $entrepreneurProfile,
                User::query()->findOrFail($data['client_user_id']),
                $data['advisor_connection_id'],
                $data['advisor_connection_secret'],
            );
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'screen_share_one_open_')) {
                throw ValidationException::withMessages([
                    'client_user_id' => 'An open screen-support session already exists for this entrepreneur or advisor.',
                ]);
            }

            throw $exception;
        }

        return response()->json([
            'id' => (string) $session->getKey(),
            'status' => $session->status,
            'client_response' => $session->client_response,
            'end_reason' => $session->end_reason,
            'expires_at' => $session->expires_at->toIso8601String(),
            'display_surface' => $session->display_surface,
        ], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
