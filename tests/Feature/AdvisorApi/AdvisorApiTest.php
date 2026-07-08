<?php

declare(strict_types=1);

namespace Tests\Feature\AdvisorApi;

use App\Enums\EngagementType;
use App\Models\AdvisorApiClient;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Goal;
use App\Models\Meeting;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\User;
use App\Services\AdvisorApi\TokenIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdvisorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_advisor_api_requires_approved_hashed_token_and_scopes_reads(): void
    {
        [$advisor, $client, $otherClient] = $this->advisorWithClients();
        $superAdmin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);
        $issued = app(TokenIssuer::class)->issue(
            name: 'Integration',
            advisor: $advisor,
            approvedBy: $superAdmin,
            scopes: [AdvisorApiClient::SCOPE_READ_CLIENTS],
            rateLimitPerMinute: 60,
        );

        $this->assertStringStartsWith('fsa_api_', $issued['token']);
        $this->assertNotSame($issued['token'], $issued['client']->token_hash);
        $this->assertSame(hash('sha256', $issued['token']), $issued['client']->token_hash);

        $response = $this->withToken($issued['token'])->getJson('/api/advisor/v1/clients');

        $response->assertOk()
            ->assertJsonFragment(['id' => $client->id])
            ->assertJsonMissing(['id' => $otherClient->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'advisor_api.call']);
    }

    public function test_advisor_api_write_allowlist_and_rate_limit(): void
    {
        [$advisor, $client] = $this->advisorWithClients();
        $superAdmin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);
        $issued = app(TokenIssuer::class)->issue(
            name: 'Writer',
            advisor: $advisor,
            approvedBy: $superAdmin,
            scopes: [AdvisorApiClient::SCOPE_READ_CLIENTS, AdvisorApiClient::SCOPE_WRITE_MEETING_NOTES],
            rateLimitPerMinute: 1,
        );

        $this->withToken($issued['token'])->postJson("/api/advisor/v1/clients/{$client->id}/meeting-notes", [
            'title' => 'API note',
            'note' => 'Meeting note created by allowed API write.',
        ])->assertCreated();
        $this->assertSame(1, Meeting::query()->where('client_id', $client->id)->count());

        $this->withToken($issued['token'])->getJson('/api/advisor/v1/clients')->assertTooManyRequests();
    }

    public function test_advisor_api_meetings_and_actions_respect_client_public_holidays(): void
    {
        [$advisor, $client] = $this->advisorWithClients(region: 'South Canterbury');
        $superAdmin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);
        $issued = app(TokenIssuer::class)->issue(
            name: 'Holiday Writer',
            advisor: $advisor,
            approvedBy: $superAdmin,
            scopes: [
                AdvisorApiClient::SCOPE_WRITE_MEETING_NOTES,
                AdvisorApiClient::SCOPE_WRITE_ACTIONS,
            ],
            rateLimitPerMinute: 10,
        );
        $goal = Goal::query()->create([
            'client_id' => $client->id,
            'title' => 'API goal',
            'pv_target' => 0,
            'status' => Goal::STATUS_ACTIVE,
        ]);
        $milestone = Milestone::query()->create([
            'goal_id' => $goal->id,
            'client_id' => $client->id,
            'title' => 'API milestone',
            'status' => Milestone::STATUS_PENDING,
        ]);

        $this->withToken($issued['token'])->postJson("/api/advisor/v1/clients/{$client->id}/meeting-notes", [
            'title' => 'Blocked holiday note',
            'note' => 'This cannot occur on the client regional holiday.',
            'occurred_at' => '2026-09-28 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('occurred_at');

        $this->withToken($issued['token'])->postJson("/api/advisor/v1/clients/{$client->id}/actions", [
            'milestone_id' => $milestone->id,
            'title' => 'Blocked holiday action',
            'due_date' => '2026-09-28',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');

        $this->assertDatabaseMissing('meetings', ['title' => 'Blocked holiday note']);
        $this->assertDatabaseMissing('milestone_actions', ['title' => 'Blocked holiday action']);

        $this->withToken($issued['token'])->postJson("/api/advisor/v1/clients/{$client->id}/meeting-notes", [
            'title' => 'Allowed next-day note',
            'note' => 'This can occur after the regional holiday.',
            'occurred_at' => '2026-09-29 10:00:00',
        ])->assertCreated();

        $this->withToken($issued['token'])->postJson("/api/advisor/v1/clients/{$client->id}/actions", [
            'milestone_id' => $milestone->id,
            'title' => 'Allowed next-day action',
            'due_date' => '2026-09-29',
        ])->assertCreated();

        $this->assertDatabaseHas('meetings', ['title' => 'Allowed next-day note']);
        $this->assertDatabaseHas('milestone_actions', ['title' => 'Allowed next-day action']);
        $this->assertSame(1, MilestoneAction::query()->where('client_id', $client->id)->count());
    }

    /**
     * @return array{0: User, 1: Client, 2?: Client}
     */
    private function advisorWithClients(?string $region = null): array
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Advisor API Client Limited',
            'address' => $region ? ['region' => $region] : null,
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);
        $otherClient = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Hidden API Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        return [$advisor, $client, $otherClient];
    }
}
