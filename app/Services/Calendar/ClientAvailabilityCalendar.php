<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Client;
use App\Models\ClientLeavePeriod;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\StrategicPlanMilestone;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientAvailabilityCalendar
{
    public function __construct(
        private readonly PublicHolidayCalendar $publicHolidays,
    ) {}

    public function assertAvailable(Client $client, mixed $date, string $subject, string $field): void
    {
        if ($date === null || trim((string) $date) === '') {
            return;
        }

        $leave = $this->leavePeriodOn($client, $date);

        if ($leave instanceof ClientLeavePeriod) {
            throw ValidationException::withMessages([
                $field => $this->validationMessage($leave, $subject),
            ]);
        }
    }

    public function leavePeriodOn(Client $client, mixed $date): ?ClientLeavePeriod
    {
        $target = $this->carbon($date)->toDateString();

        return ClientLeavePeriod::query()
            ->where('client_id', $client->getKey())
            ->whereDate('starts_on', '<=', $target)
            ->whereDate('ends_on', '>=', $target)
            ->orderBy('starts_on')
            ->first();
    }

    public function nextAvailableDate(Client $client, mixed $date): Carbon
    {
        $next = $this->carbon($date)->startOfDay();
        $regions = $this->publicHolidays->regionsForClient($client);

        for ($attempts = 0; $attempts < 400; $attempts++) {
            $holiday = $this->publicHolidays->holidayOn($next, $regions);

            if ($holiday !== null) {
                $next->addDay();

                continue;
            }

            $leave = $this->leavePeriodOn($client, $next);

            if ($leave instanceof ClientLeavePeriod) {
                $next = $leave->ends_on->copy()->addDay()->startOfDay();

                continue;
            }

            return $next;
        }

        return $next;
    }

    /**
     * @return array{milestones:int, actions:int, strategic_plan_milestones:int}
     */
    public function rescheduleOpenDueDates(Client $client, ?ClientLeavePeriod $leave = null): array
    {
        return DB::transaction(function () use ($client, $leave): array {
            return [
                'milestones' => $this->rescheduleMilestones($client, $leave),
                'actions' => $this->rescheduleActions($client, $leave),
                'strategic_plan_milestones' => $this->rescheduleStrategicPlanMilestones($client, $leave),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaveEventsBetween(Client $client, mixed $start, mixed $end): array
    {
        $startDate = $this->carbon($start)->startOfDay();
        $endDate = $this->carbon($end)->startOfDay();

        return ClientLeavePeriod::query()
            ->where('client_id', $client->getKey())
            ->overlapping($startDate->toDateString(), $endDate->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->flatMap(function (ClientLeavePeriod $leave) use ($startDate, $endDate): array {
                $events = [];
                $cursor = $leave->starts_on->copy()->max($startDate)->startOfDay();
                $last = $leave->ends_on->copy()->min($endDate)->startOfDay();
                $range = $this->periodLabel($leave);

                while ($cursor->lte($last)) {
                    $events[] = [
                        'id' => "leave:{$leave->id}:{$cursor->toDateString()}",
                        'title' => $leave->title ?: 'On leave',
                        'starts_at' => $cursor->toIso8601String(),
                        'kind' => 'leave',
                        'kind_label' => 'Client leave',
                        'status' => 'Unavailable',
                        'description' => $range,
                        'href' => null,
                        'all_day' => true,
                    ];
                    $cursor->addDay();
                }

                return $events;
            })
            ->values()
            ->all();
    }

    public function validationMessage(ClientLeavePeriod $leave, string $subject): string
    {
        return "{$subject} cannot be scheduled during client leave ({$this->periodLabel($leave)}). Choose another date for this client.";
    }

    private function rescheduleMilestones(Client $client, ?ClientLeavePeriod $leave): int
    {
        $count = 0;

        $this->conflictingQuery(
            Milestone::query()
                ->where('client_id', $client->getKey())
                ->whereNotIn('status', [Milestone::STATUS_COMPLETED])
                ->whereNotNull('due_date'),
            $leave,
        )
            ->orderBy('due_date')
            ->each(function (Milestone $milestone) use ($client, &$count): void {
                $next = $this->nextAvailableDate($client, $milestone->due_date)->toDateString();

                if ($milestone->due_date?->toDateString() === $next) {
                    return;
                }

                $milestone->forceFill(['due_date' => $next])->save();
                $count++;
            });

        return $count;
    }

    private function rescheduleActions(Client $client, ?ClientLeavePeriod $leave): int
    {
        $count = 0;

        $this->conflictingQuery(
            MilestoneAction::query()
                ->where('client_id', $client->getKey())
                ->whereNotIn('status', [MilestoneAction::STATUS_COMPLETED])
                ->whereNotNull('due_date'),
            $leave,
        )
            ->orderBy('due_date')
            ->each(function (MilestoneAction $action) use ($client, &$count): void {
                $next = $this->nextAvailableDate($client, $action->due_date)->toDateString();

                if ($action->due_date?->toDateString() === $next) {
                    return;
                }

                $action->forceFill(['due_date' => $next])->save();
                $count++;
            });

        return $count;
    }

    private function rescheduleStrategicPlanMilestones(Client $client, ?ClientLeavePeriod $leave): int
    {
        $count = 0;

        $this->conflictingQuery(
            StrategicPlanMilestone::query()
                ->where('client_id', $client->getKey())
                ->whereNotIn('status', [StrategicPlanMilestone::STATUS_COMPLETED])
                ->whereNotNull('due_date'),
            $leave,
        )
            ->orderBy('due_date')
            ->each(function (StrategicPlanMilestone $milestone) use ($client, &$count): void {
                $next = $this->nextAvailableDate($client, $milestone->due_date)->toDateString();

                if ($milestone->due_date?->toDateString() === $next) {
                    return;
                }

                $milestone->forceFill(['due_date' => $next])->save();
                $count++;
            });

        return $count;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    private function conflictingQuery(\Illuminate\Database\Eloquent\Builder $query, ?ClientLeavePeriod $leave): \Illuminate\Database\Eloquent\Builder
    {
        if ($leave instanceof ClientLeavePeriod) {
            return $query
                ->whereDate('due_date', '>=', $leave->starts_on->toDateString())
                ->whereDate('due_date', '<=', $leave->ends_on->toDateString());
        }

        $table = $query->getModel()->getTable();

        return $query->whereExists(function ($exists) use ($table): void {
            $exists
                ->selectRaw('1')
                ->from('client_leave_periods')
                ->whereColumn('client_leave_periods.client_id', "{$table}.client_id")
                ->whereColumn('client_leave_periods.starts_on', '<=', "{$table}.due_date")
                ->whereColumn('client_leave_periods.ends_on', '>=', "{$table}.due_date");
        });
    }

    private function periodLabel(ClientLeavePeriod $leave): string
    {
        if ($leave->starts_on->isSameDay($leave->ends_on)) {
            return $leave->starts_on->format('j M Y');
        }

        return $leave->starts_on->format('j M Y').' to '.$leave->ends_on->format('j M Y');
    }

    private function carbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }
}
