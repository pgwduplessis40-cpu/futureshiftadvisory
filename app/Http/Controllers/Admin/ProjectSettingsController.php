<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Mail\MicrosoftGraphMailOAuthConnector;
use App\Services\Settings\ProjectSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class ProjectSettingsController extends Controller
{
    public function __construct(
        private readonly ProjectSettings $settings,
        private readonly AuditWriter $auditWriter,
        private readonly MicrosoftGraphMailOAuthConnector $graphMailOAuth,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/project-settings/Index', [
            'groups' => $this->settings->groupsForUi(),
            'routes' => [
                'update' => route('admin.project-settings.update', absolute: false),
                'reset' => route('admin.project-settings.reset', absolute: false),
                'test_email' => route('admin.project-settings.test-email', absolute: false),
                'test_slack' => route('admin.project-settings.test-slack', absolute: false),
                'graph_mail_connect' => route('admin.project-settings.mail-graph.connect', absolute: false),
                'graph_mail_disconnect' => route('admin.project-settings.mail-graph.disconnect', absolute: false),
            ],
            'microsoftRedirectUri' => route('calendar.callback', 'microsoft'),
            'microsoftMailRedirectUri' => $this->mailGraphCallbackUrl($request),
            'graphMail' => $this->graphMailOAuth->statusPayload(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', Rule::in(array_keys($this->groupDefinitions()))],
            'settings' => ['required', 'array'],
        ]);

        $group = (string) $validated['group'];
        $values = $validated['settings'];
        $definitions = $this->settings->definitionsForGroup($group);
        $actor = $this->actor($request);
        $saved = 0;

        foreach ($definitions as $definition) {
            $key = (string) $definition['key'];
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            if ((string) $definition['type'] === ProjectSettings::TYPE_SECRET && trim((string) $value) === '') {
                continue;
            }

            validator(
                ['value' => $value],
                ['value' => $this->rulesFor($definition)],
                [],
                ['value' => (string) $definition['label']],
            )->validate();

            $this->settings->set($definition, $value, $actor);
            $saved++;
        }

        if ($saved === 0) {
            throw ValidationException::withMessages([
                'settings' => 'No settings were changed.',
            ]);
        }

        return to_route('admin.project-settings.index')->with('status', 'project-settings-saved');
    }

    public function reset(Request $request): RedirectResponse
    {
        $definitions = $this->settings->definitionsByKey();
        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys($definitions))],
        ]);

        $this->settings->revoke($definitions[(string) $validated['key']], $this->actor($request));

        return to_route('admin.project-settings.index')->with('status', 'project-setting-reset');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:255'],
        ]);

        try {
            Mail::raw(
                'Future Shift Advisory project email settings test.',
                fn ($message) => $message
                    ->to((string) $validated['recipient'])
                    ->subject('Future Shift Advisory email test'),
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'recipient' => 'Email test failed: '.$this->mailFailureMessage($exception),
            ]);
        }

        return to_route('admin.project-settings.index')->with('status', 'project-settings-test-email-sent');
    }

    public function testSlackWebhook(Request $request): RedirectResponse
    {
        $webhookUrl = trim((string) Config::get('logging.channels.slack.url', ''));
        $actor = $this->actor($request);

        if ($webhookUrl === '') {
            $this->auditWriter->record('project_settings.slack_webhook_test_failed', actor: $actor, after: [
                'provider' => 'slack',
                'config_path' => 'logging.channels.slack.url',
                'configured' => false,
                'reason' => 'missing_webhook_url',
            ]);

            throw ValidationException::withMessages([
                'slack_webhook' => 'Slack test failed: add and save a Logging Slack webhook URL first.',
            ]);
        }

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'text' => 'Future Shift Advisory Slack logging test. If you can see this, operational alerts are connected.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->auditWriter->record('project_settings.slack_webhook_test_failed', actor: $actor, after: [
                'provider' => 'slack',
                'config_path' => 'logging.channels.slack.url',
                'configured' => true,
                'webhook_last_four' => mb_substr($webhookUrl, -4),
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'slack_webhook' => 'Slack test failed: '.$this->providerFailureMessage($exception),
            ]);
        }

        if (! $response->successful()) {
            $this->auditWriter->record('project_settings.slack_webhook_test_failed', actor: $actor, after: [
                'provider' => 'slack',
                'config_path' => 'logging.channels.slack.url',
                'configured' => true,
                'webhook_last_four' => mb_substr($webhookUrl, -4),
                'response_status' => $response->status(),
            ]);

            throw ValidationException::withMessages([
                'slack_webhook' => 'Slack test failed: Slack returned HTTP '.$response->status().'.',
            ]);
        }

        $this->auditWriter->record('project_settings.slack_webhook_test_sent', actor: $actor, after: [
            'provider' => 'slack',
            'config_path' => 'logging.channels.slack.url',
            'configured' => true,
            'webhook_last_four' => mb_substr($webhookUrl, -4),
            'response_status' => $response->status(),
        ]);

        return to_route('admin.project-settings.index')
            ->with('status', 'project-settings-test-slack-sent')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Slack test alert sent.',
            ]);
    }

    private function mailFailureMessage(Throwable $exception): string
    {
        return $this->providerFailureMessage(
            $exception,
            'the mail provider rejected the request. Check the credentials and provider configuration.',
        );
    }

    private function providerFailureMessage(
        Throwable $exception,
        string $fallback = 'the provider rejected the request. Check the credentials and provider configuration.',
    ): string {
        $message = trim((string) preg_replace('/\s+/', ' ', $exception->getMessage()));

        if ($message === '') {
            return $fallback;
        }

        return mb_substr($message, 0, 500);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupDefinitions(): array
    {
        return collect($this->settings->definitionsByKey())
            ->reject(fn (array $definition): bool => (string) $definition['group'] === ProjectSettings::GROUP_PARTNER_AGREEMENT)
            ->groupBy(fn (array $definition): string => (string) $definition['group'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, mixed>
     */
    private function rulesFor(array $definition): array
    {
        return match ((string) $definition['type']) {
            ProjectSettings::TYPE_SECRET => ['required', 'string', 'min:4', 'max:10000'],
            ProjectSettings::TYPE_INTEGER => [
                'required',
                'integer',
                'min:'.(int) ($definition['min'] ?? 0),
                'max:'.(int) ($definition['max'] ?? PHP_INT_MAX),
            ],
            ProjectSettings::TYPE_BOOLEAN => ['required', 'boolean'],
            ProjectSettings::TYPE_EMAIL => ['nullable', 'email:rfc', 'max:255'],
            ProjectSettings::TYPE_URL => ['nullable', 'url', 'max:2048'],
            ProjectSettings::TYPE_SELECT => ['nullable', 'string', Rule::in((array) ($definition['options'] ?? []))],
            ProjectSettings::TYPE_STRING_LIST => ['nullable', 'string', 'max:4000'],
            ProjectSettings::TYPE_TEXT => ['nullable', 'string', 'max:20000'],
            default => ['nullable', 'string', 'max:2048'],
        };
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function mailGraphCallbackUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost().route('admin.project-settings.mail-graph.callback', absolute: false);
    }
}
