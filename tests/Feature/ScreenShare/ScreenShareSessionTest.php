<?php

declare(strict_types=1);

namespace Tests\Feature\ScreenShare;

use App\Enums\EngagementType;
use App\Events\ScreenSharePrompt;
use App\Events\ScreenShareSignal;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\ScreenShareConnection;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Services\ScreenShare\ClientPortalContextTokens;
use App\Services\ScreenShare\EntrepreneurScreenSharePresence;
use App\Services\ScreenShare\EntrepreneurScreenShareRequests;
use App\Services\ScreenShare\ScreenShareConnectionCredentials;
use App\Services\ScreenShare\ScreenShareIceServers;
use App\Services\ScreenShare\ScreenSharePresence;
use App\Services\ScreenShare\ScreenShareSessions;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ScreenShareSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $advisor;

    private Client $client;

    private User $clientUser;

    private User $otherClientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config([
            'screen-share.request_timeout_seconds' => 60,
            'screen-share.picker_timeout_seconds' => 90,
            'screen-share.max_duration_minutes' => 30,
            'screen-share.reconnect_grace_seconds' => 15,
            'screen-share.turn_urls' => 'turns:turn.test:5349?transport=tcp',
            'screen-share.turn_shared_secret' => 'test-turn-secret',
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

        $this->otherClientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_TEAM,
            'primary_role' => User::TYPE_CLIENT_TEAM,
        ]);
        $this->otherClientUser->assignRole(User::TYPE_CLIENT_TEAM);

        $this->client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000999',
            'legal_name' => 'Screen Support Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $this->clientUser->getKey(),
        ]);

        foreach ([
            [$this->advisor, 'lead_advisor'],
            [$this->clientUser, 'primary_contact'],
            [$this->otherClientUser, 'team_member'],
        ] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $this->client->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }
    }

    public function test_first_response_wins_and_binds_the_approving_tab(): void
    {
        [$session, $tabs, $nonces] = $this->requestedSession(2);
        $firstTab = $tabs[0];
        $secondTab = $tabs[1];

        $approved = app(ScreenShareSessions::class)->respond(
            $this->clientUser,
            $session,
            (string) $firstTab->connection->getKey(),
            $firstTab->secret,
            $nonces[(string) $firstTab->connection->getKey()],
            true,
        );

        $late = app(ScreenShareSessions::class)->respond(
            $this->clientUser,
            $session,
            (string) $secondTab->connection->getKey(),
            $secondTab->secret,
            $nonces[(string) $secondTab->connection->getKey()],
            false,
        );

        $this->assertSame(ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER, $approved->status);
        $this->assertSame((string) $firstTab->connection->getKey(), (string) $approved->client_connection_id);
        $this->assertSame('portal.dashboard', $approved->consent_context['route_key']);
        $this->assertSame(ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER, $late->status);
        $this->assertNull($late->end_reason);
    }

    public function test_presence_lease_survives_background_browser_timer_throttling(): void
    {
        config(['screen-share.presence_ttl_seconds' => 45]);

        $clientConnection = $this->clientConnection($this->clientUser);

        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Background Browser Entrepreneur',
            'email' => 'background-browser-entrepreneur@example.test',
        ]);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $entrepreneurConnection = app(EntrepreneurScreenSharePresence::class)
            ->registerPortalParticipant($entrepreneur, $token);

        $minimumExpiry = now()->addSeconds(90);

        $this->assertTrue($clientConnection->connection->expires_at->greaterThan($minimumExpiry));
        $this->assertTrue($entrepreneurConnection->connection->expires_at->greaterThan($minimumExpiry));
    }

    public function test_unprompted_client_team_member_cannot_answer_another_users_request(): void
    {
        [$session] = $this->requestedSession();
        $otherTab = $this->clientConnection($this->otherClientUser);

        try {
            app(ScreenShareSessions::class)->respond(
                $this->otherClientUser,
                $session,
                (string) $otherTab->connection->getKey(),
                $otherTab->secret,
                str_repeat('a', 64),
                true,
            );
            $this->fail('A different client user must not be able to answer the request.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(ScreenShareSession::STATUS_REQUESTED, $session->refresh()->status);
    }

    public function test_client_request_endpoint_accepts_the_browser_string_user_id(): void
    {
        Event::fake([ScreenSharePrompt::class]);
        $advisorConnection = app(ScreenSharePresence::class)->registerAdvisor($this->advisor, $this->client);
        $this->clientConnection($this->clientUser);

        $response = $this->actingAs($this->advisor)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->advisor->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('advisor.clients.screen-share.sessions.store', $this->client),
                [
                    'client_user_id' => (string) $this->clientUser->getKey(),
                    'advisor_connection_id' => (string) $advisorConnection->connection->getKey(),
                    'advisor_connection_secret' => $advisorConnection->secret,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('status', ScreenShareSession::STATUS_REQUESTED);
    }

    public function test_client_can_poll_for_a_pending_screen_support_prompt(): void
    {
        [$session, $tabs, $nonces] = $this->requestedSession();
        $clientTab = $tabs[0];

        $response = $this->actingAs($this->clientUser)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->clientUser->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.connections.pending-prompt', $clientTab->connection),
                ['connection_secret' => $clientTab->secret],
            );

        $response
            ->assertOk()
            ->assertJsonPath('prompt.session_id', (string) $session->getKey())
            ->assertJsonPath('prompt.context.label', 'your client portal');
        $this->assertSame(
            $nonces[(string) $clientTab->connection->getKey()],
            $response->json('prompt.nonce'),
        );
    }

    public function test_participants_can_poll_for_persisted_signals(): void
    {
        Event::fake([ScreenSharePrompt::class, ScreenShareSignal::class]);
        $sessions = app(ScreenShareSessions::class);
        $advisorConnection = app(ScreenSharePresence::class)->registerAdvisor($this->advisor, $this->client);
        $clientConnection = $this->clientConnection($this->clientUser);
        $session = $sessions->request(
            $this->advisor,
            $this->client,
            $this->clientUser,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
        );
        $prompt = Event::dispatched(ScreenSharePrompt::class)[0][0];

        $sessions->respond(
            $this->clientUser,
            $session,
            (string) $clientConnection->connection->getKey(),
            $clientConnection->secret,
            $prompt->broadcastWith()['nonce'],
            true,
        );
        $offerSdp = "v=0\r\na=ssrc:1234 msid:stream track";
        $this->actingAs($this->clientUser)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->clientUser->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.sessions.signal', $session),
                [
                    'connection_id' => (string) $clientConnection->connection->getKey(),
                    'connection_secret' => $clientConnection->secret,
                    'type' => 'offer',
                    'payload' => ['type' => 'offer', 'sdp' => $offerSdp],
                ],
            )
            ->assertNoContent();

        $advisorPoll = $this->actingAs($this->advisor)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->advisor->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.sessions.pending-signals', $session),
                [
                    'connection_id' => (string) $advisorConnection->connection->getKey(),
                    'connection_secret' => $advisorConnection->secret,
                    'after_id' => 0,
                ],
            );

        $advisorPoll
            ->assertOk()
            ->assertJsonPath('signals.0.type', 'offer')
            ->assertJsonPath('signals.0.payload.sdp', $offerSdp."\r\n");
        $this->assertTrue(
            $advisorConnection->connection->fresh()->expires_at->isAfter(now()->addSeconds(40)),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'screen_share.offer_sent',
            'actor_user_key' => (string) $this->clientUser->getKey(),
        ]);

        $this->actingAs($this->advisor)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->advisor->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.sessions.pending-signals', $session),
                [
                    'connection_id' => (string) $advisorConnection->connection->getKey(),
                    'connection_secret' => $advisorConnection->secret,
                    'after_id' => PHP_INT_MAX,
                ],
            )
            ->assertOk()
            ->assertJsonPath('signals.0.type', 'offer')
            ->assertJsonPath('signals.0.payload.sdp', $offerSdp."\r\n");

        $answerSdp = "v=0\r\na=sendrecv";
        $this->actingAs($this->advisor)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->advisor->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.sessions.signal', $session),
                [
                    'connection_id' => (string) $advisorConnection->connection->getKey(),
                    'connection_secret' => $advisorConnection->secret,
                    'type' => 'answer',
                    'payload' => ['type' => 'answer', 'sdp' => $answerSdp],
                ],
            )
            ->assertNoContent();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'screen_share.answer_sent',
            'actor_user_key' => (string) $this->advisor->getKey(),
        ]);

        $this->actingAs($this->clientUser)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->clientUser->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.sessions.pending-signals', $session),
                [
                    'connection_id' => (string) $clientConnection->connection->getKey(),
                    'connection_secret' => $clientConnection->secret,
                    'after_id' => 0,
                ],
            )
            ->assertOk()
            ->assertJsonPath('signals.0.type', 'answer')
            ->assertJsonPath('signals.0.payload.sdp', $answerSdp."\r\n");

        $sessions->end(
            $this->advisor,
            $session,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
            'completed_advisor_ended',
        );
        $sessions->end(
            $this->clientUser,
            $session,
            (string) $clientConnection->connection->getKey(),
            $clientConnection->secret,
            'connection_lost',
        );

        $this->assertSame(1, AuditEvent::query()
            ->where('subject_id', $session->getKey())
            ->where('action', 'screen_share.ended')
            ->count());
    }

    public function test_assigned_advisor_can_request_support_from_an_entrepreneur_profile(): void
    {
        Event::fake([ScreenSharePrompt::class]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Screen Support Entrepreneur',
            'email' => 'screen-support-entrepreneur@example.test',
        ]);

        $presence = app(EntrepreneurScreenSharePresence::class);
        $advisorConnection = $presence->registerAdvisor($this->advisor, $profile);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $entrepreneurConnection = $presence->registerPortalParticipant($entrepreneur, $token);

        $session = app(EntrepreneurScreenShareRequests::class)->request(
            $this->advisor,
            $profile,
            $entrepreneur,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
        );
        $prompt = Event::dispatched(ScreenSharePrompt::class)[0][0];
        $nonce = $prompt->broadcastWith()['nonce'];

        $approved = app(ScreenShareSessions::class)->respond(
            $entrepreneur,
            $session,
            (string) $entrepreneurConnection->connection->getKey(),
            $entrepreneurConnection->secret,
            $nonce,
            true,
        );

        $this->assertNull($approved->client_id);
        $this->assertSame((string) $profile->getKey(), (string) $approved->entrepreneur_profile_id);
        $this->assertSame('entrepreneur_assignment', $approved->authorization_basis['path']);
        $this->assertSame(ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER, $approved->status);
    }

    public function test_entrepreneur_request_endpoint_accepts_the_browser_string_user_id(): void
    {
        Event::fake([ScreenSharePrompt::class]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Endpoint Support Entrepreneur',
            'email' => 'endpoint-support-entrepreneur@example.test',
        ]);

        $presence = app(EntrepreneurScreenSharePresence::class);
        $advisorConnection = $presence->registerAdvisor($this->advisor, $profile);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $presence->registerPortalParticipant($entrepreneur, $token);

        $response = $this->actingAs($this->advisor)
            ->withSession([
                'auth.mfa_user_id' => (string) $this->advisor->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('advisor.entrepreneurs.screen-share.sessions.store', $profile),
                [
                    'client_user_id' => (string) $entrepreneur->getKey(),
                    'advisor_connection_id' => (string) $advisorConnection->connection->getKey(),
                    'advisor_connection_secret' => $advisorConnection->secret,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('status', ScreenShareSession::STATUS_REQUESTED);
    }

    public function test_entrepreneur_can_poll_for_a_pending_screen_support_prompt(): void
    {
        Event::fake([ScreenSharePrompt::class]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Polling Support Entrepreneur',
            'email' => 'polling-support-entrepreneur@example.test',
        ]);

        $presence = app(EntrepreneurScreenSharePresence::class);
        $advisorConnection = $presence->registerAdvisor($this->advisor, $profile);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $entrepreneurConnection = $presence->registerPortalParticipant($entrepreneur, $token);
        $session = app(EntrepreneurScreenShareRequests::class)->request(
            $this->advisor,
            $profile,
            $entrepreneur,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
        );
        $prompt = Event::dispatched(ScreenSharePrompt::class)[0][0];

        $response = $this->actingAs($entrepreneur)
            ->withSession([
                'auth.mfa_user_id' => (string) $entrepreneur->getKey(),
                'auth.mfa_confirmed_at' => now()->getTimestamp(),
            ])
            ->postJson(
                route('screen-share.connections.pending-prompt', $entrepreneurConnection->connection),
                ['connection_secret' => $entrepreneurConnection->secret],
            );

        $response
            ->assertOk()
            ->assertJsonPath('prompt.session_id', (string) $session->getKey())
            ->assertJsonPath('prompt.context.label', 'your entrepreneur workspace');
        $this->assertSame($prompt->broadcastWith()['nonce'], $response->json('prompt.nonce'));
    }

    public function test_super_admin_can_request_support_from_an_entrepreneur_profile(): void
    {
        Event::fake([ScreenSharePrompt::class]);
        $superAdmin = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $this->advisor->getKey(),
            'name' => 'Platform Support Entrepreneur',
            'email' => 'platform-support-entrepreneur@example.test',
        ]);

        $presence = app(EntrepreneurScreenSharePresence::class);
        $advisorConnection = $presence->registerAdvisor($superAdmin, $profile);
        $token = app(ClientPortalContextTokens::class)->issueForEntrepreneur(
            $entrepreneur,
            $profile,
            'portal.entrepreneur.dashboard',
        );
        $entrepreneurConnection = $presence->registerPortalParticipant($entrepreneur, $token);

        $session = app(EntrepreneurScreenShareRequests::class)->request(
            $superAdmin,
            $profile,
            $entrepreneur,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
        );
        $prompt = Event::dispatched(ScreenSharePrompt::class)[0][0];
        $nonce = $prompt->broadcastWith()['nonce'];

        app(ScreenShareSessions::class)->respond(
            $entrepreneur,
            $session,
            (string) $entrepreneurConnection->connection->getKey(),
            $entrepreneurConnection->secret,
            $nonce,
            true,
        );

        $this->assertSame('super_admin', $session->refresh()->authorization_basis['path']);
        $this->assertSame(ScreenShareSession::STATUS_APPROVED_PENDING_BROWSER, $session->refresh()->status);
    }

    public function test_expiry_sweep_ends_requested_sessions_and_releases_the_lock(): void
    {
        [$session] = $this->requestedSession();
        $session->forceFill(['expires_at' => now()->subSecond()])->save();

        $expired = app(ScreenShareSessions::class)->expireDueSessions();

        $this->assertCount(1, $expired);
        $this->assertSame(ScreenShareSession::STATUS_ENDED, $session->refresh()->status);
        $this->assertSame('request_timed_out', $session->end_reason);
        $this->assertDatabaseHas('audit_events', ['action' => 'screen_share.expired']);
    }

    public function test_stale_participant_connection_ends_an_active_session(): void
    {
        [$session, $tabs, $nonces] = $this->requestedSession();
        $clientTab = $tabs[0];
        $sessions = app(ScreenShareSessions::class);
        $sessions->respond(
            $this->clientUser,
            $session,
            (string) $clientTab->connection->getKey(),
            $clientTab->secret,
            $nonces[(string) $clientTab->connection->getKey()],
            true,
        );
        $sessions->recordBrowserPermission(
            $this->clientUser,
            $session,
            (string) $clientTab->connection->getKey(),
            $clientTab->secret,
            true,
            'browser',
        );
        $sessions->markActive(
            $this->clientUser,
            $session,
            (string) $clientTab->connection->getKey(),
            $clientTab->secret,
        );

        ScreenShareConnection::query()
            ->whereKey($clientTab->connection->getKey())
            ->update(['last_seen_at' => now()->subSeconds(16)]);

        $ended = $sessions->endIfConnectionNotReconnected(
            (string) $session->getKey(),
            (string) $clientTab->connection->getKey(),
        );

        $this->assertInstanceOf(ScreenShareSession::class, $ended);
        $this->assertSame(ScreenShareSession::STATUS_ENDED, $session->refresh()->status);
        $this->assertSame('connection_lost', $session->end_reason);
    }

    public function test_turn_credentials_are_scoped_to_an_approved_participant_and_do_not_expose_the_secret(): void
    {
        [$session, $tabs, $nonces] = $this->requestedSession();
        $clientTab = $tabs[0];
        app(ScreenShareSessions::class)->respond(
            $this->clientUser,
            $session,
            (string) $clientTab->connection->getKey(),
            $clientTab->secret,
            $nonces[(string) $clientTab->connection->getKey()],
            true,
        );

        $servers = app(ScreenShareIceServers::class)->forParticipant(
            $this->clientUser,
            $session,
            (string) $clientTab->connection->getKey(),
            $clientTab->secret,
        );

        $this->assertSame('turns:turn.test:5349?transport=tcp', $servers[0]['urls'][0]);
        $this->assertArrayHasKey('username', $servers[0]);
        $this->assertArrayHasKey('credential', $servers[0]);
        $this->assertStringNotContainsString('test-turn-secret', json_encode($servers, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     0: ScreenShareSession,
     *     1: array<int, ScreenShareConnectionCredentials>,
     *     2: array<string, string>
     * }
     */
    private function requestedSession(int $tabCount = 1): array
    {
        Event::fake([ScreenSharePrompt::class]);

        $advisorConnection = app(ScreenSharePresence::class)->registerAdvisor($this->advisor, $this->client);
        $clientConnections = [];
        for ($tab = 0; $tab < $tabCount; $tab++) {
            $clientConnections[] = $this->clientConnection($this->clientUser);
        }
        $session = app(ScreenShareSessions::class)->request(
            $this->advisor,
            $this->client,
            $this->clientUser,
            (string) $advisorConnection->connection->getKey(),
            $advisorConnection->secret,
        );
        $nonces = [];
        foreach (Event::dispatched(ScreenSharePrompt::class) as $dispatched) {
            /** @var ScreenSharePrompt $prompt */
            $prompt = $dispatched[0];
            $payload = $prompt->broadcastWith();
            $promptedConnection = collect($session->prompted_connections)
                ->first(fn (array $item): bool => hash_equals(
                    $item['nonce_hash'],
                    hash('sha256', $payload['nonce']),
                ));

            $this->assertIsArray($promptedConnection);
            $nonces[(string) $promptedConnection['connection_id']] = $payload['nonce'];
        }

        $this->assertCount($tabCount, $nonces);

        return [$session, $clientConnections, $nonces];
    }

    private function clientConnection(User $user): ScreenShareConnectionCredentials
    {
        $token = app(ClientPortalContextTokens::class)->issue($user, $this->client, 'portal.dashboard');

        return app(ScreenSharePresence::class)->registerClient($user, $token);
    }
}
