<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Notifications\BiasMonitorSignalNotification;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class BiasMonitor
{
    public const LAYER_ID = BiasDetector::LAYER_ID;

    public const DEFAULT_MIN_FINDINGS = 4;

    public const DEFAULT_SKEW_THRESHOLD = 0.5;

    /**
     * @var array<string, string>
     */
    private const COHORT_DIMENSIONS = [
        'entity_type' => 'Entity type',
        'engagement_type' => 'Engagement type',
        'gst_registered' => 'GST registration',
    ];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(
        int $minFindings = self::DEFAULT_MIN_FINDINGS,
        float $skewThreshold = self::DEFAULT_SKEW_THRESHOLD,
        int $windowDays = 30,
        ?CarbonInterface $windowEnd = null,
    ): LearningLayerRun {
        $minFindings = max(1, $minFindings);
        $skewThreshold = max(0.0, min(1.0, $skewThreshold));
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);
        $alerts = [];

        $this->context->apply('system', []);

        $run = DB::transaction(function () use (
            $minFindings,
            $skewThreshold,
            $windowDays,
            $windowStart,
            $windowEnd,
            &$alerts,
        ): LearningLayerRun {
            $candidatesCreated = 0;

            foreach ($this->systematicSignals($windowStart, $windowEnd, $minFindings, $skewThreshold) as $signal) {
                if ($this->detectedCandidateExists((string) $signal['signal_key'])) {
                    continue;
                }

                $candidate = $this->createCandidate($signal, $windowStart, $windowEnd);
                $alerts[] = [
                    'candidate' => $candidate,
                    'signal' => $signal,
                ];
                $candidatesCreated++;
            }

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidatesCreated,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => $windowDays,
                    'min_findings' => $minFindings,
                    'skew_threshold' => $skewThreshold,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record(
                action: 'ai.bias_monitor.ran',
                subject: $run,
                after: [
                    'layer_id' => self::LAYER_ID,
                    'candidates_created' => $candidatesCreated,
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'min_findings' => $minFindings,
                    'skew_threshold' => $skewThreshold,
                ],
            );

            return $run;
        });

        foreach ($alerts as $alert) {
            $this->sendAlert($alert['candidate'], $alert['signal']);
        }

        return $run;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function systematicSignals(
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        int $minFindings,
        float $skewThreshold,
    ): Collection {
        $findings = AnalysisFinding::query()
            ->with(['run', 'client'])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->oldest('created_at')
            ->get()
            ->filter(fn (AnalysisFinding $finding): bool => $finding->run !== null && $finding->client !== null)
            ->values();

        return $findings
            ->groupBy(fn (AnalysisFinding $finding): string => $this->enumValue($finding->run->module))
            ->flatMap(function (Collection $moduleFindings, string $module) use ($minFindings, $skewThreshold): Collection {
                return $this->signalsForModule($module, $moduleFindings, $minFindings, $skewThreshold);
            })
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return Collection<int, array<string, mixed>>
     */
    private function signalsForModule(
        string $module,
        Collection $findings,
        int $minFindings,
        float $skewThreshold,
    ): Collection {
        $signals = collect();

        foreach (array_keys(self::COHORT_DIMENSIONS) as $dimension) {
            $grouped = $findings
                ->filter(fn (AnalysisFinding $finding): bool => $this->cohortValue($finding->client, $dimension) !== null)
                ->groupBy(fn (AnalysisFinding $finding): string => (string) $this->cohortValue($finding->client, $dimension));

            foreach ($grouped as $value => $cohortFindings) {
                $baselineFindings = $findings
                    ->reject(fn (AnalysisFinding $finding): bool => $this->cohortValue($finding->client, $dimension) === $value)
                    ->values();

                if ($cohortFindings->count() < $minFindings || $baselineFindings->count() < $minFindings) {
                    continue;
                }

                $cohortHigh = $cohortFindings->filter(fn (AnalysisFinding $finding): bool => $this->isHighSeverity($finding))->count();
                $baselineHigh = $baselineFindings->filter(fn (AnalysisFinding $finding): bool => $this->isHighSeverity($finding))->count();
                $cohortRate = $cohortHigh / $cohortFindings->count();
                $baselineRate = $baselineHigh / $baselineFindings->count();
                $delta = $cohortRate - $baselineRate;

                if ($delta < $skewThreshold) {
                    continue;
                }

                $signals->push($this->signalPayload(
                    module: $module,
                    dimension: $dimension,
                    value: (string) $value,
                    cohortFindings: $cohortFindings,
                    baselineFindings: $baselineFindings,
                    cohortHigh: $cohortHigh,
                    baselineHigh: $baselineHigh,
                    cohortRate: $cohortRate,
                    baselineRate: $baselineRate,
                    delta: $delta,
                    minFindings: $minFindings,
                    skewThreshold: $skewThreshold,
                ));
            }
        }

        return $signals;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $cohortFindings
     * @param  Collection<int, AnalysisFinding>  $baselineFindings
     * @return array<string, mixed>
     */
    private function signalPayload(
        string $module,
        string $dimension,
        string $value,
        Collection $cohortFindings,
        Collection $baselineFindings,
        int $cohortHigh,
        int $baselineHigh,
        float $cohortRate,
        float $baselineRate,
        float $delta,
        int $minFindings,
        float $skewThreshold,
    ): array {
        $clientIds = $cohortFindings
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'type' => 'systematic_bias_skew',
            'signal_key' => $this->signalKey($module, $dimension, $value),
            'module' => $module,
            'dimension' => $dimension,
            'dimension_label' => self::COHORT_DIMENSIONS[$dimension],
            'value' => $value,
            'metric' => 'high_severity_rate',
            'cohort_count' => $cohortFindings->count(),
            'cohort_high_count' => $cohortHigh,
            'cohort_high_rate' => round($cohortRate, 4),
            'baseline_count' => $baselineFindings->count(),
            'baseline_high_count' => $baselineHigh,
            'baseline_high_rate' => round($baselineRate, 4),
            'rate_delta' => round($delta, 4),
            'min_findings' => $minFindings,
            'skew_threshold' => $skewThreshold,
            'client_ids' => $clientIds,
            'finding_ids' => $cohortFindings->pluck('id')->values()->all(),
            'baseline_finding_ids' => $baselineFindings->pluck('id')->take(20)->values()->all(),
            'sample_titles' => $cohortFindings->pluck('title')->take(5)->values()->all(),
            'prompt_versions' => $cohortFindings
                ->map(fn (AnalysisFinding $finding): mixed => $finding->run?->prompt_version)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function detectedCandidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'bias_monitor')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function createCandidate(array $signal, CarbonInterface $windowStart, CarbonInterface $windowEnd): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'bias_monitor',
                'signal_key' => $signal['signal_key'],
                'module' => $signal['module'],
                'dimension' => $signal['dimension'],
                'value' => $signal['value'],
                'metric' => $signal['metric'],
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => sprintf(
                'Bias monitor detected a systematic severity skew for %s analyses where %s is %s.',
                $signal['module'],
                $signal['dimension_label'],
                $signal['value'],
            ),
            'proposed_change' => [
                'action' => 'review_module_bias_or_calibration',
                'module' => $signal['module'],
                'dimension' => $signal['dimension'],
                'cohort_value' => $signal['value'],
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'module' => $signal['module'],
                'dimension' => $signal['dimension'],
                'cohort_value' => $signal['value'],
                'client_ids' => $signal['client_ids'],
                'prompt_versions' => $signal['prompt_versions'],
            ],
            'clients_affected' => count($signal['client_ids']),
            'magnitude' => ((float) $signal['rate_delta']) >= 0.75 ? 'high' : 'medium',
            'confidence' => min(0.95, 0.55 + (((float) $signal['rate_delta']) / 2)),
            'evidence' => $signal,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function sendAlert(LearningUpdate $candidate, array $signal): void
    {
        $recipients = $this->alertRecipients((array) $signal['client_ids']);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new BiasMonitorSignalNotification($candidate, $signal));
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return Collection<int, User>
     */
    private function alertRecipients(array $clientIds): Collection
    {
        $superAdmins = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();

        $advisors = $clientIds === []
            ? new EloquentCollection
            : ClientTeamMember::query()
                ->with('user')
                ->whereIn('client_id', $clientIds)
                ->get()
                ->pluck('user')
                ->filter(fn (mixed $user): bool => $user instanceof User && $user->user_type === User::TYPE_ADVISOR)
                ->values();

        return $superAdmins
            ->merge($advisors)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function signalKey(string $module, string $dimension, string $value): string
    {
        return hash('sha256', implode('|', [
            'bias_monitor',
            $module,
            $dimension,
            $value,
            'high_severity_rate',
        ]));
    }

    private function cohortValue(?Client $client, string $dimension): ?string
    {
        if (! $client instanceof Client) {
            return null;
        }

        $value = match ($dimension) {
            'entity_type' => $client->entity_type,
            'engagement_type' => $this->enumValue($client->engagement_type),
            'gst_registered' => $client->gst_registered ? 'registered' : 'not_registered',
            default => null,
        };

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function isHighSeverity(AnalysisFinding $finding): bool
    {
        return in_array($this->enumValue($finding->severity), [
            FindingSeverity::High->value,
            FindingSeverity::Critical->value,
        ], true);
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
