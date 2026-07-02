<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Payments\PaymentWebhookVerifier;
use App\Services\Voice\Contracts\WhisperClient;
use App\Services\Voice\FakeWhisperClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class IntegrationActivationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_backed_live_requires_all_credentials_ready(): void
    {
        config([
            'integrations.stats_nz.live' => true,
            'integrations.stats_nz.api_key' => 'stats-env-key',
        ]);

        $this->assertTrue(app(IntegrationActivationResolver::class)->isLive('stats_nz'));

        config(['integrations.stats_nz.api_key' => null]);

        $this->assertFalse(app(IntegrationActivationResolver::class)->isLive('stats_nz'));
    }

    public function test_revoked_db_credential_is_terminal_even_when_env_fallback_exists(): void
    {
        config([
            'integrations.stats_nz.live' => true,
            'integrations.stats_nz.api_key' => 'stats-env-key',
        ]);
        $admin = $this->admin();
        $credentials = app(IntegrationCredentials::class);

        $credentials->set('stats_nz', 'subscription_key', 'stats-db-key', $admin);
        $credentials->revoke('stats_nz', 'subscription_key', $admin);

        $this->assertFalse(app(IntegrationActivationResolver::class)->isLive('stats_nz'));
        $this->assertNull($credentials->get('stats_nz', 'subscription_key'));
    }

    public function test_db_activation_can_make_a_wired_env_backed_integration_live_with_flag_off(): void
    {
        config([
            'integrations.stats_nz.live' => false,
            'integrations.stats_nz.api_key' => 'stats-env-key',
        ]);

        app(IntegrationActivationResolver::class)->activate('stats_nz', $this->admin());

        $this->assertTrue(app(IntegrationActivationResolver::class)->isLive('stats_nz'));
    }

    public function test_companies_entity_role_search_accepts_legacy_companies_office_key(): void
    {
        config([
            'integrations.companies_entity_role_search.live' => true,
            'integrations.companies_entity_role_search.api_key' => null,
            'integrations.companies_office.api_key' => null,
        ]);

        app(IntegrationCredentials::class)->set('companies_office', 'api_key', 'legacy-role-search-key', $this->admin());

        $resolver = app(IntegrationActivationResolver::class);

        $this->assertTrue($resolver->credentialsReady('companies_entity_role_search'));
        $this->assertTrue($resolver->readiness('companies_entity_role_search'));
        $this->assertTrue($resolver->isLive('companies_entity_role_search'));
    }

    public function test_activation_is_blocked_until_exact_required_credentials_are_present(): void
    {
        config([
            'integrations.payments.stripe.live' => false,
            'integrations.payments.stripe.secret' => 'sk_test_only',
            'integrations.payments.stripe.webhook_secret' => null,
        ]);

        try {
            app(IntegrationActivationResolver::class)->activate('stripe', $this->admin());
            $this->fail('Stripe activation should require both the secret and webhook secret.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('integration_activations', 0);
    }

    public function test_revoking_required_credential_drops_activated_integration_off_live(): void
    {
        config([
            'integrations.payments.stripe.live' => false,
            'integrations.payments.stripe.secret' => null,
            'integrations.payments.stripe.webhook_secret' => null,
        ]);
        $admin = $this->admin();
        $credentials = app(IntegrationCredentials::class);

        $credentials->set('stripe', 'secret', 'sk_live_vaulted', $admin);
        $credentials->set('stripe', 'webhook_secret', 'whsec_vaulted', $admin);
        app(IntegrationActivationResolver::class)->activate('stripe', $admin);

        $this->assertTrue(app(IntegrationActivationResolver::class)->isLive('stripe'));

        $credentials->revoke('stripe', 'secret', $admin);

        $this->assertFalse(app(IntegrationActivationResolver::class)->isLive('stripe'));
    }

    public function test_activating_replacement_ai_provider_deactivates_current_ai_provider(): void
    {
        config([
            'services.anthropic.key' => 'anthropic-live-key',
            'ai.providers.replacement' => [
                'display_name' => 'Replacement AI',
                'integration_key' => 'replacement_ai',
                'client' => self::class,
                'status' => 'available',
            ],
            'integration_registry.integrations.replacement_ai' => [
                'display_name' => 'Replacement AI',
                'category' => 'ai',
                'fallback_mode' => 'api_required',
                'managed_via' => 'vault',
                'wiring_status' => 'wired',
                'credentials' => [],
            ],
        ]);
        $admin = $this->admin();
        $resolver = app(IntegrationActivationResolver::class);

        $this->assertTrue($resolver->isLive('anthropic'));

        $resolver->activate('replacement_ai', $admin);

        $this->assertFalse($resolver->isLive('anthropic'));
        $this->assertTrue($resolver->isLive('replacement_ai'));
        $this->assertDatabaseHas('integration_activations', [
            'integration_key' => 'anthropic',
            'active' => false,
        ]);
        $this->assertDatabaseHas('integration_activations', [
            'integration_key' => 'replacement_ai',
            'active' => true,
        ]);
    }

    public function test_not_wired_integration_forces_fake_binding_even_when_env_flag_is_enabled(): void
    {
        config(['services.whisper.live' => true]);

        $this->assertFalse(app(IntegrationActivationResolver::class)->isLive('whisper'));
        $this->assertInstanceOf(FakeWhisperClient::class, app(WhisperClient::class));
    }

    public function test_payment_webhook_verifier_uses_vaulted_secret_over_raw_config(): void
    {
        config(['integrations.payments.stripe.webhook_secret' => 'wrong-config-secret']);
        app(IntegrationCredentials::class)->set('stripe', 'webhook_secret', 'vaulted-webhook-secret', $this->admin());

        $body = '{"id":"evt_vaulted"}';
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'vaulted-webhook-secret');
        $request = Request::create(
            uri: '/webhooks/payments/stripe',
            method: 'POST',
            server: ['HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature],
            content: $body,
        );

        $this->assertSame([true, null], app(PaymentWebhookVerifier::class)->verifyStripe($request));
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }
}
