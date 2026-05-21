<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\OffboardingRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AdvisorClientCapacity
{
    /**
     * @return array{active_count: int, limit: int, warning_threshold: int, remaining: int, warning: bool, blocked: bool}
     */
    public function summary(User $advisor): array
    {
        $limit = max(1, (int) config('clients.capacity.limit', 30));
        $warningThreshold = min(
            $limit,
            max(0, (int) config('clients.capacity.warning_threshold', 24)),
        );
        $activeCount = (int) DB::table('client_team')
            ->join('clients', 'clients.id', '=', 'client_team.client_id')
            ->where('client_team.user_id', $advisor->getKey())
            ->where('client_team.role', 'lead_advisor')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('offboarding_records')
                    ->whereColumn('offboarding_records.client_id', 'clients.id')
                    ->where('offboarding_records.status', OffboardingRecord::STATUS_COMPLETED);
            })
            ->distinct('clients.id')
            ->count('clients.id');

        return [
            'active_count' => $activeCount,
            'limit' => $limit,
            'warning_threshold' => $warningThreshold,
            'remaining' => max(0, $limit - $activeCount),
            'warning' => $activeCount >= $warningThreshold && $activeCount < $limit,
            'blocked' => $activeCount >= $limit,
        ];
    }
}
