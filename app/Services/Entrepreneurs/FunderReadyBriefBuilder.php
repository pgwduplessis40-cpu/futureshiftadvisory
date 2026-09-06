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

/**
 * A lender-facing brief deliberately bounded to the approved summary, a small
 * set of source highlights, and the decision-level financial position. The
 * comprehensive master plan remains available as a separate internal preview.
 */
final class FunderReadyBriefBuilder
{
    /** @var list<string> */
    private const HIGHLIGHT_KEYS = [
        'business-type-location',
        'industry-context',
        'revenue-model',
        'goals-objectives',
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

    /** @return array<string, mixed> */
    public function status(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $summary = $this->executiveSummaries->status($plan, $profile);
        $readiness = $this->issueReadiness->evaluate($plan);
        $reasons = array_values(array_unique(array_filter([
            ! (bool) ($summary['usable'] ?? false)
                ? 'Finalise a passing assessment to generate the approved executive summary.'
                : null,
            ...((array) ($readiness['reasons'] ?? [])),
        ])));
        $active = (bool) ($summary['usable'] ?? false)
            && (bool) ($readiness['external_issue_ready'] ?? false);

        return [
            'active' => $active,
            'label' => $active ? 'Lender brief ready' : 'Lender brief blocked',
            'reasons' => $reasons,
            'executive_summary' => $summary,
        ];
    }

    public function filename(EntrepreneurProfile $profile): string
    {
        return Str::slug($profile->name ?: 'entrepreneur').'-lender-brief.pdf';
    }

    public function pdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $status = $this->status($plan, $profile);
        abort_unless((bool) $status['active'], 409, implode(' ', (array) $status['reasons']));

        $document = $this->document($profile, $plan, $status);

        try {
            return $this->pdf->render($this->html($document));
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback($document);
        }
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function document(EntrepreneurProfile $profile, BusinessPlan $plan, array $status): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $summary = $this->executiveSummaries->status($plan, $profile);
        $section = $plan->sections->first(fn (PlanSection $candidate): bool => $candidate->key === BusinessPlanExecutiveSummary::SECTION_KEY);
        $budget = $this->budgetPack->payload($profile, $plan);
        $sourceBodies = $plan->sections->pluck('body')->filter(fn (mixed $body): bool => is_string($body) && trim($body) !== '')->all();

        return [
            'profile' => $profile,
            'plan' => $plan,
            'status' => $status,
            'business_name' => $this->identity->businessName($profile, $plan, $sourceBodies),
            'summary' => (string) ($section?->body ?? ''),
            'summary_status' => $summary,
            'budget' => $budget,
            'highlights' => $this->highlights($plan),
            'snapshot' => substr((string) ($summary['context_hash'] ?? ''), 0, 12),
            'prepared_at' => now()->format('M j, Y g:i A'),
        ];
    }

    /** @return list<array{title:string,body:string,evidence_count:int}> */
    private function highlights(BusinessPlan $plan): array
    {
        return collect(self::HIGHLIGHT_KEYS)
            ->map(function (string $key) use ($plan): ?array {
                $section = $plan->sections->first(fn (PlanSection $candidate): bool => (string) data_get($candidate->metadata, 'requirement_key') === $key
                    || $candidate->key === 'founder-'.($key === 'business-type-location' ? 'foundation' : ($key === 'industry-context' ? 'market' : 'strategy')).'-'.$key);
                if (! $section instanceof PlanSection || trim((string) $section->body) === '') {
                    return null;
                }

                return [
                    'title' => (string) $section->title,
                    'body' => $this->excerpt((string) $section->body, 700),
                    'evidence_count' => count((array) $section->attached_document_ids),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $document */
    private function html(array $document): string
    {
        $profile = $document['profile'];
        $businessName = $document['business_name'];
        $budget = (array) $document['budget'];
        $decision = (array) ($budget['funding_decision'] ?? []);
        $summary = (array) ($budget['summary'] ?? []);
        $title = 'Funder-Ready Brief'.($businessName === null ? '' : ' - '.$businessName);
        $summaryHtml = Str::markdown((string) $document['summary'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $highlights = collect((array) $document['highlights'])->map(fn (array $highlight): string => sprintf(
            '<article class="brief-highlight"><h3>%s</h3><p>%s</p><span>%s</span></article>',
            $this->escape($highlight['title']),
            $this->escape($highlight['body']),
            $this->escape($highlight['evidence_count'] === 1 ? '1 supporting file' : $highlight['evidence_count'].' supporting files'),
        ))->implode('');
        $annual = collect((array) ($budget['annual_totals'] ?? []))->take(3)->map(fn (array $row): string => sprintf(
            '<tr><td>Year %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape((string) ($row['year'] ?? '-')),
            $this->money($row['revenue'] ?? 0),
            $this->money($row['net_profit_before_tax'] ?? 0),
            $this->money($row['ending_cash'] ?? 0),
            $this->escape((string) ($row['break_even'] ?? 'Forecast')),
        ))->implode('');
        $scenarios = collect((array) ($budget['scenarios'] ?? []))->take(4)->map(fn (array $scenario): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape((string) ($scenario['name'] ?? 'Scenario')),
            $this->money($scenario['lowest_cash'] ?? 0),
            $this->money($scenario['additional_funding_needed'] ?? 0),
        ))->implode('');
        $content = '<article class="brief-summary brief-page"><h2>Executive summary</h2><div>'.$summaryHtml.'</div></article>'
            .'<article class="brief-finance brief-page"><h2>Funding and financial position</h2><dl>'
            .'<div><dt>Declared funding position</dt><dd>'.$this->escape((string) ($decision['funding_position_label'] ?? 'Not confirmed')).'</dd></div>'
            .'<div><dt>Required additional funding</dt><dd>'.$this->money($decision['required_additional_funding'] ?? 0).'</dd></div>'
            .'<div><dt>Funding available</dt><dd>'.$this->money($decision['available_funding'] ?? 0).'</dd></div>'
            .'<div><dt>Break-even timing</dt><dd>'.$this->escape($this->yearValue($summary['break_even_year'] ?? null)).'</dd></div>'
            .'</dl><p>'.$this->escape((string) ($decision['headline'] ?? 'The funding position is based on the linked Budget Pack.')).'</p></article>'
            .'<article class="brief-section brief-page"><h2>Investment case highlights</h2>'.$highlights.'</article>'
            .'<article class="brief-section brief-page"><h2>Three-year financial outlook</h2><table><thead><tr><th>Year</th><th>Revenue</th><th>NPBT</th><th>Ending cash</th><th>Break-even</th></tr></thead><tbody>'.($annual === '' ? '<tr><td colspan="5">No forecast is available.</td></tr>' : $annual).'</tbody></table>'
            .'<h2 class="brief-subheading">Downside sensitivity</h2><table><thead><tr><th>Scenario</th><th>Lowest cash</th><th>Additional funding</th></tr></thead><tbody>'.($scenarios === '' ? '<tr><td colspan="3">No sensitivity scenarios are available.</td></tr>' : $scenarios).'</tbody></table></article>'
            .'<article class="brief-decision brief-page"><h2>Decision requested</h2><p>Review the business case, the documented source evidence, and the linked Budget Pack as one reconciled lender package.</p></article>';
        $template = $this->templates->businessPlan();

        return $this->layout->document(
            title: $title,
            templateKey: $template?->getKey() ?? EntrepreneurDocumentTemplate::BUSINESS_PLAN,
            documentTag: 'Funder-ready brief',
            eyebrow: 'Lender decision brief',
            heading: $businessName ?? 'Funder-Ready Brief',
            subheading: 'Founder - '.$profile->name,
            meta: ['Assessment summary' => 'Approved and current', 'Snapshot' => (string) $document['snapshot'], 'Prepared' => (string) $document['prepared_at'], 'Currency' => 'NZD, GST exclusive'],
            contentHtml: $content,
            footer: 'Future Shift Advisory | Funder-Ready Brief | Prepared from the finalised plan and budget',
            template: $template,
            snapshotTitle: 'Document controls',
            metaColumns: 4,
            extraCss: $this->css(),
        );
    }

    /** @param array<string, mixed> $document */
    private function fallback(array $document): string
    {
        $budget = (array) $document['budget'];
        $decision = (array) ($budget['funding_decision'] ?? []);
        $annualRows = collect((array) ($budget['annual_totals'] ?? []))
            ->take(3)
            ->map(fn (array $row): array => [
                'Year '.($row['year'] ?? '-'),
                $this->money($row['revenue'] ?? 0),
                $this->money($row['net_profit_before_tax'] ?? 0),
                $this->money($row['ending_cash'] ?? 0),
            ])
            ->all();
        $scenarioRows = collect((array) ($budget['scenarios'] ?? []))
            ->take(4)
            ->map(fn (array $scenario): array => [
                (string) ($scenario['name'] ?? 'Scenario'),
                $this->money($scenario['lowest_cash'] ?? 0),
                $this->money($scenario['additional_funding_needed'] ?? 0),
            ])
            ->all();
        $blocks = [
            ['type' => 'cover', 'document_tag' => 'Funder-ready brief', 'title' => 'Funder-Ready Brief'.($document['business_name'] === null ? '' : ' - '.$document['business_name']), 'subtitle' => 'Founder - '.$document['profile']->name],
            ['type' => 'page_break'],
            ['type' => 'section', 'text' => 'Executive summary'],
            ['type' => 'paragraph', 'text' => $this->plainText((string) $document['summary'])],
            ['type' => 'page_break'],
            ['type' => 'section', 'text' => 'Funding and financial position'],
            ['type' => 'table', 'headers' => ['Metric', 'Value'], 'rows' => [
                ['Declared funding position', (string) ($decision['funding_position_label'] ?? 'Not confirmed')],
                ['Required additional funding', $this->money($decision['required_additional_funding'] ?? 0)],
                ['Funding available', $this->money($decision['available_funding'] ?? 0)],
            ], 'widths' => [1.3, 1]],
            ['type' => 'page_break'],
            ['type' => 'section', 'text' => 'Investment case highlights'],
        ];
        foreach ((array) $document['highlights'] as $highlight) {
            $blocks[] = ['type' => 'entry', 'kicker' => 'Investment case highlight', 'title' => $highlight['title'], 'body' => $highlight['body'], 'key_points' => [], 'body_bullets' => [], 'note' => $highlight['evidence_count'].' supporting files'];
        }
        $blocks[] = ['type' => 'page_break'];
        $blocks[] = ['type' => 'section', 'text' => 'Three-year financial outlook'];
        $blocks[] = ['type' => 'table', 'headers' => ['Year', 'Revenue', 'NPBT', 'Ending cash'], 'rows' => $annualRows === [] ? [['No forecast available', '-', '-', '-']] : $annualRows, 'widths' => [1, 1, 1, 1]];
        $blocks[] = ['type' => 'section', 'text' => 'Downside sensitivity'];
        $blocks[] = ['type' => 'table', 'headers' => ['Scenario', 'Lowest cash', 'Additional funding'], 'rows' => $scenarioRows === [] ? [['No scenarios available', '-', '-']] : $scenarioRows, 'widths' => [1.3, 1, 1]];
        $blocks[] = ['type' => 'page_break'];
        $blocks[] = ['type' => 'section', 'text' => 'Decision requested'];
        $blocks[] = ['type' => 'paragraph', 'text' => 'Review the business case, documented source evidence, and linked Budget Pack as one reconciled lender package.'];

        return $this->fallbackPdf->renderStructured('Funder-Ready Brief', $blocks, 'Future Shift Advisory | Funder-Ready Brief');
    }

    private function excerpt(string $body, int $limit): string
    {
        $text = $this->plainText($body);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $excerpt = mb_substr($text, 0, $limit);
        $lastSentence = max((int) strrpos($excerpt, '.'), (int) strrpos($excerpt, '!'), (int) strrpos($excerpt, '?'));

        return trim($lastSentence > 120 ? mb_substr($excerpt, 0, $lastSentence + 1) : $excerpt).'…';
    }

    private function plainText(string $body): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
    }

    private function money(mixed $value): string
    {
        $amount = (float) $value;

        return ($amount < 0 ? '-' : '').'$'.number_format(abs($amount), 0);
    }

    private function yearValue(mixed $value): string
    {
        return is_numeric($value) ? 'Year '.(int) $value : 'Not reached in forecast';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function css(): string
    {
        return <<<'CSS'
.brief-summary, .brief-finance, .brief-section, .brief-decision { break-inside: avoid; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; margin: 0 0 14px; padding: 13px 15px; }
.brief-page { break-before: page; }
.brief-summary h2, .brief-finance h2, .brief-section h2, .brief-decision h2 { color: #1c2f4a; font-size: 16px; margin: 0 0 8px; }
.brief-summary p { font-size: 11px; line-height: 1.6; margin: 0 0 9px; }
.brief-finance { background: #f8f5ee; }
.brief-finance dl { display: grid; gap: 8px; grid-template-columns: repeat(2, 1fr); margin: 0 0 10px; }
.brief-finance dt { color: #667282; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
.brief-finance dd { margin: 2px 0 0; }
.brief-highlight { border-top: 1px solid #eee7db; padding: 9px 0; }
.brief-highlight:first-of-type { border-top: 0; padding-top: 0; }
.brief-highlight h3 { color: #13233a; font-size: 12px; margin: 0 0 4px; }
.brief-highlight p { margin: 0 0 4px; }
.brief-highlight span { color: #667282; font-size: 9px; }
.brief-section table { font-size: 10px; }
.brief-subheading { color: #1c2f4a; font-size: 13px; margin: 16px 0 6px; }
.brief-decision { background: #fffaf0; border-left-color: #b8860b; }
CSS;
    }
}
