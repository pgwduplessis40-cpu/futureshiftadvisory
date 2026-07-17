<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EngagementType;
use App\Models\AdvisorClientTransferRequest;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_see_advisor_ownership_and_reassign_a_client(): void
    {
        $admin = $this->superAdmin();
        $fromAdvisor = $this->advisor('from@example.test', 'From Advisor');
        $targetAdvisor = $this->advisor('target@example.test', 'Target Advisor');
        $client = $this->client('Harbour Allocation Limited', $fromAdvisor);

        $this->actingAsMfa($admin)
            ->get(route('admin.client-allocations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/client-allocations/Index')
                ->has('clients', 1)
                ->where('clients.0.primary_advisor_name', 'From Advisor')
                ->where('clients.0.assignments.0.advisor_name', 'From Advisor')
                ->has('advisors', 2));

        $this->actingAsMfa($admin)
            ->patch(route('admin.client-allocations.reassign', $client), [
                'target_advisor_id' => $targetAdvisor->getKey(),
                'reason' => 'Capacity and sector fit.',
            ])
            ->assertRedirect(route('admin.client-allocations.index', absolute: false));

        $this->assertDatabaseMissing('client_team', [
            'client_id' => $client->getKey(),
            'user_id' => $fromAdvisor->getKey(),
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->getKey(),
            'user_id' => $targetAdvisor->getKey(),
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'advisor_team.client_reassigned_to_advisor',
            'subject_id' => $client->getKey(),
        ]);
    }

    public function test_advisor_can_request_a_transfer_and_super_admin_can_approve_it(): void
    {
        $admin = $this->superAdmin();
        $requestingAdvisor = $this->advisor('requesting@example.test', 'Requesting Advisor');
        $targetAdvisor = $this->advisor('receiving@example.test', 'Receiving Advisor');
        $client = $this->client('Transfer Request Limited', $requestingAdvisor);

        $this->actingAsMfa($requestingAdvisor)
            ->post(route('advisor.client-transfers.store'), [
                'client_id' => $client->getKey(),
                'target_advisor_id' => $targetAdvisor->getKey(),
                'reason' => 'The client needs specialist sector knowledge.',
            ])
            ->assertRedirect(route('advisor.client-transfers.index', absolute: false));

        $transfer = AdvisorClientTransferRequest::query()->sole();
        $this->assertSame(AdvisorClientTransferRequest::STATUS_PENDING, $transfer->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'advisor_client_transfer.requested',
            'subject_id' => $transfer->getKey(),
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.client-transfers.approve', $transfer), [
                'decision_reason' => 'Approved after capacity review.',
            ])
            ->assertRedirect(route('admin.client-allocations.index', absolute: false));

        $transfer->refresh();
        $this->assertSame(AdvisorClientTransferRequest::STATUS_APPROVED, $transfer->status);
        $this->assertSame($admin->getKey(), $transfer->reviewed_by_user_id);
        $this->assertNotNull($transfer->completed_at);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->getKey(),
            'user_id' => $targetAdvisor->getKey(),
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'advisor_client_transfer.approved',
            'subject_id' => $transfer->getKey(),
        ]);
    }

    public function test_advisor_cannot_request_a_transfer_for_another_advisors_client(): void
    {
        $owner = $this->advisor('owner@example.test', 'Client Owner');
        $otherAdvisor = $this->advisor('other@example.test', 'Other Advisor');
        $targetAdvisor = $this->advisor('target@example.test', 'Target Advisor');
        $client = $this->client('Private Client Limited', $owner);

        $this->actingAsMfa($otherAdvisor)
            ->post(route('advisor.client-transfers.store'), [
                'client_id' => $client->getKey(),
                'target_advisor_id' => $targetAdvisor->getKey(),
                'reason' => 'Attempted transfer.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('advisor_client_transfer_requests', 0);
    }

    public function test_junior_advisor_client_list_is_scoped_to_their_assignments(): void
    {
        $junior = $this->advisor('junior@example.test', 'Junior Advisor', User::TYPE_JUNIOR_ADVISOR);
        $otherAdvisor = $this->advisor('senior@example.test', 'Senior Advisor');
        $visibleClient = $this->client('Visible Limited', $junior);
        $hiddenClient = $this->client('Hidden Limited', $otherAdvisor);

        $this->actingAsMfa($junior)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Index')
                ->has('clients', 1)
                ->where('clients.0.id', $visibleClient->getKey())
                ->missing('clients.1'));

        $this->assertNotSame($visibleClient->getKey(), $hiddenClient->getKey());
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function advisor(string $email, string $name, string $type = User::TYPE_ADVISOR): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => $name,
            'email' => $email,
            'user_type' => $type,
            'primary_role' => $type,
            'session_timeout_minutes' => 30,
        ]);
        $user->assignRole($type);

        return $user;
    }

    private function client(string $name, User $advisor): Client
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }
}
