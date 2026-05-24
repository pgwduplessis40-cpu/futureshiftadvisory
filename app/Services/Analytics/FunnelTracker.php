<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Client;
use App\Models\FunnelEvent;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FunnelTracker
{
    public const LAYER_ID = 15;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function enter(string $flow, string $step, ?Client $client = null, ?User $user = null, ?CarbonInterface $at = null): FunnelEvent
    {
        $at ??= now();
        $open = $this->openEvent($flow, $step, $client, $user);

        if ($open instanceof FunnelEvent) {
            return $open;
        }

        return FunnelEvent::query()->create([
            'flow' => $flow,
            'step' => $step,
            'client_id' => $client?->getKey(),
            'user_id' => $user?->getKey(),
            'entered_at' => $at,
            'abandoned' => false,
        ]);
    }

    public function complete(string $flow, string $step, ?Client $client = null, ?User $user = null, ?CarbonInterface $at = null): FunnelEvent
    {
        $at ??= now();
        $event = $this->openEvent($flow, $step, $client, $user)
            ?? $this->enter($flow, $step, $client, $user, $at);

        $event->forceFill([
            'completed_at' => $at,
            'abandoned' => false,
        ])->save();

        return $event->refresh();
    }

    public function abandonStaleEntries(?CarbonInterface $olderThan = null): int
    {
        $olderThan ??= now()->subDay();

        return FunnelEvent::query()
            ->whereNull('completed_at')
            ->where('abandoned', false)
            ->where('entered_at', '<=', $olderThan)
            ->update(['abandoned' => true, 'updated_at' => now()]);
    }

    /**
     * A null client id list means all clients.
     *
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function summary(?array $clientIds = null, ?CarbonInterface $since = null): array
    {
        if ($clientIds === []) {
            return $this->emptySummary();
        }

        $events = $this->events($clientIds, $since)->get();
        $steps = $this->stepSummaries($events);
        $worst = $steps->sortByDesc('drop_off_rate')->first();

        return [
            'summary' => [
                'events' => $events->count(),
                'abandoned' => $events->where('abandoned', true)->count(),
                'completed' => $events->filter(fn (FunnelEvent $event): bool => $event->completed_at !== null)->count(),
                'worst_drop_off_rate' => is_array($worst) ? $worst['drop_off_rate'] : 0.0,
            ],
            'steps' => $steps->values()->all(),
        ];
    }

    public function runMonthlySuggestions(int $minimumEntered = 3, int $windowDays = 30, ?CarbonInterface $windowEnd = null): LearningLayerRun
    {
        $minimumEntered = max(1, $minimumEntered);
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        return DB::transaction(function () use ($minimumEntered, $windowDays, $windowStart, $windowEnd): LearningLayerRun {
            $this->abandonStaleEntries($windowEnd->copy()->subDay());
            $summary = $this->summary(null, $windowStart);
            $candidate = collect($summary['steps'])
                ->filter(fn (array $step): bool => $step['entered'] >= $minimumEntered && $step['drop_off_rate'] > 0)
                ->sortByDesc('drop_off_rate')
                ->first();
            $created = 0;

            if (is_array($candidate) && ! $this->candidateExists($candidate, $windowStart, $windowEnd)) {
                $this->createCandidate($candidate, $windowStart, $windowEnd);
                $created = 1;
            }

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $created,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => $windowDays,
                    'minimum_entered' => $minimumEntered,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('funnel_analytics_layer.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'candidates_created' => $created,
            ]);

            return $run;
        });
    }

    private function openEvent(string $flow, string $step, ?Client $client, ?User $user): ?FunnelEvent
    {
        return FunnelEvent::query()
            ->where('flow', $flow)
            ->where('step', $step)
            ->when($client instanceof Client, fn ($query) => $query->where('client_id', $client->getKey()))
            ->when(! ($client instanceof Client), fn ($query) => $query->whereNull('client_id'))
            ->when($user instanceof User, fn ($query) => $query->where('user_id', $user->getKey()))
            ->when(! ($user instanceof User), fn ($query) => $query->whereNull('user_id'))
            ->whereNull('completed_at')
            ->where('abandoned', false)
            ->latest('entered_at')
            ->first();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     */
    private function events(?array $clientIds, ?CarbonInterface $since)
    {
        return FunnelEvent::query()
            ->with('client')
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->when($since instanceof CarbonInterface, fn ($query) => $query->where('entered_at', '>=', $since))
            ->orderBy('flow')
            ->orderBy('step');
    }

    /**
     * @param  Collection<int, FunnelEvent>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function stepSummaries(Collection $events): Collection
    {
        return $events
            ->groupBy(fn (FunnelEvent $event): string => $event->flow.'|'.$event->step)
            ->map(function (Collection $group): array {
                /** @var FunnelEvent $first */
                $first = $group->first();
                $entered = $group->count();
                $completed = $group->filter(fn (FunnelEvent $event): bool => $event->completed_at !== null)->count();
                $dropped = $group->where('abandoned', true);
                $abandoned = $dropped->count();
                $dropOff = $entered === 0 ? 0.0 : round(($entered - $completed) / $entered, 4);
                $droppedClients = $this->droppedClients($dropped);

                return [
                    'flow' => $first->flow,
                    'step' => $first->step,
                    'entered' => $entered,
                    'completed' => $completed,
                    'abandoned' => $abandoned,
                    'dropped_count' => $abandoned,
                    'dropped_clients' => $droppedClients,
                    'last_dropped_at' => $this->lastDroppedAt($dropped),
                    'returned_count' => $this->returnedCount($group, $dropped),
                    'drop_off_rate' => $dropOff,
                ];
            })
            ->sortBy(fn (array $row): string => $row['flow'].'|'.$row['step'])
            ->values();
    }

    /**
     * @param  Collection<int, FunnelEvent>  $dropped
     * @return array<int, array{id:string, name:string, last_dropped_at:string|null, show_url:string}>
     */
    private function droppedClients(Collection $dropped): array
    {
        return $dropped
            ->filter(fn (FunnelEvent $event): bool => $event->client instanceof Client)
            ->groupBy(fn (FunnelEvent $event): string => (string) $event->client_id)
            ->map(function (Collection $events): array {
                /** @var FunnelEvent $event */
                $event = $events
                    ->sortByDesc(fn (FunnelEvent $candidate): int => $this->dropTimestamp($candidate))
                    ->first();
                /** @var Client $client */
                $client = $event->client;

                return [
                    'id' => (string) $client->getKey(),
                    'name' => $client->legal_name,
                    'last_dropped_at' => $this->dropDate($event),
                    'show_url' => route('advisor.clients.show', $client, absolute: false),
                ];
            })
            ->sortBy('name')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FunnelEvent>  $dropped
     */
    private function lastDroppedAt(Collection $dropped): ?string
    {
        $event = $dropped
            ->sortByDesc(fn (FunnelEvent $candidate): int => $this->dropTimestamp($candidate))
            ->first();

        return $event instanceof FunnelEvent ? $this->dropDate($event) : null;
    }

    /**
     * @param  Collection<int, FunnelEvent>  $group
     * @param  Collection<int, FunnelEvent>  $dropped
     */
    private function returnedCount(Collection $group, Collection $dropped): int
    {
        $completedByClient = $group
            ->filter(fn (FunnelEvent $event): bool => $event->client_id !== null && $event->completed_at !== null)
            ->groupBy(fn (FunnelEvent $event): string => (string) $event->client_id);

        return $dropped
            ->filter(fn (FunnelEvent $event): bool => $event->client_id !== null)
            ->groupBy(fn (FunnelEvent $event): string => (string) $event->client_id)
            ->filter(function (Collection $droppedEvents, string $clientId) use ($completedByClient): bool {
                $lastDrop = $droppedEvents->max(fn (FunnelEvent $event): int => $this->dropTimestamp($event));
                $completed = $completedByClient->get($clientId, collect());

                return $completed->contains(
                    fn (FunnelEvent $event): bool => $event->completed_at !== null
                        && $event->completed_at->getTimestamp() > $lastDrop,
                );
            })
            ->count();
    }

    private function dropTimestamp(FunnelEvent $event): int
    {
        return $event->entered_at->getTimestamp();
    }

    private function dropDate(FunnelEvent $event): ?string
    {
        return $event->entered_at?->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'summary' => [
                'events' => 0,
                'abandoned' => 0,
                'completed' => 0,
                'worst_drop_off_rate' => 0.0,
            ],
            'steps' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function candidateExists(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'funnel_analytics_layer')
            ->where('source->signal_key', $this->signalKey($candidate, $windowStart, $windowEnd))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function createCandidate(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'funnel_analytics_layer',
                'signal_key' => $this->signalKey($candidate, $windowStart, $windowEnd),
                'flow' => $candidate['flow'],
                'step' => $candidate['step'],
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => sprintf(
                'Funnel drop-off is %.1f%% for %s / %s; review the step experience.',
                ((float) $candidate['drop_off_rate']) * 100,
                $candidate['flow'],
                $candidate['step'],
            ),
            'proposed_change' => [
                'action' => 'review_funnel_step_ux',
                'flow' => $candidate['flow'],
                'step' => $candidate['step'],
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'flows' => [$candidate['flow']],
                'dashboard' => 'advisor_funnel_analytics',
            ],
            'clients_affected' => 0,
            'magnitude' => ((float) $candidate['drop_off_rate']) >= 0.5 ? 'medium' : 'low',
            'confidence' => 0.7,
            'evidence' => $candidate,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function signalKey(array $candidate, CarbonInterface $windowStart, CarbonInterface $windowEnd): string
    {
        return hash('sha256', implode('|', [
            $candidate['flow'],
            $candidate['step'],
            $windowStart->toDateString(),
            $windowEnd->toDateString(),
        ]));
    }
}
