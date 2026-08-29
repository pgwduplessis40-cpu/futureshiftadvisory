<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Analysis\HolidaysActLiabilityCalculator;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\AcquisitionGoNoGoReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\AcquisitionDealMechanics;
use App\Services\Reports\Data\AcquisitionGoNoGoReportInputs;
use App\Services\Reports\Data\AcquisitionHolidaysActLiability;
use App\Services\Reports\Data\AcquisitionPriceChip;
use App\Services\Reports\Data\AcquisitionWalkAwayPrice;
use App\Services\Reports\Data\DdMoneyRange;
use App\Services\Reports\Data\ReportSectionDraft;
use App\Support\Reports\SourceReferenceLabeler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the advisor-reviewed acquisition Go/No-Go decision report.
 */
final class AcquisitionGoNoGoReportComposer implements AcquisitionGoNoGoReportComposition
{
    public function __construct(
        private readonly DueDiligenceReportSupport $support,
        private readonly HolidaysActLiabilityCalculator $holidaysActLiability,
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
                'type' => ReportType::AcquisitionGoNoGo,
                'title' => ReportType::AcquisitionGoNoGo->label().' - '.$inputs->engagement->target_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_report_layer_composition',
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'dd_valuation_id' => (string) ($inputs->valuation?->getKey() ?? ''),
                    'recommendation' => $inputs->recommendation->decision,
                    'recommendation_rationale' => $inputs->recommendation->rationale,
                    'advisor_review_required' => true,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'pending_review',
            ]);

            $this->persistSections($report, $this->sections($inputs));
            $this->renderAndAuditAfterCommit($report, $actor, $inputs);

            return $report->refresh()->load(['client', 'sections']);
        });
    }

    private function inputs(DdEngagement $engagement): AcquisitionGoNoGoReportInputs
    {
        $engagement->loadMissing('client');

        if (! $engagement->client instanceof Client) {
            throw new InvalidArgumentException('Acquisition Go/No-Go reports require a DD engagement with a client.');
        }

        $findings = $this->support->findings($engagement);
        $valuation = $this->support->latestValuation($engagement);
        $risks = $findings->isEmpty()
            ? DdRiskRegisterItem::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->orderBy('rank')
                ->get()
            : $this->support->refreshRiskRegister($engagement, $findings, $valuation);
        $recommendation = $this->support->recommendation($risks, $valuation);

        return new AcquisitionGoNoGoReportInputs(
            engagement: $engagement,
            valuation: $valuation,
            risks: $risks,
            recommendation: $recommendation,
            price: $this->walkAwayPrice($engagement, $valuation, $risks),
        );
    }

    /** @return list<ReportSectionDraft> */
    private function sections(AcquisitionGoNoGoReportInputs $inputs): array
    {
        return [
            $this->decisionSection($inputs),
            $this->walkAwayPriceChipSection($inputs),
            $this->dealMechanicsSection($inputs),
            $this->evidenceSection($inputs),
        ];
    }

    private function decisionSection(AcquisitionGoNoGoReportInputs $inputs): ReportSectionDraft
    {
        $price = $inputs->price;
        $priceSignal = $price->askingPriceNzd !== null && $price->walkAwayPriceNzd !== null
            ? ($price->askingPriceNzd > $price->walkAwayPriceNzd
                ? 'Asking price is above the calculated walk-away price.'
                : 'Asking price is at or below the calculated walk-away price.')
            : 'Asking price or walk-away price is not available yet.';

        return ReportSectionDraft::generated(
            key: 'go_no_go_decision',
            title: 'Go/No-Go decision',
            body: sprintf(
                "Decision: %s.\nTarget: %s.\nWalk-away price: %s.\nAsking price: %s.\nPrice gap to walk-away: %s.\nMaterial risk chips: %d.\nPrice signal: %s\nRationale: %s",
                ucfirst(str_replace('_', ' ', $inputs->recommendation->decision)),
                $inputs->engagement->target_name,
                $this->money($price->walkAwayPriceNzd),
                $this->money($price->askingPriceNzd),
                $this->money($price->gapToWalkAwayNzd),
                $inputs->risks->filter(fn (DdRiskRegisterItem $risk): bool => $risk->price_adjustment_nzd > 0)->count(),
                $priceSignal,
                $inputs->recommendation->rationale,
            ),
            sourceReference: $inputs->valuation instanceof DdValuation
                ? 'dd_valuation:'.$inputs->valuation->getKey()
                : 'dd_engagement:'.$inputs->engagement->getKey(),
            dataQualityNote: 'Data quality note: decision combines valuation, asking price, risk register, and price-chip calculations for advisor review.',
            metadata: [
                'recommendation' => $inputs->recommendation->decision,
                'recommendation_rationale' => $inputs->recommendation->rationale,
                ...$price->metadata(),
            ],
        );
    }

    private function walkAwayPriceChipSection(AcquisitionGoNoGoReportInputs $inputs): ReportSectionDraft
    {
        $price = $inputs->price;
        $chips = collect($price->priceChips)
            ->map(fn (AcquisitionPriceChip $chip): string => sprintf(
                '%s: %s off price (%s).',
                $chip->label,
                $this->money($chip->amountNzd),
                $chip->basis,
            ))
            ->implode("\n");

        return ReportSectionDraft::generated(
            key: 'walk_away_price_chips',
            title: 'Walk-away price and red-flag price chips',
            body: sprintf(
                "Base valuation high point: %s.\nRisk price chips: %s.\nHolidays Act liability chip: %s.\nWorking-capital chip: %s.\nCalculated walk-away price: %s.\n\nPrice chips:\n%s",
                $this->money($price->baseHighNzd),
                $this->money($price->riskAdjustmentNzd),
                $this->money($price->holidaysActLiabilityNzd),
                $this->money($price->workingCapitalAdjustmentNzd),
                $this->money($price->walkAwayPriceNzd),
                $chips !== '' ? $chips : 'No quantified price chips are available yet.',
            ),
            sourceReference: 'dd_risk_register:'.$inputs->engagement->getKey().':walk_away',
            dataQualityNote: 'Data quality note: walk-away price starts with the valuation high point and deducts quantified DD risk, Holidays Act, and working-capital chips where available.',
            metadata: [
                'risk_register_ids' => $inputs->risks
                    ->map(fn (DdRiskRegisterItem $risk): string => (string) $risk->getKey())
                    ->values()
                    ->all(),
                ...$price->metadata(),
            ],
        );
    }

    private function dealMechanicsSection(AcquisitionGoNoGoReportInputs $inputs): ReportSectionDraft
    {
        $mechanics = $inputs->price->dealMechanics;

        return ReportSectionDraft::generated(
            key: 'deal_mechanics',
            title: 'NZ deal mechanics',
            body: sprintf(
                'GST going-concern zero-rating: %s.\nGST cash exposure if not zero-rated: %s.\nWorking-capital peg: %s.\nVendor finance: %s.\nEarn-out: %s.\nCompletion note: these mechanics change cash-to-complete and negotiation structure even when headline price is unchanged.',
                $mechanics->gstZeroRatingStatus,
                $this->money($mechanics->gstCashExposureNzd),
                $mechanics->workingCapitalPeg,
                $mechanics->vendorFinance,
                $mechanics->earnout,
            ),
            sourceReference: 'dd_engagement:'.$inputs->engagement->getKey().':deal_mechanics',
            dataQualityNote: 'Data quality note: deal mechanics are sourced from DD target details and should be checked against transaction documents before client advice is released.',
            metadata: $mechanics->metadata(),
        );
    }

    private function evidenceSection(AcquisitionGoNoGoReportInputs $inputs): ReportSectionDraft
    {
        $riskSources = $this->riskSourceLabels($inputs->risks);
        $valuationSource = $inputs->valuation instanceof DdValuation
            ? SourceReferenceLabeler::label('dd_valuation:'.$inputs->valuation->getKey(), 'DD valuation')
                .' as at '.($inputs->valuation->as_at?->toDateString() ?? 'not dated')
            : 'no DD valuation available';

        return ReportSectionDraft::generated(
            key: 'go_no_go_evidence',
            title: 'Evidence and limitations',
            body: sprintf(
                'Valuation source: %s.\nRisk-register rows used: %d.\nEvidence sources: %s.\nAdvisor review: confirm any unquantified legal, tax, employment, and financing conditions before issuing the decision to the client.',
                $valuationSource,
                $inputs->risks->count(),
                $riskSources === [] ? 'No explicit risk source attribution recorded.' : implode(', ', $riskSources),
            ),
            sourceReference: 'dd_engagement:'.$inputs->engagement->getKey().':go_no_go_evidence',
            dataQualityNote: 'Data quality note: Go/No-Go report is a one-page decision layer over persisted DD evidence, valuation, and risk PV data.',
            metadata: [
                'dd_valuation_id' => (string) ($inputs->valuation?->getKey() ?? ''),
                'risk_register_ids' => $inputs->risks
                    ->map(fn (DdRiskRegisterItem $risk): string => (string) $risk->getKey())
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return list<string>
     */
    private function riskSourceLabels(Collection $risks): array
    {
        $labels = [];

        foreach ($risks as $risk) {
            $sources = $risk->source_attributions;

            foreach ($sources as $source) {
                if (! is_array($source)) {
                    continue;
                }

                $label = SourceReferenceLabeler::label(
                    (string) ($source['source_reference'] ?? $source['id'] ?? ''),
                    (string) ($source['claim'] ?? ''),
                );

                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /** @param Collection<int, DdRiskRegisterItem> $risks */
    private function walkAwayPrice(DdEngagement $engagement, ?DdValuation $valuation, Collection $risks): AcquisitionWalkAwayPrice
    {
        $baseRange = $valuation instanceof DdValuation
            ? ($this->support->valuationRange($valuation, 'dcf_value') ?? $this->support->valuationRange($valuation, 'reconciled'))
            : null;
        $baseHigh = $baseRange instanceof DdMoneyRange ? $baseRange->high : null;
        $riskAdjustment = round((float) $risks->sum('price_adjustment_nzd'), 2);
        $holidaysAct = $this->holidaysActLiability($engagement);
        $workingCapitalAdjustment = $this->workingCapitalAdjustmentNzd($engagement);
        $dealStructureAdjustment = $this->adjustmentTotal(data_get($engagement->target_details, 'deal_structure_adjustments', []));
        $synergyAdjustment = $this->adjustmentTotal(data_get($engagement->target_details, 'synergy_adjustments', []));
        $askingPrice = $this->askingPriceNzd($engagement, $valuation);
        $walkAway = $baseHigh === null
            ? null
            : round(max(0, $baseHigh + $dealStructureAdjustment + $synergyAdjustment - $riskAdjustment - $holidaysAct->amountNzd - $workingCapitalAdjustment), 2);

        return new AcquisitionWalkAwayPrice(
            baseRange: $baseRange,
            baseHighNzd: $baseHigh,
            dealStructureAdjustmentNzd: $dealStructureAdjustment,
            synergyAdjustmentNzd: $synergyAdjustment,
            riskAdjustmentNzd: $riskAdjustment,
            holidaysActLiabilityNzd: $holidaysAct->amountNzd,
            workingCapitalAdjustmentNzd: $workingCapitalAdjustment,
            walkAwayPriceNzd: $walkAway,
            askingPriceNzd: $askingPrice,
            gapToWalkAwayNzd: $askingPrice !== null && $walkAway !== null
                ? round($askingPrice - $walkAway, 2)
                : null,
            priceChips: $this->priceChips($risks, $holidaysAct, $workingCapitalAdjustment),
            dealMechanics: $this->dealMechanics($engagement, $askingPrice),
        );
    }

    private function askingPriceNzd(DdEngagement $engagement, ?DdValuation $valuation): ?float
    {
        $buyerPositionPrice = data_get($valuation?->buyer_position, 'asking_price_nzd');

        if (is_numeric($buyerPositionPrice)) {
            return round((float) $buyerPositionPrice, 2);
        }

        $askingPrice = data_get($engagement->target_details, 'asking_price');
        $rate = $valuation instanceof DdValuation ? $valuation->source_to_nzd_rate : 1.0;

        return is_numeric($askingPrice) ? round((float) $askingPrice * $rate, 2) : null;
    }

    private function holidaysActLiability(DdEngagement $engagement): AcquisitionHolidaysActLiability
    {
        $raw = data_get($engagement->target_details, 'holidays_act_liability', data_get($engagement->target_details, 'holidays_act'));

        if (is_numeric($raw)) {
            return new AcquisitionHolidaysActLiability(
                amountNzd: round((float) $raw, 2),
                basis: 'Advisor supplied Holidays Act liability.',
            );
        }

        if (is_array($raw)) {
            $total = $raw['total_liability'] ?? $raw['total_liability_nzd'] ?? null;

            if (is_numeric($total)) {
                return new AcquisitionHolidaysActLiability(
                    amountNzd: round((float) $total, 2),
                    basis: is_string($raw['basis'] ?? null) ? $raw['basis'] : 'Advisor supplied Holidays Act liability.',
                );
            }

            if (is_numeric($raw['underpaid_hours'] ?? null) && is_numeric($raw['hourly_rate'] ?? null)) {
                $calculation = $this->holidaysActLiability->calculate(
                    (float) $raw['underpaid_hours'],
                    (float) $raw['hourly_rate'],
                    (float) ($raw['buffer_rate'] ?? 0.15),
                );

                return new AcquisitionHolidaysActLiability(
                    amountNzd: $calculation['total_liability'],
                    basis: sprintf(
                        'Calculated from %.2f underpaid hours at %s/hour plus remediation buffer.',
                        $calculation['underpaid_hours'],
                        $this->money($calculation['hourly_rate']),
                    ),
                );
            }
        }

        return new AcquisitionHolidaysActLiability(0.0, 'No Holidays Act liability chip recorded.');
    }

    private function workingCapitalAdjustmentNzd(DdEngagement $engagement): float
    {
        $direct = data_get($engagement->target_details, 'working_capital_adjustment_nzd');

        if (is_numeric($direct)) {
            return round(max(0, (float) $direct), 2);
        }

        return round(max(0, $this->adjustmentTotal(
            data_get($engagement->target_details, 'working_capital_adjustments', []),
            data_get($engagement->target_details, 'completion_accounts_adjustments', []),
        )), 2);
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return list<AcquisitionPriceChip>
     */
    private function priceChips(Collection $risks, AcquisitionHolidaysActLiability $holidaysAct, float $workingCapitalAdjustment): array
    {
        $chips = [];

        foreach ($risks as $risk) {
            if ($risk->price_adjustment_nzd <= 0) {
                continue;
            }

            $chips[] = new AcquisitionPriceChip(
                label: $risk->title,
                amountNzd: round($risk->price_adjustment_nzd, 2),
                basis: str_replace('_', ' ', $risk->risk_level).' DD risk priced from RiskCostPv.',
                sourceReference: 'dd_risk_register:'.$risk->getKey(),
            );
        }

        if ($holidaysAct->amountNzd > 0) {
            $chips[] = new AcquisitionPriceChip(
                label: 'Holidays Act remediation',
                amountNzd: $holidaysAct->amountNzd,
                basis: $holidaysAct->basis,
                sourceReference: 'holidays_act_liability:target_details',
            );
        }

        if ($workingCapitalAdjustment > 0) {
            $chips[] = new AcquisitionPriceChip(
                label: 'Working-capital true-up',
                amountNzd: $workingCapitalAdjustment,
                basis: 'Advisor supplied working-capital/completion adjustment.',
                sourceReference: 'working_capital_adjustment:target_details',
            );
        }

        return $chips;
    }

    private function dealMechanics(DdEngagement $engagement, ?float $askingPriceNzd): AcquisitionDealMechanics
    {
        $zeroRated = data_get($engagement->target_details, 'gst_going_concern_zero_rating', data_get($engagement->target_details, 'gst.zero_rating'));
        $zeroRatingStatus = is_bool($zeroRated)
            ? ($zeroRated ? 'confirmed as intended' : 'not confirmed')
            : (is_string($zeroRated) && trim($zeroRated) !== '' ? $zeroRated : 'not recorded');

        return new AcquisitionDealMechanics(
            gstZeroRatingStatus: $zeroRatingStatus,
            gstCashExposureNzd: $zeroRated === false && $askingPriceNzd !== null
                ? round($askingPriceNzd * 0.15, 2)
                : 0.0,
            workingCapitalPeg: $this->dealMechanicText(data_get($engagement->target_details, 'working_capital_peg')),
            vendorFinance: $this->dealMechanicText(data_get($engagement->target_details, 'vendor_finance')),
            earnout: $this->dealMechanicText(data_get($engagement->target_details, 'earnout')),
        );
    }

    private function adjustmentTotal(mixed ...$groups): float
    {
        $total = 0.0;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = array_key_exists('amount', $group) || array_key_exists('value', $group)
                ? [$group]
                : array_values(array_filter($group, 'is_array'));

            foreach ($items as $item) {
                $amount = $item['amount'] ?? $item['value'] ?? null;

                if (is_numeric($amount)) {
                    $total += (float) $amount;
                }
            }
        }

        return round($total, 2);
    }

    private function dealMechanicText(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return $this->money((float) $value);
        }

        if (is_array($value)) {
            foreach (['summary', 'status', 'label', 'description'] as $key) {
                if (is_string($value[$key] ?? null) && trim($value[$key]) !== '') {
                    return $value[$key];
                }
            }

            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return 'not recorded';
    }

    private function money(?float $value): string
    {
        return $value === null ? 'n/a' : 'NZD '.number_format($value, 0);
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

    private function renderAndAuditAfterCommit(Report $report, ?User $actor, AcquisitionGoNoGoReportInputs $inputs): void
    {
        $callback = function () use ($report, $actor, $inputs): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record(
                'dd.go_no_go_report_generated',
                subject: $report,
                actor: $actor,
                after: [
                    'dd_engagement_id' => (string) $inputs->engagement->getKey(),
                    'recommendation' => $inputs->recommendation->decision,
                    'sections' => $report->sections()->count(),
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
