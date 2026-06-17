<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\User;
use App\Services\Calendar\CalendarConnector;
use App\Services\Calendar\CalendarSync;
use App\Services\Integration\Exceptions\IntegrationRequestFailedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarConnector $connector,
        private readonly CalendarSync $sync,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $this->calendarUser($request);
        $connections = CalendarConnection::query()
            ->forUser($user)
            ->latest()
            ->get();
        $connectedConnectionIds = $connections
            ->filter(fn (CalendarConnection $connection): bool => $connection->connected())
            ->pluck('id')
            ->all();

        $providerLabels = CalendarConnection::providerLabels();

        return Inertia::render('settings/calendar', [
            'providers' => collect($providerLabels)
                ->map(fn (string $label, string $provider): array => [
                    'provider' => $provider,
                    'label' => $label,
                    'connected' => $connections->contains(
                        fn (CalendarConnection $connection): bool => $connection->provider === $provider
                            && $connection->connected(),
                    ),
                    'connect_url' => route('calendar.connect', $provider, absolute: false),
                ])
                ->values()
                ->all(),
            'connections' => $connections
                ->map(fn (CalendarConnection $connection): array => $this->connectionPayload($connection))
                ->values()
                ->all(),
            'externalEvents' => CalendarEventMapping::query()
                ->whereIn('calendar_connection_id', $connectedConnectionIds)
                ->where('is_external_only', true)
                ->where('starts_at', '>=', now()->subDay())
                ->with('calendarConnection')
                ->orderBy('starts_at')
                ->limit(12)
                ->get()
                ->map(fn (CalendarEventMapping $mapping): array => $this->externalEventPayload($mapping))
                ->values()
                ->all(),
        ]);
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $user = $this->calendarUser($request);
        $this->validateProvider($provider);

        return redirect()->away($this->connector->authorizeUrl($user, $provider));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $user = $this->calendarUser($request);
        $this->validateProvider($provider);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $this->connector->connectFromCallback(
                user: $user,
                provider: $provider,
                code: $validated['code'],
                state: $validated['state'],
            );
        } catch (IntegrationRequestFailedException|InvalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Calendar connection failed. Check the provider configuration and try connecting again.')]);

            return to_route('calendar.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar connected.')]);

        return to_route('calendar.edit');
    }

    public function sync(Request $request, CalendarConnection $calendarConnection): RedirectResponse
    {
        $user = $this->calendarUser($request);
        $this->authorizeConnection($calendarConnection, $user);

        try {
            $this->sync->syncConnection($calendarConnection, $user);
        } catch (IntegrationRequestFailedException) {
            $calendarConnection->forceFill([
                'status' => CalendarConnection::STATUS_ERROR,
            ])->save();

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Calendar sync failed. Reconnect the calendar or check the provider configuration.')]);

            return to_route('calendar.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar sync completed.')]);

        return to_route('calendar.edit');
    }

    public function revoke(Request $request, CalendarConnection $calendarConnection): RedirectResponse
    {
        $user = $this->calendarUser($request);
        $this->authorizeConnection($calendarConnection, $user);

        $this->connector->revoke($calendarConnection, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar disconnected.')]);

        return to_route('calendar.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionPayload(CalendarConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'provider' => $connection->provider,
            'label' => $connection->providerLabel(),
            'external_account_email' => $connection->external_account_email,
            'status' => $connection->status,
            'token_expires_at' => $connection->token_expires_at?->toIso8601String(),
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'sync_url' => route('calendar.sync', $connection, absolute: false),
            'revoke_url' => route('calendar.revoke', $connection, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalEventPayload(CalendarEventMapping $mapping): array
    {
        $connection = $mapping->calendarConnection;

        return [
            'id' => $mapping->id,
            'provider' => $connection?->provider,
            'provider_label' => $connection?->providerLabel(),
            'title' => $mapping->title,
            'starts_at' => $mapping->starts_at?->toIso8601String(),
            'ends_at' => $mapping->ends_at?->toIso8601String(),
            'location' => $mapping->location,
            'attendees' => $mapping->attendees ?? [],
        ];
    }

    private function calendarUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($user->user_type, [
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
            User::TYPE_SUPER_ADMIN,
        ], true), 403);

        return $user;
    }

    private function validateProvider(string $provider): void
    {
        validator(
            ['provider' => $provider],
            ['provider' => ['required', 'string', Rule::in(CalendarConnection::providers())]],
        )->validate();
    }

    private function authorizeConnection(CalendarConnection $connection, User $user): void
    {
        abort_unless((string) $connection->user_id === (string) $user->getKey(), 404);
    }
}
