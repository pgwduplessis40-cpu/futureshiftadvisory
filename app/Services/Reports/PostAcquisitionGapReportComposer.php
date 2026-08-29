<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ProposalStatus;
use App\Enums\ReportType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\PlanSection;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Reports\Contracts\PostAcquisitionGapReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\PostAcquisitionGapReportInputs;
use App\Services\Reports\Data\PostAcquisitionPlanRequirement;
use App\Services\Reports\Data\ReportSectionDraft;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Owns the post-acquisition migration handoff and DD-gap report type.
 */
final class PostAcquisitionGapReportComposer implements PostAcquisitionGapReportComposition
{
    public function __construct(
        private readonly AcquisitionPlanRequirements $planRequirements,
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(PostAcquisitionMigration $migration, ?User $actor = null): Report
    {
        $inputs = $this->inputs($migration);

        return DB::transaction(function () use ($inputs, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'type' => ReportType::PostAcquisitionGap,
                'title' => ReportType::PostAcquisitionGap->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'post_acquisition_migration_id' => (string) $inputs->migration->getKey(),
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'buyer_client_id' => (string) $inputs->migration->buyer_client_id,
                    'business_plan_id' => $inputs->plan?->getKey() === null ? '' : (string) $inputs->plan->getKey(),
                    'dd_pv_baseline' => $inputs->migration->dd_pv_baseline,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections($report, $this->sections($inputs));
            $this->renderAndAuditAfterCommit($report, $actor, $inputs);

            return $report->refresh()->load('sections');
        });
    }

    private function inputs(PostAcquisitionMigration $migration): PostAcquisitionGapReportInputs
    {
        $migration->loadMissing([
            'advisoryClient',
            'buyerClient',
            'businessPlan.phases.sections',
            'ddReport',
            'engagement',
            'gapQuestionnaireResponse.answers.question',
            'proposal.feeCalculation',
        ]);

        $client = $migration->advisoryClient;
        $engagement = $migration->engagement;

        if (! $client instanceof Client || ! $engagement instanceof DdEngagement) {
            throw new InvalidArgumentException('Post-acquisition gap reports require a migration, advisory client, and DD engagement.');
        }

        $plan = $migration->businessPlan;
        $plan = $plan instanceof BusinessPlan ? $plan : null;
        $requirements = $this->requirementsFor($plan);

        return new PostAcquisitionGapReportInputs(
            migration: $migration,
            client: $client,
            engagement: $engagement,
            plan: $plan,
            risks: DdRiskRegisterItem::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->orderBy('rank')
                ->get(),
            integrationPlan: DdIntegrationPlanItem::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->orderBy('day')
                ->get(),
            completeRequirements: array_values(array_map(
                static fn (PostAcquisitionPlanRequirement $requirement): string => $requirement->label(),
                array_filter($requirements, static fn (PostAcquisitionPlanRequirement $requirement): bool => $requirement->complete),
            )),
            missingRequirements: array_values(array_map(
                static fn (PostAcquisitionPlanRequirement $requirement): string => $requirement->label(),
                array_filter($requirements, static fn (PostAcquisitionPlanRequirement $requirement): bool => ! $requirement->complete),
            )),
        );
    }

    /** @return list<PostAcquisitionPlanRequirement> */
    private function requirementsFor(?BusinessPlan $plan): array
    {
        if ($plan instanceof BusinessPlan) {
            /** @var array<string, list<array{phase_title: string, title: string, complete: bool}>> $payload */
            $payload = $this->planRequirements->payload($plan);
            $requirements = [];

            foreach ($payload as $phaseRequirements) {
                foreach ($phaseRequirements as $requirement) {
                    $requirements[] = new PostAcquisitionPlanRequirement(
                        phaseTitle: $requirement['phase_title'],
                        title: $requirement['title'],
                        complete: $requirement['complete'],
                    );
                }
            }

            return $requirements;
        }

        /** @var list<array{title: string, requirements: list<array{phase_title: string, title: string, complete: bool}>}> $template */
        $template = $this->planRequirements->templatePayload();
        $requirements = [];

        foreach ($template as $phase) {
            foreach ($phase['requirements'] as $requirement) {
                $requirements[] = new PostAcquisitionPlanRequirement(
                    phaseTitle: $requirement['phase_title'],
                    title: $requirement['title'],
                    complete: $requirement['complete'],
                );
            }
        }

        return $requirements;
    }

    /** @return list<ReportSectionDraft> */
    private function sections(PostAcquisitionGapReportInputs $inputs): array
    {
        return [
            $this->handoffSummarySection($inputs),
            $this->ddGapsSection($inputs),
            $this->businessPlanComparisonSection($inputs),
            $this->advisorActionsSection($inputs),
        ];
    }

    private function handoffSummarySection(PostAcquisitionGapReportInputs $inputs): ReportSectionDraft
    {
        $response = $inputs->migration->gapQuestionnaireResponse;
        $gapRemaining = $response instanceof QuestionnaireResponse && $response->submitted_at !== null
            ? 0
            : count(data_get($inputs->migration->metadata, 'gap_questions_remaining', []));
        $proposal = $inputs->migration->proposal;
        $proposalStatus = $proposal instanceof Proposal
            ? str_replace('_', ' ', $proposal->status->value)
            : 'not generated';
        $planStatus = $inputs->plan instanceof BusinessPlan
            ? str_replace('_', ' ', (string) $inputs->plan->status)
            : 'not prepared';
        $body = sprintf(
            "Target: %s.\nDD PV baseline: %s.\nMigrated DD documents: %d.\nPost-acquisition gap questionnaire: %s.\nAcquisition business plan: %s; %d plan requirement gap(s) remain.\nProposal status: %s.",
            $inputs->engagement->target_name,
            $this->money($inputs->migration->dd_pv_baseline),
            count($inputs->migration->migrated_document_ids),
            $gapRemaining === 0 ? 'submitted or fully prefilled' : "{$gapRemaining} client confirmation item(s) remain",
            $planStatus,
            count($inputs->missingRequirements),
            $proposalStatus,
        );

        return ReportSectionDraft::generated(
            key: 'post_acquisition_handoff_summary',
            title: 'Handoff summary',
            body: $body,
            sourceReference: 'post_acquisition_migration:'.$inputs->migration->getKey(),
            dataQualityNote: 'Data quality note: handoff summary combines DD migration metadata, client gap-questionnaire state, and linked acquisition-plan status.',
            metadata: [
                'post_acquisition_migration_id' => (string) $inputs->migration->getKey(),
                'business_plan_id' => $inputs->plan?->getKey() === null ? '' : (string) $inputs->plan->getKey(),
                'proposal_id' => $proposal?->getKey() === null ? '' : (string) $proposal->getKey(),
            ],
        );
    }

    private function ddGapsSection(PostAcquisitionGapReportInputs $inputs): ReportSectionDraft
    {
        $riskBody = $inputs->risks->isEmpty()
            ? 'No ranked DD risk gaps were available at handoff.'
            : $inputs->risks
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '#%d %s - %s. PV cost: %s. Indicative price adjustment: %s.',
                    $risk->rank,
                    str_replace('_', ' ', $risk->risk_level),
                    $risk->title,
                    $this->money($risk->pv_of_cost),
                    $this->money($risk->price_adjustment_nzd),
                ))
                ->implode("\n");
        $integrationBody = $inputs->integrationPlan->isEmpty()
            ? 'No 100-day integration actions were generated from DD yet.'
            : $inputs->integrationPlan
                ->map(fn (DdIntegrationPlanItem $item): string => sprintf(
                    'Day %d %s - %s (%s priority).',
                    $item->day,
                    $item->phase,
                    $item->action,
                    $item->priority,
                ))
                ->implode("\n");

        return ReportSectionDraft::generated(
            key: 'post_acquisition_dd_gaps',
            title: 'DD gaps requiring advisory attention',
            body: "Ranked DD gaps:\n{$riskBody}\n\nIntegration actions from DD:\n{$integrationBody}",
            sourceReference: 'dd_gap_sources:'.$inputs->migration->dd_engagement_id,
            dataQualityNote: 'Data quality note: DD gaps come from persisted DD risk-register rows and generated integration-plan actions.',
            metadata: [
                'risk_register_ids' => $inputs->risks->map(static fn (DdRiskRegisterItem $risk): string => (string) $risk->getKey())->values()->all(),
                'integration_plan_ids' => $inputs->integrationPlan->map(static fn (DdIntegrationPlanItem $item): string => (string) $item->getKey())->values()->all(),
            ],
        );
    }

    private function businessPlanComparisonSection(PostAcquisitionGapReportInputs $inputs): ReportSectionDraft
    {
        if (! $inputs->plan instanceof BusinessPlan) {
            return ReportSectionDraft::generated(
                key: 'post_acquisition_plan_comparison',
                title: 'DD to business-plan gap comparison',
                body: "No acquisition business plan is linked to this handoff yet.\nPending plan gaps:\n".implode("\n", $inputs->missingRequirements),
                sourceReference: 'post_acquisition_plan:none:'.$inputs->migration->getKey(),
                dataQualityNote: 'Data quality note: this comparison is template-only until the DD acquisition business plan is populated.',
                metadata: [
                    'missing_requirements' => $inputs->missingRequirements,
                ],
            );
        }

        $uncoveredRisks = $this->uncoveredRiskTitles($inputs->risks, $inputs->plan);
        $body = sprintf(
            "Business plan status: %s.\nCompleted plan requirements:\n%s\n\nPending plan requirements:\n%s\n\nDD risks not explicitly referenced in the plan by risk title:\n%s",
            str_replace('_', ' ', (string) $inputs->plan->status),
            $inputs->completeRequirements === [] ? 'None yet.' : implode("\n", $inputs->completeRequirements),
            $inputs->missingRequirements === [] ? 'None.' : implode("\n", $inputs->missingRequirements),
            $uncoveredRisks === [] ? 'None detected by title match.' : implode("\n", $uncoveredRisks),
        );

        return ReportSectionDraft::generated(
            key: 'post_acquisition_plan_comparison',
            title: 'DD to business-plan gap comparison',
            body: $body,
            sourceReference: 'business_plan:'.$inputs->plan->getKey(),
            dataQualityNote: 'Data quality note: plan comparison checks the DD acquisition-plan requirement template and whether ranked DD risk titles appear in completed plan sections.',
            metadata: [
                'business_plan_id' => (string) $inputs->plan->getKey(),
                'missing_requirements' => $inputs->missingRequirements,
                'complete_requirements' => $inputs->completeRequirements,
                'uncovered_risk_titles' => $uncoveredRisks,
            ],
        );
    }

    private function advisorActionsSection(PostAcquisitionGapReportInputs $inputs): ReportSectionDraft
    {
        $actions = [];
        $response = $inputs->migration->gapQuestionnaireResponse;
        $proposal = $inputs->migration->proposal;
        $proposalStatus = $proposal instanceof Proposal ? $proposal->status : null;

        if (! $response instanceof QuestionnaireResponse || $response->submitted_at === null) {
            $actions[] = 'Ask the client to complete the post-acquisition gap questionnaire and confirm the DD-prefilled answers.';
        }

        if (! $inputs->plan instanceof BusinessPlan) {
            $actions[] = 'Prepare or link the DD acquisition business plan before finalising post-acquisition advice.';
        } elseif (! $inputs->planIsComplete()) {
            $actions[] = 'Resolve remaining plan gaps: '.implode('; ', $inputs->missingRequirements).'.';
        }

        if ($proposalStatus === ProposalStatus::Draft) {
            $actions[] = 'Review and release the generated post-acquisition proposal so the client can sign off.';
        } elseif ($proposalStatus === null) {
            $actions[] = 'Generate a post-acquisition advisory proposal once scope and gaps are confirmed.';
        }

        if ($actions === []) {
            $actions[] = 'Proceed with advisor-led post-acquisition advisory scoping and first 100-day implementation planning.';
        }

        return ReportSectionDraft::generated(
            key: 'post_acquisition_advisor_actions',
            title: 'Advisor action list',
            body: implode("\n", $actions),
            sourceReference: 'post_acquisition_actions:'.$inputs->migration->getKey(),
            dataQualityNote: 'Data quality note: action list reflects current persisted workflow state and should be reviewed by the advisor before client advice is issued.',
            metadata: [
                'actions' => $actions,
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return list<string>
     */
    private function uncoveredRiskTitles(Collection $risks, BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');
        $planText = Str::lower($plan->phases
            ->flatMap(static fn ($phase) => $phase->sections)
            ->map(static fn (PlanSection $section): string => $section->title."\n".$section->body)
            ->implode("\n"));

        return $risks
            ->filter(static function (DdRiskRegisterItem $risk) use ($planText): bool {
                $title = Str::lower(trim($risk->title));

                return $title !== '' && ! str_contains($planText, $title);
            })
            ->pluck('title')
            ->map(static fn (mixed $title): string => (string) $title)
            ->values()
            ->all();
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }

    /** @param list<ReportSectionDraft> $sections */
    private function persistSections(Report $report, array $sections): void
    {
        foreach ($sections as $position => $section) {
            ReportSection::query()->create([
                ...$section->toAttributes(),
                'report_id' => $report->getKey(),
                'client_id' => $report->client_id,
                'position' => $position + 1,
            ]);
        }
    }

    private function renderAndAuditAfterCommit(Report $report, ?User $actor, PostAcquisitionGapReportInputs $inputs): void
    {
        $callback = function () use ($report, $actor, $inputs): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record(
                'post_acquisition.gap_report_generated',
                subject: $report,
                actor: $actor,
                after: [
                    'post_acquisition_migration_id' => (string) $inputs->migration->getKey(),
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'sections' => $report->sections()->count(),
                    'missing_plan_requirements' => $inputs->missingRequirements,
                    'pdf_path' => (string) ($report->pdf_path ?? ''),
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
