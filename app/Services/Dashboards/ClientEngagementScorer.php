<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\MessageThread;
use App\Models\Milestone;
use App\Models\QuestionnaireResponse;
use App\Services\DataQuality\QuestionnaireCompletenessCalculator;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Support\Methodology\ProvidesMethodology;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClientEngagementScorer implements ProvidesMethodology
{
    private const SCORE_KEYS = [
        'questionnaire_pct',
        'documents_pct',
        'milestones_on_track_pct',
        'comms_recency_pct',
    ];

    private const FOCUS_BY_COMPONENT = [
        'questionnaire_pct' => 'questionnaire',
        'documents_pct' => 'documents',
        'milestones_on_track_pct' => 'goals',
        'comms_recency_pct' => 'messages',
        'idea_validation_pct' => 'idea-validation',
        'plan_progress_pct' => 'goals',
        'activity_recency_pct' => 'messages',
    ];

    private const PLAN_SCORE_KEYS = [
        'plan_progress_pct',
        'milestones_on_track_pct',
        'activity_recency_pct',
    ];

    private const VALIDATION_SCORE_KEYS = [
        'idea_validation_pct',
        'milestones_on_track_pct',
        'activity_recency_pct',
    ];

    public static function methodologyIds(): array
    {
        return ['engagement.score'];
    }

    public function __construct(
        private readonly QuestionnaireCompletenessCalculator $questionnaires,
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurWorkspaces,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function score(Client $client): array
    {
        return $this->scoreMany(new EloquentCollection([$client]))[(string) $client->getKey()];
    }

    /**
     * @param  iterable<int, Client>  $clients
     * @return array<string, array<string, mixed>>
     */
    public function scoreMany(iterable $clients): array
    {
        $clientCollection = $clients instanceof EloquentCollection
            ? $clients
            : new EloquentCollection(is_array($clients) ? $clients : iterator_to_array($clients));
        $clientIds = $clientCollection
            ->map(fn (Client $client): string => (string) $client->getKey())
            ->values()
            ->all();

        if ($clientIds === []) {
            return [];
        }

        $responsesByClient = QuestionnaireResponse::query()
            ->whereIn('client_id', $clientIds)
            ->with(['answers.question', 'questionnaire.sections.questions'])
            ->get()
            ->groupBy(fn (QuestionnaireResponse $response): string => (string) $response->client_id);
        $documentsByClient = Document::query()
            ->visibleToClients()
            ->whereIn('client_id', $clientIds)
            ->with('verifications')
            ->get()
            ->groupBy(fn (Document $document): string => (string) $document->client_id);
        $verificationsByClient = DocumentVerification::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy(fn (DocumentVerification $verification): string => (string) $verification->client_id);
        $milestonesByClient = Milestone::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy(fn (Milestone $milestone): string => (string) $milestone->client_id);
        $latestMessageByClient = MessageThread::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('max(last_activity_at) as last_activity_at'))
            ->groupBy('client_id')
            ->pluck('last_activity_at', 'client_id');
        $entrepreneurProfilesByClient = $this->entrepreneurWorkspaces->forClients($clientCollection);
        $entrepreneurPlansByClient = $this->entrepreneurPlansByClient($entrepreneurProfilesByClient);

        return $clientCollection
            ->mapWithKeys(function (Client $client) use (
                $documentsByClient,
                $entrepreneurProfilesByClient,
                $entrepreneurPlansByClient,
                $latestMessageByClient,
                $milestonesByClient,
                $responsesByClient,
                $verificationsByClient,
            ): array {
                $clientId = (string) $client->getKey();
                $milestoneScore = $this->milestoneScore($this->group($milestonesByClient, $clientId));
                $commsScore = $this->commsScore($latestMessageByClient->get($clientId));
                $profile = $entrepreneurProfilesByClient->get($clientId);
                $latestIdeaValidation = $profile instanceof EntrepreneurProfile
                    ? $this->latestIdeaValidation($profile)
                    : null;
                $latestIdeaActivity = $latestIdeaValidation instanceof IdeaValidation
                    ? $this->latestIdeaValidationActivity($latestIdeaValidation)
                    : null;
                $plan = $entrepreneurPlansByClient->get($clientId);
                $planCompletion = $plan instanceof BusinessPlan
                    ? PlanRequirements::completion($plan)
                    : null;
                $latestPlanActivity = $plan instanceof BusinessPlan
                    ? $this->latestPlanActivity($plan)
                    : null;
                $planActivityScore = $this->commsScore($latestPlanActivity);
                $ideaActivityScore = $this->commsScore($latestIdeaActivity);
                $activityScore = max($commsScore['score'], $planActivityScore['score'], $ideaActivityScore['score']);
                $latestActivityDays = collect([
                    $commsScore['last_comms_days'],
                    $planActivityScore['last_comms_days'],
                    $ideaActivityScore['last_comms_days'],
                ])->filter(fn (mixed $days): bool => is_int($days))->min();
                $standardScores = [
                    'questionnaire_pct' => $this->questionnaires
                        ->calculate($this->group($responsesByClient, $clientId))
                        ->score,
                    'documents_pct' => $this->documentsScore(
                        $this->group($documentsByClient, $clientId),
                        $this->group($verificationsByClient, $clientId),
                    ),
                    'milestones_on_track_pct' => $milestoneScore['score'],
                    'comms_recency_pct' => $commsScore['score'],
                ];
                if ($plan instanceof BusinessPlan) {
                    $scoringMode = 'entrepreneur_plan';
                    $scoreComponents = [
                        'plan_progress_pct' => (int) $planCompletion['percent'],
                        'milestones_on_track_pct' => $milestoneScore['score'],
                        'activity_recency_pct' => $activityScore,
                    ];
                    $scoreKeys = self::PLAN_SCORE_KEYS;
                    $score = $this->weightedScore(
                        $scoreComponents,
                        config('dashboards.engagement.entrepreneur_plan_weights', []),
                        self::PLAN_SCORE_KEYS,
                    );
                } elseif ($latestIdeaValidation instanceof IdeaValidation) {
                    $scoringMode = 'entrepreneur_validation';
                    $scoreComponents = [
                        'idea_validation_pct' => $this->ideaValidationScore($latestIdeaValidation),
                        'milestones_on_track_pct' => $milestoneScore['score'],
                        'activity_recency_pct' => $activityScore,
                    ];
                    $scoreKeys = self::VALIDATION_SCORE_KEYS;
                    $score = $this->weightedScore(
                        $scoreComponents,
                        config('dashboards.engagement.entrepreneur_validation_weights', []),
                        self::VALIDATION_SCORE_KEYS,
                    );
                } else {
                    $scoringMode = 'standard_advisory';
                    $scoreComponents = $standardScores;
                    $scoreKeys = self::SCORE_KEYS;
                    $score = $this->compositeScore($standardScores);
                }

                $scores = $scoringMode === 'standard_advisory'
                    ? $standardScores
                    : [...$standardScores, ...$scoreComponents];
                $weakestComponent = $this->weakestComponent($scoreComponents, $scoreKeys);
                $display = [
                    'overdue_count' => $milestoneScore['overdue_count'],
                    'blocked_count' => $milestoneScore['blocked_count'],
                    'last_comms_days' => $commsScore['last_comms_days'],
                ];

                if ($plan instanceof BusinessPlan) {
                    $display['last_activity_days'] = $latestActivityDays;
                    $display['last_plan_activity_at'] = $latestPlanActivity?->toIso8601String();
                }

                if ($latestIdeaValidation instanceof IdeaValidation) {
                    $display['last_activity_days'] = $latestActivityDays;
                    $display['last_idea_validation_at'] = $latestIdeaActivity?->toIso8601String();
                    $display['idea_validation_status'] = $this->ideaValidationStatus($latestIdeaValidation);
                }

                return [
                    $clientId => [
                        'scoring_mode' => $scoringMode,
                        'level' => $this->levelFor($score),
                        'score' => $score,
                        'scores' => $scores,
                        'display' => $display,
                        'weakest_component' => $weakestComponent,
                        'focus_section' => self::FOCUS_BY_COMPONENT[$weakestComponent],
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Document>  $documents
     * @param  EloquentCollection<int, DocumentVerification>  $verifications
     */
    private function documentsScore(EloquentCollection $documents, EloquentCollection $verifications): int
    {
        if ($documents->isEmpty()) {
            return 0;
        }

        if ($verifications->contains(fn (DocumentVerification $verification): bool => $verification->isBlockingAnalysis())) {
            return 0;
        }

        $verified = $documents
            ->filter(function (Document $document): bool {
                return $document->verifications->isNotEmpty()
                    && $document->verifications->every(
                        fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
                    );
            })
            ->count();

        return $this->percent($verified, $documents->count());
    }

    /**
     * @param  EloquentCollection<int, Milestone>  $milestones
     * @return array{score:int, overdue_count:int, blocked_count:int}
     */
    private function milestoneScore(EloquentCollection $milestones): array
    {
        $active = $milestones->filter(
            fn (Milestone $milestone): bool => $milestone->status !== Milestone::STATUS_COMPLETED,
        );

        if ($active->isEmpty()) {
            return [
                'score' => 100,
                'overdue_count' => 0,
                'blocked_count' => 0,
            ];
        }

        $overdueCount = $active
            ->filter(fn (Milestone $milestone): bool => $this->isOverdue($milestone->due_date))
            ->count();
        $blockedCount = $active
            ->filter(fn (Milestone $milestone): bool => $milestone->status === Milestone::STATUS_BLOCKED)
            ->count();
        $offTrack = $active
            ->filter(fn (Milestone $milestone): bool => $milestone->status === Milestone::STATUS_BLOCKED || $this->isOverdue($milestone->due_date))
            ->count();

        return [
            'score' => $this->percent($active->count() - $offTrack, $active->count()),
            'overdue_count' => $overdueCount,
            'blocked_count' => $blockedCount,
        ];
    }

    /**
     * @return array{score:int, last_comms_days:int|null}
     */
    private function commsScore(mixed $latestActivity): array
    {
        $latest = $this->carbon($latestActivity);

        if (! $latest instanceof CarbonInterface) {
            return [
                'score' => 0,
                'last_comms_days' => null,
            ];
        }

        $days = max(0, (int) floor($latest->diffInDays(now(), false)));
        $decayDays = max(1, (int) config('dashboards.engagement.comms_decay_days', 30));
        $score = $this->clamp((int) round(100 * (1 - ($days / $decayDays))));

        return [
            'score' => $score,
            'last_comms_days' => $days,
        ];
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function compositeScore(array $scores): int
    {
        return $this->weightedScore($scores, config('dashboards.engagement.weights', []), self::SCORE_KEYS);
    }

    /**
     * @param  array<string, int>  $scores
     * @param  array<string, mixed>  $weights
     * @param  array<int, string>  $keys
     */
    private function weightedScore(array $scores, array $weights, array $keys): int
    {
        $weighted = 0.0;

        foreach ($keys as $key) {
            $weighted += ($scores[$key] ?? 0) * (float) ($weights[$key] ?? 0);
        }

        return $this->clamp((int) round($weighted, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * @param  array<string, int>  $scores
     * @param  array<int, string>  $keys
     */
    private function weakestComponent(array $scores, array $keys): string
    {
        $weakest = $keys[0];

        foreach ($keys as $key) {
            if (($scores[$key] ?? 0) < ($scores[$weakest] ?? 0)) {
                $weakest = $key;
            }
        }

        return $weakest;
    }

    /**
     * @param  Collection<string, EntrepreneurProfile>  $profilesByClient
     * @return Collection<string, BusinessPlan>
     */
    private function entrepreneurPlansByClient(Collection $profilesByClient): Collection
    {
        $profileIdsByClient = $profilesByClient
            ->map(fn (EntrepreneurProfile $profile): string => (string) $profile->getKey());

        if ($profileIdsByClient->isEmpty()) {
            return collect();
        }

        $clientIdsByProfile = $profileIdsByClient->flip();

        return BusinessPlan::query()
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->whereIn('entrepreneur_profile_id', $profileIdsByClient->values()->all())
            ->with(['sections', 'budgetRunway'])
            ->get()
            ->sortByDesc(fn (BusinessPlan $plan): int => $this->latestPlanActivity($plan)?->getTimestamp() ?? 0)
            ->groupBy(fn (BusinessPlan $plan): string => (string) $clientIdsByProfile->get((string) $plan->entrepreneur_profile_id, ''))
            ->reject(fn (Collection $plans, string $clientId): bool => $clientId === '')
            ->map(fn (Collection $plans): BusinessPlan => $plans->first());
    }

    private function latestIdeaValidation(EntrepreneurProfile $profile): ?IdeaValidation
    {
        $profile->loadMissing('ideaValidations');

        return $profile->ideaValidations
            ->sortByDesc(fn (IdeaValidation $validation): int => $this->latestIdeaValidationActivity($validation)?->getTimestamp() ?? 0)
            ->first();
    }

    private function latestIdeaValidationActivity(IdeaValidation $validation): ?CarbonInterface
    {
        $evaluation = $validation->ai_evaluation ?? [];

        return collect([
            $validation->updated_at,
            $validation->evaluated_at,
            $validation->advisor_gate_passed_at,
            $validation->recalled_at,
            data_get($evaluation, 'metadata.changes_requested_at'),
            data_get($evaluation, 'metadata.refresh_requested_at'),
            data_get($evaluation, 'metadata.refresh_started_at'),
            data_get($evaluation, 'metadata.refresh_completed_at'),
            data_get($evaluation, 'metadata.refresh_failed_at'),
        ])
            ->map(fn (mixed $value): ?CarbonInterface => $this->carbon($value))
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->sortByDesc(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->first();
    }

    private function ideaValidationScore(IdeaValidation $validation): int
    {
        if ($validation->advisor_gate_passed_at instanceof CarbonInterface) {
            return 100;
        }

        $status = $this->ideaValidationStatus($validation);

        return match ($status) {
            'awaiting_resubmission' => 60,
            'refreshing' => 70,
            'refresh_failed' => 45,
            'recalled' => 40,
            'submitted' => 80,
            default => 25,
        };
    }

    private function ideaValidationStatus(IdeaValidation $validation): string
    {
        $evaluation = $validation->ai_evaluation ?? [];
        $gateStatus = data_get($evaluation, 'metadata.advisor_gate_status');
        $refreshStatus = data_get($evaluation, 'metadata.refresh_status');

        if ($validation->advisor_gate_passed_at instanceof CarbonInterface || $gateStatus === 'approved') {
            return 'approved';
        }

        if ($validation->recalled_at instanceof CarbonInterface) {
            return 'recalled';
        }

        if ($gateStatus === 'changes_requested') {
            return 'awaiting_resubmission';
        }

        if (in_array($refreshStatus, ['queued', 'running'], true)) {
            return 'refreshing';
        }

        if ($refreshStatus === 'failed') {
            return 'refresh_failed';
        }

        return 'submitted';
    }

    private function latestPlanActivity(BusinessPlan $plan): ?CarbonInterface
    {
        $plan->loadMissing('sections', 'budgetRunway');

        return collect([
            $plan->updated_at,
            $plan->budgetRunway?->updated_at,
            ...$plan->sections->pluck('updated_at')->all(),
        ])
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->sortByDesc(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->first();
    }

    private function levelFor(int $score): string
    {
        $green = (int) config('dashboards.engagement.thresholds.green', 75);
        $amber = (int) config('dashboards.engagement.thresholds.amber', 50);

        return match (true) {
            $score >= $green => 'green',
            $score >= $amber => 'amber',
            default => 'red',
        };
    }

    private function isOverdue(mixed $dueDate): bool
    {
        return $dueDate instanceof CarbonInterface
            && $dueDate->lt(now()->startOfDay());
    }

    private function carbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function percent(int $part, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return $this->clamp((int) round(($part / $total) * 100));
    }

    private function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<string, EloquentCollection<int, TModel>>  $groups
     * @return EloquentCollection<int, TModel>
     */
    private function group(Collection $groups, string $clientId): EloquentCollection
    {
        $group = $groups->get($clientId);

        if ($group instanceof EloquentCollection) {
            return $group;
        }

        if ($group instanceof Collection) {
            return new EloquentCollection($group->all());
        }

        if (is_array($group)) {
            return new EloquentCollection($group);
        }

        return new EloquentCollection;
    }
}
