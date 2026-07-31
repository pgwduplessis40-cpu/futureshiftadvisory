<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalHealth;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OperationalHealthFixtureVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_fixtures_are_excluded_from_operational_directories_and_capacity(): void
    {
        Storage::fake('secure_local');
        $this->seed(RoleSeeder::class);
        $this->artisan('fsa:seed-operational-health-fixtures')->assertSuccessful();

        $monitorAdmin = User::query()
            ->where('email', config('operational_health.users.super_admin_email'))
            ->firstOrFail();
        $advisor = User::factory()->withTwoFactor()->create([
            'name' => 'Visible Advisor',
            'email' => 'visible-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'legal_name' => 'Visible Advisory Client Limited',
            'trading_name' => 'Visible Advisory Client',
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'status' => ClientStatus::ACTIVE,
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal', EngagementType::STANDARD_ADVISORY->value],
        ]);

        $monitorClient = Client::query()
            ->where('registry_sources->source', 'operational_health_fixture')
            ->firstOrFail();
        ClientTeamMember::query()->create([
            'client_id' => $monitorClient->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal'],
        ]);

        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $monitorAdmin->getKey(),
            'name' => 'Visible Founder',
            'email' => 'visible-founder@example.test',
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('clients', 1)
                ->where('clients.0.id', $client->getKey()));

        $this->actingAsMfa($monitorAdmin)
            ->get(route('advisor.entrepreneurs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('entrepreneurs', 1)
                ->where('entrepreneurs.0.id', $profile->getKey())
                ->where('capacity.active_count', 1));

        $this->actingAsMfa($monitorAdmin)
            ->get(route('admin.pilot-fee-waivers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('clients', 2)
                ->where('clients.0.id', $client->getKey())
                ->where('clients.1.id', $profile->getKey()));

        $this->actingAsMfa($monitorAdmin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('staff', 1)
                ->where('staff.0.id', $advisor->getKey())
                ->where('staff.0.client_capacity.active_count', 1));

        $this->actingAsMfa($monitorAdmin)
            ->get(route('admin.client-allocations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('clients', 1)
                ->where('clients.0.id', $client->getKey())
                ->has('advisors', 1)
                ->where('advisors.0.id', $advisor->getKey()));
    }
}
