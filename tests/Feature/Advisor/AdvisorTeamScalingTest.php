<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Models\AdvisorTeamMember;
use App\Models\Client;
use App\Models\User;
use App\Services\Clients\AdvisorTeamManager;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdvisorTeamScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lead_inherits_team_clients_but_members_only_see_direct_clients(): void
    {
        app(RequestContext::class)->apply('system', []);
        $manager = app(AdvisorTeamManager::class);
        $lead = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $member = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $observer = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $client = $this->client('Harbour Hive');

        $team = $manager->createTeam('North Advisory', $lead);
        $manager->addMember($team, $member, AdvisorTeamMember::ROLE_MEMBER);
        $manager->assignClient($team, $client, $member);

        $this->assertContains((string) $client->id, $lead->accessibleClientIds());
        $this->assertContains((string) $client->id, $member->accessibleClientIds());
        $this->assertNotContains((string) $client->id, $observer->accessibleClientIds());

        $capacity = $manager->capacity($team);
        $this->assertSame(2, $capacity['advisor_count']);
        $this->assertSame(1, $capacity['active_client_count']);
    }

    public function test_client_reassignment_is_audited(): void
    {
        app(RequestContext::class)->apply('system', []);
        $manager = app(AdvisorTeamManager::class);
        $leadA = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $leadB = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $advisor = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $client = $this->client('Meridian Workshop');
        $teamA = $manager->createTeam('North Advisory', $leadA);
        $teamB = $manager->createTeam('South Advisory', $leadB);
        $manager->assignClient($teamA, $client, $advisor);

        $count = $manager->reassignClient($teamA, $teamB, $client, $leadA);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'advisor_team_id' => $teamB->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'advisor_team.client_reassigned',
            'subject_id' => $client->id,
        ]);
    }

    private function client(string $name): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);
    }
}
