<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\SuccessionPlan;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Contracts\SuccessionValueGapReportComposition;
use App\Services\Reports\Data\ReportSectionDraft;
use App\Services\Reports\Data\SuccessionOption;
use App\Services\Reports\Data\SuccessionValueGapInputs;
use App\Services\Reports\Data\SuccessionValueGapMetrics;
use Illuminate\Support\Facades\DB;

/**
 * Owns the advisor-reviewed standalone succession value-gap report type.
 */
final class SuccessionValueGapReportComposer implements SuccessionValueGapReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(Client $client, ?User $actor = null): Report
    {
        $inputs = $this->inputs($client);

        return DB::transaction(function () use ($inputs, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'type' => ReportType::SuccessionValueGap,
                'title' => ReportType::SuccessionValueGap->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_report_layer_composition',
                    'business_valuation_id' => $inputs->valuation instanceof BusinessValuation ? (string) $inputs->valuation->getKey() : '',
                    'succession_plan_id' => $inputs->successionPlan instanceof SuccessionPlan ? (string) $inputs->successionPlan->getKey() : '',
                    'advisor_review_required' => true,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'pending_review',
            ]);

            $this->persistSections($report, $inputs->client, $this->sections($inputs));
            $this->renderAndAuditAfterCommit($report, $actor, $inputs);

            return $report->refresh()->load(['client', 'sections']);
        });
    }

    private function inputs(Client $client): SuccessionValueGapInputs
    {
        $valuation = BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
        $successionPlan = SuccessionPlan::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
        $improvements = ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->orderBy('rank')
            ->latest()
            ->limit(6)
            ->get()
            ->sortBy('rank')
            ->values();

        return new SuccessionValueGapInputs($client, $valuation, $successionPlan, $improvements);
    }

    /** @return list<ReportSectionDraft> */
    private function sections(SuccessionValueGapInputs $inputs): array
    {
        $metrics = $this->metrics($inputs);

        return [
            $this->gapSummarySection($inputs, $metrics),
            $this->readinessSection($inputs),
            $this->improvementBridgeSection($inputs, $metrics),
            $this->optionsSection($inputs),
        ];
    }

    private function gapSummarySection(SuccessionValueGapInputs $inputs, SuccessionValueGapMetrics $metrics): ReportSectionDraft
    {
        $body = sprintf(
            "Current valuation midpoint: %s.\nOwner target exit PV: %s.\nCurrent value gap: %s.\nPV of ranked improvements: %s.\nProjected value after improvements: %s.\nRemaining gap after improvements: %s.\n\nInterpretation: this report connects succession readiness to enterprise value so the owner can see which improvements most directly close the exit gap.",
            $this->money($metrics->currentValueNzd),
            $this->money($metrics->targetExitPvNzd),
            $this->money($metrics->currentGapNzd),
            $this->money($metrics->improvementPvNzd),
            $this->money($metrics->projectedValueNzd),
            $this->money($metrics->remainingGapNzd),
        );

        return ReportSectionDraft::generated(
            key: 'succession_value_gap',
            title: 'Value-gap analysis',
            body: $body,
            sourceReference: $inputs->successionPlan instanceof SuccessionPlan
                ? 'succession_plan:'.$inputs->successionPlan->getKey()
                : 'client:'.$inputs->client->getKey().':succession_value_gap',
            dataQualityNote: 'Data quality note: value gap compares latest valuation, latest succession target-exit PV, and ranked improvement PV rows.',
            metadata: [
                'business_valuation_id' => $inputs->valuation instanceof BusinessValuation ? (string) $inputs->valuation->getKey() : '',
                'succession_plan_id' => $inputs->successionPlan instanceof SuccessionPlan ? (string) $inputs->successionPlan->getKey() : '',
                'current_value_available' => $metrics->currentValueNzd !== null,
                'current_value_nzd' => $metrics->currentValueNzd ?? 0.0,
                'target_exit_pv_available' => $metrics->targetExitPvNzd !== null,
                'target_exit_pv_nzd' => $metrics->targetExitPvNzd ?? 0.0,
                'current_gap_available' => $metrics->currentGapNzd !== null,
                'current_gap_nzd' => $metrics->currentGapNzd ?? 0.0,
                'improvement_pv_nzd' => $metrics->improvementPvNzd,
                'projected_value_available' => $metrics->projectedValueNzd !== null,
                'projected_value_nzd' => $metrics->projectedValueNzd ?? 0.0,
                'remaining_gap_available' => $metrics->remainingGapNzd !== null,
                'remaining_gap_nzd' => $metrics->remainingGapNzd ?? 0.0,
            ],
        );
    }

    private function readinessSection(SuccessionValueGapInputs $inputs): ReportSectionDraft
    {
        $successionPlan = $inputs->successionPlan;

        if (! $successionPlan instanceof SuccessionPlan) {
            return ReportSectionDraft::generated(
                key: 'exit_readiness',
                title: 'Exit readiness',
                body: 'No succession plan is available yet. Run the succession planner before relying on exit-readiness scoring.',
                sourceReference: 'succession_plan:none:'.$inputs->client->getKey(),
                dataQualityNote: 'Data quality note: succession readiness is pending a succession-plan run.',
                metadata: ['succession_plan_available' => false],
            );
        }

        $actions = $this->ownerDependencyActions($successionPlan);
        $constraint = $successionPlan->owner_readiness_is_primary_constraint
            ? 'Owner readiness is the primary constraint.'
            : 'Owner readiness is not currently marked as the primary constraint.';
        $body = sprintf(
            "Exit readiness score: %d/10.\n%s\nOwner dependency actions:\n%s",
            $successionPlan->exit_readiness_score,
            $constraint,
            $actions === [] ? 'No owner dependency actions recorded.' : implode("\n", $actions),
        );

        return ReportSectionDraft::generated(
            key: 'exit_readiness',
            title: 'Exit readiness',
            body: $body,
            sourceReference: 'succession_plan:'.$successionPlan->getKey().':readiness',
            dataQualityNote: 'Data quality note: readiness comes from the persisted succession plan and still requires advisor review.',
            metadata: [
                'succession_plan_id' => (string) $successionPlan->getKey(),
                'exit_readiness_score' => $successionPlan->exit_readiness_score,
                'owner_readiness_is_primary_constraint' => $successionPlan->owner_readiness_is_primary_constraint,
                'owner_dependency_action_count' => count($actions),
            ],
        );
    }

    private function improvementBridgeSection(SuccessionValueGapInputs $inputs, SuccessionValueGapMetrics $metrics): ReportSectionDraft
    {
        $rows = $inputs->improvements
            ->map(fn (ImprovementOpportunity $opportunity): string => sprintf(
                '#%d %s: %s PV impact over %d year(s).',
                $opportunity->rank,
                $opportunity->title,
                $this->money($opportunity->pv_of_impact),
                $opportunity->duration_years,
            ))
            ->implode("\n");

        return ReportSectionDraft::generated(
            key: 'improvement_value_bridge',
            title: 'Improvement value bridge',
            body: sprintf(
                "Improvement PV included in bridge: %s.\n\nRanked improvement bridge:\n%s",
                $this->money($metrics->improvementPvNzd),
                $rows !== '' ? $rows : 'No ranked improvement opportunities are available yet.',
            ),
            sourceReference: 'improvement_opportunities:'.$inputs->client->getKey(),
            dataQualityNote: 'Data quality note: improvement PV is the sum of active, ranked improvement opportunities and should be challenged before being treated as a valuation conclusion.',
            metadata: [
                'improvement_opportunity_ids' => $inputs->improvements
                    ->map(fn (ImprovementOpportunity $opportunity): string => (string) $opportunity->getKey())
                    ->all(),
                'improvement_count' => $inputs->improvements->count(),
                'improvement_pv_nzd' => $metrics->improvementPvNzd,
            ],
        );
    }

    private function optionsSection(SuccessionValueGapInputs $inputs): ReportSectionDraft
    {
        $options = $inputs->successionPlan instanceof SuccessionPlan
            ? $this->options($inputs->successionPlan)
            : [];

        return ReportSectionDraft::generated(
            key: 'succession_options',
            title: 'Exit option comparison',
            body: $options === []
                ? 'No succession options are recorded yet.'
                : implode("\n", array_map(fn (SuccessionOption $option): string => $option->line(), $options)),
            sourceReference: $inputs->successionPlan instanceof SuccessionPlan
                ? 'succession_plan:'.$inputs->successionPlan->getKey().':options'
                : 'succession_plan:none:'.$inputs->client->getKey(),
            dataQualityNote: 'Data quality note: option comparison is sourced from the latest succession planner output.',
            metadata: [
                'succession_plan_id' => $inputs->successionPlan instanceof SuccessionPlan ? (string) $inputs->successionPlan->getKey() : '',
                'option_count' => count($options),
                'option_names' => array_map(fn (SuccessionOption $option): string => $option->name, $options),
                'option_fit_scores' => array_map(fn (SuccessionOption $option): string => $option->fitScore, $options),
            ],
        );
    }

    private function metrics(SuccessionValueGapInputs $inputs): SuccessionValueGapMetrics
    {
        $currentValueNzd = $this->rounded($inputs->valuation?->reconciled_mid);
        $targetExitPvNzd = $this->rounded($inputs->successionPlan?->target_exit_pv);
        $improvementPvNzd = 0.0;

        foreach ($inputs->improvements as $improvement) {
            $improvementPvNzd += $improvement->pv_of_impact;
        }

        $improvementPvNzd = round($improvementPvNzd, 2);
        $projectedValueNzd = $currentValueNzd === null ? null : round($currentValueNzd + $improvementPvNzd, 2);

        return new SuccessionValueGapMetrics(
            currentValueNzd: $currentValueNzd,
            targetExitPvNzd: $targetExitPvNzd,
            currentGapNzd: $currentValueNzd === null || $targetExitPvNzd === null ? null : round($targetExitPvNzd - $currentValueNzd, 2),
            improvementPvNzd: $improvementPvNzd,
            projectedValueNzd: $projectedValueNzd,
            remainingGapNzd: $projectedValueNzd === null || $targetExitPvNzd === null ? null : round($targetExitPvNzd - $projectedValueNzd, 2),
        );
    }

    /** @return list<string> */
    private function ownerDependencyActions(SuccessionPlan $successionPlan): array
    {
        /** @var mixed $plan Malformed historic JSON must not break report composition. */
        $plan = $successionPlan->owner_dependency_plan;
        $actions = is_array($plan) ? ($plan['actions'] ?? []) : [];

        if (! is_array($actions)) {
            return [];
        }

        $lines = [];

        foreach ($actions as $action) {
            if (is_scalar($action)) {
                $line = trim((string) $action);

                if ($line !== '') {
                    $lines[] = $line;
                }

                continue;
            }

            if (is_array($action)) {
                $lines[] = json_encode($action, JSON_THROW_ON_ERROR);
            }
        }

        return $lines;
    }

    /** @return list<SuccessionOption> */
    private function options(SuccessionPlan $successionPlan): array
    {
        /** @var mixed $source Malformed historic JSON must not break report composition. */
        $source = $successionPlan->options;

        if (! is_array($source)) {
            return [];
        }

        $options = [];

        foreach ($source as $option) {
            if (! is_array($option)) {
                continue;
            }

            $options[] = new SuccessionOption(
                name: $this->text($option['name'] ?? null, 'Succession option'),
                fitScore: $this->text($option['fit_score'] ?? $option['score'] ?? null, 'n/a'),
                rationale: $this->text($option['rationale'] ?? null, 'Advisor review required.'),
            );
        }

        return $options;
    }

    private function rounded(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function text(mixed $value, string $fallback): string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : $fallback;
    }

    /** @param list<ReportSectionDraft> $sections */
    private function persistSections(Report $report, Client $client, array $sections): void
    {
        foreach ($sections as $position => $section) {
            ReportSection::query()->create([
                ...$section->toAttributes(),
                'report_id' => $report->getKey(),
                'client_id' => $client->getKey(),
                'position' => $position + 1,
            ]);
        }
    }

    private function money(?float $value): string
    {
        return $value === null ? 'n/a' : 'NZD '.number_format($value, 0);
    }

    private function renderAndAuditAfterCommit(Report $report, ?User $actor, SuccessionValueGapInputs $inputs): void
    {
        $callback = function () use ($report, $actor, $inputs): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record(
                'succession.value_gap_report_generated',
                subject: $report,
                actor: $actor,
                after: [
                    'client_id' => (string) $inputs->client->getKey(),
                    'business_valuation_id' => $inputs->valuation instanceof BusinessValuation ? (string) $inputs->valuation->getKey() : '',
                    'succession_plan_id' => $inputs->successionPlan instanceof SuccessionPlan ? (string) $inputs->successionPlan->getKey() : '',
                    'review_status' => 'pending_review',
                ],
            );
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
