<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CalendarConnection;
use App\Models\MailOAuthConnection;
use App\Models\ProjectSetting;
use App\Models\User;
use App\Services\Calendar\CalendarConnector;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Mail\MicrosoftGraphMailOAuthConnector;
use App\Services\Settings\ProjectSettings;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
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
                ->where('groups.0.fields.0.options.1', 'graph')
                ->where('routes.update', route('admin.project-settings.update', absolute: false))
                ->where('routes.reset', route('admin.project-settings.reset', absolute: false))
                ->where('routes.test_email', route('admin.project-settings.test-email', absolute: false))
                ->where('routes.test_slack', route('admin.project-settings.test-slack', absolute: false))
                ->where('routes.graph_mail_connect', route('admin.project-settings.mail-graph.connect', absolute: false))
                ->where('routes.graph_mail_disconnect', route('admin.project-settings.mail-graph.disconnect', absolute: false))
                ->where('microsoftRedirectUri', route('calendar.callback', 'microsoft'))
                ->where('microsoftMailRedirectUri', route('admin.project-settings.mail-graph.callback'))
                ->where('graphMail.connected', false)
            );
    }

    public function test_project_settings_mail_oauth_redirect_uri_uses_current_request_host(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get('https://futureshiftadvisory.test/admin/project-settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where(
                    'microsoftMailRedirectUri',
                    'https://futureshiftadvisory.test/admin/project-settings/mail/graph/callback',
                )
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
                    'mail.mailers.smtp.scheme' => 'smtp',
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
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
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

    public function test_super_admin_can_store_graph_mail_settings_with_encrypted_secret(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->patch(route('admin.project-settings.update'), [
                'group' => 'email_delivery',
                'settings' => [
                    'mail.default' => 'graph',
                    'mail.mailers.graph.tenant' => '7a864e37-4fb0-46c9-9a9e-d96335f6be5a',
                    'mail.mailers.graph.client_id' => '8d134408-8168-4858-9890-d7317389f744',
                    'mail.mailers.graph.client_secret' => 'graph-secret-9876',
                    'mail.mailers.graph.from_address' => 'pieter@futureshiftadvisory.nz',
                    'mail.mailers.graph.base_url' => 'https://graph.microsoft.com/v1.0',
                    'mail.mailers.graph.scope' => 'https://graph.microsoft.com/.default',
                    'mail.mailers.graph.timeout' => 20,
                ],
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false));

        $secret = ProjectSetting::query()
            ->where('setting_key', 'mail.mailers.graph.client_secret')
            ->firstOrFail();

        $this->assertTrue($secret->is_secret);
        $this->assertSame('9876', $secret->last_four);
        $this->assertStringNotContainsString('graph-secret-9876', (string) $secret->value_envelope);
        $this->assertSame('graph', config('mail.default'));
        $this->assertSame('7a864e37-4fb0-46c9-9a9e-d96335f6be5a', config('mail.mailers.graph.tenant'));
        $this->assertSame('graph-secret-9876', config('mail.mailers.graph.client_secret'));
        $this->assertSame('pieter@futureshiftadvisory.nz', config('mail.mailers.graph.from_address'));
        $this->assertSame(20, config('mail.mailers.graph.timeout'));
    }

    public function test_graph_sender_is_used_as_mail_from_when_default_from_is_placeholder(): void
    {
        $admin = $this->superAdmin();
        Config::set('mail.from.address', 'hello@example.com');

        $this->actingAsMfa($admin)
            ->patch(route('admin.project-settings.update'), [
                'group' => 'email_delivery',
                'settings' => [
                    'mail.default' => 'graph',
                    'mail.mailers.graph.tenant' => '7a864e37-4fb0-46c9-9a9e-d96335f6be5a',
                    'mail.mailers.graph.client_id' => '8d134408-8168-4858-9890-d7317389f744',
                    'mail.mailers.graph.client_secret' => 'graph-secret-9876',
                    'mail.mailers.graph.from_address' => 'pieter@futureshiftadvisory.nz',
                ],
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false));

        $this->assertSame('graph', config('mail.default'));
        $this->assertSame('pieter@futureshiftadvisory.nz', config('mail.mailers.graph.from_address'));
        $this->assertSame('pieter@futureshiftadvisory.nz', config('mail.from.address'));
    }

    public function test_graph_mailer_sends_raw_message_through_microsoft_graph(): void
    {
        Config::set('mail.default', 'graph');
        Config::set('mail.mailers.graph', [
            'transport' => 'graph',
            'tenant' => 'fsa-test-tenant',
            'client_id' => 'graph-client-id',
            'client_secret' => 'graph-client-secret',
            'from_address' => 'pieter@futureshiftadvisory.nz',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'token_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'scope' => 'https://graph.microsoft.com/.default',
            'timeout' => 15,
        ]);
        Mail::forgetMailers();

        Http::fake([
            'https://login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/token' => Http::response([
                'access_token' => 'graph-access-token',
                'expires_in' => 3600,
            ]),
            'https://graph.microsoft.com/v1.0/users/pieter%40futureshiftadvisory.nz/sendMail' => Http::response('', 202),
        ]);

        Mail::raw(
            'Graph mail body',
            fn ($message) => $message
                ->to('recipient@example.test')
                ->subject('Graph transport test'),
        );

        Http::assertSent(fn ($request): bool => $request->url() === 'https://login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'graph-client-id'
            && $request['client_secret'] === 'graph-client-secret'
            && $request['scope'] === 'https://graph.microsoft.com/.default');

        Http::assertSent(function ($request): bool {
            $decoded = base64_decode($request->body(), true);

            return $request->url() === 'https://graph.microsoft.com/v1.0/users/pieter%40futureshiftadvisory.nz/sendMail'
                && $request->hasHeader('Authorization', 'Bearer graph-access-token')
                && $request->hasHeader('Content-Type', 'text/plain')
                && is_string($decoded)
                && str_contains($decoded, 'Graph mail body')
                && str_contains($decoded, 'Subject: Graph transport test');
        });
    }

    public function test_graph_mailer_refreshes_cached_token_once_after_permission_denied(): void
    {
        Config::set('mail.default', 'graph');
        Config::set('mail.mailers.graph', [
            'transport' => 'graph',
            'tenant' => 'fsa-test-tenant',
            'client_id' => 'graph-client-id',
            'client_secret' => 'graph-client-secret',
            'from_address' => 'pieter@futureshiftadvisory.nz',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'token_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'scope' => 'https://graph.microsoft.com/.default',
            'timeout' => 15,
        ]);
        Mail::forgetMailers();

        Cache::put(
            'mail:graph:token:'.sha1('fsa-test-tenant|graph-client-id|https://graph.microsoft.com/.default'),
            'stale-token',
            3600,
        );

        $sendAttempts = 0;
        $tokenAttempts = 0;

        Http::fake(function ($request) use (&$sendAttempts, &$tokenAttempts) {
            if ($request->url() === 'https://login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/token') {
                $tokenAttempts++;

                return Http::response([
                    'access_token' => 'fresh-token',
                    'expires_in' => 3600,
                ]);
            }

            if ($request->url() === 'https://graph.microsoft.com/v1.0/users/pieter%40futureshiftadvisory.nz/sendMail') {
                $sendAttempts++;

                return $sendAttempts === 1
                    ? Http::response(['error' => ['message' => 'Access is denied.']], 403)
                    : Http::response('', 202);
            }

            return Http::response('', 404);
        });

        Mail::raw(
            'Graph mail body',
            fn ($message) => $message
                ->to('recipient@example.test')
                ->subject('Graph transport retry test'),
        );

        $this->assertSame(2, $sendAttempts);
        $this->assertSame(1, $tokenAttempts);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users/pieter%40futureshiftadvisory.nz/sendMail'
            && $request->hasHeader('Authorization', 'Bearer stale-token'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users/pieter%40futureshiftadvisory.nz/sendMail'
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_super_admin_can_connect_graph_mail_oauth_and_send_test_email(): void
    {
        $admin = $this->superAdmin();

        Config::set('mail.default', 'log');
        Config::set('mail.from.address', 'hello@example.com');
        Config::set('mail.mailers.graph', [
            'transport' => 'graph',
            'auth_mode' => 'client_credentials',
            'tenant' => 'fsa-test-tenant',
            'client_id' => 'graph-client-id',
            'client_secret' => 'graph-client-secret',
            'from_address' => 'principal@futureshiftadvisory.nz',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'authorize_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'scope' => 'https://graph.microsoft.com/.default',
            'delegated_scopes' => ['offline_access', 'User.Read', 'Mail.Send', 'Mail.Send.Shared'],
            'timeout' => 15,
        ]);

        $mailCallbackUrl = 'https://futureshiftadvisory.test/admin/project-settings/mail/graph/callback';
        $authorizeUrl = app(MicrosoftGraphMailOAuthConnector::class)->authorizeUrl($admin, $mailCallbackUrl);
        parse_str((string) parse_url($authorizeUrl, PHP_URL_QUERY), $authorizeQuery);

        $this->assertSame('graph-client-id', $authorizeQuery['client_id'] ?? null);
        $this->assertSame('offline_access User.Read Mail.Send Mail.Send.Shared', $authorizeQuery['scope'] ?? null);
        $this->assertSame($mailCallbackUrl, $authorizeQuery['redirect_uri'] ?? null);
        $this->assertSame('select_account', $authorizeQuery['prompt'] ?? null);
        $this->assertSame('principal@futureshiftadvisory.nz', $authorizeQuery['login_hint'] ?? null);

        Http::fake([
            'https://login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/token' => Http::response([
                'access_token' => 'delegated-access-token',
                'refresh_token' => 'delegated-refresh-token',
                'expires_in' => 3600,
            ]),
            'https://graph.microsoft.com/v1.0/me*' => Http::response([
                'id' => 'graph-user-id',
                'mail' => 'pieter@futureshiftadvisory.nz',
                'userPrincipalName' => 'pieter@futureshiftadvisory.nz',
            ]),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.project-settings.mail-graph.callback', [
                'code' => 'oauth-code',
                'state' => $authorizeQuery['state'] ?? '',
            ]))
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHas('status', 'graph-mail-oauth-connected');

        /** @var MailOAuthConnection $connection */
        $connection = MailOAuthConnection::query()->firstOrFail();
        $this->assertSame(MailOAuthConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('pieter@futureshiftadvisory.nz', $connection->mailbox_email);
        $this->assertStringNotContainsString('delegated-access-token', $connection->access_token_envelope);
        $this->assertStringNotContainsString('delegated-refresh-token', (string) $connection->refresh_token_envelope);
        $this->assertSame('delegated-access-token', app(KeyEnvelope::class)->decrypt($connection->access_token_envelope));
        $this->assertSame('delegated-refresh-token', app(KeyEnvelope::class)->decrypt((string) $connection->refresh_token_envelope));
        $this->assertSame('graph', config('mail.default'));
        $this->assertSame('delegated', config('mail.mailers.graph.auth_mode'));
        $this->assertSame('principal@futureshiftadvisory.nz', config('mail.mailers.graph.from_address'));
        $this->assertSame('principal@futureshiftadvisory.nz', config('mail.from.address'));

        app(RequestContext::class)->apply(RequestContext::ROLE_GUEST, []);
        $guestStatus = app(MicrosoftGraphMailOAuthConnector::class)->statusPayload();
        $this->assertTrue($guestStatus['connected']);
        $this->assertSame('pieter@futureshiftadvisory.nz', $guestStatus['mailbox_email']);
        $this->assertSame('principal@futureshiftadvisory.nz', $guestStatus['configured_sender']);
        $this->assertTrue(app(IntegrationActivationResolver::class)->readiness('mail_delivery'));

        Http::fake([
            'https://graph.microsoft.com/v1.0/users/principal%40futureshiftadvisory.nz/sendMail' => Http::response('', 202),
        ]);
        Mail::forgetMailers();

        $this->actingAsMfa($admin)
            ->from(route('admin.project-settings.index'))
            ->post(route('admin.project-settings.test-email'), [
                'recipient' => 'recipient@example.test',
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHas('status', 'project-settings-test-email-sent');

        Http::assertSent(function ($request): bool {
            $decoded = base64_decode($request->body(), true);

            return $request->url() === 'https://graph.microsoft.com/v1.0/users/principal%40futureshiftadvisory.nz/sendMail'
                && $request->hasHeader('Authorization', 'Bearer delegated-access-token')
                && is_string($decoded)
                && str_contains($decoded, 'Future Shift Advisory project email settings test.')
                && str_contains($decoded, 'Subject: Future Shift Advisory email test');
        });
    }

    public function test_graph_mail_oauth_reuses_calendar_microsoft_credentials_when_mail_credentials_are_blank(): void
    {
        $admin = $this->superAdmin();

        Config::set('mail.mailers.graph', [
            'transport' => 'graph',
            'auth_mode' => 'client_credentials',
            'tenant' => '',
            'client_id' => '',
            'client_secret' => '',
            'from_address' => '',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'authorize_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'scope' => 'https://graph.microsoft.com/.default',
            'delegated_scopes' => ['offline_access', 'User.Read', 'Mail.Send', 'Mail.Send.Shared'],
            'timeout' => 15,
        ]);
        Config::set('integrations.calendar.microsoft.tenant', 'fsa-test-tenant');
        Config::set('integrations.calendar.microsoft.client_id', 'existing-calendar-client-id');
        Config::set('integrations.calendar.microsoft.client_secret', 'existing-calendar-client-secret');

        $authorizeUrl = app(MicrosoftGraphMailOAuthConnector::class)->authorizeUrl($admin);
        parse_str((string) parse_url($authorizeUrl, PHP_URL_QUERY), $authorizeQuery);

        $this->assertStringContainsString('login.microsoftonline.com/fsa-test-tenant/oauth2/v2.0/authorize', $authorizeUrl);
        $this->assertSame('existing-calendar-client-id', $authorizeQuery['client_id'] ?? null);
        $this->assertSame('offline_access User.Read Mail.Send Mail.Send.Shared', $authorizeQuery['scope'] ?? null);
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

    public function test_legacy_smtp_scheme_values_are_mapped_to_supported_mailer_schemes(): void
    {
        $admin = $this->superAdmin();
        $settings = app(ProjectSettings::class);
        $definitions = $settings->definitionsByKey();

        $settings->set($definitions['mail.mailers.smtp.scheme'], 'tls', $admin);

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));

        $this->actingAsMfa($admin)
            ->get(route('admin.project-settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('groups.0.fields.3.key', 'mail.mailers.smtp.scheme')
                ->where('groups.0.fields.3.value', 'smtp')
                ->where('groups.0.fields.3.options.0', 'smtp')
                ->where('groups.0.fields.3.options.1', 'smtps')
            );
    }

    public function test_test_email_returns_provider_failure_as_validation_error(): void
    {
        $admin = $this->superAdmin();

        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new RuntimeException('SMTP authentication failed.'));

        $this->actingAsMfa($admin)
            ->from(route('admin.project-settings.index'))
            ->post(route('admin.project-settings.test-email'), [
                'recipient' => 'pieter@futureshiftadvisory.nz',
            ])
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHasErrors([
                'recipient' => 'Email test failed: SMTP authentication failed.',
            ]);
    }

    public function test_test_slack_webhook_posts_to_configured_webhook(): void
    {
        $admin = $this->superAdmin();

        Config::set('logging.channels.slack.url', 'https://hooks.slack.test/services/fsa-alerts');

        Http::fake([
            'https://hooks.slack.test/services/fsa-alerts' => Http::response('ok', 200),
        ]);

        $this->actingAsMfa($admin)
            ->from(route('admin.project-settings.index'))
            ->post(route('admin.project-settings.test-slack'))
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHas('toast.message', 'Slack test alert sent.');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.slack.test/services/fsa-alerts'
            && str_contains((string) $request['text'], 'Future Shift Advisory Slack logging test'));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'project_settings.slack_webhook_test_sent',
            'actor_user_key' => (string) $admin->getKey(),
        ]);
    }

    public function test_test_slack_webhook_requires_configured_url(): void
    {
        $admin = $this->superAdmin();

        Config::set('logging.channels.slack.url', '');

        $this->actingAsMfa($admin)
            ->from(route('admin.project-settings.index'))
            ->post(route('admin.project-settings.test-slack'))
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHasErrors([
                'slack_webhook' => 'Slack test failed: add and save a Logging Slack webhook URL first.',
            ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'project_settings.slack_webhook_test_failed',
            'actor_user_key' => (string) $admin->getKey(),
        ]);
    }

    public function test_test_slack_webhook_returns_provider_failure_as_validation_error(): void
    {
        $admin = $this->superAdmin();

        Config::set('logging.channels.slack.url', 'https://hooks.slack.test/services/fsa-alerts');

        Http::fake([
            'https://hooks.slack.test/services/fsa-alerts' => Http::response('nope', 500),
        ]);

        $this->actingAsMfa($admin)
            ->from(route('admin.project-settings.index'))
            ->post(route('admin.project-settings.test-slack'))
            ->assertRedirect(route('admin.project-settings.index', absolute: false))
            ->assertSessionHasErrors([
                'slack_webhook' => 'Slack test failed: Slack returned HTTP 500.',
            ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'project_settings.slack_webhook_test_failed',
            'actor_user_key' => (string) $admin->getKey(),
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->superAdmin()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
