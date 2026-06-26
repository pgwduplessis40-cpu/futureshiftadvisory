<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\ProjectSetting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class ProjectSettings
{
    public const TYPE_STRING = 'string';

    public const TYPE_SECRET = 'secret';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_EMAIL = 'email';

    public const TYPE_URL = 'url';

    public const TYPE_SELECT = 'select';

    public const TYPE_STRING_LIST = 'string_list';

    /**
     * @var array<string, array{title:string, description:string}>
     */
    private const GROUPS = [
        'email_delivery' => [
            'title' => 'Email delivery',
            'description' => 'Application email transport, sender identity, and provider keys.',
        ],
        'slack_notifications' => [
            'title' => 'Slack notifications',
            'description' => 'User-facing Slack notification delivery settings.',
        ],
        'logging_slack' => [
            'title' => 'Logging Slack webhook',
            'description' => 'Operational logging destination for Slack alerts.',
        ],
        'microsoft_graph' => [
            'title' => 'Microsoft Graph',
            'description' => 'Outlook calendar OAuth and Graph API connection settings.',
        ],
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private const DEFINITIONS = [
        [
            'key' => 'mail.default',
            'group' => 'email_delivery',
            'label' => 'Delivery provider',
            'type' => self::TYPE_SELECT,
            'config_path' => 'mail.default',
            'default' => 'log',
            'options' => ['log', 'graph', 'smtp', 'ses', 'postmark', 'resend', 'array'],
        ],
        [
            'key' => 'mail.mailers.smtp.host',
            'group' => 'email_delivery',
            'label' => 'SMTP host',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.mailers.smtp.host',
            'default' => '127.0.0.1',
        ],
        [
            'key' => 'mail.mailers.smtp.port',
            'group' => 'email_delivery',
            'label' => 'SMTP port',
            'type' => self::TYPE_INTEGER,
            'config_path' => 'mail.mailers.smtp.port',
            'default' => 2525,
            'min' => 1,
            'max' => 65535,
        ],
        [
            'key' => 'mail.mailers.smtp.scheme',
            'group' => 'email_delivery',
            'label' => 'SMTP scheme',
            'type' => self::TYPE_SELECT,
            'config_path' => 'mail.mailers.smtp.scheme',
            'default' => 'smtp',
            'options' => ['smtp', 'smtps', ''],
        ],
        [
            'key' => 'mail.mailers.smtp.username',
            'group' => 'email_delivery',
            'label' => 'SMTP username',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.mailers.smtp.username',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.smtp.password',
            'group' => 'email_delivery',
            'label' => 'SMTP password',
            'type' => self::TYPE_SECRET,
            'config_path' => 'mail.mailers.smtp.password',
            'default' => '',
        ],
        [
            'key' => 'mail.from.address',
            'group' => 'email_delivery',
            'label' => 'From address',
            'type' => self::TYPE_EMAIL,
            'config_path' => 'mail.from.address',
            'default' => 'hello@example.com',
        ],
        [
            'key' => 'mail.from.name',
            'group' => 'email_delivery',
            'label' => 'From name',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.from.name',
            'default' => 'Future Shift Advisory',
        ],
        [
            'key' => 'mail.owner_address',
            'group' => 'email_delivery',
            'label' => 'Owner notification email',
            'type' => self::TYPE_EMAIL,
            'config_path' => 'mail.owner_address',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.graph.tenant',
            'group' => 'email_delivery',
            'label' => 'Graph tenant',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.mailers.graph.tenant',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.graph.client_id',
            'group' => 'email_delivery',
            'label' => 'Graph client ID',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.mailers.graph.client_id',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.graph.client_secret',
            'group' => 'email_delivery',
            'label' => 'Graph client secret',
            'type' => self::TYPE_SECRET,
            'config_path' => 'mail.mailers.graph.client_secret',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.graph.from_address',
            'group' => 'email_delivery',
            'label' => 'Graph sender mailbox',
            'type' => self::TYPE_EMAIL,
            'config_path' => 'mail.mailers.graph.from_address',
            'default' => '',
        ],
        [
            'key' => 'mail.mailers.graph.base_url',
            'group' => 'email_delivery',
            'label' => 'Graph base URL',
            'type' => self::TYPE_URL,
            'config_path' => 'mail.mailers.graph.base_url',
            'default' => 'https://graph.microsoft.com/v1.0',
        ],
        [
            'key' => 'mail.mailers.graph.scope',
            'group' => 'email_delivery',
            'label' => 'Graph OAuth scope',
            'type' => self::TYPE_STRING,
            'config_path' => 'mail.mailers.graph.scope',
            'default' => 'https://graph.microsoft.com/.default',
        ],
        [
            'key' => 'mail.mailers.graph.timeout',
            'group' => 'email_delivery',
            'label' => 'Graph timeout seconds',
            'type' => self::TYPE_INTEGER,
            'config_path' => 'mail.mailers.graph.timeout',
            'default' => 15,
            'min' => 1,
            'max' => 120,
        ],
        [
            'key' => 'services.ses.key',
            'group' => 'email_delivery',
            'label' => 'SES key',
            'type' => self::TYPE_SECRET,
            'config_path' => 'services.ses.key',
            'default' => '',
        ],
        [
            'key' => 'services.ses.secret',
            'group' => 'email_delivery',
            'label' => 'SES secret',
            'type' => self::TYPE_SECRET,
            'config_path' => 'services.ses.secret',
            'default' => '',
        ],
        [
            'key' => 'services.ses.region',
            'group' => 'email_delivery',
            'label' => 'SES region',
            'type' => self::TYPE_STRING,
            'config_path' => 'services.ses.region',
            'default' => 'us-east-1',
        ],
        [
            'key' => 'services.postmark.key',
            'group' => 'email_delivery',
            'label' => 'Postmark key',
            'type' => self::TYPE_SECRET,
            'config_path' => 'services.postmark.key',
            'default' => '',
        ],
        [
            'key' => 'services.resend.key',
            'group' => 'email_delivery',
            'label' => 'Resend key',
            'type' => self::TYPE_SECRET,
            'config_path' => 'services.resend.key',
            'default' => '',
        ],
        [
            'key' => 'services.slack.notifications.bot_user_oauth_token',
            'group' => 'slack_notifications',
            'label' => 'Bot user OAuth token',
            'type' => self::TYPE_SECRET,
            'config_path' => 'services.slack.notifications.bot_user_oauth_token',
            'default' => '',
        ],
        [
            'key' => 'services.slack.notifications.channel',
            'group' => 'slack_notifications',
            'label' => 'Default channel',
            'type' => self::TYPE_STRING,
            'config_path' => 'services.slack.notifications.channel',
            'default' => '',
        ],
        [
            'key' => 'logging.channels.slack.url',
            'group' => 'logging_slack',
            'label' => 'Webhook URL',
            'type' => self::TYPE_SECRET,
            'config_path' => 'logging.channels.slack.url',
            'default' => '',
        ],
        [
            'key' => 'integrations.calendar.microsoft.live',
            'group' => 'microsoft_graph',
            'label' => 'Live Graph calendar sync',
            'type' => self::TYPE_BOOLEAN,
            'config_path' => 'integrations.calendar.microsoft.live',
            'default' => false,
        ],
        [
            'key' => 'integrations.calendar.microsoft.tenant',
            'group' => 'microsoft_graph',
            'label' => 'Tenant',
            'type' => self::TYPE_STRING,
            'config_path' => 'integrations.calendar.microsoft.tenant',
            'default' => 'common',
        ],
        [
            'key' => 'integrations.calendar.microsoft.client_id',
            'group' => 'microsoft_graph',
            'label' => 'Client ID',
            'type' => self::TYPE_STRING,
            'config_path' => 'integrations.calendar.microsoft.client_id',
            'default' => '',
        ],
        [
            'key' => 'integrations.calendar.microsoft.client_secret',
            'group' => 'microsoft_graph',
            'label' => 'Client secret',
            'type' => self::TYPE_SECRET,
            'config_path' => 'integrations.calendar.microsoft.client_secret',
            'default' => '',
        ],
        [
            'key' => 'integrations.calendar.microsoft.authorize_url',
            'group' => 'microsoft_graph',
            'label' => 'Authorize URL',
            'type' => self::TYPE_URL,
            'config_path' => 'integrations.calendar.microsoft.authorize_url',
            'default' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
        ],
        [
            'key' => 'integrations.calendar.microsoft.token_url',
            'group' => 'microsoft_graph',
            'label' => 'Token URL',
            'type' => self::TYPE_URL,
            'config_path' => 'integrations.calendar.microsoft.token_url',
            'default' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
        ],
        [
            'key' => 'integrations.calendar.microsoft.base_url',
            'group' => 'microsoft_graph',
            'label' => 'Graph base URL',
            'type' => self::TYPE_URL,
            'config_path' => 'integrations.calendar.microsoft.base_url',
            'default' => 'https://graph.microsoft.com/v1.0/me',
        ],
        [
            'key' => 'integrations.calendar.microsoft.scopes',
            'group' => 'microsoft_graph',
            'label' => 'OAuth scopes',
            'type' => self::TYPE_STRING_LIST,
            'config_path' => 'integrations.calendar.microsoft.scopes',
            'default' => "Calendars.ReadWrite\noffline_access",
        ],
    ];

    public function __construct(private readonly KeyEnvelope $envelope) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupsForUi(): array
    {
        $settings = $this->storedSettings();

        return collect(self::GROUPS)
            ->map(function (array $group, string $groupKey) use ($settings): array {
                $fields = collect(self::DEFINITIONS)
                    ->where('group', $groupKey)
                    ->map(fn (array $definition): array => $this->fieldForUi($definition, $settings->get((string) $definition['key'])))
                    ->values()
                    ->all();

                return [
                    'key' => $groupKey,
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'fields' => $fields,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitionsByKey(): array
    {
        return collect(self::DEFINITIONS)
            ->keyBy(fn (array $definition): string => (string) $definition['key'])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitionsForGroup(string $groupKey): array
    {
        return collect(self::DEFINITIONS)
            ->where('group', $groupKey)
            ->values()
            ->all();
    }

    public function applyRuntimeOverrides(): void
    {
        if (! $this->tableAvailable()) {
            $this->normaliseMailRuntimeConfig();

            return;
        }

        $definitions = $this->definitionsByKey();

        try {
            $this->withSystemContext(function () use ($definitions): void {
                ProjectSetting::query()
                    ->whereIn('setting_key', array_keys($definitions))
                    ->get()
                    ->each(function (ProjectSetting $setting) use ($definitions): void {
                        $definition = $definitions[$setting->setting_key] ?? null;
                        $configPath = is_array($definition) ? ($definition['config_path'] ?? null) : null;
                        if (! is_string($configPath) || $configPath === '') {
                            return;
                        }

                        $value = $this->storedValue($setting);
                        Config::set($configPath, $this->configValue($definition, $value));
                    });
            });
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->normaliseMailRuntimeConfig();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function set(array $definition, mixed $value, User $actor): ProjectSetting
    {
        $key = (string) $definition['key'];
        $isSecret = $this->isSecret($definition);
        $storageValue = $this->normaliseForStorage($definition, $value);
        $envelope = $isSecret ? $this->envelope->encrypt($storageValue) : null;

        /** @var ProjectSetting $setting */
        $setting = ProjectSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            [
                'group_key' => (string) $definition['group'],
                'value_type' => (string) $definition['type'],
                'is_secret' => $isSecret,
                'value' => $isSecret ? null : $storageValue,
                'value_envelope' => $envelope,
                'value_envelope_meta' => is_string($envelope) ? $this->envelope->inspect($envelope) : null,
                'last_four' => $isSecret ? Str::substr($storageValue, -4) : null,
                'set_by_user_id' => $actor->getKey(),
            ],
        );

        app(AuditWriter::class)->record(
            action: $isSecret ? 'project_setting.secret_set' : 'project_setting.set',
            subject: $setting,
            actor: $actor,
            after: [
                'setting_key' => $key,
                'group_key' => (string) $definition['group'],
                'value_type' => (string) $definition['type'],
                'is_secret' => $isSecret,
            ],
        );

        Config::set((string) $definition['config_path'], $this->configValue($definition, $storageValue));
        $this->normaliseMailRuntimeConfig();

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function revoke(array $definition, User $actor): void
    {
        $setting = ProjectSetting::query()
            ->where('setting_key', (string) $definition['key'])
            ->first();

        if (! $setting instanceof ProjectSetting) {
            return;
        }

        $setting->delete();

        app(AuditWriter::class)->record(
            action: 'project_setting.revoked',
            actor: $actor,
            after: [
                'setting_key' => (string) $definition['key'],
                'group_key' => (string) $definition['group'],
                'is_secret' => $this->isSecret($definition),
            ],
        );
    }

    private function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('project_settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<string, ProjectSetting>
     */
    private function storedSettings(): Collection
    {
        if (! $this->tableAvailable()) {
            return collect();
        }

        return ProjectSetting::query()
            ->get()
            ->keyBy('setting_key');
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function fieldForUi(array $definition, ?ProjectSetting $setting): array
    {
        $isSecret = $this->isSecret($definition);
        $configPath = (string) ($definition['config_path'] ?? '');
        $configValue = $configPath === '' ? ($definition['default'] ?? '') : Config::get($configPath, $definition['default'] ?? '');
        $source = $setting instanceof ProjectSetting ? 'project' : 'config';

        return [
            'key' => (string) $definition['key'],
            'group' => (string) $definition['group'],
            'label' => (string) $definition['label'],
            'type' => (string) $definition['type'],
            'config_path' => $configPath,
            'is_secret' => $isSecret,
            'value' => $isSecret ? '' : $this->uiValue($definition, $setting instanceof ProjectSetting ? $this->storedValue($setting) : $configValue),
            'configured' => $setting instanceof ProjectSetting || $this->filledValue($configValue),
            'source' => $source,
            'last_four' => $setting?->last_four,
            'options' => $definition['options'] ?? [],
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function isSecret(array $definition): bool
    {
        return (string) ($definition['type'] ?? '') === self::TYPE_SECRET;
    }

    private function storedValue(ProjectSetting $setting): string
    {
        if ($setting->is_secret) {
            return is_string($setting->value_envelope)
                ? $this->envelope->decrypt($setting->value_envelope)
                : '';
        }

        return (string) $setting->value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normaliseForStorage(array $definition, mixed $value): string
    {
        $value = $this->normaliseSpecialValue($definition, $value);

        return match ((string) $definition['type']) {
            self::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::TYPE_INTEGER => (string) (int) $value,
            self::TYPE_STRING_LIST => collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->implode("\n"),
            default => trim((string) $value),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function configValue(array $definition, mixed $value): mixed
    {
        $value = $this->normaliseForStorage($definition, $value);

        return match ((string) $definition['type']) {
            self::TYPE_BOOLEAN => $value === '1',
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_STRING_LIST => collect(explode("\n", $value))
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->values()
                ->all(),
            self::TYPE_SELECT => $value === '' ? null : $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function uiValue(array $definition, mixed $value): string
    {
        $value = $this->normaliseSpecialValue($definition, $value);

        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item): string => (string) $item)->implode("\n");
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function filledValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normaliseSpecialValue(array $definition, mixed $value): mixed
    {
        if (($definition['key'] ?? null) !== 'mail.mailers.smtp.scheme') {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            'tls' => 'smtp',
            'ssl' => 'smtps',
            default => $value,
        };
    }

    private function normaliseMailRuntimeConfig(): void
    {
        if (Str::lower(trim((string) Config::get('mail.default', 'log'))) !== 'graph') {
            return;
        }

        $graphFrom = trim((string) Config::get('mail.mailers.graph.from_address', ''));
        if (filter_var($graphFrom, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $mailFrom = trim((string) Config::get('mail.from.address', ''));
        if (
            $mailFrom === ''
            || Str::lower($mailFrom) === 'hello@example.com'
            || filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false
        ) {
            Config::set('mail.from.address', $graphFrom);
        }
    }

    private function withSystemContext(callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $callback();
        }

        $previous = DB::selectOne(<<<'SQL'
            SELECT
                current_setting('fsa.role', true) AS role,
                current_setting('fsa.client_ids', true) AS client_ids,
                current_setting('fsa.user_id', true) AS user_id,
                current_setting('fsa.report_id', true) AS report_id,
                current_setting('fsa.npo_engagement_id', true) AS npo_engagement_id
        SQL);

        DB::statement(<<<'SQL'
            SELECT
                set_config('fsa.role', ?, false),
                set_config('fsa.client_ids', ?, false),
                set_config('fsa.user_id', ?, false),
                set_config('fsa.report_id', ?, false),
                set_config('fsa.npo_engagement_id', ?, false)
        SQL, ['system', '', '', '', '']);

        try {
            return $callback();
        } finally {
            DB::statement(<<<'SQL'
                SELECT
                    set_config('fsa.role', ?, false),
                    set_config('fsa.client_ids', ?, false),
                    set_config('fsa.user_id', ?, false),
                    set_config('fsa.report_id', ?, false),
                    set_config('fsa.npo_engagement_id', ?, false)
            SQL, [
                (string) ($previous->role ?? ''),
                (string) ($previous->client_ids ?? ''),
                (string) ($previous->user_id ?? ''),
                (string) ($previous->report_id ?? ''),
                (string) ($previous->npo_engagement_id ?? ''),
            ]);
        }
    }
}
