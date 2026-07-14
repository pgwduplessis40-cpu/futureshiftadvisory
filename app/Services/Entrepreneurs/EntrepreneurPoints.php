<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurMilestoneAward;
use App\Models\EntrepreneurPointEvent;
use App\Models\EntrepreneurProfile;
use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

final class EntrepreneurPoints
{
    /**
     * Points are earned once from an immutable, evidence-backed milestone.
     * Material grade improvements remain repeatable because their awards are.
     *
     * @var array<string, int>
     */
    private const VALUES = [
        EntrepreneurMilestones::IDEA_VALIDATED => 100,
        'phase_1' => 80,
        'phase_2' => 80,
        'phase_3' => 80,
        'phase_4' => 80,
        'phase_5' => 80,
        EntrepreneurMilestones::PLAN_SUBMITTED => 150,
        EntrepreneurMilestones::FIRST_ASSESSMENT => 125,
        EntrepreneurMilestones::GRADE_UP => 75,
        EntrepreneurMilestones::ADVISORY_READY => 200,
    ];

    public function __construct(private readonly RequestContext $context) {}

    public function record(EntrepreneurMilestoneAward $award): EntrepreneurPointEvent
    {
        return $this->context->withSystemContext(function () use ($award): EntrepreneurPointEvent {
            $existing = EntrepreneurPointEvent::query()
                ->where('milestone_award_id', $award->getKey())
                ->first();

            if ($existing instanceof EntrepreneurPointEvent) {
                return $existing;
            }

            $key = (string) $award->milestone_key;

            try {
                return EntrepreneurPointEvent::query()->create([
                    'entrepreneur_profile_id' => $award->entrepreneur_profile_id,
                    'milestone_award_id' => $award->getKey(),
                    'milestone_key' => $key,
                    'points' => self::valueFor($key),
                    'earned_at' => $award->earned_at,
                ]);
            } catch (QueryException) {
                return EntrepreneurPointEvent::query()
                    ->where('milestone_award_id', $award->getKey())
                    ->firstOrFail();
            }
        });
    }

    /**
     * @param  Collection<int, EntrepreneurMilestoneAward>  $awards
     */
    public function reconcile(Collection $awards): void
    {
        $awards->each(fn (EntrepreneurMilestoneAward $award) => $this->record($award));
    }

    /**
     * @return array{total:int, milestone_count:int}
     */
    public function summary(EntrepreneurProfile $profile): array
    {
        return [
            'total' => (int) EntrepreneurPointEvent::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->sum('points'),
            'milestone_count' => (int) EntrepreneurPointEvent::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->count(),
        ];
    }

    /**
     * @param  array{key:string,label:string,progress_percent:int}|null  $milestone
     * @return array{key:string,label:string,points:int,description:string}|null
     */
    public function nextQuest(?array $milestone): ?array
    {
        if ($milestone === null) {
            return null;
        }

        $key = $milestone['key'];

        return [
            'key' => $key,
            'label' => $milestone['label'],
            'points' => self::valueFor($key),
            'description' => $this->questDescription($key),
        ];
    }

    public static function valueFor(string $milestoneKey): int
    {
        return self::VALUES[$milestoneKey] ?? 0;
    }

    private function questDescription(string $milestoneKey): string
    {
        return match ($milestoneKey) {
            EntrepreneurMilestones::IDEA_VALIDATED => 'Complete the idea validation and have your advisor approve it.',
            'phase_1' => 'Complete the foundation of your plan.',
            'phase_2' => 'Capture your market evidence and customer path.',
            'phase_3' => 'Complete the strategy section of your plan.',
            'phase_4' => 'Complete the legal and operations section.',
            'phase_5' => 'Complete the financial plan and assumptions.',
            EntrepreneurMilestones::PLAN_SUBMITTED => 'Submit your completed plan for review.',
            EntrepreneurMilestones::FIRST_ASSESSMENT => 'Receive your first advisor assessment.',
            EntrepreneurMilestones::GRADE_UP => 'Use feedback to improve your plan assessment.',
            EntrepreneurMilestones::ADVISORY_READY => 'Reach the evidence threshold for advisory support.',
            default => 'Complete the next verified milestone in your business journey.',
        };
    }
}
