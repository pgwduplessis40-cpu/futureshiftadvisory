<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\ScreenShare\ClientPortalContextTokens;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurPlanWorkspaceController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanWorkspacePayload $payload,
        private readonly ClientPortalContextTokens $portalContexts,
    ) {}

    public function show(Request $request): Response
    {
        $profile = $this->workspace->profileFor($this->workspace->user($request));

        return Inertia::render('portal/entrepreneur/Plan', [
            ...$this->payload->show($profile),
            'coBrowse' => $this->coBrowsePayload($request, $profile),
        ]);
    }

    /** @return array<string, int|string>|null */
    private function coBrowsePayload(Request $request, EntrepreneurProfile $profile): ?array
    {
        $user = $request->user();

        if (! (bool) config('co-browse.enabled')
            || ! $user instanceof User
            || $user->user_type !== User::TYPE_ENTREPRENEUR
            || (string) $profile->user_id !== (string) $user->getKey()) {
            return null;
        }

        return [
            'portal_context_token' => $this->portalContexts->issueForEntrepreneur(
                $user,
                $profile,
                'portal.entrepreneur.plan',
            ),
            'connection_url' => route('portal.co-browse.connections.store', absolute: false),
            'prompt_url' => route('co-browse.connections.pending-prompt', ['connection' => '__connection__'], absolute: false),
            'connection_heartbeat_url' => route('co-browse.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'response_url' => route('portal.co-browse.sessions.response', ['session' => '__session__'], absolute: false),
            'pending_actions_url' => route('co-browse.sessions.pending-actions', ['session' => '__session__'], absolute: false),
            'status_url' => route('co-browse.sessions.status', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('co-browse.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('co-browse.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('co-browse.heartbeat_interval_seconds', 10)),
        ];
    }
}
