<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\ClientStatus;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AdvisorDashboardClientScope
{
    /** @return array<int, string> */
    public function visibleClientIds(User $user): array
    {
        $query = $this->visibleClientQuery(null);

        if ($user->user_type !== User::TYPE_SUPER_ADMIN) {
            $clientIds = $user->accessibleClientIds();

            if ($clientIds === []) {
                return [];
            }

            $query->whereIn('id', $clientIds);
        }

        /** @var array<int, string> $clientIds */
        $clientIds = $query->pluck('id')->all();

        return $clientIds;
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Builder<Client>
     */
    public function visibleClientQuery(?array $clientIds): Builder
    {
        $query = Client::query()
            ->withoutOperationalHealthFixtures()
            ->whereNotIn('status', [
                ClientStatus::SUSPENDED->value,
                ClientStatus::OFFBOARDED->value,
            ]);

        if (is_array($clientIds)) {
            if ($clientIds === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('id', $clientIds);
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function visibleEntrepreneurQuery(Builder $query, User $user): Builder
    {
        $query
            ->whereNotIn('stage', [
                EntrepreneurStage::CANCELLED->value,
                EntrepreneurStage::SUSPENDED->value,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('client_id')
                    ->orWhereHas('client', fn (Builder $clientQuery): Builder => $clientQuery->whereNotIn('status', [
                        ClientStatus::SUSPENDED->value,
                        ClientStatus::OFFBOARDED->value,
                    ]));
            });

        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return $query;
        }

        return $query->where('assigned_advisor_id', $user->getKey());
    }
}
