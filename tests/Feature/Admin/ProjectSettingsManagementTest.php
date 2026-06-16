<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CalendarConnection;
use App\Models\ProjectSetting;
use App\Models\User;
use App\Services\Calendar\CalendarConnector;
use App\Services\Settings\ProjectSettings;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProjectSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_view_project_settings_groups(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('admin.project-settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/project-settings/Index')
                ->where('groups.0.key', 'email_delivery')
                ->where('groups.3.key', 'microsoft_graph')
                ->where('routes.update', route('admin.project-settings.update', absolute: false))
                ->where('routes.reset', route('admin.project-settings.reset', absolute: false))
                ->where('routes.test_email', route('admin.project-settings.test-email', absolute: false))
                ->where('microsoftRedirectUri', route('calendar.callback', 'microsoft'))
            );
    }

    public function test_super_admin_can_store_project_email_settings_with_encrypted_secret(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->patch(route('admin.project-settings.update'), [
                'group' => 'email_delivery',
                'settings' => [
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => 'smtp.office365.com',
                    'mail.mailers.smtp.port' => 587,
                    'mail.mailers.smtp.scheme' => 'tls',
                    'mail.mailers.smtp.username' => 'shared@futureshiftadvisory.nz',
                    'mail.mailers.smtp.password' => 'smtp-secret-1234',
                    'mail.from.address' => 'shared@futureshiftadvisory.nz',
                    'mail.from.name' => 'Future Shift Advisory',
                    'mail.owner_address' => 'admin@futureshiftadvisory.nz',
                ],
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false));

        $secret = ProjectSetting::query()
            ->where('setting_key', 'mail.mailers.smtp.password')
            ->firstOrFail();

        $this->assertTrue($secret->is_secret);
        $this->assertSame('1234', $secret->last_four);
        $this->assertStringNotContainsString('smtp-secret-1234', (string) $secret->value_envelope);
        $this->assertSame(
            KeyEnvelope::ALG_V1,
            app(KeyEnvelope::class)->inspect((string) $secret->value_envelope)['alg'],
        );
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.office365.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtp-secret-1234', config('mail.mailers.smtp.password'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'project_setting.secret_set',
            'subject_id' => $secret->id,
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.project-settings.index'))
            ->assertOk()
            ->assertDontSee('smtp-secret-1234');
    }

    public function test_project_settings_feed_microsoft_graph_calendar_authorization(): void
    {
        $admin = $this->superAdmin();
        $settings = app(ProjectSettings::class);
        $definitions = $settings->definitionsByKey();

        $settings->set($definitions['integrations.calendar.microsoft.tenant'], 'fsa-test-tenant', $admin);
        $settings->set($definitions['integrations.calendar.microsoft.client_id'], 'graph-client-id', $admin);
        $settings->set(
            $definitions['integrations.calendar.microsoft.scopes'],
            "Calendars.ReadWrite\noffline_access\nUser.Read",
            $admin,
        );

        $url = app(CalendarConnector::class)->authorizeUrl($admin, CalendarConnection::PROVIDER_MICROSOFT);
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $this->assertStringContainsString('login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/authorize', $url);
        $this->assertSame('graph-client-id', $query['client_id'] ?? null);
        $this->assertSame('Calendars.ReadWrite offline_access User.Read', $query['scope'] ?? null);
        $this->assertSame('query', $query['response_mode'] ?? null);
    }

    public function test_super_admin_can_reset_project_setting_to_config_fallback(): void
    {
        $admin = $this->superAdmin();
        $settings = app(ProjectSettings::class);
        $definitions = $settings->definitionsByKey();

        $settings->set($definitions['mail.default'], 'smtp', $admin);

        $this->actingAsMfa($admin)
            ->patch(route('admin.project-settings.reset'), [
                'key' => 'mail.default',
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false));

        $this->assertNull(ProjectSetting::query()->where('setting_key', 'mail.default')->first());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'project_setting.revoked',
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->superAdmin()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
