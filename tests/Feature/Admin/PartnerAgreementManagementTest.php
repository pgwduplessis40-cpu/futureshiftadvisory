<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProjectSetting;
use App\Services\Settings\ProjectSettings;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PartnerAgreementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_partner_agreement_is_managed_outside_project_settings(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('admin.project-settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/project-settings/Index')
                ->has('groups', 4)
            );

        $this->actingAsMfa($admin)
            ->get(route('admin.partner-agreement.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/partner-agreement/Index')
                ->where('group.key', ProjectSettings::GROUP_PARTNER_AGREEMENT)
                ->where('group.title', 'Partner Agreement')
                ->where('group.fields.0.key', 'panels.agreements.title')
                ->where('routes.update', route('admin.partner-agreement.update', absolute: false))
                ->where('routes.reset', route('admin.partner-agreement.reset', absolute: false))
            );
    }

    public function test_super_admin_can_update_and_reset_partner_agreement_terms(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->patch(route('admin.partner-agreement.update'), [
                'settings' => [
                    'panels.agreements.broker_terms' => 'Updated broker partner wording.',
                ],
            ])
            ->assertRedirect(route('admin.partner-agreement.index', absolute: false));

        $this->assertSame('Updated broker partner wording.', config('panels.agreements.broker_terms'));
        $this->assertDatabaseHas('project_settings', [
            'setting_key' => 'panels.agreements.broker_terms',
            'group_key' => ProjectSettings::GROUP_PARTNER_AGREEMENT,
            'value' => 'Updated broker partner wording.',
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.partner-agreement.reset'), [
                'key' => 'panels.agreements.broker_terms',
            ])
            ->assertRedirect(route('admin.partner-agreement.index', absolute: false));

        $this->assertNull(ProjectSetting::query()
            ->where('setting_key', 'panels.agreements.broker_terms')
            ->first());
    }
}
