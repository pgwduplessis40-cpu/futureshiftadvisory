<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\EngagementType;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ClientPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_advisor_can_view_only_assigned_clients(): void
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $assigned = $this->client('Assigned Policy Client');
        $other = $this->client('Other Policy Client');

        ClientTeamMember::query()->create([
            'client_id' => $assigned->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [],
        ]);

        $this->assertTrue(Gate::forUser($advisor)->allows('view', $assigned));
        $this->assertFalse(Gate::forUser($advisor)->allows('view', $other));
    }

    public function test_super_admin_permission_can_view_any_client(): void
    {
        $admin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $this->client('Admin Policy Client')));
    }

    public function test_manage_permission_still_requires_subject_access(): void
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->givePermissionTo(Permission::CLIENTS_MANAGE->value);

        $this->assertFalse(Gate::forUser($advisor)->allows('update', $this->client('Unassigned Managed Client')));
    }

    private function client(string $name): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
        ]);
    }
}
