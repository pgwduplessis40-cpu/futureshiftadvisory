<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\DocumentVerification;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\NpoGovernanceReviewReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\NpoGovernanceReviewInputs;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the NPO Governance Review report type and its board-ready section contract.
 *
 * @phpstan-type GovernanceFindings Collection<int, GovernanceReviewFinding>
 * @phpstan-type Attribution array{claim: string, source_reference: string}
 */
final class NpoGovernanceReviewReportComposer implements NpoGovernanceReviewReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(NpoEngagement $engagement, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement);

        return DB::transaction(function () use ($engagement, $inputs, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::GovernanceReview,
                'title' => ReportType::GovernanceReview->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5a',
                    'npo_engagement_id' => $engagement->getKey(),
                    'legal_structure' => $engagement->legal_structure->value,
                    'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
                    'reviewed_finding_ids' => $inputs->findings->map(fn (GovernanceReviewFinding $finding): string => (string) $finding->getKey())->values()->all(),
                    's42g_statement_required' => true,
                    'legal_disclaimer_required' => true,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections($report, $inputs->client, $this->sections($engagement, $inputs->client, $inputs->findings, $actor));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.governance_review_report_generated',
                fn (Report $rendered): array => [
                    'client_id' => $inputs->client->getKey(),
                    'npo_engagement_id' => $engagement->getKey(),
                    'sections' => $rendered->sections()->count(),
                    'reviewed_findings' => $inputs->findings->count(),
                    'pdf_path' => $rendered->pdf_path,
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    private function inputs(NpoEngagement $engagement): NpoGovernanceReviewInputs
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Governance Review reports require an NPO engagement with a client.');
        }

        if ($engagement->sub_type !== NpoEngagementSubType::GovernanceReview) {
            throw new InvalidArgumentException('Only governance-review NPO engagements can generate a Governance Review Report.');
        }

        /** @var GovernanceFindings $findings */
        $findings = GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('status', GovernanceReviewFinding::STATUS_REVIEWED)
            ->get()
            ->sortBy(fn (GovernanceReviewFinding $finding): string => sprintf(
                '%02d-%s-%s',
                $this->severityPosition($finding->severity),
                $finding->category,
                $finding->finding_key,
            ))
            ->values();

        if ($findings->isEmpty()) {
            throw new InvalidArgumentException('Governance Review Report requires advisor-reviewed governance findings.');
        }

        return new NpoGovernanceReviewInputs($client, $findings);
    }

    /**
     * @param  GovernanceFindings  $findings
     * @return list<ReportSectionDraft>
     */
    private function sections(NpoEngagement $engagement, Client $client, Collection $findings, ?User $actor): array
    {
        return [
            $this->executiveSummary($engagement, $client, $findings),
            $this->s42gStatement($engagement, $client, $findings, $actor),
            $this->findingSection($engagement, $findings, ['board_composition'], 'board_composition_skills', 'Board composition and skills assessment', 'No advisor-reviewed board composition finding is available yet.'),
            $this->findingSection($engagement, $findings, ['constitution_currency', 'legal_structure_compliance'], 'constitution_currency', 'Constitution and statutory currency', 'No advisor-reviewed constitution currency finding is available yet.'),
            $this->findingSection($engagement, $findings, ['conflicts_of_interest'], 'conflicts_of_interest', 'Conflicts of interest framework', 'No advisor-reviewed conflicts-of-interest finding is available yet.'),
            $this->findingSection($engagement, $findings, ['financial_oversight'], 'financial_oversight', 'Financial oversight', 'No advisor-reviewed financial oversight finding is available yet.'),
            $this->complianceStatus($engagement, $findings),
            $this->actionPlan($engagement, $findings),
            $this->legalDisclaimer($engagement),
        ];
    }

    /** @param GovernanceFindings $findings */
    private function executiveSummary(NpoEngagement $engagement, Client $client, Collection $findings): ReportSectionDraft
    {
        $priorityFindings = $findings->filter(
            fn (GovernanceReviewFinding $finding): bool => in_array($finding->severity, [FindingSeverity::Critical, FindingSeverity::High], true),
        );

        return $this->section(
            key: 'executive_summary',
            title: 'Executive summary',
            body: sprintf(
                "Governance Review for %s.\nLegal structure: %s.\nAdvisor-reviewed findings: %d.\nPriority governance risks: %d.\n\n%s",
                $client->legal_name,
                $engagement->legal_structure->label(),
                $findings->count(),
                $priorityFindings->count(),
                $priorityFindings->isEmpty()
                    ? 'No critical or high governance findings are currently marked for the board-ready report.'
                    : 'Priority findings: '.$priorityFindings->pluck('title')->take(5)->implode('; '),
            ),
            findings: $findings,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /** @param GovernanceFindings $findings */
    private function s42gStatement(NpoEngagement $engagement, Client $client, Collection $findings, ?User $actor): ReportSectionDraft
    {
        $advisor = $actor instanceof User ? trim($actor->name.' <'.$actor->email.'>') : 'Future Shift Advisory advisor';
        $legalStructure = $engagement->legal_structure->label();
        $applicability = str_contains(strtolower($legalStructure), 'charity')
            ? 's.42G officer eligibility and governance evidence are in scope for this registered-charity review.'
            : 's.42G is recorded as a charity-specific governance lens; applicability should be confirmed against the organisation registration status.';

        return $this->section(
            key: 's42g_evidence_statement',
            title: 's.42G evidence statement',
            body: sprintf(
                "s.42G evidence statement date: %s.\nScope: Governance Review Report for %s, engagement %s.\nAdvisor: %s.\nLegal structure reviewed: %s.\nEvidence base: %d advisor-reviewed governance finding(s), source attributions, questionnaire evidence, and registry/compliance status where available.\n%s",
                now()->toDateString(),
                $client->legal_name,
                $engagement->getKey(),
                $advisor,
                $legalStructure,
                $findings->count(),
                $applicability,
            ),
            findings: $findings->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, ['legal_structure_compliance', 'constitution_currency'], true))->values(),
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
            metadata: ['mandatory' => true],
        );
    }

    /**
     * @param  GovernanceFindings  $findings
     * @param  list<string>  $keys
     */
    private function findingSection(NpoEngagement $engagement, Collection $findings, array $keys, string $sectionKey, string $title, string $fallback): ReportSectionDraft
    {
        $selected = $findings->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, $keys, true))->values();

        return $this->section(
            key: $sectionKey,
            title: $title,
            body: $selected->isEmpty()
                ? $fallback
                : $selected->map(fn (GovernanceReviewFinding $finding): string => sprintf('%s [%s]: %s', $finding->title, $finding->severity->value, $finding->body))->implode("\n\n"),
            findings: $selected,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /** @param GovernanceFindings $findings */
    private function complianceStatus(NpoEngagement $engagement, Collection $findings): ReportSectionDraft
    {
        $selected = $findings->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, [
            'legal_structure_compliance', 'constitution_currency', 'paid_staff_holidays_act', 'unregistered_structure_governance',
        ], true))->values();

        return $this->section(
            key: 'compliance_status',
            title: 'Compliance status by relevant legislation',
            body: $selected->isEmpty()
                ? 'No advisor-reviewed legal-structure compliance finding is available yet.'
                : $selected->map(fn (GovernanceReviewFinding $finding): string => sprintf('%s [%s]: %s', $finding->title, $finding->severity->value, $finding->body))->implode("\n\n"),
            findings: $selected,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /** @param GovernanceFindings $findings */
    private function actionPlan(NpoEngagement $engagement, Collection $findings): ReportSectionDraft
    {
        $priorities = $findings->reject(fn (GovernanceReviewFinding $finding): bool => $finding->finding_key === 'evidence_depth')->take(6)->values();

        return $this->section(
            key: 'twelve_month_action_plan',
            title: '12-month governance action plan',
            body: $priorities->isEmpty()
                ? '12-month governance action plan is pending advisor-reviewed findings.'
                : $priorities->map(function (GovernanceReviewFinding $finding, int $index): string {
                    $window = match ($index) {
                        0 => '0-30 days', 1, 2 => '31-90 days', 3, 4 => '3-6 months', default => '6-12 months',
                    };

                    return sprintf('%s: %s - %s', $window, $finding->title, $this->firstSentence($finding->body));
                })->implode("\n"),
            findings: $priorities,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    private function legalDisclaimer(NpoEngagement $engagement): ReportSectionDraft
    {
        /** @var GovernanceFindings $findings */
        $findings = collect();

        return $this->section(
            key: 'legal_disclaimer',
            title: 'Legal disclaimer',
            body: 'This Governance Review Report is prepared for governance discussion and decision support only. It is advisory in nature and is not legal advice, a legal opinion, or a substitute for independent legal advice on the Incorporated Societies Act 2022, Charities Act 2005, Charities Amendment Act 2023, trust law, employment law, tax, funding, or any other statutory obligation.',
            findings: $findings,
            sourceReference: 'npo_governance_disclaimer:'.$engagement->getKey(),
            metadata: ['mandatory' => true],
        );
    }

    /**
     * @param  GovernanceFindings  $findings
     * @param  array<string, bool|list<string>>  $metadata
     */
    private function section(string $key, string $title, string $body, Collection $findings, string $sourceReference, array $metadata = []): ReportSectionDraft
    {
        $documentSupport = $this->documentSupport($findings);
        $findingIds = $findings->map(fn (GovernanceReviewFinding $finding): string => (string) $finding->getKey())->values()->all();
        $uncertainty = $findings->map(fn (GovernanceReviewFinding $finding): string => $finding->uncertainty->value)->unique()->values()->all();

        return new ReportSectionDraft(
            key: $key,
            title: $title,
            body: $body,
            attributions: $this->attributions($findings, $title, $sourceReference),
            documentSupport: $documentSupport,
            documentSupportNote: $this->documentSupportNote($documentSupport),
            dataQualityNote: $this->dataQualityNote($findings),
            metadata: [
                ...$metadata,
                'governance_finding_ids' => $findingIds,
                'uncertainty' => $uncertainty,
            ],
        );
    }

    private function severityPosition(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::Critical => 1,
            FindingSeverity::High => 2,
            FindingSeverity::Medium => 3,
            FindingSeverity::Low => 4,
            FindingSeverity::Info => 5,
        };
    }

    /** @param GovernanceFindings $findings */
    private function documentSupport(Collection $findings): string
    {
        $documentIds = $findings
            ->flatMap(fn (GovernanceReviewFinding $finding): array => $this->documentIds($finding->evidence))
            ->filter()
            ->unique()
            ->values();

        if ($documentIds->isEmpty()) {
            return AnalysisFinding::DOCUMENT_SUPPORT_NONE;
        }

        $verifications = DocumentVerification::query()->whereIn('document_id', $documentIds->all())->get();

        return match (true) {
            $verifications->contains('outcome', DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY) => AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY,
            $verifications->contains('outcome', DocumentVerification::OUTCOME_ADVISORY_FLAG) => AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG,
            $verifications->contains('outcome', DocumentVerification::OUTCOME_VERIFIED) => AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
            default => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        };
    }

    /** @return list<string> */
    private function documentIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        $documentIds = $value['attached_document_ids'] ?? null;
        if (is_array($documentIds)) {
            foreach ($documentIds as $documentId) {
                if (is_scalar($documentId) && trim((string) $documentId) !== '') {
                    $ids[] = trim((string) $documentId);
                }
            }
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $ids = [...$ids, ...$this->documentIds($child)];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  GovernanceFindings  $findings
     * @return list<Attribution>
     */
    private function attributions(Collection $findings, string $title, string $sourceReference): array
    {
        $attributions = [];

        foreach ($findings as $finding) {
            foreach ($finding->attributions as $attribution) {
                if (! is_array($attribution)) {
                    continue;
                }

                $claim = $attribution['claim'] ?? $title;
                $reference = $attribution['source_reference'] ?? '';
                if (is_scalar($claim) && is_scalar($reference) && trim((string) $claim) !== '' && trim((string) $reference) !== '') {
                    $attributions[] = ['claim' => (string) $claim, 'source_reference' => (string) $reference];
                }
            }
        }

        if ($attributions === []) {
            return [['claim' => $title, 'source_reference' => $sourceReference]];
        }

        return array_values(array_reduce($attributions, function (array $unique, array $attribution): array {
            $key = $attribution['claim'].'|'.$attribution['source_reference'];
            $unique[$key] = $attribution;

            return $unique;
        }, []));
    }

    /** @param GovernanceFindings $findings */
    private function dataQualityNote(Collection $findings): string
    {
        if ($findings->isEmpty()) {
            return 'Data quality note: section is mandatory report text and should be read with the advisor-reviewed governance evidence pack.';
        }

        $uncertainties = $findings
            ->map(fn (GovernanceReviewFinding $finding): string => $finding->uncertainty->value)
            ->unique()
            ->values()
            ->implode(', ');

        return sprintf('Data quality note: based on %d advisor-reviewed, source-attributed governance finding(s). Recorded uncertainty: %s.', $findings->count(), $uncertainties !== '' ? $uncertainties : 'not recorded');
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

    private function firstSentence(string $body): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/', trim($body), 2);

        return trim(is_array($parts) && isset($parts[0]) ? $parts[0] : $body);
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

    /** @param Closure(Report): array<string, bool|int|string|null> $after */
    private function renderAndAuditAfterCommit(Report $report, ?User $actor, string $action, Closure $after): void
    {
        $callback = function () use ($report, $actor, $action, $after): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after($report->refresh()));
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
