<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ServiceActivation;
use App\Services\Budgets\DdPlanBudgetAccess;

final class ServiceWorkspaces
{
    public const KEY_ADVISORY = 'standard_advisory';

    public const KEY_DUE_DILIGENCE = 'due_diligence';

    public const KEY_DD_PLAN_BUDGET = 'dd_plan_budget';

    public const KEY_ENTREPRENEUR = 'entrepreneur';

    public const KEY_NPO = 'npo';

    public const KEY_POST_ACQUISITION = 'post_acquisition_advisory';

    public function __construct(
        private readonly DdPlanBudgetAccess $planBudgetAccess,
    ) {}

    /**
     * @return array{active_key:string, items:array<int, array<string, mixed>>}
     */
    public function payload(Client $client, string|EngagementType|null $activeKey = null): array
    {
        $primaryKey = $this->workspaceKeyForEngagement($client->engagement_type);
        $activeKey = $this->normaliseWorkspaceKey($activeKey) ?? $primaryKey;
        $items = [
            $this->workspacePayload($client, $primaryKey, primary: true),
        ];
        $seen = [$primaryKey => true];

        ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->latest()
            ->get()
            ->each(function (ServiceActivation $activation) use ($client, &$items, &$seen): void {
                $key = $this->workspaceKeyForActivation($activation);

                if ($key === null || isset($seen[$key])) {
                    return;
                }

                $items[] = $this->workspacePayload(
                    client: $client,
                    key: $key,
                    primary: false,
                    statusLabel: str($activation->status)->replace('_', ' ')->title()->toString(),
                    href: $this->activationWorkspaceUrl($client, $activation),
                );
                $seen[$key] = true;
            });

        if ($this->shouldIncludePlanBudgetWorkspace($client, $primaryKey, $seen)) {
            $items[] = $this->workspacePayload(
                client: $client,
                key: self::KEY_DD_PLAN_BUDGET,
                primary: false,
                statusLabel: 'Included',
            );
            $seen[self::KEY_DD_PLAN_BUDGET] = true;
        }

        return [
            'active_key' => $activeKey,
            'items' => array_values($items),
        ];
    }

    private function workspaceKeyForEngagement(mixed $engagementType): string
    {
        $engagementType = $engagementType instanceof EngagementType
            ? $engagementType
            : EngagementType::tryFrom((string) $engagementType);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE => self::KEY_DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => self::KEY_POST_ACQUISITION,
            EngagementType::ENTREPRENEUR_MODULE => self::KEY_ENTREPRENEUR,
            EngagementType::NPO => self::KEY_NPO,
            default => self::KEY_ADVISORY,
        };
    }

    private function workspaceKeyForActivation(ServiceActivation $activation): ?string
    {
        return match ($activation->service_type) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => self::KEY_DUE_DILIGENCE,
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => self::KEY_DD_PLAN_BUDGET,
            ServiceActivation::SERVICE_ENTREPRENEUR => self::KEY_ENTREPRENEUR,
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            ServiceActivation::SERVICE_INTEGRATION => ServiceActivation::SERVICE_INTEGRATION,
            default => null,
        };
    }

    /**
     * @param  array<string, bool>  $seen
     */
    private function shouldIncludePlanBudgetWorkspace(Client $client, string $primaryKey, array $seen): bool
    {
        if (isset($seen[self::KEY_DD_PLAN_BUDGET])) {
            return false;
        }

        if (! in_array($primaryKey, [
            self::KEY_ADVISORY,
            self::KEY_DUE_DILIGENCE,
            self::KEY_POST_ACQUISITION,
        ], true)) {
            return false;
        }

        return $this->planBudgetAccess->allowed($client);
    }

    private function normaliseWorkspaceKey(string|EngagementType|null $key): ?string
    {
        if ($key instanceof EngagementType) {
            return $this->workspaceKeyForEngagement($key);
        }

        return match ($key) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => self::KEY_DUE_DILIGENCE,
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => self::KEY_DD_PLAN_BUDGET,
            EngagementType::ENTREPRENEUR_MODULE->value, ServiceActivation::SERVICE_ENTREPRENEUR => self::KEY_ENTREPRENEUR,
            EngagementType::NPO->value => self::KEY_NPO,
            EngagementType::POST_ACQUISITION_ADVISORY->value => self::KEY_POST_ACQUISITION,
            EngagementType::STANDARD_ADVISORY->value => self::KEY_ADVISORY,
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            ServiceActivation::SERVICE_INTEGRATION => ServiceActivation::SERVICE_INTEGRATION,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function workspacePayload(
        Client $client,
        string $key,
        bool $primary,
        ?string $statusLabel = null,
        ?string $href = null,
    ): array {
        return [
            'key' => $key,
            'service_type' => $key,
            'label' => $this->workspaceLabel($key),
            'description' => $this->workspaceDescription($key),
            'href' => $href ?? $this->workspaceUrl($client, $key),
            'primary' => $primary,
            'status_label' => $statusLabel ?? ($primary ? 'Original service' : 'Active'),
            'badge_count' => null,
        ];
    }

    private function workspaceUrl(Client $client, string $key): string
    {
        return match ($key) {
            self::KEY_DUE_DILIGENCE => $this->routeWithClient('portal.dd-plan.show', $client),
            self::KEY_DD_PLAN_BUDGET => $this->routeWithClient('portal.business-plan-budget.show', $client),
            self::KEY_ENTREPRENEUR => $this->routeWithClient('portal.entrepreneur.dashboard', $client),
            self::KEY_NPO => $this->routeWithClient('portal.npo-board.dashboard', $client),
            default => $this->routeWithClient('portal.dashboard', $client),
        };
    }

    private function activationWorkspaceUrl(Client $client, ServiceActivation $activation): string
    {
        return match ($activation->service_type) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => $this->routeWithClient('portal.dd-plan.show', $client),
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => $this->routeWithClient('portal.business-plan-budget.show', $client),
            ServiceActivation::SERVICE_ENTREPRENEUR => $this->routeWithClient('portal.entrepreneur.dashboard', $client),
            default => route('portal.service-activations.show', [
                'serviceActivation' => $activation,
                'client' => $client->getKey(),
            ], absolute: false),
        };
    }

    private function routeWithClient(string $name, Client $client): string
    {
        return route($name, ['client' => $client->getKey()], absolute: false);
    }

    private function workspaceLabel(string $key): string
    {
        return match ($key) {
            self::KEY_DUE_DILIGENCE => 'Due Diligence',
            self::KEY_DD_PLAN_BUDGET => 'Business Plan & Budget',
            self::KEY_ENTREPRENEUR => 'Entrepreneur',
            self::KEY_NPO => 'NPO',
            self::KEY_POST_ACQUISITION => 'Post-acquisition',
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => 'Integration scoping',
            ServiceActivation::SERVICE_INTEGRATION => 'Integration delivery',
            default => 'Advisory',
        };
    }

    private function workspaceDescription(string $key): string
    {
        return match ($key) {
            self::KEY_DUE_DILIGENCE => 'Buying-a-business questions, evidence, DD review, and acquisition next steps.',
            self::KEY_DD_PLAN_BUDGET => 'Business plan, funding budget, PDF outputs, and advisor assessment for the DD client.',
            self::KEY_ENTREPRENEUR => 'Idea validation, entrepreneur plan, budget, evidence, and advisor messages.',
            self::KEY_NPO => 'NPO governance, impact, funding, board evidence, and reporting.',
            self::KEY_POST_ACQUISITION => 'Post-close actions, migration context, and advisory handoff.',
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => 'Integration scoping, evidence, fee confirmation, and advisor messages.',
            ServiceActivation::SERVICE_INTEGRATION => 'Integration delivery, progress, and advisor messages.',
            default => 'Original advisory dashboard, onboarding, evidence, reports, and messages.',
        };
    }
}
