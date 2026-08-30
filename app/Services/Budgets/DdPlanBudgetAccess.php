<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ServiceActivation;

final class DdPlanBudgetAccess
{
    public const STATE_NOT_REQUIRED = 'not_required';

    public const STATE_NOT_REQUESTED = 'not_requested';

    public const STATE_QUOTE_REQUESTED = 'quote_requested';

    public const STATE_PAYMENT_DUE = 'payment_due';

    public const STATE_ACCEPTANCE_DUE = 'acceptance_due';

    public const STATE_ACTIVE_ADD_ON = 'active_add_on';

    /**
     * @return array<array-key, mixed>
     */
    public function payload(Client $client): array
    {
        if (! $this->isDueDiligenceClient($client)) {
            return $this->basePayload(
                allowed: true,
                state: self::STATE_NOT_REQUIRED,
                label: 'Included for this service',
                message: 'Business Plan & Budget access is part of this client workspace.',
            );
        }

        $entitlement = $this->activeEntitlement($client);
        if ($entitlement instanceof ServiceActivation) {
            return $this->payloadForActivation(
                $entitlement,
                state: self::STATE_ACTIVE_ADD_ON,
                allowed: true,
                label: 'Business Plan & Budget active',
                message: 'The Business Plan & Budget add-on has been approved and accepted for this DD client.',
            );
        }

        $request = $this->openRequest($client);
        if ($request instanceof ServiceActivation) {
            return $this->payloadForActivation(
                $request,
                state: $this->stateForOpenRequest($request),
                allowed: false,
                label: $this->labelForOpenRequest($request),
                message: $this->messageForOpenRequest($request),
            );
        }

        return $this->basePayload(
            allowed: false,
            state: self::STATE_NOT_REQUESTED,
            label: 'FSA quote required',
            message: 'This DD service is currently DD-only. Business Plan & Budget can be requested as an additional FSA-quoted add-on.',
        );
    }

    public function allowed(Client $client): bool
    {
        if (! $this->isDueDiligenceClient($client)) {
            return true;
        }

        return $this->activeEntitlement($client) instanceof ServiceActivation;
    }

    public function openRequest(Client $client): ?ServiceActivation
    {
        return ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
            ->whereNotIn('status', [
                ServiceActivation::STATUS_CANCELLED,
                ServiceActivation::STATUS_CLOSED,
                ServiceActivation::STATUS_REJECTED,
            ])
            ->latest()
            ->first();
    }

    private function activeEntitlement(Client $client): ?ServiceActivation
    {
        $addOn = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->latest()
            ->first();

        if ($addOn instanceof ServiceActivation) {
            return $addOn;
        }

        return ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->latest()
            ->get()
            ->first(fn (ServiceActivation $activation): bool => (bool) data_get(
                $activation->selected_package_snapshot,
                'access.includes_plan_budget',
                false,
            ));
    }

    private function isDueDiligenceClient(Client $client): bool
    {
        return $client->engagement_type === EngagementType::DUE_DILIGENCE;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function payloadForActivation(
        ServiceActivation $activation,
        string $state,
        bool $allowed,
        string $label,
        string $message,
    ): array {
        $snapshot = is_array($activation->selected_package_snapshot)
            ? $activation->selected_package_snapshot
            : data_get($activation->metadata, 'pre_request_pricing.package');
        $snapshot = is_array($snapshot) ? $snapshot : null;

        return [
            ...$this->basePayload($allowed, $state, $label, $message),
            'activation_id' => $activation->getKey(),
            'activation_status' => $activation->status,
            'activation_status_label' => str($activation->status)->replace('_', ' ')->title()->toString(),
            'activation_url' => route('portal.service-activations.show', $activation, absolute: false),
            'package_label' => data_get($snapshot, 'client_label'),
            'fixed_fee' => data_get($snapshot, 'fixed_fee'),
            'currency' => data_get($snapshot, 'currency', 'NZD'),
            'quote_context' => data_get($snapshot, 'quote_context'),
            'payment_status' => $activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED,
            'payment_status_label' => str((string) ($activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED))->replace('_', ' ')->title()->toString(),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function basePayload(bool $allowed, string $state, string $label, string $message): array
    {
        return [
            'allowed' => $allowed,
            'state' => $state,
            'label' => $label,
            'message' => $message,
            'request_url' => route('portal.business-plan-budget.quote.store', absolute: false),
            'activation_id' => null,
            'activation_status' => null,
            'activation_status_label' => null,
            'activation_url' => null,
            'package_label' => null,
            'fixed_fee' => null,
            'currency' => 'NZD',
            'quote_context' => null,
            'payment_status' => null,
            'payment_status_label' => null,
        ];
    }

    private function stateForOpenRequest(ServiceActivation $activation): string
    {
        if ($activation->status === ServiceActivation::STATUS_PACKAGE_SELECTED) {
            return $activation->paymentComplete()
                ? self::STATE_ACCEPTANCE_DUE
                : self::STATE_PAYMENT_DUE;
        }

        return self::STATE_QUOTE_REQUESTED;
    }

    private function labelForOpenRequest(ServiceActivation $activation): string
    {
        return match ($this->stateForOpenRequest($activation)) {
            self::STATE_PAYMENT_DUE => 'Quote approved - payment due',
            self::STATE_ACCEPTANCE_DUE => 'Quote approved - acceptance due',
            default => 'FSA quote requested',
        };
    }

    private function messageForOpenRequest(ServiceActivation $activation): string
    {
        return match ($this->stateForOpenRequest($activation)) {
            self::STATE_PAYMENT_DUE => 'FSA has selected the Business Plan & Budget add-on package. Complete the payment step before the module opens.',
            self::STATE_ACCEPTANCE_DUE => 'FSA has approved the quote and payment is complete. Accept the package scope to open Business Plan & Budget.',
            default => 'FSA is reviewing the Business Plan & Budget add-on request and will select the package, scope, and fee before anything is charged.',
        };
    }
}
