<?php

declare(strict_types=1);

namespace Tests\Feature\CoBrowse;

use App\Enums\EngagementType;
use App\Events\CoBrowseActionDispatched;
use App\Events\CoBrowsePrompt;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoBrowseSession;
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

    private function clientConnection(): CoBrowseConnectionCredentials
    {
        $token = app(ClientPortalContextTokens::class)->issue($this->clientUser, $this->client, 'portal.dashboard');

        return app(CoBrowsePresence::class)->registerClient($this->clientUser, $token);
    }
}
