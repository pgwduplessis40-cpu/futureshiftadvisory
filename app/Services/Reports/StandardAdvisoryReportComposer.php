<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AnalysisLens;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Models\WebsiteAuditSnapshot;
use App\Services\Audit\AuditWriter;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\PvWaterfallReportChart;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Contracts\StandardAdvisoryReportComposition;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Owns the standard advisory Client, Advisor, Stakeholder, and Trajectory
 * report types while the public facade retains the cross-report API.
 *
 * @phpstan-type MetadataScalar bool|float|int|string
 * @phpstan-type SectionMetadata array<string, MetadataScalar|list<MetadataScalar>>
 * @phpstan-type WaterfallStep array{key: string, label: string, kind: string, value: float|int, start: float|int, end: float|int}
 * @phpstan-type WaterfallPayload array{
 *     current_pv: float|int,
 *     improvement_pv: float|int,
 *     risk_mitigation_pv: float|int,
 *     target_pv: float|int,
 *     target_pv_range: array{low: float|int, high: float|int},
 *     waterfall: list<WaterfallStep>
 * }
 * @phpstan-type AttributionPayload array{claim: string, source_reference: string}
 * @phpstan-type SectionPayload array{
 *     key: string,
 *     title: string,
 *     body: string,
 *     lens: string|null,
 *     attributions: list<AttributionPayload>,
 *     document_support: string,
 *     document_support_note: string,
 *     data_quality_note: string,
 *     metadata: SectionMetadata
 * }
 */
final class StandardAdvisoryReportComposer implements ProvidesMethodology, StandardAdvisoryReportComposition
{
    public static function methodologyIds(): array
    {
        return ['dd.risk_register', 'dd.price_adjustment'];
    }

    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly PvWaterfallReportChart $chart,
        private readonly AuditWriter $audit,
        private readonly ReportTemplateCatalog $templateCatalog,
    ) {}

    public function compose(Client $client, ReportType $type, ?User $actor = null): Report
    {
        if (! in_array($type, [ReportType::Client, ReportType::Advisor, ReportType::Stakeholder, ReportType::Trajectory], true)) {
            throw new InvalidArgumentException("Report type [{$type->value}] is scaffolded but not composed in Phase 2 yet.");
        }

        return DB::transaction(function () use ($client, $type, $actor): Report {
            $findings = $this->findings($client);
            $waterfall = $this->waterfalls->forClient($client);
            $valuation = $this->latestValuation($client);
            $proposal = $this->latestProposal($client);
            $template = $this->templateCatalog->activeFor($type);

            $clientReleaseGate = $type === ReportType::Client && $this->standardAdvisoryClient($client);
            $reviewStatus = match (true) {
                $type === ReportType::Trajectory,
                $clientReleaseGate => 'pending_review',
                default => 'not_required',
            };

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'type' => $type,
                'title' => $type->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_2',
                    'client_release_gate' => $clientReleaseGate,
                    'redactions' => $type === ReportType::Client
                        ? ['recommendations', 'fee_detail']
                        : ($type === ReportType::Stakeholder ? ['fsa_methodology', 'fsa_ip'] : []),
                    'scaffolded_report_types' => [
                        ReportType::Stakeholder->value,
                        ReportType::Trajectory->value,
                        ReportType::DueDiligence->value,
                        ReportType::EntrepreneurAssessment->value,
                    ],
                    'template' => $this->templateCatalog->metadata($template),
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => $reviewStatus,
            ]);

            foreach ($this->sections($client, $type, $findings, $waterfall, $valuation, $proposal) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'report.generated',
                fn (Report $rendered): array => [
                    'type' => $type->value,
                    'sections' => $rendered->sections()->count(),
                    'pdf_path' => $rendered->pdf_path,
                    'pptx_path' => $rendered->pptx_path,
                ],
                ['client', 'sections'],
                $type === ReportType::Stakeholder,
            );

            return $report->refresh()->load('sections');
        });
    }

    private function standardAdvisoryClient(Client $client): bool
    {
        return $client->engagement_type === EngagementType::STANDARD_ADVISORY;
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
     * @param  WaterfallPayload  $waterfall
     * @return list<SectionPayload>
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
            ReportType::Stakeholder => $this->stakeholderSections($client, $findings, $waterfall, $valuation),
            ReportType::Trajectory => $this->trajectorySections($client, $findings),
            default => [],
        };
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  WaterfallPayload  $waterfall
     * @return list<SectionPayload>
     */
    private function clientSections(Client $client, Collection $findings, array $waterfall, ?BusinessValuation $valuation): array
    {
        $visibleFindings = $findings
            ->reject(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
            ->values();

        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
        ];

        $websiteReview = $this->websiteReviewSection($client);
        if ($websiteReview !== null) {
            $sections[] = $websiteReview;
        }

        $whatIsWrong = $this->whatIsWrongSection($client, $visibleFindings);
        if ($whatIsWrong !== null) {
            $sections[] = $whatIsWrong;
        }

        $visibleFindings
            ->each(function (AnalysisFinding $finding) use (&$sections): void {
                $sections[] = $this->findingSection($finding);
            });

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  WaterfallPayload  $waterfall
     * @return list<SectionPayload>
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

        $websiteReview = $this->websiteReviewSection($client);
        if ($websiteReview !== null) {
            $sections[] = $websiteReview;
        }

        $findings->each(function (AnalysisFinding $finding) use (&$sections): void {
            $sections[] = $this->findingSection($finding);
        });

        $sections[] = $this->implementationPlanSection($client, $findings);
        $sections[] = $this->feeProposalSection($client, $proposal);

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  WaterfallPayload  $waterfall
     * @return list<SectionPayload>
     */
    private function stakeholderSections(Client $client, Collection $findings, array $waterfall, ?BusinessValuation $valuation): array
    {
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
            $this->waterfallSection($client, $waterfall),
        ];

        $findings
            ->filter(fn (AnalysisFinding $finding): bool => in_array($finding->lens, [AnalysisLens::Diagnostic, AnalysisLens::Predictive, AnalysisLens::Prescriptive], true))
            ->each(function (AnalysisFinding $finding) use (&$sections): void {
                $sections[] = $this->findingSection($finding);
            });

        $sections[] = $this->liabilityDisclaimerSection($client);

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return list<SectionPayload>
     */
    private function trajectorySections(Client $client, Collection $findings): array
    {
        $snapshots = FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->orderBy('period_end')
            ->get();
        $valuations = BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->orderBy('as_at')
            ->get();

        return [
            $this->financialTrendSection($client, $snapshots),
            $this->pvMilestonesSection($client, $valuations),
            $this->goalOutcomeSection($client),
            $this->trajectoryNarrativeSection($client, $snapshots, $valuations, $findings),
        ];
    }

    /**
     * @return SectionPayload|null
     */
    private function websiteReviewSection(Client $client): ?array
    {
        $snapshot = WebsiteAuditSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('fetched_at')
            ->latest()
            ->first();

        if (! $snapshot instanceof WebsiteAuditSnapshot) {
            return null;
        }

        $body = match ($snapshot->fetch_status) {
            WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL => 'Website review not performed - no website URL listed/confirmed.',
            WebsiteAuditSnapshot::STATUS_BLOCKED => 'Website review not performed - the nominated website blocked the audit through robots.txt.',
            WebsiteAuditSnapshot::STATUS_UNREACHABLE => 'Website review not performed - the nominated website could not be reached.',
            default => sprintf(
                'Verified website review completed for %s at %s. Health score: findability %s/100, credibility %s/100, conversion %s/100, technical %s/100, overall %s/100. PageSpeed measurement: %s.',
                $snapshot->root_url,
                $snapshot->fetched_at?->toIso8601String() ?? 'not recorded',
                data_get($snapshot->scores, 'findability', 'not measured'),
                data_get($snapshot->scores, 'credibility', 'not measured'),
                data_get($snapshot->scores, 'conversion', 'not measured'),
                data_get($snapshot->scores, 'technical', 'not measured'),
                data_get($snapshot->scores, 'overall', 'not measured'),
                data_get($snapshot->performance, 'measured', false) ? 'available' : 'not measured',
            ),
        };

        return $this->generatedSection(
            key: 'website_review',
            title: 'Website review',
            body: $body,
            sourceReference: 'website_audit_snapshot:'.$snapshot->getKey(),
            dataQualityNote: $snapshot->fetch_status === WebsiteAuditSnapshot::STATUS_PARTIAL
                ? 'Data quality note: website review was partial; findings are limited to fetched evidence.'
                : null,
            metadata: [
                'website_audit_snapshot_id' => $snapshot->getKey(),
                'fetch_status' => $snapshot->fetch_status,
                'scores' => $snapshot->scores,
            ],
        );
    }

    /**
     * @param  WaterfallPayload  $waterfall
     * @return SectionPayload
     */
    private function valuationSection(Client $client, array $waterfall, ?BusinessValuation $valuation): array
    {
        $body = $valuation instanceof BusinessValuation
            ? sprintf(
                "Current valuation range is NZD %s to NZD %s, with reconciled midpoint NZD %s.\n%s",
                number_format($valuation->reconciled_low, 0),
                number_format($valuation->reconciled_high, 0),
                number_format($valuation->reconciled_mid, 0),
                $this->metricExplanation('valuation_range'),
            )
            : sprintf(
                "Current PV is NZD %s and modelled upside PV is NZD %s from the latest platform waterfall, with a planning range of NZD %s to NZD %s.\n%s",
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
                number_format((float) data_get($waterfall, 'target_pv_range.low', $waterfall['target_pv']), 0),
                number_format((float) data_get($waterfall, 'target_pv_range.high', $waterfall['target_pv']), 0),
                $this->metricExplanation('pv'),
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
     * @param  WaterfallPayload  $waterfall
     * @return SectionPayload
     */
    private function waterfallSection(Client $client, array $waterfall): array
    {
        return $this->generatedSection(
            key: 'pv_waterfall',
            title: 'PV waterfall',
            body: sprintf(
                "The advisor view includes current PV NZD %s, improvements NZD %s, risk mitigation NZD %s, and modelled upside PV NZD %s. The modelled upside range is NZD %s to NZD %s and assumes surfaced improvements and risk mitigations are fully captured.\n%s\n%s",
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['improvement_pv'], 0),
                number_format((float) $waterfall['risk_mitigation_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
                number_format((float) data_get($waterfall, 'target_pv_range.low', $waterfall['target_pv']), 0),
                number_format((float) data_get($waterfall, 'target_pv_range.high', $waterfall['target_pv']), 0),
                $this->metricExplanation('pv'),
                $this->metricExplanation('modelled_upside'),
            ),
            sourceReference: 'pv_waterfall:'.$client->getKey(),
            dataQualityNote: 'Data quality note: waterfall values are assembled from the latest persisted PV rows.',
            metadata: [
                'chart_html' => $this->chart->render($waterfall['waterfall']),
            ],
        );
    }

    /**
     * @return SectionPayload
     */
    private function findingSection(AnalysisFinding $finding): array
    {
        return [
            'key' => 'finding_'.$finding->getKey(),
            'title' => $finding->title,
            'body' => 'Key takeaway: '.$this->keyTakeaway($finding)."\n\n".$finding->body,
            'lens' => $finding->lens->value,
            'attributions' => $this->attributions($finding),
            'document_support' => $finding->document_support,
            'document_support_note' => $this->documentSupportNote($finding->document_support),
            'data_quality_note' => $finding->data_quality_disclaimer ?: '',
            'metadata' => [
                'analysis_finding_id' => $finding->getKey(),
                'severity' => $finding->severity->value,
                'key_takeaway' => $this->keyTakeaway($finding),
                'module' => $finding->run?->module?->value,
            ],
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return SectionPayload
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
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return SectionPayload|null
     */
    private function whatIsWrongSection(Client $client, Collection $findings): ?array
    {
        $diagnosticFindings = $findings
            ->filter(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Diagnostic)
            ->values();

        $selected = $diagnosticFindings->isNotEmpty()
            ? $diagnosticFindings
            : $findings
                ->filter(fn (AnalysisFinding $finding): bool => in_array($finding->lens, [AnalysisLens::Predictive, AnalysisLens::Descriptive], true))
                ->values();

        if ($selected->isEmpty()) {
            return null;
        }

        $body = $selected
            ->sortByDesc(fn (AnalysisFinding $finding): int => $this->severityRank($finding->severity))
            ->take(8)
            ->map(fn (AnalysisFinding $finding): string => sprintf(
                '[%s] %s: %s',
                $this->severityLabel($finding->severity),
                $finding->title,
                $this->keyTakeaway($finding),
            ))
            ->implode("\n\n");

        return $this->generatedSection(
            key: 'what_is_wrong',
            title: 'What is wrong',
            body: $body,
            sourceReference: 'analysis_findings:'.$client->getKey().':what_is_wrong',
            documentSupport: $this->strongestDocumentSupport($selected),
            dataQualityNote: $this->combinedDataQualityNote($selected),
            metadata: [
                'diagnostic_finding_ids' => $selected
                    ->sortByDesc(fn (AnalysisFinding $finding): int => $this->severityRank($finding->severity))
                    ->take(8)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
        );
    }

    private function keyTakeaway(AnalysisFinding $finding): string
    {
        $body = trim((string) $finding->body);
        $normalised = trim((string) preg_replace('/\s+/', ' ', $body));
        $parts = preg_split('/(?<=[.!?])\s+/', $normalised, 2);
        $firstSentence = trim((string) ($parts[0] ?? ''));

        if ($firstSentence === '') {
            return $finding->title;
        }

        return Str::limit($firstSentence, 180, '');
    }

    private function severityRank(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::Critical => 5,
            FindingSeverity::High => 4,
            FindingSeverity::Medium => 3,
            FindingSeverity::Low => 2,
            FindingSeverity::Info => 1,
        };
    }

    private function severityLabel(FindingSeverity $severity): string
    {
        return Str::headline($severity->value);
    }

    private function metricExplanation(string $metric): string
    {
        return match ($metric) {
            'pv' => "PV (present value) means future benefits expressed as a single today's-dollars figure.",
            'modelled_upside' => 'Modelled upside is the value the plan could unlock if the identified improvements and risk reductions are actually achieved.',
            'valuation_range' => 'A valuation range gives a low-to-high planning view; the midpoint is a guide, not a guaranteed sale price.',
            'roi' => 'ROI compares modelled value with the advisory fee; for example, 3.25 means every NZD 1 of fee is modelled to unlock NZD 3.25 of value.',
            'goal_target' => 'A goal target is the value outcome the advisor and client agree to track over time.',
            default => '',
        };
    }

    /**
     * @return SectionPayload
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
            'Latest proposal v%s has suggested midpoint fee NZD %s. For every NZD 1 of advisory fee, the model shows NZD %s of potential value. Proposal status: %s.',
            $proposal->version,
            number_format($fee->suggested_mid, 0),
            number_format($proposal->roi_ratio, 2),
            Str::headline($proposal->status->value),
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
     * @return SectionPayload
     */
    private function liabilityDisclaimerSection(Client $client): array
    {
        return $this->generatedSection(
            key: 'liability_disclaimer',
            title: 'Liability disclaimer',
            body: 'This stakeholder report is prepared for discussion and decision support only. It does not replace financial, legal, tax, lending, investment, or governance advice. Recipients should rely on their own professional advisers before acting on the information.',
            sourceReference: 'stakeholder_disclaimer:'.$client->getKey(),
            dataQualityNote: 'Data quality note: the disclaimer applies to every exported stakeholder report.',
        );
    }

    /**
     * @param  Collection<int, FinancialSnapshot>  $snapshots
     * @return SectionPayload
     */
    private function financialTrendSection(Client $client, Collection $snapshots): array
    {
        $first = $snapshots->first();
        $latest = $snapshots->last();

        if (! $first instanceof FinancialSnapshot || ! $latest instanceof FinancialSnapshot) {
            return $this->generatedSection(
                key: 'financial_trends',
                title: 'Start to current metrics',
                body: 'Financial trend snapshots are not available yet.',
                sourceReference: 'financial_snapshots:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: trend analysis is pending connected or imported financial snapshots.',
            );
        }

        $metrics = collect(['revenue', 'gross_margin', 'cash_balance', 'debtor_days'])
            ->map(function (string $metric) use ($first, $latest): string {
                $start = data_get($first->metrics, $metric);
                $current = data_get($latest->metrics, $metric);

                return sprintf('%s: %s -> %s', str($metric)->replace('_', ' ')->title(), $this->formatMetric($start), $this->formatMetric($current));
            })
            ->implode("\n");

        return $this->generatedSection(
            key: 'financial_trends',
            title: 'Start to current metrics',
            body: sprintf(
                "Engagement start period: %s\nCurrent period: %s\n%s",
                $first->period_end->toDateString(),
                $latest->period_end->toDateString(),
                $metrics,
            ),
            sourceReference: 'financial_snapshots:'.$first->getKey().':'.$latest->getKey(),
            dataQualityNote: 'Data quality note: trend values compare earliest and latest persisted financial snapshots.',
            metadata: [
                'start_snapshot_id' => $first->getKey(),
                'current_snapshot_id' => $latest->getKey(),
            ],
        );
    }

    /**
     * @param  Collection<int, BusinessValuation>  $valuations
     * @return SectionPayload
     */
    private function pvMilestonesSection(Client $client, Collection $valuations): array
    {
        if ($valuations->isEmpty()) {
            return $this->generatedSection(
                key: 'pv_milestones',
                title: 'PV milestones',
                body: 'PV milestones are not available yet.',
                sourceReference: 'business_valuations:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: milestone analysis is pending persisted valuations.',
            );
        }

        $body = $valuations
            ->map(fn (BusinessValuation $valuation): string => sprintf(
                '%s: NZD %s midpoint',
                $valuation->as_at?->toDateString() ?? 'undated',
                number_format($valuation->reconciled_mid, 0),
            ))
            ->implode("\n");

        return $this->generatedSection(
            key: 'pv_milestones',
            title: 'PV milestones',
            body: $body,
            sourceReference: 'business_valuations:'.$client->getKey(),
            dataQualityNote: 'Data quality note: milestones are based on persisted business valuation rows.',
            metadata: [
                'valuation_ids' => $valuations->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @return SectionPayload
     */
    private function goalOutcomeSection(Client $client): array
    {
        $goals = Goal::query()
            ->with(['baselineBusinessValuation', 'latestBusinessValuation', 'milestones'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->get();

        if ($goals->isEmpty()) {
            return $this->generatedSection(
                key: 'goal_outcomes',
                title: 'Goal outcome measurement',
                body: 'No PV-linked goals have been recorded yet.',
                sourceReference: 'goals:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: goal outcome measurement starts once advisor goals have a baseline and target.',
            );
        }

        $body = $goals
            ->map(function (Goal $goal): string {
                $baseline = $goal->baselineBusinessValuation;
                $current = $goal->latestBusinessValuation;
                $baselineValue = $baseline instanceof BusinessValuation ? $baseline->reconciled_mid : null;
                $currentValue = $current instanceof BusinessValuation ? $current->reconciled_mid : null;
                $target = (float) $goal->pv_target;
                $gap = $currentValue === null || $target <= 0
                    ? null
                    : $target - $currentValue;
                $realised = round((float) $goal->milestones
                    ->where('status', Milestone::STATUS_COMPLETED)
                    ->sum('pv_of_impact'), 2);

                return sprintf(
                    '%s: baseline %s; current %s; target %s%s; verified milestone PV %s; status %s.',
                    $goal->title,
                    $baselineValue === null ? 'not measured' : 'NZD '.number_format($baselineValue, 0),
                    $currentValue === null ? 'not re-measured' : 'NZD '.number_format($currentValue, 0),
                    $target > 0 ? 'NZD '.number_format($target, 0) : 'not set',
                    $gap === null ? '' : '; gap '.($gap <= 0 ? 'met by NZD '.number_format(abs($gap), 0) : 'NZD '.number_format($gap, 0)),
                    'NZD '.number_format($realised, 0),
                    str_replace('_', ' ', $goal->status),
                );
            })
            ->implode("\n");

        return $this->generatedSection(
            key: 'goal_outcomes',
            title: 'Goal outcome measurement',
            body: $body,
            sourceReference: 'goals:'.$client->getKey(),
            dataQualityNote: 'Data quality note: baseline and current PV use persisted business valuations; verified milestone PV explains the evidence-backed contribution and still requires advisor review.',
            metadata: [
                'goal_ids' => $goals->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, FinancialSnapshot>  $snapshots
     * @param  Collection<int, BusinessValuation>  $valuations
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return SectionPayload
     */
    private function trajectoryNarrativeSection(Client $client, Collection $snapshots, Collection $valuations, Collection $findings): array
    {
        $firstValuation = $valuations->first();
        $latestValuation = $valuations->last();
        $pvChange = $firstValuation instanceof BusinessValuation && $latestValuation instanceof BusinessValuation
            ? $latestValuation->reconciled_mid - $firstValuation->reconciled_mid
            : null;
        $currentFindingTitles = $findings
            ->take(3)
            ->pluck('title')
            ->implode('; ');
        $body = sprintf(
            "Auto-generated narrative for advisor review.\nSnapshots reviewed: %s.\nPV movement: %s.\nCurrent focus: %s.",
            $snapshots->count(),
            $pvChange === null ? 'not enough valuation milestones' : 'NZD '.number_format($pvChange, 0),
            $currentFindingTitles !== '' ? $currentFindingTitles : 'no current findings',
        );

        return $this->generatedSection(
            key: 'trajectory_narrative',
            title: 'Advisor-reviewed trajectory narrative',
            body: $body,
            sourceReference: 'trajectory_report:'.$client->getKey(),
            dataQualityNote: 'Data quality note: narrative is generated from persisted report inputs and requires advisor review before sharing.',
            metadata: [
                'advisor_review_required' => true,
            ],
        );
    }

    /**
     * @param  SectionMetadata  $metadata
     * @return SectionPayload
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
            'data_quality_note' => $dataQualityNote ?: '',
            'metadata' => $metadata,
        ];
    }

    /**
     * @return list<AttributionPayload>
     */
    private function attributions(AnalysisFinding $finding): array
    {
        $attributions = $finding->attributions;
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
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED => 'Backed by verified documents you uploaded.',
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG => 'Includes a document point that needs advisor review.',
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY => 'Includes a document discrepancy that needs resolution.',
            default => '',
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
            ? ''
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

    private function formatMetric(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'n/a';
        }

        return number_format((float) $value, abs((float) $value) < 1 ? 2 : 0);
    }

    /**
     * @param  callable(Report): array<string, bool|float|int|string|array<int, string>>  $after
     * @param  array<int, string>  $load
     */
    private function renderAndAuditAfterCommit(
        Report $report,
        ?User $actor,
        string $action,
        callable $after,
        array $load,
        bool $withPptx = false,
    ): void {
        $callback = function () use ($report, $actor, $action, $after, $load, $withPptx): void {
            $report->refresh()->load($load);
            $this->artifacts->render($report, $withPptx);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after($report->refresh()));
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
