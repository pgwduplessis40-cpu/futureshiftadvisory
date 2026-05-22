<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AnalysisFeedback;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FeedbackLearningLayer
{
    public const LAYER_ID = 11;

    public const DEFAULT_THRESHOLD = 3;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(int $threshold = self::DEFAULT_THRESHOLD, int $windowDays = 30, ?CarbonInterface $windowEnd = null): LearningLayerRun
    {
        $threshold = max(1, $threshold);
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        return DB::transaction(function () use ($threshold, $windowDays, $windowStart, $windowEnd): LearningLayerRun {
            $candidatesCreated = 0;

            foreach ($this->correctionsByModule($windowStart, $windowEnd) as $module => $feedbackRows) {
                if ($feedbackRows->count() < $threshold) {
                    continue;
                }

                if ($this->detectedCandidateExists((string) $module)) {
                    continue;
                }

                $this->createCandidate((string) $module, $feedbackRows, $threshold, $windowStart, $windowEnd);
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
                    'threshold' => $threshold,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record(
                action: 'analysis_feedback_learning_layer.ran',
                subject: $run,
                after: [
                    'layer_id' => self::LAYER_ID,
                    'candidates_created' => $candidatesCreated,
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'threshold' => $threshold,
                ],
            );

            return $run;
        });
    }

    /**
     * @return Collection<string, Collection<int, AnalysisFeedback>>
     */
    private function correctionsByModule(CarbonInterface $windowStart, CarbonInterface $windowEnd): Collection
    {
        return AnalysisFeedback::query()
            ->with(['finding.run'])
            ->where('decision', AnalysisFeedback::DECISION_CORRECT)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->oldest('created_at')
            ->get()
            ->filter(fn (AnalysisFeedback $feedback): bool => $feedback->finding?->run !== null)
            ->groupBy(fn (AnalysisFeedback $feedback): string => $feedback->finding->run->module->value);
    }

    private function detectedCandidateExists(string $module): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'analysis_feedback_learning_layer')
            ->where('source->module', $module)
            ->exists();
    }

    /**
     * @param  Collection<int, AnalysisFeedback>  $feedbackRows
     */
    private function createCandidate(
        string $module,
        Collection $feedbackRows,
        int $threshold,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): void {
        $findings = $feedbackRows
            ->map(fn (AnalysisFeedback $feedback) => $feedback->finding)
            ->filter();
        $runs = $findings
            ->map(fn ($finding) => $finding->run)
            ->filter();
        $clients = $findings
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->values();

        LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'analysis_feedback_learning_layer',
                'module' => $module,
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => "Advisor corrections reached the governed-learning threshold for the {$module} analysis module.",
            'proposed_change' => [
                'action' => 'review_module_prompt_or_finding_mapping',
                'module' => $module,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'module' => $module,
                'prompt_versions' => $runs
                    ->pluck('prompt_version')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'clients_affected' => $clients->count(),
            'magnitude' => $feedbackRows->count() >= ($threshold * 2) ? 'medium' : 'low',
            'confidence' => 0.65,
            'evidence' => [
                'threshold' => $threshold,
                'corrections_count' => $feedbackRows->count(),
                'feedback_ids' => $feedbackRows->pluck('id')->values()->all(),
                'finding_ids' => $findings->pluck('id')->values()->all(),
                'run_ids' => $runs->pluck('id')->unique()->values()->all(),
                'correction_samples' => $feedbackRows
                    ->pluck('corrected_body')
                    ->filter()
                    ->take(3)
                    ->values()
                    ->all(),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
