<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\ServiceActivation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class CanonicalEntrepreneurWorkspace
{
    public function forClient(Client $client): ?EntrepreneurProfile
    {
        return $this->forClients(new EloquentCollection([$client]))->get((string) $client->getKey());
    }

    /**
     * @param  iterable<int, Client>  $clients
     * @return Collection<string, EntrepreneurProfile>
     */
    public function forClients(iterable $clients): Collection
    {
        $clients = $clients instanceof EloquentCollection
            ? $clients
            : new EloquentCollection(is_array($clients) ? $clients : iterator_to_array($clients));
        $clientIds = $clients
            ->map(fn (Client $client): string => (string) $client->getKey())
            ->values()
            ->all();

        if ($clientIds === []) {
            return collect();
        }

        /** @var Collection<string, array<int, string>> $profileIdsByClient */
        $profileIdsByClient = collect($clientIds)->mapWithKeys(
            fn (string $clientId): array => [$clientId => []],
        );

        EntrepreneurProfile::query()
            ->whereIn('client_id', $clientIds)
            ->get(['id', 'client_id'])
            ->each(function (EntrepreneurProfile $profile) use ($profileIdsByClient): void {
                $clientId = (string) $profile->client_id;
                $profileIdsByClient->put(
                    $clientId,
                    [...$profileIdsByClient->get($clientId, []), (string) $profile->getKey()],
                );
            });

        ServiceActivation::query()
            ->whereIn('client_id', $clientIds)
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereNotNull('related_entrepreneur_profile_id')
            ->get(['client_id', 'related_entrepreneur_profile_id'])
            ->each(function (ServiceActivation $activation) use ($profileIdsByClient): void {
                $clientId = (string) $activation->client_id;
                $profileId = (string) $activation->related_entrepreneur_profile_id;

                $profileIdsByClient->put(
                    $clientId,
                    [...$profileIdsByClient->get($clientId, []), $profileId],
                );
            });

        $profileIds = $profileIdsByClient
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($profileIds === []) {
            return collect();
        }

        $profiles = EntrepreneurProfile::query()
            ->whereIn('id', $profileIds)
            ->with([
                'businessPlans.sections',
                'businessPlans.budgetRunway',
                'ideaValidations',
            ])
            ->get()
            ->keyBy(fn (EntrepreneurProfile $profile): string => (string) $profile->getKey());

        return $profileIdsByClient
            ->map(function (array $candidateIds, string $clientId) use ($profiles): ?EntrepreneurProfile {
                $candidates = collect($candidateIds)
                    ->unique()
                    ->map(fn (string $profileId): ?EntrepreneurProfile => $profiles->get($profileId))
                    ->filter(fn (mixed $profile): bool => $profile instanceof EntrepreneurProfile)
                    ->values()
                    ->all();

                if ($candidates === []) {
                    return null;
                }

                usort(
                    $candidates,
                    fn (EntrepreneurProfile $left, EntrepreneurProfile $right): int => $this->workspaceRank($right, $clientId) <=> $this->workspaceRank($left, $clientId),
                );

                return $candidates[0];
            })
            ->filter(fn (mixed $profile): bool => $profile instanceof EntrepreneurProfile);
    }

    public function forProfile(EntrepreneurProfile $profile): EntrepreneurProfile
    {
        $clientId = $profile->client_id;

        if (! is_string($clientId) || $clientId === '') {
            $clientId = ServiceActivation::query()
                ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
                ->where('status', ServiceActivation::STATUS_ACTIVE)
                ->where('related_entrepreneur_profile_id', $profile->getKey())
                ->value('client_id');
        }

        if (! is_string($clientId) || $clientId === '') {
            return $profile;
        }

        $client = Client::query()->find($clientId);

        return $client instanceof Client
            ? $this->forClient($client) ?? $profile
            : $profile;
    }

    /**
     * @return array<int, int>
     */
    private function workspaceRank(EntrepreneurProfile $profile, string $clientId): array
    {
        $plans = $profile->businessPlans
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR);
        $plan = $plans
            ->sortByDesc(fn (BusinessPlan $plan): int => $this->latestPlanActivity($plan)?->getTimestamp() ?? 0)
            ->first();
        $planCompletion = $plan instanceof BusinessPlan
            ? PlanRequirements::completion($plan)['percent']
            : 0;
        $ideaActivity = $profile->ideaValidations
            ->map(fn ($validation): int => $this->timestamp($validation->updated_at))
            ->max() ?? 0;

        return [
            $plan instanceof BusinessPlan ? 1 : 0,
            $planCompletion,
            $plan instanceof BusinessPlan ? $this->timestamp($this->latestPlanActivity($plan)) : 0,
            $profile->ideaValidations->isNotEmpty() ? 1 : 0,
            $ideaActivity,
            $this->timestamp($profile->last_active_at),
            $this->timestamp($profile->updated_at),
            (string) $profile->client_id === $clientId ? 1 : 0,
        ];
    }

    private function latestPlanActivity(BusinessPlan $plan): ?CarbonInterface
    {
        return collect([
            $plan->updated_at,
            $plan->budgetRunway?->updated_at,
            ...$plan->sections->pluck('updated_at')->all(),
        ])
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->sortByDesc(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->first();
    }

    private function timestamp(mixed $value): int
    {
        return $value instanceof CarbonInterface ? $value->getTimestamp() : 0;
    }
}
