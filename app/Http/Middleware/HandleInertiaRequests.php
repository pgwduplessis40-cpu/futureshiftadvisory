<?php

namespace App\Http\Middleware;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Notifications\NotificationCenter;
use App\Services\Portal\OnboardingWizard;
use App\Services\ScreenShare\ClientPortalContextTokens;
use App\Services\ServiceActivations\ServiceActivationNavigation;
use App\Support\ReleaseVersion;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly ReleaseVersion $releaseVersion,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $assetVersion = parent::version($request);
        $releaseVersion = $this->releaseVersion->current();

        if ($releaseVersion === '') {
            return $assetVersion;
        }

        return $assetVersion !== null
            ? $releaseVersion.'-'.$assetVersion
            : $releaseVersion;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'publicUrl' => config('app.public_url'),
            'releaseVersion' => $this->releaseVersion->current(),
            'auth' => [
                'user' => $request->user(),
            ],
            'notificationSummary' => fn () => $request->user() instanceof User
                ? app(NotificationCenter::class)->summary($request->user())
                : null,
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
            'portalClient' => fn () => $this->portalClient($request),
            'portalServices' => fn () => $this->portalServices($request),
            'portalScreenShare' => fn () => $this->portalScreenShare($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function portalClientModel(Request $request): ?Client
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
        ) {
            return null;
        }

        $clientIds = $user->accessibleClientIds();
        if ($clientIds === []) {
            return null;
        }

        return Client::query()
            ->whereIn('id', $clientIds)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function portalClient(Request $request): ?array
    {
        $client = $this->portalClientModel($request);

        if (! $client instanceof Client) {
            return null;
        }

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => is_string($client->engagement_type)
                ? $client->engagement_type
                : $client->engagement_type?->value,
            'onboarding_complete' => app(OnboardingWizard::class)->state($client)['submitted_at'] !== null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function portalServices(Request $request): ?array
    {
        $client = $this->portalClientModel($request);

        if (! $client instanceof Client) {
            return null;
        }

        return app(ServiceActivationNavigation::class)->payload($client);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function portalScreenShare(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->routeIs('portal.*')) {
            return null;
        }

        if ($user->user_type === User::TYPE_ENTREPRENEUR) {
            $profile = EntrepreneurProfile::query()
                ->where('user_id', $user->getKey())
                ->latest()
                ->first();

            if (! $profile instanceof EntrepreneurProfile) {
                return null;
            }

            return $this->screenSharePayload(
                app(ClientPortalContextTokens::class)->issueForEntrepreneur(
                    $user,
                    $profile,
                    'portal.entrepreneur.dashboard',
                ),
                route('portal.entrepreneur-screen-share.connections.store', absolute: false),
            );
        }

        if (! in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)) {
            return null;
        }

        $client = $this->portalClientModel($request);

        if (! $client instanceof Client) {
            return null;
        }

        return $this->screenSharePayload(
            app(ClientPortalContextTokens::class)->issue($user, $client, 'portal.dashboard'),
            route('portal.screen-share.connections.store', absolute: false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function screenSharePayload(string $portalContextToken, string $connectionUrl): array
    {
        return [
            'portal_context_token' => $portalContextToken,
            'connection_url' => $connectionUrl,
            'prompt_url' => route('screen-share.connections.pending-prompt', ['connection' => '__connection__'], absolute: false),
            'connection_heartbeat_url' => route('screen-share.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'response_url' => route('portal.screen-share.sessions.response', ['session' => '__session__'], absolute: false),
            'browser_permission_url' => route('portal.screen-share.sessions.browser-permission', ['session' => '__session__'], absolute: false),
            'ice_servers_url' => route('screen-share.sessions.ice-servers', ['session' => '__session__'], absolute: false),
            'active_url' => route('screen-share.sessions.active', ['session' => '__session__'], absolute: false),
            'signal_url' => route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false),
            'pending_signals_url' => route('screen-share.sessions.pending-signals', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('screen-share.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('screen-share.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('screen-share.heartbeat_interval_seconds', 10)),
            'warning_at_minutes' => max(0, (int) config('screen-share.warning_at_minutes', 25)),
        ];
    }
}
