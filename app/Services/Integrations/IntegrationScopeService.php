<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pv\PvEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class IntegrationScopeService
{
    public function __construct(
        private readonly IntegrationScopeCalculator $calculator,
        private readonly PvEngine $pv,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Client $client, array $attributes, ?User $actor = null): IntegrationScope
    {
        return DB::transaction(function () use ($client, $attributes, $actor): IntegrationScope {
            $scope = IntegrationScope::query()->create([
                'client_id' => $client->getKey(),
                'systems' => $this->rows($attributes['systems'] ?? []),
                'tasks' => $this->rows($attributes['tasks'] ?? []),
                'connections' => $this->rows($attributes['connections'] ?? []),
                'delivery_mode' => $attributes['delivery_mode'] ?? null,
                'partner_cost_estimate' => $attributes['partner_cost_estimate'] ?? null,
                'partner_margin_percent' => $attributes['partner_margin_percent'] ?? 25,
                'capture_percent' => $attributes['capture_percent'] ?? 80,
                'savings_horizon_years' => $attributes['savings_horizon_years'] ?? 3,
                'discount_rate_percent' => $attributes['discount_rate_percent'] ?? 12,
                'quoted_fee' => $attributes['quoted_fee'] ?? null,
                'fsa_hosting_enabled' => $attributes['fsa_hosting_enabled'] ?? false,
                'fee_override_reason' => $attributes['fee_override_reason'] ?? null,
                'source_document_ids' => $this->rows($attributes['source_document_ids'] ?? []),
                'extracted_rows' => $this->rows($attributes['extracted_rows'] ?? []),
                'created_by_user_id' => $actor?->getKey(),
            ]);

            return $this->recalculateLocked($scope, $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(IntegrationScope $scope, array $attributes, ?User $actor = null): IntegrationScope
    {
        return DB::transaction(function () use ($scope, $attributes, $actor): IntegrationScope {
            $scope = IntegrationScope::query()->lockForUpdate()->findOrFail($scope->getKey());
            $changes = [];
            foreach (['systems', 'tasks', 'connections', 'source_document_ids', 'extracted_rows'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $changes[$field] = $this->rows($attributes[$field]);
                }
            }
            foreach (['delivery_mode', 'partner_cost_estimate', 'partner_margin_percent', 'capture_percent', 'savings_horizon_years', 'discount_rate_percent', 'quoted_fee', 'fsa_hosting_enabled', 'fee_override_reason'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $changes[$field] = $attributes[$field];
                }
            }
            $scope->forceFill($changes)->save();

            return $this->recalculateLocked($scope, $actor);
        });
    }

    public function recalculate(IntegrationScope $scope, ?User $actor = null): IntegrationScope
    {
        return DB::transaction(function () use ($scope, $actor): IntegrationScope {
            $locked = IntegrationScope::query()->lockForUpdate()->findOrFail($scope->getKey());

            return $this->recalculateLocked($locked, $actor);
        });
    }

    private function recalculateLocked(IntegrationScope $scope, ?User $actor): IntegrationScope
    {
        $scope->loadMissing('client');
        $client = $scope->client;
        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Integration scopes must belong to a client.');
        }

        if (! $this->isComplete($scope)) {
            $hasRows = $scope->systems !== [] || $scope->tasks !== [] || $scope->connections !== [];
            $scope->forceFill([
                'computed' => [],
                'pv_calculation_id' => null,
                'flags' => [],
                'status' => $hasRows ? IntegrationScope::STATUS_PARTIAL : IntegrationScope::STATUS_NOT_STARTED,
            ])->save();

            $this->audit->record('integration_scope.saved_draft', subject: $scope, actor: $actor, after: [
                'status' => $scope->status,
            ]);

            return $scope->refresh();
        }

        $calculated = $this->calculator->calculate(
            $scope,
            IntegrationFeeBand::query()->where('is_active', true)->get()->all(),
        );
        $annualSavings = (float) $calculated['annual_savings'];
        $horizon = max(1, min(5, (int) $scope->savings_horizon_years));
        $pv = $this->pv->calculate(
            client: $client,
            type: PvType::ImprovementOpportunity,
            discountMethod: DiscountMethod::AdvisorConfigured,
            cashFlows: array_fill(1, $horizon, $annualSavings),
            discountOptions: [
                'rate' => max(0.0001, min(0.9999, (float) $scope->discount_rate_percent / 100)),
                'rationale' => 'Integration scope savings PV uses the advisor-confirmed scoping discount assumption.',
                'source_reference' => 'integration_scope:'.$scope->getKey(),
            ],
            sourceAttributions: collect($calculated['task_rows'])
                ->map(fn (array $task): array => [
                    'claim' => $task['description'].' annual saving estimate of '.$task['annual_cost_wasted'],
                    'source_reference' => 'integration_scope:'.$scope->getKey().':task:'.$task['id'],
                ])
                ->all(),
        );
        $computed = [
            ...$calculated,
            'pv_calculation_id' => $pv->getKey(),
            'pv_savings' => (float) data_get($pv->result, 'present_value', 0),
            'source_document_ids' => $this->rows($scope->source_document_ids),
        ];

        $scope->forceFill([
            'computed' => $computed,
            'pv_calculation_id' => $pv->getKey(),
            'flags' => $calculated['flags'],
            'status' => IntegrationScope::STATUS_COMPLETE,
        ])->save();

        $this->audit->record('integration_scope.recalculated', subject: $scope, actor: $actor, after: [
            'status' => $scope->status,
            'annual_savings' => $computed['annual_savings'],
            'complexity_band' => $computed['complexity_band'],
            'quoted_fee' => $computed['quoted_fee'],
            'pv_calculation_id' => $pv->getKey(),
        ]);

        return $scope->refresh();
    }

    private function isComplete(IntegrationScope $scope): bool
    {
        if (! is_array($scope->systems) || $scope->systems === [] || ! is_array($scope->tasks) || $scope->tasks === [] || ! is_array($scope->connections) || $scope->connections === [] || $scope->delivery_mode === null) {
            return false;
        }

        return collect($scope->tasks)->every(static fn (mixed $task): bool => is_array($task)
            && is_numeric($task['minutes_per_occurrence'] ?? null)
            && is_numeric($task['people_count'] ?? null)
            && is_numeric($task['hourly_cost'] ?? null)
            && in_array($task['occurrences_per'] ?? null, ['day', 'week', 'month'], true));
    }

    /** @return array<int, mixed> */
    private function rows(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
