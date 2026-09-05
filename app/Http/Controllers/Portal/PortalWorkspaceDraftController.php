<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\PortalWorkspaceDrafts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type WorkspaceDraftPayload from PortalWorkspaceDrafts */
final class PortalWorkspaceDraftController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly PortalWorkspaceDrafts $drafts,
    ) {}

    public function show(Request $request, string $draftKey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $client = $user->user_type === User::TYPE_ENTREPRENEUR
            ? null
            : $this->clients->resolveFor($request);
        $this->assertAllowedKey($draftKey);

        return response()->json([
            'payload' => $this->drafts->payload($user, $draftKey),
            'saved_at' => $this->drafts->savedAt($user, $draftKey),
        ]);
    }

    public function store(Request $request, string $draftKey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $client = $user->user_type === User::TYPE_ENTREPRENEUR
            ? null
            : $this->clients->resolveFor($request);
        $this->assertAllowedKey($draftKey);

        $payload = $request->validate([
            'payload' => ['required', 'array', 'max:100'],
        ])['payload'];
        /** @var WorkspaceDraftPayload $payload */
        $encoded = json_encode($payload);
        if (! is_string($encoded) || strlen($encoded) > 64_000) {
            throw ValidationException::withMessages([
                'payload' => 'This draft is too large to save safely.',
            ]);
        }

        $draft = $this->drafts->save($user, $client, $draftKey, $payload);

        return response()->json([
            'saved_at' => $draft->saved_at?->toIso8601String(),
        ]);
    }

    private function assertAllowedKey(string $draftKey): void
    {
        abort_unless((bool) preg_match('/^(wellbeing|service-request|dd-questionnaire|onboarding|outcome|message|npo-metric|strategic-milestone)(?::[A-Za-z0-9._-]+)*$/', $draftKey), 404);
    }
}
