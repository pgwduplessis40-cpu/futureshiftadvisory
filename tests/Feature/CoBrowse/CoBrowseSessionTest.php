<?php

declare(strict_types=1);

namespace Tests\Feature\CoBrowse;

use App\Enums\EngagementType;
use App\Events\CoBrowseActionDispatched;
use App\Events\CoBrowsePrompt;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoBrowseConnection;
use App\Models\CoBrowseSession;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\CoBrowse\CoBrowseConnectionCredentials;
use App\Services\CoBrowse\CoBrowsePresence;
use App\Services\CoBrowse\CoBrowseSessions;
use App\Services\ScreenShare\ClientPortalContextTokens;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CoBrowseSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $advisor;

    private Client $client;

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config([
            'co-browse.enabled' => true,
            'co-browse.request_timeout_seconds' => 60,
            'co-browse.max_duration_minutes' => 20,
            'co-browse.presence_ttl_seconds' => 45,
            'co-browse.actions_per_second' => 5,
        ]);
        app(RequestContext::class)->apply('system', []);

        $this->advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $this->advisor->assignRole(User::TYPE_ADVISOR);

        $this->clientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $this->clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $this->client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000999',
            'legal_name' => 'Guided Assistance Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $this->clientUser->getKey(),
        ]);

        foreach ([[$this->advisor, 'lead_advisor'], [$this->clientUser, 'primary_contact']] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $this->client->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }
    }

    public function test_client_must_explicitly_approve_before_audited_guidance_is_delivered(): void
    {
        Event::fake([CoBrowsePrompt::class, CoBrowseActionDispatched::class]);
        $presence = app(CoBrowsePresence::class);
        $sessions = app(CoBrowseSessions::class);
        $advisor = $presence->registerAdvisorForClient($this->advisor, $this->client);
        $client = $this->clientConnection();

        $session = $sessions->requestForClient(
            $this->advisor,
            $this->client,
            $this->clientUser,
            (string) $advisor->connection->getKey(),
            $advisor->secret,
        );
        $prompt = Event::dispatched(CoBrowsePrompt::class)[0][0];
        $pending = $sessions->pendingPrompt($this->clientUser, $client->connection);

        $this->assertSame((string) $session->getKey(), $pending['session_id']);
        $this->assertSame($prompt->broadcastWith()['nonce'], $pending['nonce']);

        $approved = $sessions->respond(
            $this->clientUser,
            $session,
            (string) $client->connection->getKey(),
            $client->secret,
            $pending['nonce'],
            true,
        );

        $this->assertSame(CoBrowseSession::STATUS_ACTIVE, $approved->status);
        $this->assertSame('portal.dashboard', $approved->consent_context['route_key']);

        $sessions->action(
            $this->advisor,
            $approved,
            (string) $advisor->connection->getKey(),
            $advisor->secret,
            'highlight',
            ['target' => 'client.dashboard.progress'],
        );

        $actions = $sessions->pendingActions(
            $this->clientUser,
            $approved,
            (string) $client->connection->getKey(),
            $client->secret,
            0,
        );

        $this->assertSame('highlight', $actions[0]['type']);
        $this->assertSame('client.dashboard.progress', $actions[0]['payload']['target']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'co_browse.client_approved',
            'actor_user_key' => (string) $this->clientUser->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'co_browse.highlight',
            'actor_user_key' => (string) $this->advisor->getKey(),
        ]);
    }

    public function test_guided_assistance_presence_lease_survives_background_browser_timer_throttling(): void
    {
        config(['co-browse.presence_ttl_seconds' => 45]);

        $connection = $this->clientConnection();

        $this->assertTrue($connection->connection->expires_at->greaterThan(now()->addSeconds(90)));
    }

    public function test_entrepreneur_dashboard_can_register_guided_assistance_presence(): void
    {
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Guided Assistance Entrepreneur',
            'email' => 'guided-assistance-entrepreneur@example.test',
        ]);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $csrfToken = 'co-browse-csrf-token';

        $response = $this->actingAs($entrepreneur)
            ->withSession([
                '_token' => $csrfToken,
                'auth.mfa_user_id' => (string) $entrepreneur->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(route('portal.co-browse.connections.store'), [
                'portal_context_token' => $token,
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'connection_id',
                'connection_secret',
                'channel',
                'expires_at',
            ]);
        $this->assertSame(64, strlen((string) $response->json('connection_secret')));
        $this->assertDatabaseHas('co_browse_connections', [
            'id' => $response->json('connection_id'),
            'entrepreneur_profile_id' => $profile->getKey(),
            'user_id' => $entrepreneur->getKey(),
            'participant_type' => CoBrowseConnection::TYPE_CLIENT,
            'context_key' => 'portal.entrepreneur.dashboard',
        ]);
    }

    public function test_client_status_reports_when_the_advisor_ends_guided_assistance(): void
    {
        Event::fake([CoBrowsePrompt::class]);
        $presence = app(CoBrowsePresence::class);
        $sessions = app(CoBrowseSessions::class);
        $advisor = $presence->registerAdvisorForClient($this->advisor, $this->client);
        $client = $this->clientConnection();

        $session = $sessions->requestForClient(
            $this->advisor,
            $this->client,
            $this->clientUser,
            (string) $advisor->connection->getKey(),
            $advisor->secret,
        );
        $prompt = Event::dispatched(CoBrowsePrompt::class)[0][0];
        $approved = $sessions->respond(
            $this->clientUser,
            $session,
            (string) $client->connection->getKey(),
            $client->secret,
            $prompt->broadcastWith()['nonce'],
            true,
        );

        $sessions->end(
            $this->advisor,
            $approved,
            (string) $advisor->connection->getKey(),
            $advisor->secret,
            'completed_advisor_ended',
        );

        $status = $sessions->status(
            $this->clientUser,
            $approved,
            (string) $client->connection->getKey(),
            $client->secret,
        );

        $this->assertSame(CoBrowseSession::STATUS_ENDED, $status->status);
    }

    public function test_unapproved_or_unknown_actions_cannot_be_delivered(): void
    {
        Event::fake([CoBrowsePrompt::class]);
        $presence = app(CoBrowsePresence::class);
        $sessions = app(CoBrowseSessions::class);
        $advisor = $presence->registerAdvisorForClient($this->advisor, $this->client);
        $client = $this->clientConnection();

        $session = $sessions->requestForClient(
            $this->advisor,
            $this->client,
            $this->clientUser,
            (string) $advisor->connection->getKey(),
            $advisor->secret,
        );

        try {
            $sessions->action(
                $this->advisor,
                $session,
                (string) $advisor->connection->getKey(),
                $advisor->secret,
                'pointer',
                ['x' => 0.5, 'y' => 0.5],
            );
            $this->fail('Guidance must not be sent before client approval.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $prompt = Event::dispatched(CoBrowsePrompt::class)[0][0];
        $approved = $sessions->respond(
            $this->clientUser,
            $session,
            (string) $client->connection->getKey(),
            $client->secret,
            $prompt->broadcastWith()['nonce'],
            true,
        );

        try {
            $sessions->action(
                $this->advisor,
                $approved,
                (string) $advisor->connection->getKey(),
                $advisor->secret,
                'highlight',
                ['target' => 'client.dashboard.unknown'],
            );
            $this->fail('Only server-nominated Future Shift Advisory targets are allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_client_guided_assistance_http_flow_covers_connection_consent_and_lifecycle_endpoints(): void
    {
        Event::fake([CoBrowseActionDispatched::class, CoBrowsePrompt::class]);

        $advisorConnection = $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('advisor.clients.co-browse.connections.store', $this->client))
            ->assertOk();
        $clientToken = app(ClientPortalContextTokens::class)->issue($this->clientUser, $this->client, 'portal.dashboard');
        $clientConnection = $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('portal.co-browse.connections.store'), ['portal_context_token' => $clientToken])
            ->assertOk();

        $clientConnectionId = (string) $clientConnection->json('connection_id');
        $clientSecret = (string) $clientConnection->json('connection_secret');

        $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.connections.heartbeat', $clientConnectionId), ['connection_secret' => $clientSecret])
            ->assertOk()
            ->assertJsonStructure(['expires_at']);

        $requested = $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('advisor.clients.co-browse.sessions.store', $this->client), [
                'client_user_id' => (string) $this->clientUser->getKey(),
                'advisor_connection_id' => $advisorConnection->json('connection_id'),
                'advisor_connection_secret' => $advisorConnection->json('connection_secret'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', CoBrowseSession::STATUS_REQUESTED);
        $session = CoBrowseSession::query()->findOrFail($requested->json('id'));

        $pending = $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.connections.pending-prompt', $clientConnectionId), ['connection_secret' => $clientSecret])
            ->assertOk()
            ->assertJsonPath('prompt.session_id', (string) $session->getKey());
        $nonce = (string) $pending->json('prompt.nonce');

        $approved = $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('portal.co-browse.sessions.response', $session), [
                'action' => 'approve',
                'connection_id' => $clientConnectionId,
                'connection_secret' => $clientSecret,
                'nonce' => $nonce,
            ])
            ->assertOk()
            ->assertJsonPath('status', CoBrowseSession::STATUS_ACTIVE);
        $targets = $approved->json('targets');
        $this->assertIsArray($targets);
        $this->assertSame('Progress', $targets['client.dashboard.progress'] ?? null);

        $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('co-browse.sessions.actions.store', $session), [
                'connection_id' => $advisorConnection->json('connection_id'),
                'connection_secret' => $advisorConnection->json('connection_secret'),
                'type' => 'highlight',
                'payload' => ['target' => 'client.dashboard.progress'],
            ])
            ->assertNoContent();

        $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.sessions.pending-actions', $session), [
                'connection_id' => $clientConnectionId,
                'connection_secret' => $clientSecret,
                'after_id' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('actions.0.type', 'highlight');

        $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.sessions.status', $session), [
                'connection_id' => $clientConnectionId,
                'connection_secret' => $clientSecret,
            ])
            ->assertOk()
            ->assertJsonPath('status', CoBrowseSession::STATUS_ACTIVE);

        $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.sessions.heartbeat', $session), [
                'connection_id' => $clientConnectionId,
                'connection_secret' => $clientSecret,
            ])
            ->assertOk()
            ->assertJsonPath('status', CoBrowseSession::STATUS_ACTIVE);

        $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('co-browse.sessions.end', $session), [
                'connection_id' => $advisorConnection->json('connection_id'),
                'connection_secret' => $advisorConnection->json('connection_secret'),
                'reason' => 'completed_advisor_ended',
            ])
            ->assertOk()
            ->assertJsonPath('status', CoBrowseSession::STATUS_ENDED)
            ->assertJsonPath('end_reason', 'completed_advisor_ended');

        $this->actingAs($this->clientUser)
            ->withSession($this->mfaSession($this->clientUser))
            ->postJson(route('co-browse.sessions.pending-actions', $session), [
                'connection_id' => $clientConnectionId,
                'connection_secret' => $clientSecret,
                'after_id' => 1,
            ])
            ->assertOk()
            ->assertExactJson(['actions' => []]);
    }

    public function test_entrepreneur_guided_assistance_endpoints_bind_the_assigned_advisor_and_portal_participant(): void
    {
        Event::fake([CoBrowsePrompt::class]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Guided Assistance Entrepreneur',
            'email' => 'guided-assistance-endpoint@example.test',
        ]);

        $advisorConnection = $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('advisor.entrepreneurs.co-browse.connections.store', $profile))
            ->assertOk();
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $clientConnection = $this->actingAs($entrepreneur)
            ->withSession($this->mfaSession($entrepreneur))
            ->postJson(route('portal.co-browse.connections.store'), ['portal_context_token' => $token])
            ->assertOk();

        $this->actingAs($this->advisor)
            ->withSession($this->mfaSession($this->advisor))
            ->postJson(route('advisor.entrepreneurs.co-browse.sessions.store', $profile), [
                'client_user_id' => (string) $entrepreneur->getKey(),
                'advisor_connection_id' => $advisorConnection->json('connection_id'),
                'advisor_connection_secret' => $advisorConnection->json('connection_secret'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', CoBrowseSession::STATUS_REQUESTED);

        $this->assertDatabaseHas('co_browse_connections', [
            'id' => $clientConnection->json('connection_id'),
            'entrepreneur_profile_id' => $profile->getKey(),
            'user_id' => $entrepreneur->getKey(),
        ]);
    }

    /** @return array<string, int|string> */
    private function mfaSession(User $user): array
    {
        return [
            'auth.mfa_user_id' => (string) $user->getKey(),
            'auth.mfa_confirmed_at' => now()->getTimestamp(),
        ];
    }

    private function clientConnection(): CoBrowseConnectionCredentials
    {
        $token = app(ClientPortalContextTokens::class)->issue($this->clientUser, $this->client, 'portal.dashboard');

        return app(CoBrowsePresence::class)->registerClient($this->clientUser, $token);
    }
}
