<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\MessageThread;
use App\Models\Milestone;
use App\Models\QuestionnaireResponse;
use App\Services\DataQuality\QuestionnaireCompletenessCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClientEngagementScorer
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
    ];

    public function __construct(private readonly QuestionnaireCompletenessCalculator $questionnaires) {}

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

        return $clientCollection
            ->mapWithKeys(function (Client $client) use (
                $documentsByClient,
                $latestMessageByClient,
                $milestonesByClient,
                $responsesByClient,
                $verificationsByClient,
            ): array {
                $clientId = (string) $client->getKey();
                $milestoneScore = $this->milestoneScore($this->group($milestonesByClient, $clientId));
                $commsScore = $this->commsScore($latestMessageByClient->get($clientId));
                $scores = [
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
                $weakestComponent = $this->weakestComponent($scores);
                $score = $this->compositeScore($scores);

                return [
                    $clientId => [
                        'level' => $this->levelFor($score),
                        'score' => $score,
                        'scores' => $scores,
                        'display' => [
                            'overdue_count' => $milestoneScore['overdue_count'],
                            'blocked_count' => $milestoneScore['blocked_count'],
                            'last_comms_days' => $commsScore['last_comms_days'],
                        ],
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
        $weights = config('dashboards.engagement.weights', []);
        $weighted = 0.0;

        foreach (self::SCORE_KEYS as $key) {
            $weighted += ($scores[$key] ?? 0) * (float) ($weights[$key] ?? 0);
        }

        return $this->clamp((int) round($weighted, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function weakestComponent(array $scores): string
    {
        $weakest = self::SCORE_KEYS[0];

        foreach (self::SCORE_KEYS as $key) {
            if (($scores[$key] ?? 0) < ($scores[$weakest] ?? 0)) {
                $weakest = $key;
            }
        }

        return $weakest;
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
