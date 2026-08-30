<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ServiceActivation;
use Illuminate\Support\Collection;

final class ServiceActivationNavigation
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Client $client): array
    {
        $activations = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(10)
            ->get();

        $open = $activations
            ->filter(fn (ServiceActivation $activation): bool => $activation->isOpen())
            ->values();
        $hasDueDiligenceContext = $this->hasDueDiligenceContext($client, $open);

        return [
            'request_url' => route('portal.service-activations.store', absolute: false),
            'options' => [
                $this->optionPayload(
                    ServiceActivation::SERVICE_DUE_DILIGENCE,
                    'Explore buying a business',
                    'Open a DD workspace when you are considering a purchase or investment.',
                    ! $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE),
                    'self_start',
                ),
                ...($hasDueDiligenceContext ? [
                    $this->ddPlanBudgetOption(
                        ! $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET),
                    ),
                ] : []),
                $this->optionPayload(
                    ServiceActivation::SERVICE_ENTREPRENEUR,
                    'Test new Business Idea',
                    'Open idea validation, business-plan, and budget support inside this portal.',
                    ! $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_ENTREPRENEUR),
                    'self_start',
                ),
                $this->advisorLedOption(
                    'standard_advisory',
                    'Standard Advisory',
                    'Whole-business diagnostic, prioritised advisory roadmap, valuation context, and client-ready report.',
                    'Advisor-led engagement',
                    'Ask your FSA advisor to confirm or open this engagement path.',
                ),
                $this->advisorLedOption(
                    'post_acquisition_advisory',
                    'Post-acquisition Advisory',
                    'First-100-days gap review, integration priorities, and follow-on advisory after a completed purchase.',
                    'Advisor-led handoff',
                    'This starts after DD or an advisor handoff confirms the post-close scope.',
                ),
                $this->advisorLedOption(
                    'npo',
                    'NPO Advisory',
                    'Not-for-profit health, governance, impact, and funder-accountability support.',
                    'Advisor-led engagement',
                    'Ask your FSA advisor to confirm the NPO scope and board access needed.',
                ),
                $this->advisorLedOption(
                    ServiceActivation::SERVICE_INTEGRATION_SCOPING,
                    'Systems integration scoping',
                    'Advisor-offered scoping for time-saving systems, data, and workflow integration opportunities.',
                    'Advisor offered',
                    'FSA offers this after reviewing your current systems and potential efficiency gains.',
                ),
                $this->advisorLedOption(
                    ServiceActivation::SERVICE_INTEGRATION,
                    'Systems integration delivery',
                    'Implementation delivery after an approved integration scope, proposal, and payment path.',
                    'Follows approved scope',
                    'This opens after the integration scope and proposal have been approved.',
                ),
            ],
            'items' => $activations
                ->map(fn (ServiceActivation $activation): array => $this->activationPayload($activation))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activationPayload(ServiceActivation $activation): array
    {
        return [
            'id' => $activation->id,
            'service_type' => $activation->service_type,
            'client_label' => $activation->clientLabel(),
            'status' => $activation->status,
            'status_label' => str($activation->status)->replace('_', ' ')->title()->toString(),
            'package_label' => data_get($activation->selected_package_snapshot, 'client_label'),
            'fixed_fee' => data_get($activation->selected_package_snapshot, 'fixed_fee'),
            'currency' => data_get($activation->selected_package_snapshot, 'currency', 'NZD'),
            'payment_status' => $activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED,
            'payment_status_label' => str((string) ($activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED))->replace('_', ' ')->title()->toString(),
            'accepted_at' => $activation->accepted_at?->toIso8601String(),
            'created_at' => $activation->created_at?->toIso8601String(),
            'url' => route('portal.service-activations.show', $activation, absolute: false),
            'workspace_url' => $this->workspaceUrl($activation),
        ];
    }

    /**
     * @param  Collection<int, ServiceActivation>  $open
     */
    private function hasDueDiligenceContext(Client $client, Collection $open): bool
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType === EngagementType::DUE_DILIGENCE
            || $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE);
    }

    /**
     * @return array<string, mixed>
     */
    private function ddPlanBudgetOption(bool $available): array
    {
        return [
            'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            'label' => 'DD + Business Plan & Budget',
            'description' => 'Request FSA quote approval for the acquisition business plan and funding budget add-on.',
            'available' => $available,
            'delivery_mode' => 'quote_approval',
            'availability_label' => $available ? 'FSA quote required' : 'Already requested',
            'unavailable_reason' => 'You already have an open Business Plan & Budget quote or add-on for this DD workspace.',
            'start_url' => $available ? route('portal.business-plan-budget.show', absolute: false) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionPayload(
        string $serviceType,
        string $label,
        string $description,
        bool $available,
        string $deliveryMode,
    ): array {
        return [
            'service_type' => $serviceType,
            'label' => $label,
            'description' => $description,
            'available' => $available,
            'delivery_mode' => $deliveryMode,
            'availability_label' => $available ? 'Start available' : 'Already open',
            'unavailable_reason' => $available
                ? null
                : 'You already have an open workspace for this service. Use the active workspace instead of starting another request.',
            'start_url' => $available
                ? route('portal.service-activations.create', ['serviceType' => $serviceType], absolute: false)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function advisorLedOption(
        string $serviceType,
        string $label,
        string $description,
        string $availabilityLabel,
        string $unavailableReason,
    ): array {
        return [
            'service_type' => $serviceType,
            'label' => $label,
            'description' => $description,
            'available' => false,
            'delivery_mode' => 'advisor_led',
            'availability_label' => $availabilityLabel,
            'unavailable_reason' => $unavailableReason,
            'start_url' => null,
        ];
    }

    private function workspaceUrl(ServiceActivation $activation): ?string
    {
        if ($activation->status !== ServiceActivation::STATUS_ACTIVE) {
            return null;
        }

        return match ($activation->service_type) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => route('portal.dd-plan.show', absolute: false),
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => route('portal.business-plan-budget.show', absolute: false),
            ServiceActivation::SERVICE_ENTREPRENEUR => route('portal.entrepreneur.dashboard', absolute: false),
            default => null,
        };
    }
}
