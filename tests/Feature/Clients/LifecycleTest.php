<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Notifications\ClientLifecycleNotification;
use App\Services\Clients\LifecycleManager;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

final class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_lifecycle_controls_are_visible_on_client_profile(): void
    {
        [$advisor, $client] = $this->clientWithTeam();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.status', ClientStatus::ACTIVE->value)
                ->where('client.status_label', ClientStatus::ACTIVE->label())
                ->where('client.lifecycle_update_url', route('advisor.clients.lifecycle.update', $client, absolute: false))
                ->has('client.status_options', 4));
    }

    public function test_suspending_and_restoring_client_revokes_and_restores_portal_access(): void
    {
        Notification::fake();
        [$advisor, $client, $clientUser] = $this->clientWithTeam();

        $this->assertSame([$client->id], $clientUser->accessibleClientIds());

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.clients.lifecycle.update', $client), [
                'status' => ClientStatus::SUSPENDED->value,
                'reason' => 'Payment issue needs resolution.',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertSame(ClientStatus::SUSPENDED, $client->refresh()->status);
        $this->assertSame([], $clientUser->refresh()->accessibleClientIds());
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $clientUser->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'client.lifecycle.transitioned',
            'client_id' => $client->id,
        ]);
        Notification::assertSentTo($clientUser, ClientLifecycleNotification::class, function (ClientLifecycleNotification $notification): bool {
            return $notification->status === ClientStatus::SUSPENDED;
        });

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertForbidden();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.clients.lifecycle.update', $client), [
                'status' => ClientStatus::ACTIVE->value,
                'reason' => 'Account resolved.',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertSame(ClientStatus::ACTIVE, $client->refresh()->status);
        $this->assertSame([$client->id], $clientUser->refresh()->accessibleClientIds());

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk();
    }

    public function test_direct_client_status_writes_are_blocked_by_observer(): void
    {
        [, $client] = $this->clientWithTeam();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Client status transitions must go through LifecycleManager.');

        $client->forceFill([
            'status' => ClientStatus::SUSPENDED->value,
        ])->save();
    }

    public function test_lifecycle_manager_can_mark_client_offboarded(): void
    {
        Notification::fake();
        [$advisor, $client, $clientUser] = $this->clientWithTeam();

        app(LifecycleManager::class)->offboard($client, $advisor, 'Structured offboarding complete.');

        $this->assertSame(ClientStatus::OFFBOARDED, $client->refresh()->status);
        Notification::assertSentTo($clientUser, ClientLifecycleNotification::class, function (ClientLifecycleNotification $notification): bool {
            return $notification->status === ClientStatus::OFFBOARDED;
        });
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithTeam(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => 'client.lifecycle@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000400',
            'legal_name' => 'Lifecycle Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client, $clientUser];
    }
}
