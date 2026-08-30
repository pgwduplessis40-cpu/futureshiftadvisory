<?php

declare(strict_types=1);

namespace App\Services\Journeys;

use App\Models\Client;
use App\Models\ServiceJourneyEnrollment;
use App\Models\ServiceJourneyMilestoneAward;
use App\Models\ServiceJourneyPointEvent;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ServiceJourney
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly ServiceJourneyStateResolver $states,
    ) {}

    /**
     * @param  array<string, mixed>  $portalJourney
     * @return array<string, mixed>
     */
    public function payload(Client $client, User $participant, array $portalJourney): array
    {
        $serviceKey = ServiceJourneyPrograms::normalise((string) data_get($portalJourney, 'primary.service_type', 'standard_advisory'));
        $program = ServiceJourneyPrograms::for($serviceKey);
        $enrollment = $this->enrollmentFor($client, $participant, $serviceKey);
        $awards = $enrollment instanceof ServiceJourneyEnrollment
            ? $enrollment->milestoneAwards()->orderBy('earned_at')->get()
            : collect();
        $pointEvents = $enrollment instanceof ServiceJourneyEnrollment
            ? $enrollment->pointEvents()->get()
            : collect();
        $stages = $this->programMilestones((array) data_get($portalJourney, 'stages', []));
        $programMilestones = $this->programMilestones((array) ($program['milestones'] ?? []));
        $stagesByKey = collect($stages)->keyBy('key');
        $milestones = collect($programMilestones)
            ->map(function (array $milestone) use ($stagesByKey): array {
                $stage = $stagesByKey->get($milestone['key']);

                return [
                    ...$milestone,
                    'status' => is_array($stage) ? (string) ($stage['status'] ?? 'pending') : 'pending',
                    'description' => is_array($stage) ? (string) ($stage['description'] ?? '') : '',
                    'owner_label' => is_array($stage) ? (string) ($stage['owner_label'] ?? '') : '',
                ];
            })
            ->values();

        $next = $milestones->first(fn (array $milestone): bool => $milestone['status'] !== 'complete');

        return [
            'available' => true,
            'enabled' => (bool) $enrollment?->recognition_enabled,
            'service_key' => $serviceKey,
            'program_version' => $program['version'],
            'title' => $program['title'],
            'preference_url' => route('portal.service-journey.preference', absolute: false),
            'seen_url' => route('portal.service-journey.seen', absolute: false),
            'milestones' => $milestones->all(),
            'points' => [
                'total' => (int) $pointEvents->sum('points'),
                'milestone_count' => $pointEvents->count(),
            ],
            'badges' => $awards
                ->map(fn (ServiceJourneyMilestoneAward $award): array => $this->awardPayload($award, $program))
                ->values()
                ->all(),
            'new_badge_count' => $awards->whereNull('seen_at')->count(),
            'next_quest' => is_array($next) && $next['points'] > 0
                ? [
                    'key' => $next['key'],
                    'label' => $next['label'],
                    'points' => $next['points'],
                    'description' => $next['description'],
                ]
                : null,
        ];
    }

    /**
     * Builds a conservative recognition payload for an active secondary
     * service where the shared dashboard has no service-specific stage UI.
     *
     * @return array<string, mixed>
     */
    public function payloadForService(Client $client, User $participant, string $serviceKey): array
    {
        $serviceKey = ServiceJourneyPrograms::normalise($serviceKey);
        $program = ServiceJourneyPrograms::for($serviceKey);
        $state = $this->states->forClient($client, $serviceKey);

        return $this->payload($client, $participant, [
            'primary' => ['service_type' => $serviceKey],
            'stages' => collect($this->programMilestones((array) ($program['milestones'] ?? [])))
                ->map(function (array $milestone) use ($state): array {
                    $complete = (bool) data_get($state, 'stages.'.$milestone['key'], false);

                    return [
                        'key' => $milestone['key'],
                        'status' => $complete ? 'complete' : 'pending',
                        'description' => $complete
                            ? 'Verified in the current service record.'
                            : 'This recognition is recorded once the service-specific evidence is verified.',
                        'owner_label' => $milestone['owner'] === 'client' ? 'Awaiting you' : 'With FSA',
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function setRecognition(Client $client, User $participant, string $serviceKey, bool $enabled): ServiceJourneyEnrollment
    {
        $serviceKey = ServiceJourneyPrograms::normalise($serviceKey);
        $program = ServiceJourneyPrograms::for($serviceKey);

        /** @var ServiceJourneyEnrollment $enrollment */
        $enrollment = $this->context->withSystemContext(function () use ($client, $participant, $serviceKey, $program, $enabled): ServiceJourneyEnrollment {
            return DB::transaction(function () use ($client, $participant, $serviceKey, $program, $enabled): ServiceJourneyEnrollment {
                $enrollment = ServiceJourneyEnrollment::query()->firstOrNew([
                    'client_id' => $client->getKey(),
                    'participant_user_id' => $participant->getKey(),
                    'service_key' => $serviceKey,
                ]);

                $enrollment->fill([
                    'program_version' => $program['version'],
                    'recognition_enabled' => $enabled,
                    'timezone' => $enrollment->timezone ?: config('gamification.timezone', 'Pacific/Auckland'),
                    'recognition_enabled_at' => $enabled ? now() : $enrollment->recognition_enabled_at,
                    'recognition_disabled_at' => $enabled ? null : now(),
                ])->save();

                return $enrollment->refresh();
            });
        });

        if ($enabled) {
            $this->reconcile($enrollment, $this->states->forClient($client, $serviceKey));
        }

        return $enrollment->refresh();
    }

    public function markSeen(Client $client, User $participant, string $serviceKey): int
    {
        $enrollment = $this->enrollmentFor($client, $participant, ServiceJourneyPrograms::normalise($serviceKey));
        if (! $enrollment instanceof ServiceJourneyEnrollment) {
            return 0;
        }

        return $this->context->withSystemContext(fn (): int => ServiceJourneyMilestoneAward::query()
            ->where('service_journey_enrollment_id', $enrollment->getKey())
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]));
    }

    public function reconcileEnabled(?string $clientId = null): int
    {
        $count = 0;

        $this->context->withSystemContext(function () use (&$count, $clientId): void {
            ServiceJourneyEnrollment::query()
                ->with('client')
                ->where('recognition_enabled', true)
                ->when($clientId !== null && $clientId !== '', fn ($query) => $query->where('client_id', $clientId))
                ->orderBy('id')
                ->each(function (ServiceJourneyEnrollment $enrollment) use (&$count): void {
                    $client = $enrollment->client;
                    if (! $client instanceof Client) {
                        return;
                    }

                    $this->reconcile($enrollment, $this->states->forClient($client, $enrollment->service_key));
                    $count++;
                });
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function reconcile(ServiceJourneyEnrollment $enrollment, array $state): void
    {
        if (! $enrollment->recognition_enabled) {
            return;
        }

        $program = ServiceJourneyPrograms::for($enrollment->service_key);
        $completeStages = collect((array) ($state['stages'] ?? []))
            ->filter(fn (mixed $complete): bool => $complete === true)
            ->keys();

        foreach ($program['milestones'] as $milestone) {
            if (! $completeStages->contains($milestone['key'])) {
                continue;
            }

            $this->award($enrollment, $milestone, $program);
        }
    }

    /**
     * @param  array<string, mixed>  $milestone
     * @param  array<string, mixed>  $program
     */
    private function award(ServiceJourneyEnrollment $enrollment, array $milestone, array $program): ServiceJourneyMilestoneAward
    {
        return $this->context->withSystemContext(function () use ($enrollment, $milestone, $program): ServiceJourneyMilestoneAward {
            try {
                $award = ServiceJourneyMilestoneAward::query()->firstOrCreate(
                    [
                        'service_journey_enrollment_id' => $enrollment->getKey(),
                        'milestone_key' => $milestone['key'],
                    ],
                    [
                        'evidence_source_type' => 'service_journey_stage',
                        'evidence_source_id' => $enrollment->service_key.':'.$milestone['key'],
                        'evidence_snapshot' => [
                            'service_key' => $enrollment->service_key,
                            'program_version' => $program['version'],
                            'stage_key' => $milestone['key'],
                            'earned_at_estimated' => true,
                        ],
                        'earned_at' => now(),
                    ],
                );
            } catch (QueryException) {
                $award = ServiceJourneyMilestoneAward::query()
                    ->where('service_journey_enrollment_id', $enrollment->getKey())
                    ->where('milestone_key', $milestone['key'])
                    ->firstOrFail();
            }

            if ((int) $milestone['points'] > 0) {
                try {
                    ServiceJourneyPointEvent::query()->firstOrCreate(
                        ['service_journey_milestone_award_id' => $award->getKey()],
                        [
                            'service_journey_enrollment_id' => $enrollment->getKey(),
                            'milestone_key' => $milestone['key'],
                            'points' => $milestone['points'],
                            'earned_at' => $award->earned_at,
                        ],
                    );
                } catch (QueryException) {
                    ServiceJourneyPointEvent::query()
                        ->where('service_journey_milestone_award_id', $award->getKey())
                        ->firstOrFail();
                }
            }

            return $award;
        });
    }

    private function enrollmentFor(Client $client, User $participant, string $serviceKey): ?ServiceJourneyEnrollment
    {
        return ServiceJourneyEnrollment::query()
            ->where('client_id', $client->getKey())
            ->where('participant_user_id', $participant->getKey())
            ->where('service_key', $serviceKey)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $program
     * @return array<string, mixed>
     */
    private function awardPayload(ServiceJourneyMilestoneAward $award, array $program): array
    {
        $milestone = collect($this->programMilestones((array) ($program['milestones'] ?? [])))
            ->firstWhere('key', $award->milestone_key);

        return [
            'id' => $award->getKey(),
            'key' => $award->milestone_key,
            'label' => is_array($milestone) ? $milestone['label'] : str($award->milestone_key)->replace('_', ' ')->title()->toString(),
            'earned_at' => $award->earned_at?->toIso8601String(),
            'earned_at_estimated' => (bool) data_get($award->evidence_snapshot, 'earned_at_estimated', false),
            'seen_at' => $award->seen_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $milestones
     * @return array<int, array<string, mixed>>
     */
    private function programMilestones(array $milestones): array
    {
        return collect($milestones)
            ->filter(fn (mixed $milestone): bool => is_array($milestone))
            ->values()
            ->all();
    }
}
