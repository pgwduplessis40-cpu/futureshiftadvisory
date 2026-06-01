<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationActivation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class IntegrationActivationResolver
{
    private ?bool $activationStoreAvailable = null;

    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly IntegrationCredentials $credentials,
        private readonly AuditWriter $audit,
    ) {}

    public function isLive(string $integrationKey): bool
    {
        $definition = $this->registry->integration($integrationKey);
        if ($definition === null || ($definition['wiring_status'] ?? 'wired') !== 'wired') {
            return false;
        }

        if (! $this->activated($integrationKey, $definition)) {
            return false;
        }

        return $this->credentialsReady($integrationKey);
    }

    public function credentialsReady(string $integrationKey): bool
    {
        $fields = $this->registry->credentialFields($integrationKey);

        foreach ($fields as $field) {
            if (! $this->credentials->present($integrationKey, $field)) {
                return false;
            }
        }

        return true;
    }

    public function readiness(string $integrationKey): bool
    {
        $definition = $this->registry->integration($integrationKey);
        if ($definition === null) {
            return false;
        }

        if (($definition['wiring_status'] ?? 'wired') !== 'wired') {
            return false;
        }

        if (($definition['managed_via'] ?? 'vault') === 'environment') {
            return $this->environmentReady($integrationKey);
        }

        return $this->credentialsReady($integrationKey);
    }

    public function activate(string $integrationKey, User $actor): IntegrationActivation
    {
        abort_unless($this->activationStoreAvailable(), 503, 'Integration activation store is not migrated.');

        $definition = $this->registry->integration($integrationKey);
        abort_if($definition === null, 404);
        abort_if(($definition['managed_via'] ?? 'vault') !== 'vault', 422);
        abort_if(($definition['wiring_status'] ?? 'wired') !== 'wired', 422);
        abort_unless($this->credentialsReady($integrationKey), 422);

        $activation = IntegrationActivation::query()->updateOrCreate(
            ['integration_key' => $integrationKey],
            [
                'active' => true,
                'activated_by_user_id' => $actor->getKey(),
                'activated_at' => now(),
                'deactivated_at' => null,
            ],
        );

        $this->audit->record('integration.activation.enabled', subject: $activation, actor: $actor, after: [
            'integration_key' => $integrationKey,
            'active' => true,
        ]);

        return $activation;
    }

    public function deactivate(string $integrationKey, User $actor): IntegrationActivation
    {
        abort_unless($this->activationStoreAvailable(), 503, 'Integration activation store is not migrated.');

        $activation = IntegrationActivation::query()->updateOrCreate(
            ['integration_key' => $integrationKey],
            [
                'active' => false,
                'activated_by_user_id' => $actor->getKey(),
                'deactivated_at' => now(),
            ],
        );

        $this->audit->record('integration.activation.disabled', subject: $activation, actor: $actor, after: [
            'integration_key' => $integrationKey,
            'active' => false,
        ]);

        return $activation;
    }

    /**
     * @return Collection<string, IntegrationActivation>
     */
    public function activations(): Collection
    {
        if (! $this->activationStoreAvailable()) {
            return collect();
        }

        return IntegrationActivation::query()
            ->get()
            ->keyBy('integration_key');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function activated(string $integrationKey, array $definition): bool
    {
        if ($this->activationStoreAvailable()) {
            $activation = IntegrationActivation::query()
                ->where('integration_key', $integrationKey)
                ->first();

            if ($activation instanceof IntegrationActivation) {
                return $activation->active;
            }
        }

        $liveConfigPath = $definition['live_config_path'] ?? null;
        if (is_string($liveConfigPath) && $liveConfigPath !== '') {
            return (bool) Config::get($liveConfigPath, false);
        }

        return $this->credentialsReady($integrationKey);
    }

    private function activationStoreAvailable(): bool
    {
        return $this->activationStoreAvailable ??= Schema::hasTable('integration_activations');
    }

    private function environmentReady(string $integrationKey): bool
    {
        return match ($integrationKey) {
            'mail_delivery' => $this->mailReady((string) Config::get('mail.default', 'log')),
            'logging_slack' => filled(Config::get('logging.channels.slack.url')),
            default => $this->environmentCredentialsReady($integrationKey),
        };
    }

    private function environmentCredentialsReady(string $integrationKey): bool
    {
        foreach ($this->registry->credentialFields($integrationKey) as $field) {
            $definition = $this->registry->credential($integrationKey, $field);
            $configPath = $definition['config_path'] ?? null;
            if (! is_string($configPath) || $configPath === '' || ! filled(Config::get($configPath))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $seen
     */
    private function mailReady(string $mailer, array $seen = []): bool
    {
        if ($mailer === '' || in_array($mailer, $seen, true)) {
            return false;
        }

        $transport = (string) Config::get("mail.mailers.{$mailer}.transport", $mailer);

        return match ($transport) {
            'smtp' => $this->smtpReady($mailer),
            'ses', 'ses-v2' => filled(Config::get('services.ses.key')) && filled(Config::get('services.ses.secret')),
            'postmark' => filled(Config::get('services.postmark.key')),
            'resend' => filled(Config::get('services.resend.key')),
            'sendmail' => filled(Config::get("mail.mailers.{$mailer}.path")),
            'failover', 'roundrobin' => $this->childMailerReady($mailer, [...$seen, $mailer]),
            default => false,
        };
    }

    private function smtpReady(string $mailer): bool
    {
        $host = (string) Config::get("mail.mailers.{$mailer}.host", '');

        return $host !== ''
            && ! in_array(Str::lower($host), ['127.0.0.1', 'localhost'], true)
            && filled(Config::get("mail.mailers.{$mailer}.username"))
            && filled(Config::get("mail.mailers.{$mailer}.password"));
    }

    /**
     * @param  array<int, string>  $seen
     */
    private function childMailerReady(string $mailer, array $seen): bool
    {
        $children = Config::get("mail.mailers.{$mailer}.mailers", []);
        if (! is_array($children) || $children === []) {
            return false;
        }

        foreach ($children as $child) {
            if (is_string($child) && $this->mailReady($child, $seen)) {
                return true;
            }
        }

        return false;
    }
}
