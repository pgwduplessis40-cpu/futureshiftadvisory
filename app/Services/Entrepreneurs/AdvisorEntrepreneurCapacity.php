<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class AdvisorEntrepreneurCapacity
{
    /**
     * @return array{active_count: int, limit: int, warning_threshold: int, remaining: int, warning: bool, blocked: bool}
     */
    public function summary(User $advisor): array
    {
        $limit = max(1, (int) config('entrepreneurs.capacity.limit', 30));
        $warningThreshold = min(
            $limit,
            max(0, (int) config('entrepreneurs.capacity.warning_threshold', 24)),
        );
        $activeCount = EntrepreneurProfile::query()
            ->withoutOperationalHealthFixtures()
            ->where('assigned_advisor_id', $advisor->getKey())
            ->whereIn('stage', EntrepreneurStage::activeCapacityValues())
            ->where(function (Builder $query): void {
                $query
                    ->where('stage', '!=', EntrepreneurStage::INVITED->value)
                    ->orWhereNull('invite_token_id')
                    ->orWhereNotNull('user_id')
                    ->orWhereHas('inviteToken', function (Builder $invite): void {
                        $invite
                            ->whereNotNull('accepted_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->count();

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
            'capacity' => "This advisor has reached the capacity of {$summary['limit']} active entrepreneurs.",
        ]);
    }
}
