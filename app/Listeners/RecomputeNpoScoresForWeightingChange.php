<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\NpoEngagementWeightingChanged;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\NpoHealthScorer;

final class RecomputeNpoScoresForWeightingChange
{
    public function __construct(private readonly NpoHealthScorer $scorer) {}

    public function handle(NpoEngagementWeightingChanged $event): void
    {
        $engagement = NpoEngagement::query()->find($event->npoEngagementId);

        if (! $engagement instanceof NpoEngagement) {
            return;
        }

        $actor = $event->actorUserId === null
            ? null
            : User::query()->find($event->actorUserId);

        $this->scorer->recomputeHistoricalForWeightingChange($engagement, $actor instanceof User ? $actor : null);
    }
}
