<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Services\Pdf\PdfText;
use Illuminate\Support\Str;

/**
 * Bounded extracts keep the decision brief readable; complete source text and
 * line-item schedules remain in the master plan and Budget Pack.
 *
 * @phpstan-type BriefTable array{title:string,headers:list<string>,rows:list<list<string>>}
 * @phpstan-type BriefPage array{title:string,paragraphs:list<string>,tables:list<BriefTable>}
 *
 * @phpstan-import-type ReleaseStatus from FounderBriefRelease
 */
final class FounderBriefPages
{
    public function __construct(
        private readonly BudgetPackBuilder $budgets,
        private readonly FounderBriefFinancials $financials,
    ) {}

    /** @param ReleaseStatus $status
     * @return list<BriefPage>
     */
    public function build(EntrepreneurProfile $profile, BusinessPlan $plan, array $status, bool $preview): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $summary = (string) $plan->sections->firstWhere('key', BusinessPlanExecutiveSummary::SECTION_KEY)?->body;
        $budget = $this->budgets->payload($profile, $plan);
        $evidenceCount = $plan->sections->flatMap(fn (PlanSection $section): array => (array) $section->attached_document_ids)->unique()->count();
        $reviewParagraphs = [
            'Basis of this brief: the saved business plan and Budget forecast as at '.now()->format('j M Y').'. Financial results are calculated from the Budget; the narrative summarises the plan responses.',
            $evidenceCount > 0
                ? 'Supporting files linked to the plan: '.$evidenceCount.'. Review the source evidence alongside the customer, market and pricing assumptions before making a funding decision.'
                : 'No supporting files are linked to the saved plan. Customer, market and pricing statements remain founder assertions until corroborated.',
            'Forecast sales are estimates, not contracted income unless supported by the source evidence. Consider delivery capacity, payment timing and the downside scenarios alongside the headline results.',
            'Decision requested: consider the business case, funding requirement and use of funds set out in this brief. Any financing amount, terms and commitment require a separate agreement.',
        ];
        if ($preview && ! $status['active']) {
            $reviewParagraphs[] = 'DRAFT: review is still required before this brief can be issued. Resolve the questions in the plan and budget workspace and review the completed PDF.';
        }

        $pages = [
            self::page('About this brief', [
                $preview ? 'ADVISOR DRAFT. Review copy only; not approved for external issue. Viewing or downloading this copy does not approve release.' : 'This version is ready for external issue under the recorded release checks or advisor approval.',
                'This brief brings the business case, funding requirement and financial outlook together. The full business plan, supporting evidence and Budget Pack provide the underlying detail.',
                'Forecasts are planning estimates from the saved Budget, not historical accounts. Missing information is marked as unavailable rather than assumed to be zero.',
            ]),
            self::page('Executive summary',
                $status['executive_summary']['usable'] && trim($summary) !== ''
                    ? $this->summaryParagraphs($summary)
                    : ['A current approved executive summary is not available. The following pages can still be reviewed against the saved plan and Budget. Complete summary generation before approving external issue.'],
            ),
            self::page('Business and market', $this->extracts($plan, [
                'business-type-location' => 'Business and operating model',
                'mission-vision' => 'Purpose and direction',
                'industry-context' => 'Market and customers',
                'differentiation' => 'Competitive position',
            ])),
            self::page('Delivery, milestones and risks', $this->extracts($plan, [
                'revenue-model' => 'How the business earns revenue',
                'organisation-management' => 'People and delivery',
                'goals-objectives' => 'Milestones and objectives',
                'risk-register' => 'Risks and responses',
            ])),
            ...$this->financials->build($budget, $plan->budgetRunway),
            self::page('Evidence and funding decision', $reviewParagraphs),
        ];
        $contents = [];
        foreach (array_slice($pages, 1, null, true) as $index => $page) {
            $contents[] = [(string) ($index + 1), $page['title']];
        }
        $pages[0]['tables'][] = self::table('Reading guide', ['Page', 'Section'], $contents);

        return $pages;
    }

    /** @param array<string,string> $requirements
     * @return list<string>
     */
    private function extracts(BusinessPlan $plan, array $requirements): array
    {
        $paragraphs = [];
        foreach ($requirements as $key => $label) {
            $section = $plan->sections->first(fn (PlanSection $row): bool => data_get($row->metadata, 'requirement_key') === $key || str_ends_with($row->key, '-'.$key));
            $text = trim((string) $section?->body);
            $paragraphs[] = $label.': '.($text === '' ? 'No saved response is available.' : self::excerpt($text, 500, completeSentences: true));
        }

        return $paragraphs;
    }

    /** @return list<string> */
    private function summaryParagraphs(string $summary): array
    {
        $text = self::excerpt($summary, 2800, completeSentences: true);
        $sentences = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d])/u', $text) ?: [$text];
        $paragraphs = [];
        $paragraph = '';
        foreach ($sentences as $sentence) {
            if ($paragraph !== '' && mb_strlen($paragraph.' '.$sentence) > 600) {
                $paragraphs[] = $paragraph;
                $paragraph = '';
            }
            $paragraph = $paragraph === '' ? $sentence : $paragraph.' '.$sentence;
        }
        if ($paragraph !== '') {
            $paragraphs[] = $paragraph;
        }

        return $paragraphs;
    }

    public static function excerpt(string $text, int $limit, bool $completeSentences = false): string
    {
        $plain = html_entity_decode(strip_tags(Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $plain = trim(preg_replace('/\s+/u', ' ', PdfText::normalise($plain)) ?? '');
        if (mb_strlen($plain) <= $limit) {
            return $plain;
        }
        $prefix = mb_substr($plain, 0, $limit);
        if ($completeSentences) {
            // Do not turn a bounded extract into an unfinished claim. The
            // lookahead uses the full source so a cut decimal is not a sentence.
            preg_match_all('/[.!?](?:["\x{201D}\x{2019}])?(?=\s|$)/u', $plain, $ends, PREG_OFFSET_CAPTURE);
            $complete = '';
            foreach ($ends[0] as [$ending, $offset]) {
                $candidate = substr($plain, 0, $offset + strlen($ending));
                if (mb_strlen($candidate) > $limit) {
                    break;
                }
                $complete = $candidate;
            }

            return $complete === '' ? 'Read the full source for this response.' : trim($complete);
        }
        $break = mb_strrpos($prefix, ' ');

        return rtrim($break === false ? $prefix : mb_substr($prefix, 0, $break)).'…';
    }

    /** @param list<string> $paragraphs
     * @param  list<BriefTable>  $tables
     * @return BriefPage
     */
    public static function page(string $title, array $paragraphs = [], array $tables = []): array
    {
        return compact('title', 'paragraphs', 'tables');
    }

    /** @param list<string> $headers
     * @param  list<list<string>>  $rows
     * @return BriefTable
     */
    public static function table(string $title, array $headers, array $rows): array
    {
        if ($rows === []) {
            $rows = [array_merge(['No saved figures available'], array_fill(0, count($headers) - 1, '-'))];
        }

        return compact('title', 'headers', 'rows');
    }

    public static function money(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'Not available';
        }

        return ((float) $value < 0 ? '-' : '').'$'.number_format(abs((float) $value), 2);
    }
}
