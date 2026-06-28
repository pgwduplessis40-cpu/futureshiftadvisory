<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Models\Client;
use App\Models\ServiceActivation;

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

        return [
            'request_url' => route('portal.service-activations.store', absolute: false),
            'options' => [
                $this->optionPayload(
                    ServiceActivation::SERVICE_DUE_DILIGENCE,
                    'Explore buying a business',
                    'Open a DD workspace when you are considering a purchase or investment.',
                    ! $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE),
                ),
                $this->optionPayload(
                    ServiceActivation::SERVICE_ENTREPRENEUR,
                    'Test a new idea',
                    'Open idea validation, business-plan, and budget support inside this portal.',
                    ! $open->contains(fn (ServiceActivation $activation): bool => $activation->service_type === ServiceActivation::SERVICE_ENTREPRENEUR),
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
            'created_at' => $activation->created_at?->toIso8601String(),
            'url' => route('portal.service-activations.show', $activation, absolute: false),
            'workspace_url' => $activation->status === ServiceActivation::STATUS_ACTIVE
                ? ($activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE
                    ? route('portal.dd-plan.show', absolute: false)
                    : route('portal.entrepreneur.plan.show', absolute: false))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionPayload(string $serviceType, string $label, string $description, bool $available): array
    {
        return [
            'service_type' => $serviceType,
            'label' => $label,
            'description' => $description,
            'available' => $available,
            'start_url' => route('portal.service-activations.create', ['serviceType' => $serviceType], absolute: false),
        ];
    }
}
