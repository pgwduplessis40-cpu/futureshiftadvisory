<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Contracts\ValuationReportComposition;
use App\Services\Reports\Data\ReportSectionDraft;
use App\Services\Reports\Data\ValuationAdjustment;
use App\Services\Reports\Data\ValuationAssetFloor;
use App\Services\Reports\Data\ValuationEarningsNormalisation;
use App\Services\Reports\Data\ValuationReportInputs;
use Illuminate\Support\Facades\DB;

/**
 * Owns the advisor-reviewed standalone business valuation report type.
 */
final class ValuationReportComposer implements ValuationReportComposition
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
                'type' => ReportType::Valuation,
                'title' => ReportType::Valuation->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_report_layer_composition',
                    'business_valuation_id' => $inputs->valuation instanceof BusinessValuation ? (string) $inputs->valuation->getKey() : '',
                    'methodology' => 'triangulated_sde_ebitda_dcf_range',
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

    private function inputs(Client $client): ValuationReportInputs
    {
        $valuation = BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
        $financialSnapshot = FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('period_end')
            ->latest('pulled_at')
            ->latest()
            ->first();

        return new ValuationReportInputs($client, $valuation, $financialSnapshot);
    }

    /** @return list<ReportSectionDraft> */
    private function sections(ValuationReportInputs $inputs): array
    {
        return [
            $this->triangulationSection($inputs),
            $this->earningsNormalisationSection($inputs),
            $this->assetFloorSection($inputs),
            $this->methodologySection($inputs),
        ];
    }

    private function triangulationSection(ValuationReportInputs $inputs): ReportSectionDraft
    {
        $valuation = $inputs->valuation;

        if (! $valuation instanceof BusinessValuation) {
            return ReportSectionDraft::generated(
                key: 'valuation_triangulation',
                title: 'Method triangulation',
                body: 'No business valuation row is available yet. Generate or import a valuation before relying on a standalone valuation report.',
                sourceReference: 'business_valuations:none:'.$inputs->client->getKey(),
                dataQualityNote: 'Data quality note: valuation report is pending source valuation data.',
                metadata: ['method_count' => 0],
            );
        }

        $body = sprintf(
            "Valuation as at: %s.\nReconciled range: %s low, %s midpoint, %s high.\n\nMethod triangulation:\n%s\n\nConclusion: use the range, not a point estimate. The midpoint is a planning anchor; negotiation and advice should stay inside the evidenced low/high range unless the advisor records a specific adjustment.",
            $valuation->as_at?->toDateString() ?? 'not dated',
            $this->money($valuation->reconciled_low),
            $this->money($valuation->reconciled_mid),
            $this->money($valuation->reconciled_high),
            implode("\n", $this->valuationMethodRows($valuation)),
        );

        return ReportSectionDraft::generated(
            key: 'valuation_triangulation',
            title: 'Method triangulation',
            body: $body,
            sourceReference: 'business_valuation:'.$valuation->getKey(),
            dataQualityNote: ($valuation->data_quality_disclaimer ?: 'Data quality note: valuation uses persisted SDE, EBITDA, and DCF/PV methods.').' Data-age stamp: valuation as at '.($valuation->as_at?->toDateString() ?? 'not dated').'.',
            metadata: [
                'business_valuation_id' => (string) $valuation->getKey(),
                'method_count' => 3,
                'reconciled_low' => $valuation->reconciled_low,
                'reconciled_mid' => $valuation->reconciled_mid,
                'reconciled_high' => $valuation->reconciled_high,
            ],
        );
    }

    private function earningsNormalisationSection(ValuationReportInputs $inputs): ReportSectionDraft
    {
        $normalisation = $this->earningsNormalisation($inputs->valuation, $inputs->financialSnapshot);
        $addBacks = collect($normalisation->addBacks)
            ->map(fn (ValuationAdjustment $row): string => sprintf(
                '%s: %s (%s)',
                $row->label,
                $this->money($row->amount),
                $row->rationale,
            ))
            ->implode("\n");

        $body = sprintf(
            "Reported net profit: %s.\nNormalised EBITDA: %s.\nSeller discretionary earnings (SDE): %s.\n\nAdd-backs and normalisation adjustments:\n%s\n\nAdvisor check: for owner-operated NZ SMEs, SDE and one-off add-backs should be reviewed before this valuation is used for pricing or lending conversations.",
            $this->money($normalisation->reportedNetProfit),
            $this->money($normalisation->normalisedEbitda),
            $this->money($normalisation->sellerDiscretionaryEarnings),
            $addBacks !== '' ? $addBacks : 'No structured add-backs are recorded on the latest valuation.',
        );

        return ReportSectionDraft::generated(
            key: 'earnings_normalisation',
            title: 'Earnings normalisation worksheet',
            body: $body,
            sourceReference: $inputs->valuation instanceof BusinessValuation
                ? 'business_valuation:'.$inputs->valuation->getKey().':normalisation'
                : 'financial_snapshot:'.$inputs->client->getKey(),
            dataQualityNote: 'Data quality note: normalisation is assembled from the latest valuation inputs, valuation adjustments, and the latest financial snapshot where present.',
            metadata: [
                'add_back_count' => count($normalisation->addBacks),
                'financial_snapshot_id' => $inputs->financialSnapshot instanceof FinancialSnapshot ? (string) $inputs->financialSnapshot->getKey() : '',
                'reported_net_profit_available' => $normalisation->reportedNetProfit !== null,
                'normalised_ebitda_available' => $normalisation->normalisedEbitda !== null,
                'seller_discretionary_earnings_available' => $normalisation->sellerDiscretionaryEarnings !== null,
            ],
        );
    }

    private function assetFloorSection(ValuationReportInputs $inputs): ReportSectionDraft
    {
        $floor = $this->assetFloor($inputs);
        $valuation = $inputs->valuation;
        $sanityCheck = match (true) {
            $floor->assetFloorNzd === null => 'Asset floor could not be calculated from the latest balance sheet.',
            $valuation instanceof BusinessValuation && $valuation->reconciled_low < $floor->assetFloorNzd => 'The valuation low point sits below the asset floor; advisor review should reconcile whether assets are surplus, impaired, or required for operations.',
            $valuation instanceof BusinessValuation => 'The valuation range sits above the available asset floor sanity check.',
            default => 'Asset floor is ready for comparison once a valuation is available.',
        };

        $body = sprintf(
            'Asset floor estimate: %s.\nCash and surplus asset indicator: %s.\nLiabilities included: %s.\nSanity check: %s',
            $this->money($floor->assetFloorNzd),
            $this->money($floor->cashOrSurplusAssetIndicatorNzd),
            $this->money($floor->liabilitiesNzd),
            $sanityCheck,
        );

        return ReportSectionDraft::generated(
            key: 'asset_floor',
            title: 'Asset floor and surplus asset check',
            body: $body,
            sourceReference: $floor->sourceReference,
            dataQualityNote: 'Data quality note: asset floor is a sanity check from balance-sheet fields, not a replacement for the earnings and DCF valuation methods.',
            metadata: [
                'asset_floor_available' => $floor->assetFloorNzd !== null,
                'cash_indicator_available' => $floor->cashOrSurplusAssetIndicatorNzd !== null,
                'liabilities_available' => $floor->liabilitiesNzd !== null,
            ],
        );
    }

    private function methodologySection(ValuationReportInputs $inputs): ReportSectionDraft
    {
        $valuation = $inputs->valuation;
        $sources = $this->sourceReferences($valuation?->source_attributions);
        $disclosures = $valuation instanceof BusinessValuation ? $this->valuationDisclosureLines($valuation) : [];

        $body = sprintf(
            "Methodology version: valuation.business; report-layer composition %s.\nUncertainty standard: forward-looking valuation outputs are presented as ranges.\nInput attribution: %s.\nProfessional scope and reliance notes:\n%s\nAdvisor review: confirm earnings add-backs, surplus assets, multiple feed date, DCF assumptions, basis of value, purpose, and reliance limitations before releasing to a client.",
            now()->toDateString(),
            $sources === [] ? 'No explicit source attribution recorded on the valuation row.' : implode(', ', $sources),
            $disclosures === [] ? 'No structured valuation disclosures are recorded yet.' : implode("\n", $disclosures),
        );

        return ReportSectionDraft::generated(
            key: 'valuation_methodology',
            title: 'Methodology and source notes',
            body: $body,
            sourceReference: $valuation instanceof BusinessValuation
                ? 'business_valuation:'.$valuation->getKey().':methodology'
                : 'client:'.$inputs->client->getKey().':valuation_methodology',
            dataQualityNote: 'Data quality note: this section surfaces methodology, uncertainty, and source-age checks so stale reference data is visible.',
            metadata: [
                'calculation_methods' => ['Business valuation reconciliation', 'Present-value engine'],
                'source_count' => count($sources),
                'disclosure_count' => count($disclosures),
                'as_at' => $valuation?->as_at?->toIso8601String() ?? '',
            ],
        );
    }

    /** @return list<string> */
    private function valuationMethodRows(BusinessValuation $valuation): array
    {
        return [
            $this->valuationMethodRow('SDE capitalisation', $valuation->sde_value),
            $this->valuationMethodRow('EBITDA market multiple', $valuation->ebitda_value),
            $this->valuationMethodRow('PV/DCF', $valuation->dcf_value),
        ];
    }

    private function valuationMethodRow(string $label, mixed $range): string
    {
        if (! is_array($range)) {
            return "{$label}: n/a.";
        }

        $input = $range['input'] ?? null;

        return sprintf(
            '%s: %s low, %s midpoint, %s high%s.',
            $label,
            $this->money($range['low'] ?? null),
            $this->money($range['mid'] ?? $range['present_value'] ?? null),
            $this->money($range['high'] ?? null),
            is_numeric($input) ? ' from input '.$this->money($input) : '',
        );
    }

    private function earningsNormalisation(?BusinessValuation $valuation, ?FinancialSnapshot $snapshot): ValuationEarningsNormalisation
    {
        $reportedNetProfit = $this->numericFrom($snapshot?->profit_and_loss, ['net_profit', 'profit', 'net_income']);
        $normalisedEbitda = $this->numericFrom($valuation?->ebitda_value, ['input', 'normalised_ebitda'])
            ?? $this->numericFrom($snapshot?->metrics, ['ebitda', 'normalised_ebitda']);
        $sde = $this->numericFrom($valuation?->sde_value, ['input', 'sde'])
            ?? $this->numericFrom($snapshot?->metrics, ['sde']);
        $addBacks = $this->normalisationAddBacks($valuation?->adjustments);

        if ($sde !== null && $normalisedEbitda !== null && $sde > $normalisedEbitda) {
            $addBacks[] = new ValuationAdjustment(
                label: 'Owner discretionary earnings uplift',
                amount: round($sde - $normalisedEbitda, 2),
                rationale: 'SDE exceeds normalised EBITDA in the valuation inputs.',
            );
        }

        return new ValuationEarningsNormalisation($reportedNetProfit, $normalisedEbitda, $sde, $addBacks);
    }

    /** @param list<string> $keys */
    private function numericFrom(mixed $values, array $keys): ?float
    {
        if (! is_array($values)) {
            return null;
        }

        foreach ($keys as $key) {
            $value = data_get($values, $key);

            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    /** @return list<ValuationAdjustment> */
    private function normalisationAddBacks(mixed $adjustments): array
    {
        if (! is_array($adjustments)) {
            return [];
        }

        $rows = [];

        foreach ($adjustments as $key => $adjustment) {
            if (is_array($adjustment)) {
                $amount = $adjustment['amount'] ?? $adjustment['value'] ?? null;

                if (is_numeric($amount)) {
                    $rows[] = new ValuationAdjustment(
                        label: $this->adjustmentLabel($adjustment['label'] ?? $adjustment['name'] ?? null, $key),
                        amount: round((float) $amount, 2),
                        rationale: $this->adjustmentRationale($adjustment['rationale'] ?? $adjustment['description'] ?? null),
                    );
                }

                continue;
            }

            if (is_string($key) && is_numeric($adjustment)) {
                $rows[] = new ValuationAdjustment(
                    label: str($key)->replace('_', ' ')->title()->toString(),
                    amount: round((float) $adjustment, 2),
                    rationale: 'Valuation adjustment recorded on the valuation row.',
                );
            }
        }

        return $rows;
    }

    private function adjustmentLabel(mixed $label, int|string $fallbackKey): string
    {
        if (is_scalar($label) && trim((string) $label) !== '') {
            return (string) $label;
        }

        return is_string($fallbackKey)
            ? str($fallbackKey)->replace('_', ' ')->title()->toString()
            : 'Valuation adjustment';
    }

    private function adjustmentRationale(mixed $rationale): string
    {
        return is_scalar($rationale) && trim((string) $rationale) !== ''
            ? (string) $rationale
            : 'Advisor supplied valuation adjustment.';
    }

    private function assetFloor(ValuationReportInputs $inputs): ValuationAssetFloor
    {
        $balanceSheet = $inputs->financialSnapshot?->balance_sheet;
        $totalAssets = $this->numericFrom($balanceSheet, ['total_assets', 'assets.total', 'assets']);
        $liabilities = $this->numericFrom($balanceSheet, ['total_liabilities', 'liabilities.total', 'liabilities']);
        $cash = $this->numericFrom($balanceSheet, ['cash', 'bank', 'cash_and_cash_equivalents']);

        return new ValuationAssetFloor(
            assetFloorNzd: $totalAssets === null ? null : round($totalAssets - ($liabilities ?? 0), 2),
            cashOrSurplusAssetIndicatorNzd: $cash,
            liabilitiesNzd: $liabilities,
            sourceReference: $inputs->financialSnapshot instanceof FinancialSnapshot
                ? 'financial_snapshot:'.$inputs->financialSnapshot->getKey()
                : 'financial_snapshot:none:'.$inputs->client->getKey(),
        );
    }

    /** @return list<string> */
    private function sourceReferences(mixed $attributions): array
    {
        if (! is_array($attributions)) {
            return [];
        }

        $references = [];

        foreach ($attributions as $source) {
            if (! is_array($source)) {
                continue;
            }

            $reference = $source['source_reference'] ?? $source['id'] ?? null;

            if (is_scalar($reference) && trim((string) $reference) !== '') {
                $references[] = (string) $reference;
            }
        }

        return array_values(array_unique($references));
    }

    /** @return list<string> */
    private function valuationDisclosureLines(BusinessValuation $valuation): array
    {
        $disclosures = $valuation->valuation_disclosures;

        if (! is_array($disclosures)) {
            return [];
        }

        $lines = [];

        foreach ($disclosures as $disclosure) {
            if (! is_array($disclosure)) {
                continue;
            }

            $message = $disclosure['message'] ?? null;

            if (! is_scalar($message) || trim((string) $message) === '') {
                continue;
            }

            $type = $disclosure['type'] ?? 'disclosure';
            $typeLabel = is_scalar($type) ? str((string) $type)->replace('_', ' ')->title()->toString() : 'Disclosure';
            $lines[] = "{$typeLabel}: ".trim((string) $message);
        }

        return $lines;
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

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }

    private function renderAndAuditAfterCommit(Report $report, ?User $actor, ValuationReportInputs $inputs): void
    {
        $callback = function () use ($report, $actor, $inputs): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record(
                'valuation.report_generated',
                subject: $report,
                actor: $actor,
                after: [
                    'client_id' => (string) $inputs->client->getKey(),
                    'business_valuation_id' => $inputs->valuation instanceof BusinessValuation ? (string) $inputs->valuation->getKey() : '',
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
