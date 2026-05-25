<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Models\ClientTeamMember;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Notifications\BiasMonitorSignalNotification;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class BiasCalibration
{
    public const LAYER_ID = BiasMonitor::LAYER_ID;

    public function __construct(
        private readonly BiasMonitor $monitor,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(
        int $minFindings = BiasMonitor::DEFAULT_MIN_FINDINGS,
        float $skewThreshold = BiasMonitor::DEFAULT_SKEW_THRESHOLD,
        int $windowDays = 30,
        ?CarbonInterface $windowEnd = null,
        bool $runMonitorFirst = true,
    ): LearningLayerRun {
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays(max(1, $windowDays));
        $upstreamRun = $runMonitorFirst
            ? $this->monitor->run($minFindings, $skewThreshold, $windowDays, $windowEnd)
            : null;
        $alerts = [];

        $this->context->apply('system', []);

        $run = DB::transaction(function () use (
            $windowStart,
            $windowEnd,
            $windowDays,
            $minFindings,
            $skewThreshold,
            $upstreamRun,
            &$alerts,
        ): LearningLayerRun {
            $candidatesCreated = 0;

            foreach ($this->biasMonitorCandidates($windowStart, $windowEnd) as $monitorCandidate) {
                $signalKey = (string) ($monitorCandidate->source['signal_key'] ?? '');

                if ($signalKey === '' || $this->detectedCandidateExists($signalKey)) {
                    continue;
                }

                $candidate = $this->createCandidate($monitorCandidate, $windowStart, $windowEnd);
                $alerts[] = [
                    'candidate' => $candidate,
                    'signal' => $this->alertSignal($candidate),
                ];
                $candidatesCreated++;
            }

            /** @var LearningLayerRun $run */
            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidatesCreated,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => max(1, $windowDays),
                    'min_findings' => max(1, $minFindings),
                    'skew_threshold' => max(0.0, min(1.0, $skewThreshold)),
                    'calibration_layer' => true,
                    'upstream_bias_monitor_run_id' => $upstreamRun?->id,
                    'governed_candidates_only' => true,
                    'automatic_application' => false,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record(
                action: 'ai.bias_calibration.ran',
                subject: $run,
                after: [
                    'layer_id' => self::LAYER_ID,
                    'candidates_created' => $candidatesCreated,
                    'upstream_bias_monitor_run_id' => $upstreamRun?->id,
                    'automatic_application' => false,
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
     * @return Collection<int, LearningUpdate>
     */
    private function biasMonitorCandidates(CarbonInterface $windowStart, CarbonInterface $windowEnd): Collection
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'bias_monitor')
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->oldest('created_at')
            ->get();
    }

    private function detectedCandidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'bias_calibration')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    private function createCandidate(
        LearningUpdate $monitorCandidate,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): LearningUpdate {
        $signal = is_array($monitorCandidate->evidence) ? $monitorCandidate->evidence : [];
        $signalKey = (string) ($monitorCandidate->source['signal_key'] ?? '');

        /** @var LearningUpdate $candidate */
        $candidate = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'bias_calibration',
                'signal_key' => $signalKey,
                'bias_monitor_update_id' => $monitorCandidate->id,
                'module' => $monitorCandidate->source['module'] ?? null,
                'dimension' => $monitorCandidate->source['dimension'] ?? null,
                'value' => $monitorCandidate->source['value'] ?? null,
                'metric' => $monitorCandidate->source['metric'] ?? null,
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => sprintf(
                'Bias calibration queued for %s after systematic skew evidence reached the review threshold.',
                (string) ($monitorCandidate->source['module'] ?? 'analysis'),
            ),
            'proposed_change' => [
                'action' => 'calibrate_bias_monitoring_or_analysis_prompt',
                'module' => $monitorCandidate->source['module'] ?? null,
                'dimension' => $monitorCandidate->source['dimension'] ?? null,
                'cohort_value' => $monitorCandidate->source['value'] ?? null,
                'bias_monitor_update_id' => $monitorCandidate->id,
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => [
                'module' => $monitorCandidate->source['module'] ?? null,
                'dimension' => $monitorCandidate->source['dimension'] ?? null,
                'cohort_value' => $monitorCandidate->source['value'] ?? null,
                'client_ids' => $signal['client_ids'] ?? [],
                'prompt_versions' => $signal['prompt_versions'] ?? [],
            ],
            'clients_affected' => $monitorCandidate->clients_affected,
            'magnitude' => $monitorCandidate->magnitude,
            'confidence' => min(0.98, ((float) $monitorCandidate->confidence) + 0.03),
            'evidence' => [
                'bias_monitor_update_id' => $monitorCandidate->id,
                'bias_monitor_signal' => $signal,
                'calibration_target' => 'human_reviewed_bias_prompt_or_threshold_adjustment',
                'guardrail' => 'candidate_only_no_automatic_correction',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => self::LAYER_ID,
            'source_type' => 'bias_calibration',
            'bias_monitor_update_id' => $monitorCandidate->id,
            'automatic_application' => false,
        ]);

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function alertSignal(LearningUpdate $candidate): array
    {
        $signal = $candidate->evidence['bias_monitor_signal'] ?? [];

        if (! is_array($signal)) {
            $signal = [];
        }

        return array_merge($signal, [
            'type' => 'bias_calibration_candidate',
            'signal_key' => $candidate->source['signal_key'] ?? '',
            'module' => $candidate->source['module'] ?? ($signal['module'] ?? 'analysis'),
            'dimension' => $candidate->source['dimension'] ?? ($signal['dimension'] ?? 'unknown'),
            'dimension_label' => $signal['dimension_label'] ?? 'Bias cohort',
            'value' => $candidate->source['value'] ?? ($signal['value'] ?? 'unknown'),
            'metric' => $candidate->source['metric'] ?? ($signal['metric'] ?? 'high_severity_rate'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function sendAlert(LearningUpdate $candidate, array $signal): void
    {
        $clientIds = collect($signal['client_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_scalar($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $recipients = $this->alertRecipients($clientIds);

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
}
