<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AnalysisLens;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\PvWaterfallReportChart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ReportComposer
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly PvWaterfallReportChart $chart,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(Client $client, ReportType $type, ?User $actor = null): Report
    {
        if (! in_array($type, [ReportType::Client, ReportType::Advisor], true)) {
            throw new InvalidArgumentException("Report type [{$type->value}] is scaffolded but not composed in WO-57.");
        }

        return DB::transaction(function () use ($client, $type, $actor): Report {
            $findings = $this->findings($client);
            $waterfall = $this->waterfalls->forClient($client);
            $valuation = $this->latestValuation($client);
            $proposal = $this->latestProposal($client);

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'type' => $type,
                'title' => $type->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_2',
                    'redactions' => $type === ReportType::Client
                        ? ['recommendations', 'fee_detail']
                        : [],
                    'scaffolded_report_types' => [
                        ReportType::Stakeholder->value,
                        ReportType::Trajectory->value,
                        ReportType::DueDiligence->value,
                        ReportType::EntrepreneurAssessment->value,
                    ],
                ],
            ]);

            foreach ($this->sections($client, $type, $findings, $waterfall, $valuation, $proposal) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('report.generated', subject: $report, actor: $actor, after: [
                'type' => $type->value,
                'sections' => $report->sections()->count(),
                'pdf_path' => $report->pdf_path,
            ]);

            return $report->refresh()->load('sections');
        });
    }

    /**
     * @return Collection<int, AnalysisFinding>
     */
    private function findings(Client $client): Collection
    {
        return AnalysisFinding::query()
            ->with('run')
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(80)
            ->get()
            ->sortBy(fn (AnalysisFinding $finding): string => sprintf(
                '%d-%s',
                $this->lensPosition($finding->lens),
                $finding->created_at?->toIso8601String() ?? '',
            ))
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function sections(
        Client $client,
        ReportType $type,
        Collection $findings,
        array $waterfall,
        ?BusinessValuation $valuation,
        ?Proposal $proposal,
    ): array {
        return match ($type) {
            ReportType::Client => $this->clientSections($client, $findings, $waterfall, $valuation),
            ReportType::Advisor => $this->advisorSections($client, $findings, $waterfall, $valuation, $proposal),
            default => [],
        };
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function clientSections(Client $client, Collection $findings, array $waterfall, ?BusinessValuation $valuation): array
    {
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
        ];

        $findings
            ->reject(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
            ->each(function (AnalysisFinding $finding) use (&$sections): void {
                $sections[] = $this->findingSection($finding);
            });

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function advisorSections(
        Client $client,
        Collection $findings,
        array $waterfall,
        ?BusinessValuation $valuation,
        ?Proposal $proposal,
    ): array {
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
            $this->waterfallSection($client, $waterfall),
        ];

        $findings->each(function (AnalysisFinding $finding) use (&$sections): void {
            $sections[] = $this->findingSection($finding);
        });

        $sections[] = $this->implementationPlanSection($client, $findings);
        $sections[] = $this->feeProposalSection($client, $proposal);

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $waterfall
     * @return array<string, mixed>
     */
    private function valuationSection(Client $client, array $waterfall, ?BusinessValuation $valuation): array
    {
        $body = $valuation instanceof BusinessValuation
            ? sprintf(
                'Current valuation range is NZD %s to NZD %s, with reconciled midpoint NZD %s.',
                number_format($valuation->reconciled_low, 0),
                number_format($valuation->reconciled_high, 0),
                number_format($valuation->reconciled_mid, 0),
            )
            : sprintf(
                'Current PV is NZD %s and target PV is NZD %s from the latest platform waterfall.',
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
            );

        return $this->generatedSection(
            key: 'valuation',
            title: 'Current valuation range',
            body: $body,
            sourceReference: 'pv_waterfall:'.$client->getKey(),
            dataQualityNote: 'Data quality note: valuation figures come from persisted PV and valuation rows.',
        );
    }

    /**
     * @param  array<string, mixed>  $waterfall
     * @return array<string, mixed>
     */
    private function waterfallSection(Client $client, array $waterfall): array
    {
        return $this->generatedSection(
            key: 'pv_waterfall',
            title: 'PV waterfall',
            body: sprintf(
                'The advisor view includes current PV NZD %s, improvements NZD %s, risk mitigation NZD %s, and target PV NZD %s.',
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['improvement_pv'], 0),
                number_format((float) $waterfall['risk_mitigation_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
            ),
            sourceReference: 'pv_waterfall:'.$client->getKey(),
            dataQualityNote: 'Data quality note: waterfall values are assembled from the latest persisted PV rows.',
            metadata: [
                'chart_html' => $this->chart->render($waterfall['waterfall']),
                'waterfall' => $waterfall,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function findingSection(AnalysisFinding $finding): array
    {
        return [
            'key' => 'finding_'.$finding->getKey(),
            'title' => $finding->title,
            'body' => $finding->body,
            'lens' => $finding->lens->value,
            'attributions' => $this->attributions($finding),
            'document_support' => $finding->document_support,
            'document_support_note' => $this->documentSupportNote($finding->document_support),
            'data_quality_note' => $finding->data_quality_disclaimer
                ?: 'Data quality note: no additional disclaimer recorded for this finding.',
            'metadata' => [
                'analysis_finding_id' => $finding->getKey(),
                'severity' => $finding->severity->value,
                'module' => $finding->run?->module?->value,
            ],
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>
     */
    private function implementationPlanSection(Client $client, Collection $findings): array
    {
        $prescriptive = $findings
            ->filter(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
            ->values();

        $body = $prescriptive->isEmpty()
            ? 'No prescriptive implementation findings have been generated yet.'
            : $prescriptive
                ->map(fn (AnalysisFinding $finding): string => $finding->title.': '.$finding->body)
                ->implode("\n\n");

        return $this->generatedSection(
            key: 'implementation_plan',
            title: 'Implementation plan',
            body: $body,
            sourceReference: 'analysis_findings:'.$client->getKey().':prescriptive',
            documentSupport: $this->strongestDocumentSupport($prescriptive),
            dataQualityNote: $this->combinedDataQualityNote($prescriptive),
            metadata: [
                'prescriptive_finding_ids' => $prescriptive->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function feeProposalSection(Client $client, ?Proposal $proposal): array
    {
        if (! $proposal instanceof Proposal) {
            return $this->generatedSection(
                key: 'fee_proposal',
                title: 'Fee proposal and ROI',
                body: 'No proposal has been generated yet.',
                sourceReference: 'proposal:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: fee proposal data is not available.',
            );
        }

        $fee = $proposal->feeCalculation;
        $body = sprintf(
            'Latest proposal v%s has suggested midpoint fee NZD %s and ROI ratio %s. Proposal status is %s.',
            $proposal->version,
            number_format($fee?->suggested_mid ?? 0, 0),
            number_format($proposal->roi_ratio, 2),
            $proposal->status->value,
        );

        return $this->generatedSection(
            key: 'fee_proposal',
            title: 'Fee proposal and ROI',
            body: $body,
            sourceReference: 'proposal:'.$proposal->getKey(),
            dataQualityNote: 'Data quality note: fee and ROI values come from the selected proposal and fee calculation.',
            metadata: [
                'proposal_id' => $proposal->getKey(),
                'fee_calculation_id' => $proposal->fee_calculation_id,
                'roi_ratio' => $proposal->roi_ratio,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function generatedSection(
        string $key,
        string $title,
        string $body,
        string $sourceReference,
        string $documentSupport = AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        ?string $dataQualityNote = null,
        array $metadata = [],
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'body' => $body,
            'lens' => null,
            'attributions' => [[
                'claim' => $title,
                'source_reference' => $sourceReference,
            ]],
            'document_support' => $documentSupport,
            'document_support_note' => $this->documentSupportNote($documentSupport),
            'data_quality_note' => $dataQualityNote ?: 'Data quality note: platform-generated section from persisted records.',
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function attributions(AnalysisFinding $finding): array
    {
        $attributions = is_array($finding->attributions) ? $finding->attributions : [];
        $normalised = collect($attributions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'claim' => (string) ($item['claim'] ?? $finding->title),
                'source_reference' => (string) ($item['source_reference'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['source_reference'] !== '')
            ->values()
            ->all();

        if ($normalised !== []) {
            return $normalised;
        }

        return [[
            'claim' => $finding->title,
            'source_reference' => 'analysis_finding:'.$finding->getKey(),
        ]];
    }

    private function documentSupportNote(string $support): string
    {
        return match ($support) {
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED => 'Document support: verified document evidence is linked.',
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG => 'Document support: advisor flag is noted and must be considered.',
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY => 'Document support: accuracy discrepancy is noted.',
            default => 'Document support: no verified document support recorded.',
        };
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
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

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
    private function combinedDataQualityNote(Collection $findings): string
    {
        $notes = $findings
            ->pluck('data_quality_disclaimer')
            ->filter(fn (mixed $note): bool => is_string($note) && trim($note) !== '')
            ->unique()
            ->values();

        return $notes->isEmpty()
            ? 'Data quality note: no additional disclaimer recorded for implementation findings.'
            : $notes->implode("\n");
    }

    private function latestValuation(Client $client): ?BusinessValuation
    {
        return BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    private function latestProposal(Client $client): ?Proposal
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    private function lensPosition(AnalysisLens $lens): int
    {
        return match ($lens) {
            AnalysisLens::Descriptive => 1,
            AnalysisLens::Diagnostic => 2,
            AnalysisLens::Predictive => 3,
            AnalysisLens::Prescriptive => 4,
        };
    }

    private function renderAndStorePdf(Report $report): void
    {
        $pdf = $this->renderer->render($this->html($report));
        $path = sprintf(
            'reports/%s/%s/%s-%s.pdf',
            $report->client_id,
            now()->format('Y/m'),
            Str::uuid(),
            $report->type->value,
        );

        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Report PDF could not be stored.');
        }

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_byte_size' => strlen($pdf),
        ])->save();
    }

    private function html(Report $report): string
    {
        $report->loadMissing(['client', 'sections']);
        $sections = $report->sections
            ->sortBy('position')
            ->map(fn (ReportSection $section): string => $this->sectionHtml($section))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.brand p { margin: 0; }
.section { border: 1px solid #d8e2dc; margin-bottom: 16px; padding: 12px; break-inside: avoid; }
.section h2 { color: #214f44; font-size: 15px; margin: 0 0 6px; }
.body { white-space: pre-wrap; }
.note { color: #4b5563; font-size: 10px; margin-top: 6px; }
.chart { margin-top: 10px; }
</style>
</head>
<body>
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>%s</p>
<p>%s</p>
</header>
%s
</body>
</html>
HTML,
            $this->escape($report->title),
            $this->escape($report->type->label()),
            $this->escape($report->client?->legal_name ?? 'Client'),
            $sections,
        );
    }

    private function sectionHtml(ReportSection $section): string
    {
        $sources = collect($section->attributions ?? [])
            ->map(fn (array $item): string => (string) ($item['source_reference'] ?? 'source'))
            ->filter()
            ->implode(', ');
        $chart = is_string($section->metadata['chart_html'] ?? null)
            ? '<div class="chart">'.$section->metadata['chart_html'].'</div>'
            : '';

        return sprintf(
            <<<'HTML'
<section class="section">
<h2>%s</h2>
<div class="body">%s</div>
%s
<p class="note">%s</p>
<p class="note">%s</p>
<p class="note">Sources: %s</p>
</section>
HTML,
            $this->escape($section->title),
            nl2br($this->escape($section->body)),
            $chart,
            $this->escape($section->document_support_note),
            $this->escape($section->data_quality_note),
            $this->escape($sources),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
