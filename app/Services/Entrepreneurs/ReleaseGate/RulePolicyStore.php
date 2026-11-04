<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Enums\Permission;
use App\Models\PlanRuleConfigVersion;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RulePolicyStore
{
    public function __construct(private AuditWriter $audit) {}

    public function current(): RuleConfiguration
    {
        $record = PlanRuleConfigVersion::query()->orderByDesc('created_at')->orderByDesc('id')->first();
        $data = $record?->getAttribute('configuration');
        if (! is_array($data)) {
            return new RuleConfiguration;
        }
        /** @var array<string,string> $thresholds */
        $thresholds = (array) ($data['thresholds'] ?? []);
        /** @var array<string,string> $severities */
        $severities = (array) ($data['severities'] ?? []);
        /** @var array<string,string> $messages */
        $messages = (array) ($data['messages'] ?? []);

        return new RuleConfiguration($thresholds, $severities, $messages);
    }

    public function save(User $actor, RuleConfiguration $configuration, string $expectedHash, string $reason): PlanRuleConfigVersion
    {
        abort_unless($actor->can(Permission::ENTREPRENEURS_RELEASE_POLICY->value), 403);
        if (mb_strlen(trim($reason)) < 15) {
            throw ValidationException::withMessages(['reason' => 'Explain the policy change in at least 15 characters.']);
        }

        return DB::transaction(function () use ($actor, $configuration, $expectedHash, $reason): PlanRuleConfigVersion {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(76432109)');
            }
            if (! hash_equals($this->current()->hash(), $expectedHash)) {
                throw ValidationException::withMessages(['policy' => 'The policy changed. Refresh before saving.']);
            }
            $record = PlanRuleConfigVersion::query()->create(['configuration_hash' => $configuration->hash(), 'configuration' => $configuration->jsonSerialize(), 'reason' => trim($reason), 'actor_user_id' => $actor->getKey()]);
            $this->audit->record('entrepreneur.release_policy_changed', subject: $record, actor: $actor, after: ['configuration_hash' => $configuration->hash(), 'reason' => trim($reason)]);

            return $record;
        });
    }
}
