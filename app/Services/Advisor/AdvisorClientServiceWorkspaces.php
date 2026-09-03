<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\ServiceActivation;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;

/**
 * Shared advisor navigation for a client's active services. This deliberately
 * derives from the same client and activation records as service access rather
 * than from the first engagement type assigned to the client.
 */
final class AdvisorClientServiceWorkspaces
{
    public const KEY_ENTREPRENEUR = 'entrepreneur';

    public const KEY_DUE_DILIGENCE = 'due_diligence';

    public const KEY_DD_PLAN_BUDGET = 'dd_plan_budget';

    public function __construct(
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurs,
    ) {}

    /**
     * @return array{active_key:string,items:list<array{key:string,label:string,href:string,active:bool}>}
     */
    public function payload(Client $client, string $activeKey): array
    {
        $activeServiceTypes = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->pluck('service_type')
            ->all();
        $hasDdEngagement = DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->exists();
        $hasDueDiligenceWorkspace = $hasDdEngagement
            || in_array(ServiceActivation::SERVICE_DUE_DILIGENCE, $activeServiceTypes, true);
        $hasPlanBudgetWorkspace = in_array(ServiceActivation::SERVICE_DD_PLAN_BUDGET, $activeServiceTypes, true);
        $hasSecondaryWorkspace = $hasDueDiligenceWorkspace || $hasPlanBudgetWorkspace;

        $items = [];
        $profile = $this->entrepreneurs->forClient($client);
        if ($profile !== null) {
            $items[] = $this->item(
                self::KEY_ENTREPRENEUR,
                'Entrepreneur',
                $hasSecondaryWorkspace
                    ? route('advisor.clients.show', $client, absolute: false)
                    : route('advisor.entrepreneurs.show', $profile, absolute: false),
                $activeKey,
            );
        }

        if ($hasDueDiligenceWorkspace) {
            $items[] = $this->item(
                self::KEY_DUE_DILIGENCE,
                'Due Diligence',
                route('advisor.clients.show', $client, absolute: false).'#section-due-diligence',
                $activeKey,
            );
        }

        if ($hasPlanBudgetWorkspace) {
            $items[] = $this->item(
                self::KEY_DD_PLAN_BUDGET,
                'Business Plan & Budget',
                route('advisor.clients.show', $client, absolute: false).'#section-strategic-budget',
                $activeKey,
            );
        }

        return [
            'active_key' => $activeKey,
            'items' => $items,
        ];
    }

    public function hasActiveSecondaryWorkspace(Client $client): bool
    {
        return collect($this->payload($client, self::KEY_ENTREPRENEUR)['items'])
            ->contains(fn (array $item): bool => $item['key'] !== self::KEY_ENTREPRENEUR);
    }

    public function clientDetailUrlForMultiServiceProfile(EntrepreneurProfile $profile): ?string
    {
        $client = $this->clientForProfile($profile);

        return $client !== null && $this->hasActiveSecondaryWorkspace($client)
            ? route('advisor.clients.show', $client, absolute: false)
            : null;
    }

    public function clientForProfile(EntrepreneurProfile $profile): ?Client
    {
        if (is_string($profile->client_id) && $profile->client_id !== '') {
            return Client::query()->find($profile->client_id);
        }

        $clientId = ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('related_entrepreneur_profile_id', $profile->getKey())
            ->value('client_id');

        return is_string($clientId) && $clientId !== ''
            ? Client::query()->find($clientId)
            : null;
    }

    /**
     * @return array{key:string,label:string,href:string,active:bool}
     */
    private function item(string $key, string $label, string $href, string $activeKey): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'active' => $key === $activeKey,
        ];
    }
}
