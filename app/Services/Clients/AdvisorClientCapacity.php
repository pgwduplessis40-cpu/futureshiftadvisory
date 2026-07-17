<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\OffboardingRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvisorClientCapacity
{
    /**
     * @return array{active_count: int, limit: int, warning_threshold: int, remaining: int, warning: bool, blocked: bool}
     */
    public function summary(User $advisor): array
    {
        $limit = $this->limitFor($advisor);
        $warningThreshold = min($limit, max(1, (int) ceil($limit * $this->warningRatio())));
        $activeCount = (int) DB::table('client_team')
            ->join('clients', 'clients.id', '=', 'client_team.client_id')
            ->where('client_team.user_id', $advisor->getKey())
            ->where('client_team.role', 'lead_advisor')
            ->where('clients.status', '!=', ClientStatus::OFFBOARDED->value)
            ->where('clients.engagement_type', '!=', EngagementType::ENTREPRENEUR_MODULE->value)
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

    public function ensureCanAdd(User $advisor): void
    {
        $summary = $this->summary($advisor);

        if (! $summary['blocked']) {
            return;
        }

        throw ValidationException::withMessages([
            'capacity' => "This advisor has reached their client capacity of {$summary['limit']} active clients.",
        ]);
    }

    private function limitFor(User $advisor): int
    {
        $override = $advisor->advisor_client_capacity_limit;

        if (is_int($override) && $override > 0) {
            return $override;
        }

        return max(1, (int) config('clients.capacity.limit', 30));
    }

    private function warningRatio(): float
    {
        $ratio = (float) config('clients.capacity.warning_ratio', 0.8);

        return min(1.0, max(0.1, $ratio));
    }
}
