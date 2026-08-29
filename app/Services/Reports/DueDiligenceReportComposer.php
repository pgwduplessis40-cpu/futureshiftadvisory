<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdDisclaimer;
use App\Services\Reports\Contracts\DueDiligenceReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\DdMoneyRange;
use App\Services\Reports\Data\DueDiligenceReportInputs;
use App\Services\Reports\Data\ReportSectionDraft;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the advisor-reviewed due-diligence report type and its section contract.
 */
final class DueDiligenceReportComposer implements DueDiligenceReportComposition
{
    public function __construct(
        private readonly DueDiligenceReportSupport $support,
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(DdEngagement $engagement, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement);

        return DB::transaction(function () use ($inputs, $actor): Report {
            $inputs->engagement->forceFill([
                'recommendation' => $inputs->recommendation->decision,
            ])->save();

            $report = Report::query()->create([
                'client_id' => $inputs->engagement->client_id,
                'type' => ReportType::DueDiligence,
                'title' => ReportType::DueDiligence->label().' - '.$inputs->engagement->target_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'target_name' => $inputs->engagement->target_name,
                    'recommendation' => $inputs->recommendation->decision,
                    'recommendation_rationale' => $inputs->recommendation->rationale,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'pending_review',
            ]);

            $this->persistSections($report, $this->sections($inputs));
            $this->renderAndAuditAfterCommit($report, $actor, $inputs);

            return $report->refresh()->load('sections');
        });
    }

    private function inputs(DdEngagement $engagement): DueDiligenceReportInputs
    {
        $engagement->loadMissing('client');
        $findings = $this->support->findings($engagement);
        $valuation = $this->support->latestValuation($engagement);
        $risks = $this->support->refreshRiskRegister($engagement, $findings, $valuation);
        $integrationPlan = $this->support->refreshIntegrationPlan($engagement, $risks);

        return new DueDiligenceReportInputs(
            engagement: $engagement,
            findings: $findings,
            valuation: $valuation,
            risks: $risks,
            integrationPlan: $integrationPlan,
            recommendation: $this->support->recommendation($risks, $valuation),
        );
    }

    /** @return list<ReportSectionDraft> */
    private function sections(DueDiligenceReportInputs $inputs): array
    {
        return [
            $this->executiveSummarySection($inputs),
            $this->valuationSection($inputs),
            $this->purchasePriceRangeSection($inputs),
            $this->workstreamFindingsSection($inputs),
            $this->riskRegisterSection($inputs),
            $this->priceAdjustmentSection($inputs),
            $this->integrationPlanSection($inputs),
            $this->buyerReadinessSection($inputs),
            $this->recommendationSection($inputs),
            ReportSectionDraft::generated(
                key: 'dd_liability_disclaimer',
                title: 'Liability disclaimer',
                body: DdDisclaimer::STANDARD,
                sourceReference: 'dd_disclaimer:'.$inputs->engagement->getKey(),
                dataQualityNote: 'Data quality note: this disclaimer is included on every due diligence output.',
            ),
        ];
    }

    private function executiveSummarySection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $materialRisks = $inputs->risks
            ->whereIn('risk_level', [DdRiskRegisterItem::LEVEL_DEAL_KILLER, DdRiskRegisterItem::LEVEL_MAJOR])
            ->count();
        $body = sprintf(
            "Target: %s.\nRecommendation: %s.\nFindings reviewed: %d.\nMaterial DD risks: %d.\nValuation midpoint: %s.\nRationale: %s",
            $inputs->engagement->target_name,
            ucfirst(str_replace('_', ' ', $inputs->recommendation->decision)),
            $inputs->findings->count(),
            $materialRisks,
            $this->money($this->support->valuationMidpoint($inputs->valuation)),
            $inputs->recommendation->rationale,
        );

        return $this->sectionWithDocumentSupport(
            key: 'dd_executive_summary',
            title: 'Executive summary',
            body: $body,
            sourceReference: 'dd_engagement:'.$inputs->engagement->getKey(),
            findings: $inputs->findings,
            dataQualityNote: 'Data quality note: DD summary is assembled from completed workstream findings, valuation rows, and risk PV rows.',
            metadata: [
                'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                'recommendation' => $inputs->recommendation->decision,
                'finding_count' => $inputs->findings->count(),
                'material_risk_count' => $materialRisks,
            ],
        );
    }

    private function valuationSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $valuation = $inputs->valuation;

        if (! $valuation instanceof DdValuation) {
            return ReportSectionDraft::generated(
                key: 'dd_valuation',
                title: 'Valuation',
                body: 'No DD valuation has been generated yet.',
                sourceReference: 'dd_valuation:none:'.$inputs->engagement->getKey(),
                dataQualityNote: 'Data quality note: DD valuation is pending.',
                metadata: ['dd_valuation_available' => false],
            );
        }

        $valuation->loadMissing('businessValuation', 'pvCalculation');
        $dcf = $this->support->valuationRange($valuation, 'dcf_value');
        $reconciled = $this->support->valuationRange($valuation, 'reconciled');
        $sde = $this->support->valuationRange($valuation, 'sde_value');
        $ebitda = $this->support->valuationRange($valuation, 'ebitda_value');
        /** @var mixed $buyerPosition */
        $buyerPosition = data_get($valuation->buyer_position, 'position');
        $position = is_scalar($buyerPosition) ? (string) $buyerPosition : 'not recorded';

        return ReportSectionDraft::generated(
            key: 'dd_valuation',
            title: 'Valuation',
            body: sprintf(
                "Primary DCF/PV value: %s midpoint, with a %s to %s DCF range.\nMarket-multiple cross-checks: SDE %s; EBITDA %s.\nReconciled standalone NZD range: %s low, %s midpoint, %s high.%s\nFX: %s to NZD at %s, timestamp %s.\nBuyer position: %s.",
                $this->money($dcf?->mid),
                $this->money($dcf?->low),
                $this->money($dcf?->high),
                $this->rangeMidpoint($sde),
                $this->rangeMidpoint($ebitda),
                $this->money($reconciled?->low),
                $this->money($reconciled?->mid),
                $this->money($reconciled?->high),
                $this->support->valueWalkNote($valuation),
                $valuation->source_currency,
                number_format($valuation->source_to_nzd_rate, 4),
                $valuation->rate_timestamp?->toDateTimeString() ?? 'n/a',
                str_replace('_', ' ', $position),
            ),
            sourceReference: 'dd_valuation:'.$valuation->getKey(),
            dataQualityNote: 'Data quality note: DD valuation reuses the persisted business valuation and PV calculation, with FX normalisation where required.',
            metadata: [
                'dd_valuation_available' => true,
                'dd_valuation_id' => (string) $valuation->getKey(),
                'business_valuation_id' => (string) ($valuation->business_valuation_id ?? ''),
                'pv_calculation_id' => (string) ($valuation->pv_calculation_id ?? ''),
                'buyer_position' => $position,
                'dcf_mid_nzd' => $dcf instanceof DdMoneyRange ? $dcf->mid : 0.0,
                'dcf_available' => $dcf instanceof DdMoneyRange,
                'reconciled_mid_nzd' => $reconciled instanceof DdMoneyRange ? $reconciled->mid : 0.0,
                'reconciled_available' => $reconciled instanceof DdMoneyRange,
            ],
        );
    }

    private function purchasePriceRangeSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $valuation = $inputs->valuation;

        if (! $valuation instanceof DdValuation) {
            return ReportSectionDraft::generated(
                key: 'dd_purchase_price_range',
                title: 'Estimated purchase-price range',
                body: 'No purchase-price range can be generated until the DD valuation is available.',
                sourceReference: 'dd_purchase_price_range:none:'.$inputs->engagement->getKey(),
                dataQualityNote: 'Data quality note: purchase-price range is pending valuation inputs.',
                metadata: ['purchase_price_range_available' => false],
            );
        }

        $valuation->loadMissing('businessValuation');
        $dcf = $this->support->valuationRange($valuation, 'dcf_value') ?? $this->support->valuationRange($valuation, 'reconciled');
        $market = $this->support->marketMultipleRange($valuation);
        $precedent = $this->support->precedentTransactionRange($inputs->engagement, $valuation);
        /** @var mixed $targetDetails */
        $targetDetails = $inputs->engagement->target_details;
        /** @var mixed $buyerPosition */
        $buyerPosition = $valuation->buyer_position;
        $dealStructure = $this->support->adjustmentTotal(
            data_get($targetDetails, 'deal_structure_adjustments', []) ?: data_get($buyerPosition, 'deal_structure_adjustments', []),
        );
        $synergy = $this->support->adjustmentTotal(
            data_get($targetDetails, 'synergy_adjustments', []) ?: data_get($buyerPosition, 'synergy_adjustments', []),
        );
        $risk = round((float) $inputs->risks->sum('price_adjustment_nzd'), 2);
        $purchase = $dcf?->adjusted($dealStructure + $synergy - $risk);

        return ReportSectionDraft::generated(
            key: 'dd_purchase_price_range',
            title: 'Estimated purchase-price range',
            body: sprintf(
                "Primary basis: Discounted Cash Flow (DCF), %s low, %s midpoint, %s high.%s\nCross-checks: market multiples indicate %s low, %s midpoint, %s high; precedent transactions indicate %s low, %s midpoint, %s high.\nAdjustments applied to the DCF range: deal structure %s, synergies %s, due-diligence risk %s.\nEstimated purchase-price range for advisor review: %s low, %s midpoint, %s high.",
                $this->money($dcf?->low),
                $this->money($dcf?->mid),
                $this->money($dcf?->high),
                $this->support->valueWalkNote($valuation),
                $this->money($market?->low),
                $this->money($market?->mid),
                $this->money($market?->high),
                $this->money($precedent?->low),
                $this->money($precedent?->mid),
                $this->money($precedent?->high),
                $this->money($dealStructure),
                $this->money($synergy),
                $this->money($risk),
                $this->money($purchase?->low),
                $this->money($purchase?->mid),
                $this->money($purchase?->high),
            ),
            sourceReference: 'dd_purchase_price_range:'.$valuation->getKey(),
            dataQualityNote: 'Data quality note: range is advisor-facing and combines DCF valuation, market and precedent cross-checks, deal structure, synergies, and DD risk adjustments.',
            metadata: [
                'primary_method' => 'dcf',
                'purchase_price_range_available' => $purchase instanceof DdMoneyRange,
                'dcf_mid_nzd' => $dcf instanceof DdMoneyRange ? $dcf->mid : 0.0,
                'market_multiple_mid_nzd' => $market instanceof DdMoneyRange ? $market->mid : 0.0,
                'precedent_transaction_mid_nzd' => $precedent instanceof DdMoneyRange ? $precedent->mid : 0.0,
                'deal_structure_adjustment_nzd' => $dealStructure,
                'synergy_adjustment_nzd' => $synergy,
                'due_diligence_risk_adjustment_nzd' => $risk,
                'purchase_price_mid_nzd' => $purchase instanceof DdMoneyRange ? $purchase->mid : 0.0,
            ],
        );
    }

    private function workstreamFindingsSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $workstreamsByRun = DdWorkstream::query()
            ->where('dd_engagement_id', $inputs->engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->pluck('workstream', 'analysis_run_id');
        $body = $inputs->findings->isEmpty()
            ? 'No completed DD workstream findings are available yet.'
            : $inputs->findings
                ->map(fn (AnalysisFinding $finding): string => sprintf(
                    '%s - %s: %s',
                    str((string) ($workstreamsByRun[$finding->analysis_run_id] ?? 'general'))->replace('_', ' ')->title(),
                    $finding->title,
                    $finding->body,
                ))
                ->implode("\n\n");

        return $this->sectionWithDocumentSupport(
            key: 'dd_workstream_findings',
            title: 'Workstream findings',
            body: $body,
            sourceReference: 'dd_workstreams:'.$inputs->engagement->getKey(),
            findings: $inputs->findings,
            dataQualityNote: 'Data quality note: findings come from completed DD workstreams on the shared analysis spine.',
            metadata: [
                'finding_count' => $inputs->findings->count(),
                'finding_ids' => $inputs->findings->map(fn (AnalysisFinding $finding): string => (string) $finding->getKey())->all(),
            ],
        );
    }

    private function riskRegisterSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $body = $inputs->risks->isEmpty()
            ? 'No DD risks have been ranked yet.'
            : $inputs->risks
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '#%d %s - %s (%s PV cost)',
                    $risk->rank,
                    str_replace('_', ' ', $risk->risk_level),
                    $risk->title,
                    $this->money($risk->pv_of_cost),
                ))
                ->implode("\n");

        return ReportSectionDraft::generated(
            key: 'dd_risk_register',
            title: 'Risk register',
            body: $body,
            sourceReference: 'dd_risk_register:'.$inputs->engagement->getKey(),
            dataQualityNote: 'Data quality note: risk PV ranking uses persisted DD findings and the shared risk-cost PV engine.',
            metadata: [
                'risk_count' => $inputs->risks->count(),
                'risk_register_ids' => $inputs->risks->map(fn (DdRiskRegisterItem $risk): string => (string) $risk->getKey())->all(),
                'risk_levels' => $inputs->risks->map(fn (DdRiskRegisterItem $risk): string => $risk->risk_level)->values()->all(),
            ],
        );
    }

    private function priceAdjustmentSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $adjustments = $inputs->risks
            ->filter(fn (DdRiskRegisterItem $risk): bool => $risk->price_adjustment_nzd > 0)
            ->values();
        $total = round((float) $adjustments->sum('price_adjustment_nzd'), 2);
        $body = $adjustments->isEmpty()
            ? 'No price adjustment is indicated by the current DD risk register.'
            : $adjustments
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '%s: %s adjustment for %s risk.',
                    $risk->title,
                    $this->money($risk->price_adjustment_nzd),
                    str_replace('_', ' ', $risk->risk_level),
                ))
                ->implode("\n")."\nTotal indicative adjustment: ".$this->money($total).'.';

        return ReportSectionDraft::generated(
            key: 'dd_price_adjustment',
            title: 'Price adjustment schedule',
            body: $body,
            sourceReference: 'dd_risk_register:'.$inputs->engagement->getKey().':price_adjustment',
            dataQualityNote: 'Data quality note: adjustment schedule is indicative and must be reviewed by qualified legal/accounting advisers before negotiation.',
            metadata: [
                'total_price_adjustment_nzd' => $total,
                'adjustment_count' => $adjustments->count(),
                'risk_register_ids' => $adjustments->map(fn (DdRiskRegisterItem $risk): string => (string) $risk->getKey())->all(),
            ],
        );
    }

    private function integrationPlanSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        return ReportSectionDraft::generated(
            key: 'dd_integration_plan',
            title: '100-day integration plan',
            body: $inputs->integrationPlan
                ->map(fn (DdIntegrationPlanItem $item): string => sprintf('Day %d (%s): %s', $item->day, $item->phase, $item->action))
                ->implode("\n"),
            sourceReference: 'dd_integration_plans:'.$inputs->engagement->getKey(),
            dataQualityNote: 'Data quality note: integration actions are generated from the ranked DD risk register and require advisor review.',
            metadata: [
                'integration_plan_count' => $inputs->integrationPlan->count(),
                'integration_plan_ids' => $inputs->integrationPlan->map(fn (DdIntegrationPlanItem $item): string => (string) $item->getKey())->all(),
            ],
        );
    }

    private function buyerReadinessSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        $completed = DdWorkstream::query()
            ->where('dd_engagement_id', $inputs->engagement->getKey())
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count();
        $dataRoomItems = $inputs->engagement->dataRoomItems()->count();
        $dealKillers = $inputs->risks->where('risk_level', DdRiskRegisterItem::LEVEL_DEAL_KILLER)->count();
        $readiness = match (true) {
            $dealKillers > 0 => 'not ready until deal-killer risks are resolved',
            $completed < count(DataRoom::WORKSTREAMS) => 'partially ready; DD workstreams remain incomplete',
            ! ($inputs->valuation instanceof DdValuation) => 'partially ready; valuation is missing',
            default => 'ready for advisor-led acquisition decision review',
        };

        return ReportSectionDraft::generated(
            key: 'dd_buyer_readiness',
            title: 'Buyer readiness',
            body: sprintf(
                "Readiness: %s.\nCompleted workstreams: %d of %d.\nData room items reviewed: %d.\nDeal-killer risks: %d.",
                $readiness,
                $completed,
                count(DataRoom::WORKSTREAMS),
                $dataRoomItems,
                $dealKillers,
            ),
            sourceReference: 'dd_buyer_readiness:'.$inputs->engagement->getKey(),
            dataQualityNote: 'Data quality note: buyer readiness reflects platform DD completion signals and is not acquisition advice.',
            metadata: [
                'readiness' => $readiness,
                'completed_workstreams' => $completed,
                'required_workstreams' => count(DataRoom::WORKSTREAMS),
                'data_room_items' => $dataRoomItems,
                'deal_killer_risks' => $dealKillers,
            ],
        );
    }

    private function recommendationSection(DueDiligenceReportInputs $inputs): ReportSectionDraft
    {
        return ReportSectionDraft::generated(
            key: 'dd_recommendation',
            title: 'Recommendation',
            body: sprintf(
                "Recommendation: %s.\nRationale: %s.",
                ucfirst($inputs->recommendation->decision),
                $inputs->recommendation->rationale,
            ),
            sourceReference: 'dd_recommendation:'.$inputs->engagement->getKey(),
            dataQualityNote: 'Data quality note: recommendation is generated from DD risk, valuation, and workstream completion signals for advisor review.',
            metadata: [
                'recommendation' => $inputs->recommendation->decision,
                'rationale' => $inputs->recommendation->rationale,
            ],
        );
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, bool|float|int|string|array<int, string>>  $metadata
     */
    private function sectionWithDocumentSupport(
        string $key,
        string $title,
        string $body,
        string $sourceReference,
        Collection $findings,
        string $dataQualityNote,
        array $metadata,
    ): ReportSectionDraft {
        $documentSupport = $this->strongestDocumentSupport($findings);

        return new ReportSectionDraft(
            key: $key,
            title: $title,
            body: $body,
            attributions: [['claim' => $title, 'source_reference' => $sourceReference]],
            documentSupport: $documentSupport,
            documentSupportNote: $this->documentSupportNote($documentSupport),
            dataQualityNote: $dataQualityNote,
            metadata: $metadata,
        );
    }

    private function rangeMidpoint(?DdMoneyRange $range): string
    {
        return $range instanceof DdMoneyRange ? $this->money($range->mid) : 'n/a';
    }

    private function money(?float $value): string
    {
        return $value === null ? 'n/a' : 'NZD '.number_format($value, 0);
    }

    /** @param Collection<int, AnalysisFinding> $findings */
    private function strongestDocumentSupport(Collection $findings): string
    {
        foreach ([
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY,
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG,
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
        ] as $support) {
            if ($findings->contains(fn (AnalysisFinding $finding): bool => $finding->document_support === $support)) {
                return $support;
            }
        }

        return AnalysisFinding::DOCUMENT_SUPPORT_NONE;
    }

    private function documentSupportNote(string $support): string
    {
        return match ($support) {
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED => 'Backed by verified documents you uploaded.',
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG => 'Includes a document point that needs advisor review.',
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY => 'Includes a document discrepancy that needs resolution.',
            default => '',
        };
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

    private function renderAndAuditAfterCommit(Report $report, ?User $actor, DueDiligenceReportInputs $inputs): void
    {
        $callback = function () use ($report, $actor, $inputs): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report, true);
            $this->audit->record(
                'dd.report_generated',
                subject: $report,
                actor: $actor,
                after: [
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'recommendation' => $inputs->recommendation->decision,
                    'sections' => $report->sections()->count(),
                    'risk_count' => $inputs->risks->count(),
                    'pdf_path' => (string) ($report->pdf_path ?? ''),
                    'pptx_path' => (string) ($report->pptx_path ?? ''),
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
