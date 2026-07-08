<?php

namespace App\Http\Middleware;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\AiProviderManager;
use App\Services\Notifications\NotificationCenter;
use App\Services\Portal\OnboardingWizard;
use App\Services\ServiceActivations\ServiceActivationNavigation;
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

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
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
            'auth' => [
                'user' => $request->user(),
            ],
            'aiNotice' => fn () => $request->user()
                ? $this->aiNotice()
                : null,
            'notificationSummary' => fn () => $request->user() instanceof User
                ? app(NotificationCenter::class)->summary($request->user())
                : null,
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
            'portalClient' => fn () => $this->portalClient($request),
            'portalServices' => fn () => $this->portalServices($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aiNotice(): ?array
    {
        $notice = app(AdvisorAiNotice::class);
        $latest = $notice->latest();

        if ($latest === null) {
            return null;
        }

        $reason = (string) ($latest['reason'] ?? '');
        if (
            str_contains($reason, 'not active or its credentials are missing')
            && app(AiProviderManager::class)->activeProviderIsLive()
        ) {
            $notice->clear();

            return null;
        }

        return $latest;
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
}
