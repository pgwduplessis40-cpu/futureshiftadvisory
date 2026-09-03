<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\SimpleTextPdf;
use App\Services\Reports\BrandedReportLayout;
use Illuminate\Support\Str;
use Throwable;

final class FunderReadyBusinessPlanBuilder
{
    /**
     * The master template is intentionally a concise transformation of the
     * canonical plan requirements, rather than a second set of founder inputs.
     * Every current canonical requirement is represented at least once below.
     *
     * @var array<int, array{number:int,title:string,keys:array<int,string>,format:string}>
     */
    private const SECTION_MAP = [
        ['number' => 1, 'title' => 'Executive Summary', 'keys' => ['executive-summary'], 'format' => 'summary'],
        ['number' => 2, 'title' => 'Company Description', 'keys' => ['business-type-location', 'mission-vision'], 'format' => 'narrative'],
        ['number' => 3, 'title' => 'Market Research', 'keys' => ['industry-context', 'differentiation', 'competitor-comparison'], 'format' => 'table'],
        ['number' => 4, 'title' => 'Service Line & Pricing', 'keys' => ['revenue-model', 'success-factors'], 'format' => 'table'],
        ['number' => 5, 'title' => 'Organisation & Team', 'keys' => ['organisation-management'], 'format' => 'table'],
        ['number' => 6, 'title' => 'Systems & Operations', 'keys' => ['systems-software-processes', 'risk-register'], 'format' => 'narrative'],
        ['number' => 7, 'title' => 'Legal, IP & Compliance', 'keys' => ['legal-environment', 'intellectual-property'], 'format' => 'table'],
        ['number' => 8, 'title' => 'Culture & Values', 'keys' => ['culture'], 'format' => 'narrative'],
        ['number' => 9, 'title' => 'Goals, Objectives & Milestones', 'keys' => ['goals-objectives', 'risk-register'], 'format' => 'table'],
        ['number' => 10, 'title' => 'Finance', 'keys' => ['financial-assumptions', 'revenue-model', 'launch-funding', 'budget-runway'], 'format' => 'finance'],
    ];

    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly SimpleTextPdf $fallbackPdf,
        private readonly BrandedReportLayout $layout,
        private readonly BusinessPlanIdentity $identity,
        private readonly BusinessPlanExecutiveSummary $executiveSummaries,
        private readonly PlanIssueReadiness $issueReadiness,
        private readonly BudgetPackBuilder $budgetPack,
        private readonly EntrepreneurDocumentTemplate $templates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $issueReadiness = $this->issueReadiness->evaluate($plan);
        $summary = $this->executiveSummaries->status($plan, $profile);
        $reasons = (array) ($issueReadiness['reasons'] ?? []);

        if (! (bool) ($summary['usable'] ?? false)) {
            $reasons[] = 'A current executive summary generated after a passing assessment is required before lender review.';
        }

        $reasons = array_values(array_unique(array_filter($reasons)));
        $ready = (bool) ($issueReadiness['external_issue_ready'] ?? false)
            && (bool) ($summary['usable'] ?? false);

        return [
            'ready' => $ready,
            'label' => $ready ? 'Ready for lender review' : 'Draft - gaps to resolve',
            'tone' => $ready ? 'good' : 'high',
            'reasons' => $reasons,
            'requirements_completed' => (int) ($issueReadiness['requirements_completed'] ?? 0),
            'requirements_total' => (int) ($issueReadiness['requirements_total'] ?? 0),
            'requirements_missing' => (int) ($issueReadiness['requirements_missing'] ?? 0),
            'evidence_count' => (int) ($issueReadiness['evidence_count'] ?? 0),
            'executive_summary' => $summary,
            'budget' => (array) ($issueReadiness['budget'] ?? []),
        ];
    }

    public function filename(EntrepreneurProfile $profile): string
    {
        return Str::slug($profile->name ?: 'entrepreneur').'-funder-ready-business-plan.pdf';
    }

    public function pdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $document = $this->document($profile, $plan);

        try {
            return $this->pdf->render($this->html($document));
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback($document);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function document(EntrepreneurProfile $profile, BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $entries = $this->entries($plan);
        $summary = $this->executiveSummaries->status($plan, $profile);
        if (! (bool) ($summary['usable'] ?? false) && isset($entries[BusinessPlanExecutiveSummary::REQUIREMENT_KEY])) {
            $entries[BusinessPlanExecutiveSummary::REQUIREMENT_KEY]['body'] = '';
            $entries[BusinessPlanExecutiveSummary::REQUIREMENT_KEY]['complete'] = false;
        }
        $sourceBodies = collect($entries)
            ->pluck('body')
            ->filter(fn (mixed $body): bool => is_string($body) && trim($body) !== '')
            ->values()
            ->all();
        $businessName = $this->identity->businessName($profile, $plan, $sourceBodies);
        $status = $this->status($plan, $profile);
        $budget = $this->budgetPack->payload($profile, $plan);
        $snapshotPayload = json_encode([
            'plan' => $plan->getKey(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'entries' => collect($entries)->map(fn (array $entry): array => [
                $entry['key'],
                $entry['body'],
                $entry['evidence_count'],
            ])->values()->all(),
            'budget_updated_at' => $plan->budgetRunway?->updated_at?->toIso8601String(),
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) ?: '';

        return [
            'profile' => $profile,
            'plan' => $plan,
            'business_name' => $businessName,
            'entries' => $entries,
            'status' => $status,
            'budget' => $budget,
            'generated_at' => now()->format('M j, Y g:i A'),
            'source_snapshot' => substr(hash('sha256', $snapshotPayload), 0, 12),
        ];
    }

    /**
     * @return array<string, array{key:string,title:string,body:string,evidence_count:int,complete:bool}>
     */
    private function entries(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');

        return collect(PlanRequirements::definitions())
            ->flatMap(function (array $definition, string $phaseKey): array {
                return collect($definition['requirements'])
                    ->map(fn (array $requirement): array => [
                        ...$requirement,
                        'phase_key' => $phaseKey,
                    ])
                    ->all();
            })
            ->mapWithKeys(function (array $requirement) use ($plan): array {
                $key = (string) $requirement['key'];
                $isBudget = ($requirement['type'] ?? null) === 'budget';
                $section = $isBudget ? null : $plan->sections->first(fn (PlanSection $candidate): bool => (
                    (string) data_get($candidate->metadata, 'requirement_key') === $key
                    || $candidate->key === 'founder-'.(string) $requirement['phase_key'].'-'.$key
                ));
                $body = $section instanceof PlanSection
                    ? $this->cleanBody((string) $section->body)
                    : '';

                return [$key => [
                    'key' => $key,
                    'title' => (string) $requirement['title'],
                    'body' => $body,
                    'evidence_count' => $section instanceof PlanSection ? count((array) $section->attached_document_ids) : 0,
                    'complete' => $isBudget
                        ? $plan->budgetRunway?->status === 'complete'
                        : $section?->completeness_status === PlanSection::STATUS_COMPLETE,
                ]];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function html(array $document): string
    {
        $profile = $document['profile'];
        $plan = $document['plan'];
        $businessName = $document['business_name'];
        $status = $document['status'];
        $budget = $document['budget'];
        $entries = $document['entries'];
        $title = 'Funder-Ready Business Plan'.($businessName === null ? '' : ' - '.$businessName);
        $content = $this->draftNoticeHtml($status)
            .$this->founderResponsibilityHtml($profile, $businessName)
            .$this->contentsHtml();

        foreach (self::SECTION_MAP as $section) {
            $content .= $this->sectionHtml($section, $entries, $budget);
        }

        $template = $this->templates->businessPlan();

        return $this->layout->document(
            title: $title,
            templateKey: $template?->getKey() ?? EntrepreneurDocumentTemplate::BUSINESS_PLAN,
            documentTag: 'Funder-ready plan',
            eyebrow: 'Master funder-ready business plan template v1.0',
            heading: $businessName === null ? 'Funder-Ready Business Plan' : $businessName,
            subheading: 'Founder - '.$profile->name,
            meta: [
                'Status' => (string) ($status['label'] ?? 'Draft - gaps to resolve'),
                'Source coverage' => (int) ($status['requirements_completed'] ?? 0).'/'.(int) ($status['requirements_total'] ?? 0).' requirements',
                'Evidence files' => (string) ($status['evidence_count'] ?? 0),
                'Snapshot' => (string) $document['source_snapshot'],
                'Prepared' => (string) $document['generated_at'],
                'Currency' => 'NZD, GST exclusive',
            ],
            contentHtml: $content,
            footer: 'Future Shift Advisory | Master Funder-Ready Business Plan v1.0 | '.((bool) ($status['ready'] ?? false) ? 'Ready for lender review' : 'INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE'),
            template: $template,
            snapshotTitle: 'Document controls',
            metaColumns: 3,
            extraCss: $this->css(),
        );
    }

    private function contentsHtml(): string
    {
        $rows = collect(self::SECTION_MAP)
            ->map(fn (array $section): string => sprintf(
                '<tr><td class="funder-index-number">%02d</td><td>%s</td></tr>',
                $section['number'],
                $this->escape($section['title']),
            ))
            ->implode('');

        return '<article class="funder-contents"><p class="funder-kicker">Master template structure</p><h2>Contents</h2><table><tbody>'.$rows.'</tbody></table></article>';
    }

    /**
     * @param  array{number:int,title:string,keys:array<int,string>,format:string}  $section
     * @param  array<string, array{key:string,title:string,body:string,evidence_count:int,complete:bool}>  $entries
     * @param  array<string, mixed>  $budget
     */
    private function sectionHtml(array $section, array $entries, array $budget): string
    {
        $mappedEntries = collect($section['keys'])
            ->map(fn (string $key): ?array => $entries[$key] ?? null)
            ->filter()
            ->values()
            ->all();
        $heading = $section['number'].'. '.$section['title'];

        if ($section['format'] === 'summary') {
            $entry = $mappedEntries[0] ?? null;
            $body = is_array($entry) && trim((string) $entry['body']) !== ''
                ? $this->markdownBodyHtml((string) $entry['body'])
                : '<p class="funder-missing">The executive summary has not been generated from the current plan and budget.</p>';

            return '<article class="funder-section"><header><p class="funder-kicker">Section '.$section['number'].'</p><h2>'.$this->escape($heading).'</h2></header><div class="funder-summary-copy">'.$body.'</div></article>';
        }

        if ($section['format'] === 'finance') {
            return $this->financeHtml($heading, $mappedEntries, $budget);
        }

        $body = $section['format'] === 'table'
            ? $this->mappedTableHtml($mappedEntries, $section['title'])
            : $this->mappedNarrativeHtml($mappedEntries);

        return '<article class="funder-section"><header><p class="funder-kicker">Section '.$section['number'].'</p><h2>'.$this->escape($heading).'</h2></header>'.$body.'</article>';
    }

    /**
     * @param  array<int, array{key:string,title:string,body:string,evidence_count:int,complete:bool}>  $entries
     */
    private function mappedNarrativeHtml(array $entries): string
    {
        $html = collect($entries)
            ->map(function (array $entry): string {
                $body = trim((string) $entry['body']) === ''
                    ? '<p class="funder-missing">No completed response is available for this required source area.</p>'
                    : $this->markdownBodyHtml((string) $entry['body']);

                return '<section class="funder-entry"><div><h3>'.$this->escape($entry['title']).'</h3><span>'.$this->evidenceLabel((int) $entry['evidence_count']).'</span></div><div class="funder-entry-copy">'.$body.'</div></section>';
            })
            ->implode('');

        return $html === '' ? '<p class="funder-missing">No source content is available for this section.</p>' : $html;
    }

    /**
     * @param  array<int, array{key:string,title:string,body:string,evidence_count:int,complete:bool}>  $entries
     */
    private function mappedTableHtml(array $entries, string $sectionTitle): string
    {
        $rows = collect($entries)
            ->map(function (array $entry): string {
                $detail = trim((string) $entry['body']) === ''
                    ? '<span class="funder-missing">No completed response is available.</span>'
                    : $this->markdownBodyHtml((string) $entry['body']);

                return '<tr><td><strong>'.$this->escape($entry['title']).'</strong></td><td>'.$detail.'</td><td>'.$this->escape($this->evidenceLabel((int) $entry['evidence_count'])).'</td></tr>';
            })
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="funder-missing">No source content is available for this section.</td></tr>';
        }

        return '<table class="funder-detail-table"><thead><tr><th>'.$this->escape($sectionTitle).' input</th><th>Current lender-facing content</th><th>Evidence</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param  array<int, array{key:string,title:string,body:string,evidence_count:int,complete:bool}>  $entries
     * @param  array<string, mixed>  $budget
     */
    private function financeHtml(string $heading, array $entries, array $budget): string
    {
        $narrative = $this->mappedNarrativeHtml(array_values(array_filter($entries, fn (array $entry): bool => $entry['key'] !== 'budget-runway')));
        $decision = (array) ($budget['funding_decision'] ?? []);
        $summary = (array) ($budget['summary'] ?? []);
        $annual = collect((array) ($budget['annual_totals'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>Year %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape((string) ($row['year'] ?? '-')),
                $this->money($row['revenue'] ?? 0),
                $this->money($row['gross_profit'] ?? 0),
                $this->money($row['fixed_costs'] ?? 0),
                $this->money($row['net_profit_before_tax'] ?? 0),
                $this->money($row['ending_cash'] ?? 0),
            ))
            ->implode('');
        $useOfFunds = collect((array) ($budget['use_of_funds'] ?? []))
            ->map(fn (array $row): string => '<tr><td>'.$this->escape((string) ($row['label'] ?? 'Funding item')).'</td><td>'.$this->money($row['amount'] ?? 0).'</td><td>'.$this->escape((string) ($row['note'] ?? '')).'</td></tr>')
            ->implode('');
        $scenarios = collect((array) ($budget['scenarios'] ?? []))
            ->map(fn (array $scenario): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape((string) ($scenario['name'] ?? 'Scenario')),
                $this->escape((string) ($scenario['sensitivity_label'] ?? 'Forecast test')),
                $this->yearValue(data_get($scenario, 'summary.break_even_year')),
                $this->money($scenario['lowest_cash'] ?? 0),
                $this->money($scenario['additional_funding_needed'] ?? 0),
            ))
            ->implode('');
        $assumptions = collect((array) ($budget['assumptions'] ?? []))
            ->map(fn (array $assumption): string => '<tr><td>'.$this->escape((string) ($assumption['label'] ?? 'Assumption')).'</td><td>'.$this->escape((string) ($assumption['value'] ?? 'Not set')).'</td><td>'.$this->escape((string) ($assumption['basis'] ?? 'Founder/advisor input')).'</td><td>'.$this->escape((string) ($assumption['review_note'] ?? '')).'</td></tr>')
            ->implode('');
        $budgetReady = (bool) data_get($budget, 'funding_decision.external_issue_ready', false);

        return '<article class="funder-section funder-finance"><header><p class="funder-kicker">Section 10</p><h2>'.$this->escape($heading).'</h2></header>'
            .$narrative
            .'<section class="funder-finance-block"><h3>10.1 Financial Snapshot</h3><table><tbody>'
            .'<tr><th>Declared funding position</th><td>'.$this->escape((string) ($decision['funding_position_label'] ?? 'Funding position not confirmed')).'</td></tr>'
            .'<tr><th>Lowest cash position</th><td>'.$this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null).'</td></tr>'
            .'<tr><th>Required additional funding</th><td>'.$this->money($decision['required_additional_funding'] ?? 0).'</td></tr>'
            .'<tr><th>Funding available</th><td>'.$this->money($decision['available_funding'] ?? 0).'</td></tr>'
            .'<tr><th>Break-even timing</th><td>'.$this->yearValue($summary['break_even_year'] ?? null).'</td></tr>'
            .'<tr><th>Cash-positive timing</th><td>'.$this->yearValue($summary['cash_flow_positive_year'] ?? null).'</td></tr>'
            .'</tbody></table></section>'
            .'<section class="funder-finance-block"><h3>10.2 Annual Forecast</h3><table><thead><tr><th>Year</th><th>Revenue</th><th>Gross profit</th><th>Fixed costs</th><th>Net profit before tax</th><th>Ending cash</th></tr></thead><tbody>'.($annual === '' ? '<tr><td colspan="6" class="funder-missing">No annual forecast has been saved.</td></tr>' : $annual).'</tbody></table></section>'
            .'<section class="funder-finance-block"><h3>10.3 Key Assumptions</h3><table><thead><tr><th>Assumption</th><th>Value</th><th>Basis</th><th>Review note</th></tr></thead><tbody>'.($assumptions === '' ? '<tr><td colspan="4" class="funder-missing">No assumptions have been saved.</td></tr>' : $assumptions).'</tbody></table></section>'
            .'<section class="funder-finance-block"><h3>10.4 Break-Even &amp; Sensitivity Summary</h3><table><thead><tr><th>Scenario</th><th>Test</th><th>Break-even</th><th>Lowest cash</th><th>Cash need</th></tr></thead><tbody>'.($scenarios === '' ? '<tr><td colspan="5" class="funder-missing">No sensitivity scenarios have been saved.</td></tr>' : $scenarios).'</tbody></table></section>'
            .'<section class="funder-finance-block"><h3>10.5 Funding Request &amp; Use of Funds</h3><p class="funder-finance-headline">'.$this->escape((string) ($decision['headline'] ?? 'Complete the budget pack before relying on the funding position externally.')).'</p><table><thead><tr><th>Category</th><th>Amount</th><th>Why it matters</th></tr></thead><tbody>'.($useOfFunds === '' ? '<tr><td colspan="3" class="funder-missing">No use-of-funds data has been saved.</td></tr>' : $useOfFunds).'</tbody></table></section>'
            .'<section class="funder-finance-block funder-reconciliation"><h3>10.6 Reconciliation Gate</h3><p>'.($budgetReady && (bool) ($decision['funding_position_aligned'] ?? false) ? 'The linked Budget Pack has passed its external-issue checks and its declared funding position is aligned to the forecast. This plan remains subject to the document controls above.' : 'This plan is an internal draft until the linked Budget Pack, source evidence, and written funding position all reconcile.').'</p></section>'
            .'<section class="funder-finance-block"><h3>10.7 Path to Profitability / Repayment Capacity</h3><p>Break-even is '.$this->escape($this->yearValue($summary['break_even_year'] ?? null)).'; cumulative cash turns positive '.$this->escape($this->yearValue($summary['cash_flow_positive_year'] ?? null)).'. Lender repayment capacity must be assessed against the saved cash curve, required funding, and downside scenarios above.</p></section>'
            .'</article>';
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function fallback(array $document): string
    {
        $profile = $document['profile'];
        $status = $document['status'];
        $entries = $document['entries'];
        $budget = $document['budget'];
        $title = 'Funder-Ready Business Plan'.($document['business_name'] === null ? '' : ' - '.$document['business_name']);
        $blocks = [[
            'type' => 'cover',
            'document_tag' => 'Funder-ready plan',
            'title' => $title,
            'subtitle' => 'Founder - '.$profile->name.((bool) ($status['ready'] ?? false) ? ' | READY FOR LENDER REVIEW' : ' | INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE'),
        ], ['type' => 'page_break'], [
            'type' => 'callout',
            'title' => (string) ($status['label'] ?? 'Draft - gaps to resolve'),
            'text' => (bool) ($status['ready'] ?? false) ? 'The current plan, summary, evidence, and linked Budget Pack satisfy the lender-review gate.' : implode(' ', array_slice((array) ($status['reasons'] ?? []), 0, 4)),
        ], [
            'type' => 'toc',
            'heading' => 'Contents',
            'items' => collect(self::SECTION_MAP)->map(fn (array $section): array => ['title' => $section['number'].'. '.$section['title']])->all(),
        ]];

        foreach (self::SECTION_MAP as $section) {
            $blocks[] = ['type' => 'page_break'];
            $blocks[] = ['type' => 'section', 'text' => $section['number'].'. '.$section['title']];

            if ($section['format'] === 'finance') {
                $this->appendFallbackFinance($blocks, $budget);

                continue;
            }

            foreach ($section['keys'] as $key) {
                $entry = $entries[$key] ?? null;
                if (! is_array($entry)) {
                    continue;
                }

                $blocks[] = [
                    'type' => 'entry',
                    'kicker' => 'Source plan area',
                    'title' => (string) $entry['title'],
                    'key_points' => [],
                    'body' => trim((string) $entry['body']) === '' ? 'No completed response is available for this source area.' : $this->plainText((string) $entry['body']),
                    'body_bullets' => $this->markdownBulletItems((string) $entry['body']),
                    'note' => $this->evidenceLabel((int) $entry['evidence_count']),
                ];
            }
        }

        return $this->fallbackPdf->renderStructured(
            'Funder-Ready Business Plan',
            $blocks,
            (bool) ($status['ready'] ?? false) ? 'Ready for lender review' : 'INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $budget
     */
    private function appendFallbackFinance(array &$blocks, array $budget): void
    {
        $decision = (array) ($budget['funding_decision'] ?? []);
        $summary = (array) ($budget['summary'] ?? []);
        $blocks[] = [
            'type' => 'table',
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Lowest cash position', $this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null)],
                ['Declared funding position', (string) ($decision['funding_position_label'] ?? 'Funding position not confirmed')],
                ['Required additional funding', $this->money($decision['required_additional_funding'] ?? 0)],
                ['Funding available', $this->money($decision['available_funding'] ?? 0)],
                ['Break-even', $this->yearValue($summary['break_even_year'] ?? null)],
                ['Cash positive', $this->yearValue($summary['cash_flow_positive_year'] ?? null)],
            ],
            'widths' => [1.2, 1],
        ];
        $blocks[] = ['type' => 'subsection', 'text' => 'Funding Request & Use of Funds'];
        $blocks[] = [
            'type' => 'paragraph',
            'text' => (string) ($decision['headline'] ?? 'No funding decision has been calculated yet.'),
        ];
        $blocks[] = [
            'type' => 'table',
            'headers' => ['Category', 'Amount', 'Why it matters'],
            'rows' => collect((array) ($budget['use_of_funds'] ?? []))
                ->map(fn (array $row): array => [
                    (string) ($row['label'] ?? 'Funding item'),
                    $this->money($row['amount'] ?? 0),
                    (string) ($row['note'] ?? ''),
                ])->all(),
            'widths' => [1.2, 0.7, 2],
        ];
        $blocks[] = ['type' => 'subsection', 'text' => 'Break-Even & Sensitivity Summary'];
        $blocks[] = [
            'type' => 'table',
            'headers' => ['Scenario', 'Break-even', 'Lowest cash', 'Cash need'],
            'rows' => collect((array) ($budget['scenarios'] ?? []))
                ->map(fn (array $scenario): array => [
                    (string) ($scenario['name'] ?? 'Scenario'),
                    $this->yearValue(data_get($scenario, 'summary.break_even_year')),
                    $this->money($scenario['lowest_cash'] ?? 0),
                    $this->money($scenario['additional_funding_needed'] ?? 0),
                ])->all(),
            'widths' => [1.2, 0.9, 1, 1],
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function draftNoticeHtml(array $status): string
    {
        if ((bool) ($status['ready'] ?? false)) {
            return '';
        }

        $reasons = collect((array) ($status['reasons'] ?? []))
            ->take(5)
            ->map(fn (string $reason): string => '<li>'.$this->escape($reason).'</li>')
            ->implode('');

        return '<div class="funder-draft-watermark">INTERNAL DRAFT</div><article class="funder-draft-notice"><h2>Draft - gaps to resolve before lender review</h2><p>This output is useful for advisor review, but must not be issued externally until the controls below pass.</p>'.($reasons === '' ? '' : '<ul>'.$reasons.'</ul>').'</article>';
    }

    private function founderResponsibilityHtml(EntrepreneurProfile $profile, ?string $businessName): string
    {
        $subject = $businessName === null ? 'This business plan' : 'This business plan for '.$businessName;
        $name = trim($profile->name) !== '' ? trim($profile->name) : 'the founder';

        return '<article class="funder-responsibility"><h2>Founder responsibility</h2><p>'.$this->escape($subject.' was generated from the current Future Shift Advisory workspace. '.$name.' remains responsible for the plan content, assumptions, evidence, and funding decisions.').'</p></article>';
    }

    private function evidenceLabel(int $count): string
    {
        return $count === 1 ? '1 supporting file' : $count.' supporting files';
    }

    private function cleanBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace('/^\s*(?:[,.;:!?]+\s*)+/', '', $body) ?? $body;

        return trim($body);
    }

    private function markdownBodyHtml(string $body): string
    {
        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function plainText(string $body): string
    {
        $html = $this->markdownBodyHtml($body);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @return array<int, string>
     */
    private function markdownBulletItems(string $body): array
    {
        return collect(preg_split('/\R/', $body) ?: [])
            ->filter(fn (string $line): bool => preg_match('/^\s*(?:[-*+]\s+|\d+[.)]\s+)/', $line) === 1)
            ->map(fn (string $line): string => preg_replace('/^\s*(?:[-*+]\s+|\d+[.)]\s+)/', '', $line) ?? $line)
            ->map(fn (string $item): string => $this->plainText($item))
            ->filter()
            ->values()
            ->all();
    }

    private function yearValue(mixed $value): string
    {
        return is_numeric($value) ? 'Year '.(int) $value : 'Not reached in forecast';
    }

    private function moneyWithMonth(mixed $value, mixed $month): string
    {
        if (! is_numeric($value)) {
            return 'Not calculated';
        }

        return $this->money($value).(is_numeric($month) ? ' in Month '.(int) $month : '');
    }

    private function money(mixed $value): string
    {
        $amount = (float) $value;

        return ($amount < 0 ? '-' : '').'$'.number_format(abs($amount), 0);
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function css(): string
    {
        return <<<'CSS'
.report-content { display: block; }
.report-hero { background: #fff; border: 0; border-left: 0; break-after: page; margin: 68px 0 0; min-height: 420px; padding: 0; }
.report-hero h1 { font-size: 31px; margin: 0 0 12px; }
.report-hero p { color: #39465a; font-size: 16px; }
.funder-draft-watermark { color: #b42318; font-size: 42px; font-weight: 700; left: 19%; letter-spacing: .08em; opacity: .1; pointer-events: none; position: fixed; top: 46%; transform: rotate(-28deg); z-index: 0; }
.funder-draft-notice, .funder-responsibility { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: avoid; margin-bottom: 16px; padding: 13px 15px; }
.funder-draft-notice { background: #fff1f0; border-left-color: #b42318; }
.funder-draft-notice h2 { color: #8a1c16; }
.funder-draft-notice h2, .funder-responsibility h2 { font-size: 14px; margin: 0 0 6px; }
.funder-draft-notice p, .funder-responsibility p { margin: 0; }
.funder-draft-notice ul { margin: 8px 0 0; padding-left: 18px; }
.funder-contents { border-top: 1px solid #ded6c7; break-after: page; break-before: page; margin-bottom: 20px; padding-top: 18px; }
.funder-kicker { color: #0d7a7a; font-size: 8.5px; font-weight: 700; letter-spacing: .06em; margin: 0 0 5px; text-transform: uppercase; }
.funder-contents h2 { color: #13233a; font-size: 19px; margin: 0 0 10px; }
.funder-contents table { margin: 0; }
.funder-contents td { border: 0; border-top: 1px solid #eee7db; padding: 9px 0; text-align: left; }
.funder-index-number { color: #0d7a7a; font-weight: 700; width: 42px; }
.funder-section { border: 0; border-top: 1px solid #ded6c7; break-inside: auto; margin: 0 0 22px; padding: 17px 0 0; page-break-inside: auto; }
.funder-section > header { break-after: avoid; margin-bottom: 12px; page-break-after: avoid; }
.funder-section > header h2 { color: #13233a; font-size: 21px; margin: 0; }
.funder-summary-copy { color: #192333; font-size: 12px; line-height: 1.7; max-width: 76ch; }
.funder-summary-copy p { margin: 0 0 11px; }
.funder-entry { border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: avoid; margin: 0 0 10px; padding: 12px 14px; }
.funder-entry > div:first-child { align-items: flex-start; display: flex; gap: 10px; justify-content: space-between; margin-bottom: 8px; }
.funder-entry h3 { color: #13233a; font-size: 14px; margin: 0; }
.funder-entry > div:first-child span { background: #f8f5ee; border: 1px solid #ded6c7; color: #667282; flex: 0 0 auto; font-size: 8.5px; padding: 4px 7px; }
.funder-entry-copy, .funder-detail-table td { color: #192333; font-size: 10.5px; line-height: 1.6; }
.funder-entry-copy p, .funder-detail-table p { margin: 0 0 8px; }
.funder-detail-table { break-inside: avoid; font-size: 10.5px; }
.funder-detail-table th, .funder-detail-table td { padding: 7px 8px; text-align: left; }
.funder-detail-table th:first-child { width: 25%; }
.funder-detail-table th:last-child { width: 15%; }
.funder-missing { color: #8a1c16; }
.funder-finance { break-before: page; }
.funder-finance-block { break-inside: avoid; margin: 16px 0; }
.funder-finance-block h3 { color: #1c2f4a; font-size: 13px; margin: 0 0 7px; }
.funder-finance-block table { font-size: 10px; line-height: 1.45; }
.funder-finance-block th, .funder-finance-block td { padding: 6px 7px; text-align: left; vertical-align: top; }
.funder-finance-block th:nth-child(n+2), .funder-finance-block td:nth-child(n+2) { text-align: right; }
.funder-finance-block th:first-child, .funder-finance-block td:first-child { text-align: left; }
.funder-finance-headline { background: #f8f5ee; border-left: 3px solid #b8860b; margin: 0 0 10px; padding: 8px 10px; }
.funder-reconciliation { background: #fffaf0; border: 1px solid #ead8a5; padding: 10px 12px; }
CSS;
    }
}
