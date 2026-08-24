<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PlatformGovernanceVersion;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PlatformGovernanceVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_sees_the_current_platform_governance_version_and_history(): void
    {
        $admin = $this->superAdmin();
        $current = $this->version($admin, 3, true, ['Keep client data confidential.'], ['Advisor']);
        $this->version($admin, 2, false, ['Explain recommendations clearly.'], ['Mentor']);

        $this->actingAsMfa($admin)
            ->get(route('admin.principles-roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/principles-roles/Index')
                ->where('current.id', $current->getKey())
                ->where('current.version', 3)
                ->where('current.principles', ['Keep client data confidential.'])
                ->where('current.roles', ['Advisor'])
                ->where('current.is_active', true)
                ->has('defaults.principles', 8)
                ->has('defaults.roles', 8)
                ->has('history', 3)
                ->where('history.0.created_by', $admin->name)
                ->where('storeUrl', route('admin.principles-roles.store', absolute: false))
            );
    }

    public function test_super_admin_publishes_a_new_normalized_governance_version_and_retires_the_previous_one(): void
    {
        $admin = $this->superAdmin();
        $previous = $this->version($admin, 4, true, ['Previous principle with sufficient detail.'], ['Previous role']);

        $this->actingAsMfa($admin)
            ->post(route('admin.principles-roles.store'), [
                'principles_text' => "- Keep client data confidential.\n\n* Explain material uncertainty plainly.",
                'roles_text' => "Advisor\n\n- Mentor",
                'notes' => 'Clarified the written governance standard for the current release.',
            ])
            ->assertRedirect(route('admin.principles-roles.index', absolute: false))
            ->assertSessionHas('status', 'platform-governance-updated')
            ->assertSessionHas('toast.message', 'Principles & Roles updated to version 5.');

        $version = PlatformGovernanceVersion::query()->where('version', 5)->firstOrFail();

        $this->assertSame([
            'Keep client data confidential.',
            'Explain material uncertainty plainly.',
        ], $version->principles);
        $this->assertSame(['Advisor', 'Mentor'], $version->roles);
        $this->assertSame('Clarified the written governance standard for the current release.', $version->notes);
        $this->assertTrue($version->is_active);
        $this->assertFalse($previous->refresh()->is_active);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'platform_governance.updated',
            'subject_id' => $version->getKey(),
            'actor_user_key' => (string) $admin->getKey(),
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }

    /**
     * @param  array<int, string>  $principles
     * @param  array<int, string>  $roles
     */
    private function version(User $creator, int $version, bool $active, array $principles, array $roles): PlatformGovernanceVersion
    {
        return PlatformGovernanceVersion::query()->create([
            'version' => $version,
            'principles' => $principles,
            'roles' => $roles,
            'is_active' => $active,
            'activated_at' => now()->subDay(),
            'created_by_user_id' => $creator->getKey(),
        ]);
    }
}
