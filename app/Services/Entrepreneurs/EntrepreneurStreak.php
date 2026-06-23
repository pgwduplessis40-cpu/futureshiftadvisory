<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\EntrepreneurStreakEvent;
use App\Models\PlanSection;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class EntrepreneurStreak
{
    public function __construct(private readonly RequestContext $context) {}

    public function recordSectionSaved(PlanSection $section): void
    {
        $section->loadMissing('businessPlan.entrepreneurProfile');
        $plan = $section->businessPlan;
        $profile = $plan?->entrepreneurProfile;

        if (
            ! $plan instanceof BusinessPlan
            || ! $profile instanceof EntrepreneurProfile
            || ! $profile->gamification_on
            || $section->completeness_status !== PlanSection::STATUS_COMPLETE
        ) {
            return;
        }

        $normalized = $this->normalize($section->body);
        $wordCount = str_word_count($normalized);
        $hash = hash('sha256', $normalized);
        $last = EntrepreneurStreakEvent::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('plan_section_id', $section->getKey())
            ->latest('occurred_at')
            ->first();

        if ($last instanceof EntrepreneurStreakEvent) {
            $delta = abs($wordCount - (int) $last->word_count);
            $minimumDelta = max(1, (int) config('gamification.streak_min_word_delta', 5));

            if ($last->body_hash === $hash || $delta < $minimumDelta) {
                $this->recompute($profile);

                return;
            }
        }

        $this->context->withSystemContext(function () use ($profile, $section, $hash, $wordCount): void {
            $now = CarbonImmutable::now();

            EntrepreneurStreakEvent::query()->create([
                'entrepreneur_profile_id' => $profile->getKey(),
                'plan_section_id' => $section->getKey(),
                'body_hash' => $hash,
                'word_count' => $wordCount,
                'active_day' => $now->setTimezone((string) config('gamification.timezone', 'Pacific/Auckland'))->toDateString(),
                'occurred_at' => $now,
            ]);
        });

        $this->recompute($profile);
    }

    public function recompute(EntrepreneurProfile $profile): void
    {
        $this->context->withSystemContext(function () use ($profile): void {
            $events = EntrepreneurStreakEvent::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->orderByDesc('active_day')
                ->orderByDesc('occurred_at')
                ->get();

            $streak = $this->streakFromEvents($events);
            $latest = $events->sortByDesc('occurred_at')->first();

            $profile->forceFill([
                'current_streak' => $streak,
                'last_active_at' => $latest?->occurred_at,
            ])->save();
        });
    }

    /**
     * @param  Collection<int, EntrepreneurStreakEvent>  $events
     */
    private function streakFromEvents(Collection $events): int
    {
        $timezone = (string) config('gamification.timezone', 'Pacific/Auckland');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $yesterday = $today->subDay();
        $days = $events
            ->pluck('active_day')
            ->map(fn ($day): string => $day instanceof CarbonImmutable ? $day->toDateString() : CarbonImmutable::parse((string) $day, $timezone)->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $latestDay = CarbonImmutable::parse((string) $days->first(), $timezone)->startOfDay();
        if ($latestDay->lt($yesterday)) {
            return 0;
        }

        $expected = $latestDay;
        $streak = 0;

        foreach ($days as $day) {
            $activeDay = CarbonImmutable::parse((string) $day, $timezone)->startOfDay();
            if (! $activeDay->equalTo($expected)) {
                break;
            }

            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    private function normalize(string $body): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
    }
}
