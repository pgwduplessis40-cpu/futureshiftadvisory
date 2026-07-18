<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\PilotFeeWaiverProgram;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PilotFeeWaiverManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_open_the_program_and_assign_a_dated_client_waiver(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Pilot Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.pilot-fee-waivers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/pilot-fee-waivers/Index')
                ->where('program.status', PilotFeeWaiverProgram::STATUS_CLOSED)
                ->has('clients', 1));

        $this->actingAsMfa($admin)
            ->patch(route('admin.pilot-fee-waivers.program.update'), [
                'status' => PilotFeeWaiverProgram::STATUS_OPEN,
            ])
            ->assertRedirect(route('admin.pilot-fee-waivers.index', absolute: false));

        $this->actingAsMfa($admin)
            ->patch(route('admin.pilot-fee-waivers.clients.update', $client), [
                'enabled' => true,
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addMonth()->toDateString(),
                'reason' => 'Approved for the test pilot.',
            ])
            ->assertRedirect(route('admin.pilot-fee-waivers.index', absolute: false));

        $client->refresh();
        $this->assertTrue($client->pilot_fee_waiver_enabled);
        $this->assertSame('Approved for the test pilot.', $client->pilot_fee_waiver_reason);
        $this->assertSame($admin->getKey(), $client->pilot_fee_waiver_approved_by_user_id);
        $this->assertNotNull($client->pilot_fee_waiver_expires_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'pilot_fee_waiver_program.updated',
            'subject_id' => PilotFeeWaiverProgram::query()->sole()->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'client.pilot_fee_waiver.updated',
            'subject_id' => $client->getKey(),
        ]);
    }

    public function test_closed_program_rejects_a_new_client_waiver(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Non Pilot Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.pilot-fee-waivers.clients.update', $client), [
                'enabled' => true,
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addMonth()->toDateString(),
                'reason' => 'Should not be accepted while closed.',
            ])
            ->assertSessionHasErrors('enabled');

        $this->assertFalse($client->refresh()->pilot_fee_waiver_enabled);
    }
}
