<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\EngagementType;
use App\Models\CalendarConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IntegrationCall;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Calendar\CalendarClientResolver;
use App\Services\Calendar\CalendarConnector;
use App\Services\Calendar\CalendarSync;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\FakeGoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\FallbackGoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\LiveGoogleCalendarClient;
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

    public function test_live_mode_without_credentials_records_resilience_fallback(): void
    {
        Config::set('integrations.calendar.google.live', true);
        Config::set('integrations.calendar.google.client_secret', null);
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
        $this->assertDatabaseHas('audit_events', ['action' => 'calendar_connection.connected']);
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
