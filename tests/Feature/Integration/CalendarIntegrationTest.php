<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\EngagementType;
use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IntegrationCall;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Calendar\CalendarClientResolver;
use App\Services\Calendar\CalendarConnector;
use App\Services\Calendar\CalendarSync;
use App\Services\Integration\Exceptions\IntegrationRequestFailedException;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\FakeGoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\FallbackGoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\LiveGoogleCalendarClient;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;
use App\Services\Integration\MicrosoftGraph\FakeMicrosoftGraphClient;
use App\Services\Integration\MicrosoftGraph\FallbackMicrosoftGraphClient;
use App\Services\Integration\MicrosoftGraph\LiveMicrosoftGraphClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Throwable;

final class CalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_calendar_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Cache::flush();

        foreach (CalendarConnection::providers() as $provider) {
            Config::set("integrations.calendar.{$provider}.live", false);
            Config::set("integrations.calendar.{$provider}.client_id", null);
            Config::set("integrations.calendar.{$provider}.client_secret", null);
            Config::set("integrations.calendar.{$provider}.authorize_url", "https://{$provider}.example.test/oauth");
        }

        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        $this->forgetCalendarClients();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON calendar_connections, calendar_event_mappings FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_oauth_callback_stores_encrypted_calendar_tokens(): void
    {
        $advisor = $this->advisor('calendar-oauth@example.test');
        $state = $this->connectState($advisor, CalendarConnection::PROVIDER_GOOGLE);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.callback', [
                CalendarConnection::PROVIDER_GOOGLE,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('calendar.edit', absolute: false));

        /** @var CalendarConnection $connection */
        $connection = CalendarConnection::query()->firstOrFail();

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $connection->provider);
        $this->assertSame(CalendarConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('google-fixture-account', $connection->external_account_id);
        $this->assertStringNotContainsString('google-access-token-fixture', $connection->access_token_envelope);
        $this->assertStringNotContainsString('google-refresh-token-fixture', (string) $connection->refresh_token_envelope);
        $this->assertSame(KeyEnvelope::ALG_V1, $connection->access_token_envelope_meta['alg']);
        $this->assertSame(KeyEnvelope::ALG_V1, $connection->refresh_token_envelope_meta['alg']);
        $this->assertSame('google-access-token-fixture', app(KeyEnvelope::class)->decrypt($connection->access_token_envelope));
        $this->assertSame('google-refresh-token-fixture', app(KeyEnvelope::class)->decrypt((string) $connection->refresh_token_envelope));
        $this->assertDatabaseHas('audit_events', ['action' => 'calendar_connection.connected']);
    }

    public function test_fixture_sync_pushes_meetings_pulls_external_events_and_is_idempotent(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $connection = $this->connectedGoogle($advisor);
        $meeting = $this->meeting($client, $advisor, 'Board prep');

        $this->actingAsMfa($advisor)
            ->post(route('calendar.sync', $connection))
            ->assertRedirect(route('calendar.edit', absolute: false));

        $connection->refresh();
        $this->assertDatabaseCount('calendar_event_mappings', 2);
        $this->assertDatabaseHas('calendar_event_mappings', [
            'calendar_connection_id' => $connection->id,
            'meeting_id' => $meeting->id,
            'external_event_id' => 'google:meeting:'.$meeting->id,
            'is_external_only' => false,
        ]);
        $this->assertDatabaseHas('calendar_event_mappings', [
            'calendar_connection_id' => $connection->id,
            'external_event_id' => 'google:external:advisory-roundtable',
            'is_external_only' => true,
        ]);
        $firstSyncToken = $connection->sync_token;

        $this->actingAsMfa($advisor)
            ->post(route('calendar.sync', $connection))
            ->assertRedirect(route('calendar.edit', absolute: false));

        $this->assertDatabaseCount('calendar_event_mappings', 2);
        $this->assertNotSame($firstSyncToken, $connection->refresh()->sync_token);
        $this->assertDatabaseHas('audit_events', ['action' => 'calendar_connection.synced']);
    }

    public function test_meeting_creation_pushes_to_connected_calendar_and_surfaces_synced_state(): void
    {
        [$advisor, $client] = $this->advisorAndClient('calendar-meeting@example.test');
        $this->connectedGoogle($advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.meetings.store', $client), [
                'title' => 'Quarterly review',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'location' => 'Teams',
                'attendees' => 'Owner, Advisor',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        /** @var Meeting $meeting */
        $meeting = Meeting::query()->firstOrFail();
        $this->assertDatabaseHas('calendar_event_mappings', [
            'meeting_id' => $meeting->id,
            'external_event_id' => 'google:meeting:'.$meeting->id,
            'is_external_only' => false,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.meetings.0.title', 'Quarterly review')
                ->where('client.meetings.0.calendar_synced', true)
            );
    }

    public function test_live_oauth_failure_does_not_create_fixture_calendar_connection(): void
    {
        Config::set('integrations.calendar.google.live', false);
        $admin = User::factory()->superAdmin()->create();
        app(IntegrationCredentials::class)->set('google_calendar', 'client_id', 'google-test-client', $admin);
        app(IntegrationCredentials::class)->set('google_calendar', 'client_secret', 'google-test-secret', $admin);
        app(IntegrationActivationResolver::class)->activate('google_calendar', $admin);
        $this->forgetCalendarClients();

        $advisor = $this->advisor('calendar-live@example.test');
        $state = $this->connectState($advisor, CalendarConnection::PROVIDER_GOOGLE);
        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        $this->actingAsMfa($advisor)
            ->get(route('calendar.callback', [
                CalendarConnection::PROVIDER_GOOGLE,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('calendar.edit', absolute: false));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('calendar_connections', 0);
        $this->assertDatabaseHas('integration_calls', [
            'service' => CalendarConnection::PROVIDER_GOOGLE,
            'status' => IntegrationCall::STATUS_FAILURE,
            'attempt' => 1,
        ]);
        $this->assertDatabaseHas('integration_calls', [
            'service' => CalendarConnection::PROVIDER_GOOGLE,
            'status' => IntegrationCall::STATUS_FALLBACK,
            'attempt' => 1,
        ]);
        $this->assertDatabaseMissing('audit_events', ['action' => 'calendar_connection.connected']);
    }

    public function test_live_microsoft_oauth_stores_calendar_owner_and_replaces_previous_connection(): void
    {
        Config::set('integrations.calendar.microsoft.live', false);
        Config::set('integrations.calendar.microsoft.base_url', 'https://graph.microsoft.com/v1.0/me');
        Config::set('integrations.calendar.microsoft.token_url', 'https://login.microsoftonline.com/fsa-test/oauth2/v2.0/token');
        $admin = User::factory()->superAdmin()->create();
        app(IntegrationCredentials::class)->set('microsoft_calendar', 'client_id', 'graph-client-id', $admin);
        app(IntegrationCredentials::class)->set('microsoft_calendar', 'client_secret', 'graph-client-secret', $admin);
        app(IntegrationActivationResolver::class)->activate('microsoft_calendar', $admin);
        $this->forgetCalendarClients();

        $advisor = $this->advisor('calendar-live-microsoft@example.test');
        CalendarConnection::query()->create([
            'user_id' => $advisor->getKey(),
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'external_account_id' => $advisor->email,
            'external_account_email' => null,
            'access_token_envelope' => app(KeyEnvelope::class)->encrypt('old-access-token'),
            'refresh_token_envelope' => app(KeyEnvelope::class)->encrypt('old-refresh-token'),
            'status' => CalendarConnection::STATUS_CONNECTED,
        ]);

        $state = $this->connectState($advisor, CalendarConnection::PROVIDER_MICROSOFT);
        Http::fakeSequence()
            ->push([
                'access_token' => 'graph-access-token',
                'refresh_token' => 'graph-refresh-token',
                'expires_in' => 3600,
            ])
            ->push([
                'id' => 'AAMkAGraphCalendarId',
                'owner' => [
                    'name' => 'Microsoft Advisor',
                    'address' => 'microsoft.advisor@futureshiftadvisory.nz',
                ],
            ]);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.callback', [
                CalendarConnection::PROVIDER_MICROSOFT,
                'code' => 'live-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('calendar.edit', absolute: false));

        $this->assertDatabaseHas('calendar_connections', [
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'external_account_id' => 'AAMkAGraphCalendarId',
            'external_account_email' => 'microsoft.advisor@futureshiftadvisory.nz',
            'status' => CalendarConnection::STATUS_CONNECTED,
        ]);
        $this->assertDatabaseHas('calendar_connections', [
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'external_account_id' => $advisor->email,
            'status' => CalendarConnection::STATUS_REVOKED,
        ]);
    }

    public function test_live_microsoft_client_exchanges_pushes_updates_pulls_and_revokes_calendar_events(): void
    {
        $this->activateCalendarProvider(
            CalendarConnection::PROVIDER_MICROSOFT,
            'https://graph.example.test/v1.0/me',
            'https://login.example.test/common/oauth2/v2.0/token',
            'https://login.example.test/revoke',
        );
        Config::set('integrations.calendar.microsoft.scopes', 'Calendars.ReadWrite, offline_access, User.Read');
        [$advisor, $client] = $this->advisorAndClient('calendar-live-client-microsoft@example.test');
        $connection = $this->liveConnection($advisor, CalendarConnection::PROVIDER_MICROSOFT);
        $meeting = $this->meeting($client, $advisor, 'Microsoft calendar coverage');
        $meeting->forceFill([
            'attendees' => [
                'owner@example.test',
                ['emailAddress' => ['address' => 'advisor@example.test']],
                'not-an-email',
            ],
        ])->save();
        $mapping = CalendarEventMapping::query()->create([
            'calendar_connection_id' => $connection->getKey(),
            'meeting_id' => $meeting->getKey(),
            'external_event_id' => 'microsoft-existing-event',
        ]);

        Http::fakeSequence()
            ->push([
                'access_token' => 'microsoft-access-token',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_in' => 3600,
            ])
            ->push([
                'id' => 'microsoft-calendar-id',
                'owner' => ['address' => 'owner@example.test'],
            ])
            ->push([
                'id' => 'microsoft-created-event',
                'changeKey' => 'created-etag',
                'lastModifiedDateTime' => '2026-08-21T10:00:00Z',
                'subject' => 'Microsoft calendar coverage',
                'start' => ['dateTime' => '2026-08-23T09:00:00+00:00'],
                'end' => ['dateTime' => '2026-08-23T10:00:00+00:00'],
                'location' => ['displayName' => 'Board room'],
                'attendees' => [['emailAddress' => ['address' => 'owner@example.test']]],
            ])
            ->push([
                'id' => 'microsoft-existing-event',
                'changeKey' => 'updated-etag',
                'lastModifiedDateTime' => '2026-08-21T11:00:00Z',
                'subject' => 'Updated Microsoft calendar coverage',
                'start' => ['dateTime' => '2026-08-23T09:00:00+00:00'],
                'end' => ['dateTime' => '2026-08-23T10:00:00+00:00'],
                'location' => ['displayName' => 'Board room'],
            ])
            ->push([
                'value' => [
                    [
                        'id' => 'microsoft-pulled-event',
                        'changeKey' => 'pulled-etag',
                        'lastModifiedDateTime' => '2026-08-21T12:00:00Z',
                        'subject' => 'Pulled Microsoft event',
                        'start' => ['dateTime' => '2026-08-24T09:00:00+00:00'],
                        'end' => ['dateTime' => '2026-08-24T10:00:00+00:00'],
                        'location' => ['displayName' => 'Client site'],
                        'attendees' => [['emailAddress' => ['address' => 'client@example.test']]],
                    ],
                    ['subject' => 'Malformed remote event'],
                ],
                '@odata.deltaLink' => 'https://graph.example.test/delta/next',
            ])
            ->push([], 204);

        $calendar = app(LiveMicrosoftGraphClient::class);
        $authorizeUrl = $calendar->authorizeUrl('microsoft-state', 'https://fsa.example.test/callback', ['Calendars.ReadWrite']);
        $token = $calendar->exchangeCodeForToken('microsoft-code', 'https://fsa.example.test/callback');
        $created = $calendar->pushEvent($connection, $meeting, $token);
        $updated = $calendar->pushEvent($connection, $meeting, $token, $mapping);
        $pulled = $calendar->pullEvents($connection, $token);
        $calendar->revoke($connection, $token);

        $this->assertStringContainsString('microsoft-state', $authorizeUrl);
        $this->assertSame('microsoft-calendar-id', $token['external_account_id']);
        $this->assertSame('owner@example.test', $token['external_account_email']);
        $this->assertSame('microsoft-created-event', $created['external_event_id']);
        $this->assertSame('updated-etag', $updated['etag']);
        $this->assertSame('https://graph.example.test/delta/next', $pulled['delta_link']);
        $this->assertCount(1, $pulled['events']);
        $this->assertSame('microsoft-pulled-event', $pulled['events'][0]['external_event_id']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.example.test/common/oauth2/v2.0/token'
            && data_get($request->data(), 'scope') === 'Calendars.ReadWrite offline_access User.Read');
        Http::assertSentCount(6);
    }

    public function test_live_google_client_exchanges_pushes_pulls_both_sync_modes_and_revokes(): void
    {
        $this->activateCalendarProvider(
            CalendarConnection::PROVIDER_GOOGLE,
            'https://google.example.test/calendar/v3',
            'https://oauth.google.example.test/token',
            'https://oauth.google.example.test/revoke',
        );
        [$advisor, $client] = $this->advisorAndClient('calendar-live-client-google@example.test');
        $connection = $this->liveConnection($advisor, CalendarConnection::PROVIDER_GOOGLE);
        $meeting = $this->meeting($client, $advisor, 'Google calendar coverage');
        $meeting->forceFill(['attendees' => ['owner@example.test', ['email' => 'advisor@example.test']]])->save();

        Http::fakeSequence()
            ->push([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_in' => 3600,
            ])
            ->push([
                'id' => 'google-created-event',
                'etag' => 'google-created-etag',
                'updated' => '2026-08-21T10:00:00Z',
                'summary' => 'Google calendar coverage',
                'start' => ['dateTime' => '2026-08-23T09:00:00+00:00'],
                'end' => ['dateTime' => '2026-08-23T10:00:00+00:00'],
                'location' => 'Board room',
                'attendees' => [['email' => 'owner@example.test']],
            ])
            ->push([
                'items' => [[
                    'id' => 'google-first-sync-event',
                    'etag' => 'google-first-sync-etag',
                    'updated' => '2026-08-21T11:00:00Z',
                    'summary' => 'First Google sync',
                    'start' => ['dateTime' => '2026-08-24T09:00:00+00:00'],
                    'end' => ['dateTime' => '2026-08-24T10:00:00+00:00'],
                ]],
                'nextSyncToken' => 'google-next-sync-token',
            ])
            ->push([
                'events' => [[
                    'external_event_id' => 'google-incremental-event',
                    'updated_at' => '2026-08-21T12:00:00Z',
                    'title' => 'Incremental Google sync',
                    'starts_at' => '2026-08-25T09:00:00+00:00',
                    'ends_at' => '2026-08-25T10:00:00+00:00',
                ]],
                'sync_token' => 'google-latest-sync-token',
            ])
            ->push([], 204);

        $calendar = app(LiveGoogleCalendarClient::class);
        $token = $calendar->exchangeCodeForToken('google-code', 'https://fsa.example.test/callback');
        $created = $calendar->pushEvent($connection, $meeting, $token);
        $firstSync = $calendar->pullEvents($connection, $token);
        $connection->forceFill(['sync_token' => 'google-next-sync-token'])->save();
        $incrementalSync = $calendar->pullEvents($connection, $token);
        $calendar->revoke($connection, $token);

        $this->assertSame('google-access-token', $token['access_token']);
        $this->assertSame('google-created-event', $created['external_event_id']);
        $this->assertSame('google-next-sync-token', $firstSync['sync_token']);
        $this->assertSame('google-first-sync-event', $firstSync['events'][0]['external_event_id']);
        $this->assertSame('google-latest-sync-token', $incrementalSync['sync_token']);
        $this->assertSame('google-incremental-event', $incrementalSync['events'][0]['external_event_id']);
        Http::assertSentCount(5);
    }

    public function test_live_calendar_client_rejects_a_token_response_without_an_access_token(): void
    {
        $this->activateCalendarProvider(
            CalendarConnection::PROVIDER_GOOGLE,
            'https://google.example.test/calendar/v3',
            'https://oauth.google.example.test/token',
            'https://oauth.google.example.test/revoke',
        );
        Http::fakeSequence()->push(['refresh_token' => 'orphaned-refresh-token']);

        $this->expectException(IntegrationRequestFailedException::class);

        app(LiveGoogleCalendarClient::class)->exchangeCodeForToken(
            'google-code-without-access-token',
            'https://fsa.example.test/callback',
        );
    }

    public function test_settings_page_exposes_connections_and_external_events(): void
    {
        [$advisor, $client] = $this->advisorAndClient('calendar-settings@example.test');
        $connection = $this->connectedGoogle($advisor);
        $this->meeting($client, $advisor, 'Settings sync meeting');
        app(CalendarSync::class)->syncConnection($connection, $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/calendar')
                ->where('providers.0.provider', CalendarConnection::PROVIDER_GOOGLE)
                ->where('providers.0.connected', true)
                ->where('connections.0.provider', CalendarConnection::PROVIDER_GOOGLE)
                ->where('externalEvents.0.title', 'External advisory roundtable')
            );
    }

    public function test_other_advisor_cannot_sync_or_revoke_a_calendar_connection(): void
    {
        $advisor = $this->advisor('calendar-owner@example.test');
        $other = $this->advisor('calendar-other@example.test');
        $connection = $this->connectedGoogle($advisor);

        $this->actingAsMfa($other)
            ->post(route('calendar.sync', $connection))
            ->assertNotFound();

        $this->actingAsMfa($other)
            ->patch(route('calendar.revoke', $connection))
            ->assertNotFound();
    }

    public function test_calendar_connections_and_mappings_are_isolated_by_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Calendar RLS assertions require Postgres.');
        }

        $advisorA = $this->advisor('calendar-rls-a@example.test');
        $advisorB = $this->advisor('calendar-rls-b@example.test');
        $clientA = $this->client($advisorA);
        $clientB = $this->client($advisorB);
        $connectionA = $this->connectedGoogle($advisorA);
        $connectionB = $this->connectedMicrosoft($advisorB);
        $this->meeting($clientA, $advisorA, 'A calendar meeting');
        $this->meeting($clientB, $advisorB, 'B calendar meeting');
        app(CalendarSync::class)->syncConnection($connectionA, $advisorA);
        app(CalendarSync::class)->syncConnection($connectionB, $advisorB);

        app(RequestContext::class)->apply(User::TYPE_ADVISOR, [], (string) $advisorA->getKey());

        $visibleConnectionIds = $this->withRlsRole(fn (): array => DB::table('calendar_connections')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $visibleMappingConnectionIds = $this->withRlsRole(fn (): array => DB::table('calendar_event_mappings')
            ->pluck('calendar_connection_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all());

        $this->assertContains($connectionA->id, $visibleConnectionIds);
        $this->assertNotContains($connectionB->id, $visibleConnectionIds);
        $this->assertSame([$connectionA->id], $visibleMappingConnectionIds);
    }

    private function activateCalendarProvider(string $provider, string $baseUrl, string $tokenUrl, string $revokeUrl): void
    {
        $integration = $provider.'_calendar';
        Config::set("integrations.calendar.{$provider}.base_url", $baseUrl);
        Config::set("integrations.calendar.{$provider}.token_url", $tokenUrl);
        Config::set("integrations.calendar.{$provider}.revoke_url", $revokeUrl);

        $admin = User::factory()->superAdmin()->create();
        app(IntegrationCredentials::class)->set($integration, 'client_id', "{$provider}-client-id", $admin);
        app(IntegrationCredentials::class)->set($integration, 'client_secret', "{$provider}-client-secret", $admin);
        app(IntegrationActivationResolver::class)->activate($integration, $admin);
        $this->forgetCalendarClients();
    }

    private function liveConnection(User $advisor, string $provider): CalendarConnection
    {
        return CalendarConnection::query()->create([
            'user_id' => $advisor->getKey(),
            'provider' => $provider,
            'external_account_id' => "{$provider}-calendar-account",
            'external_account_email' => "{$provider}@example.test",
            'access_token_envelope' => app(KeyEnvelope::class)->encrypt("{$provider}-access-token"),
            'refresh_token_envelope' => app(KeyEnvelope::class)->encrypt("{$provider}-refresh-token"),
            'status' => CalendarConnection::STATUS_CONNECTED,
        ]);
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(string $email = 'calendar-advisor@example.test'): array
    {
        $advisor = $this->advisor($email);
        $client = $this->client($advisor);

        return [$advisor, $client];
    }

    private function client(User $advisor): Client
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => 'Calendar Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'registry_sources' => ['nzbn' => 'stub'],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function meeting(Client $client, User $advisor, string $title): Meeting
    {
        return Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => $title,
            'scheduled_at' => now()->addDays(2),
            'location' => 'Board room',
            'attendees' => ['Advisor', 'Owner'],
            'created_by_user_id' => $advisor->getKey(),
        ]);
    }

    private function connectedGoogle(User $advisor): CalendarConnection
    {
        return $this->connectedCalendar($advisor, CalendarConnection::PROVIDER_GOOGLE);
    }

    private function connectedMicrosoft(User $advisor): CalendarConnection
    {
        return $this->connectedCalendar($advisor, CalendarConnection::PROVIDER_MICROSOFT);
    }

    private function connectedCalendar(User $advisor, string $provider): CalendarConnection
    {
        $state = $this->connectState($advisor, $provider);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.callback', [
                $provider,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('calendar.edit', absolute: false));

        /** @var CalendarConnection $connection */
        $connection = CalendarConnection::query()
            ->where('user_id', $advisor->getKey())
            ->where('provider', $provider)
            ->firstOrFail();

        return $connection;
    }

    private function connectState(User $advisor, string $provider): string
    {
        $response = $this->actingAsMfa($advisor)
            ->get(route('calendar.connect', $provider))
            ->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertIsString($location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame("{$provider}.example.test", parse_url($location, PHP_URL_HOST));
        $this->assertArrayHasKey('state', $query);

        return (string) $query['state'];
    }

    private function forgetCalendarClients(): void
    {
        foreach ([
            GoogleCalendarClient::class,
            FakeGoogleCalendarClient::class,
            LiveGoogleCalendarClient::class,
            FallbackGoogleCalendarClient::class,
            MicrosoftGraphClient::class,
            FakeMicrosoftGraphClient::class,
            LiveMicrosoftGraphClient::class,
            FallbackMicrosoftGraphClient::class,
            RetryPolicy::class,
            ResilientHttp::class,
            CalendarClientResolver::class,
            CalendarConnector::class,
            CalendarSync::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON calendar_connections, calendar_event_mappings TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT calendar_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT calendar_rls_probe');
            }

            return $result;
        } catch (Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT calendar_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
