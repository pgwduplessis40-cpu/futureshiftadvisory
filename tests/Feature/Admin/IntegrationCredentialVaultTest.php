<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Security\MfaChallenger;
use App\Services\Storage\KeyEnvelope;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IntegrationCredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_can_store_encrypted_credential_and_resolve_plaintext_only_via_service(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stats_nz',
                'field' => 'subscription_key',
                'value' => 'stats-live-secret-1234',
            ])
            ->assertRedirect(route('admin.integration-credentials.index', absolute: false));

        $credential = IntegrationCredential::query()->firstOrFail();

        $this->assertSame('stats_nz', $credential->integration_key);
        $this->assertSame('subscription_key', $credential->field);
        $this->assertSame(IntegrationCredential::STATUS_ACTIVE, $credential->status);
        $this->assertSame('1234', $credential->last_four);
        $this->assertStringNotContainsString('stats-live-secret-1234', (string) $credential->value_envelope);
        $this->assertSame(
            'stats-live-secret-1234',
            app(IntegrationCredentials::class)->get('stats_nz', 'subscription_key'),
        );
        $this->assertSame(
            KeyEnvelope::ALG_V1,
            app(KeyEnvelope::class)->inspect((string) $credential->value_envelope)['alg'],
        );

        $this->assertDatabaseHas('audit_events', [
            'action' => 'credential.set',
            'subject_id' => $credential->id,
        ]);

        $this->get(route('admin.integration-credentials.index'))
            ->assertOk()
            ->assertDontSee('stats-live-secret-1234');
    }

    public function test_rotation_uses_active_status_and_keeps_only_masked_metadata_visible(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stripe',
                'field' => 'secret',
                'value' => 'stripe-old-secret',
            ])
            ->assertRedirect();

        $this->actingAsMfa($admin)
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stripe',
                'field' => 'secret',
                'value' => 'stripe-new-secret',
            ])
            ->assertRedirect();

        $credential = IntegrationCredential::query()->where('integration_key', 'stripe')->firstOrFail();

        $this->assertSame(IntegrationCredential::STATUS_ACTIVE, $credential->status);
        $this->assertNotNull($credential->rotated_at);
        $this->assertSame('cret', $credential->last_four);
        $this->assertSame('stripe-new-secret', app(IntegrationCredentials::class)->get('stripe', 'secret'));
        $this->assertSame(1, IntegrationCredential::query()->where('integration_key', 'stripe')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'credential.rotated']);
    }

    public function test_revoked_credential_is_terminal_and_does_not_fall_back_to_config(): void
    {
        config(['integrations.stats_nz.api_key' => 'env-stats-key']);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stats_nz',
                'field' => 'subscription_key',
                'value' => 'db-stats-key',
            ])
            ->assertRedirect();

        $this->actingAsMfa($admin)
            ->patch(route('admin.integration-credentials.revoke'), [
                'integration_key' => 'stats_nz',
                'field' => 'subscription_key',
            ])
            ->assertRedirect(route('admin.integration-credentials.index', absolute: false));

        $credential = IntegrationCredential::query()->firstOrFail();

        $this->assertSame(IntegrationCredential::STATUS_REVOKED, $credential->status);
        $this->assertNull($credential->value_envelope);
        $this->assertNull(app(IntegrationCredentials::class)->get('stats_nz', 'subscription_key'));
        $this->assertDatabaseHas('audit_events', ['action' => 'credential.revoked']);
    }

    public function test_resolver_falls_back_to_config_only_when_no_db_row_exists(): void
    {
        config(['services.anthropic.key' => 'env-anthropic-key']);

        $this->assertSame(
            'env-anthropic-key',
            app(IntegrationCredentials::class)->get('anthropic', 'key'),
        );
    }

    public function test_non_super_admin_cannot_manage_credentials(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stats_nz',
                'field' => 'subscription_key',
                'value' => 'stats-live-secret-1234',
            ])
            ->assertForbidden();
    }

    public function test_mutation_requires_fresh_step_up_even_when_mfa_session_is_verified(): void
    {
        config(['security.fresh_step_up_minutes' => 5]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->withSession([
                MfaChallenger::SESSION_CONFIRMED_AT => now()->subMinutes(10)->getTimestamp(),
                MfaChallenger::SESSION_USER_ID => (string) $admin->getAuthIdentifier(),
            ])
            ->post(route('admin.integration-credentials.store'), [
                'integration_key' => 'stats_nz',
                'field' => 'subscription_key',
                'value' => 'stats-live-secret-1234',
            ])
            ->assertRedirect(route('mfa.challenge', ['reason' => 'fresh_step_up'], false));

        $this->assertDatabaseCount('integration_credentials', 0);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->superAdmin()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
