<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
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
        $review = $status['review'];
        $reviewText = $review['approved']
            ? 'Advisor release decision: '.self::excerpt((string) $review['note'], 800)
            : ($status['active'] ? 'The current brief meets the external-issue checks.' : 'External issue is not approved. The advisor must resolve or review the release findings in the workspace.');
        $findings = array_slice($review['findings'], 0, 5);
        $reviewParagraphs = [$reviewText, ...array_map(fn (string $text): string => 'Review finding: '.self::excerpt($text, 220), $findings)];
        if (count($review['findings']) > 5) {
            $reviewParagraphs[] = (count($review['findings']) - 5).' further findings are recorded in the advisor workspace.';
        }
        $reviewParagraphs[] = 'Supporting files linked to plan responses: '.$plan->sections->flatMap(fn (PlanSection $section): array => (array) $section->attached_document_ids)->unique()->count().'. The brief summarises the saved plan and Budget; supporting files and complete source wording remain in the master plan and evidence workspace.';
        $reviewParagraphs[] = 'Decision requested: consider the business case together with the funding requirement, cash forecast and downside scenarios. Any financing terms or commitment require a separate decision.';

        return [
            self::page('About this brief', [
                $preview ? 'ADVISOR DRAFT. Review copy only; not approved for external issue. Viewing or downloading this copy does not approve release.' : 'This version is ready for external issue under the recorded release checks or advisor approval.',
                'This eleven-page brief brings the business case and financials together. Narrative extracts and long cost schedules are condensed and labelled; the full business plan and Budget Pack retain the detail.',
                'Reading guide: 2 Executive summary; 3 Business and market; 4 Delivery and risks; 5 Financial outlook; 6 Monthly cash flow; 7 Funding; 8 Running costs; 9 One-off and future costs; 10 Assumptions and sensitivities; 11 Advisor review.',
                'Forecasts are planning estimates from the saved Budget, not historical accounts. Missing information is marked as unavailable rather than assumed to be zero.',
            ]),
            self::page('Executive summary', [
                $status['executive_summary']['usable'] && trim($summary) !== ''
                    ? self::excerpt($summary, 2800)
                    : 'A current approved executive summary is not available. The following pages can still be reviewed against the saved plan and Budget. Complete summary generation before approving external issue.',
            ]),
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
            self::page('Advisor review and decision', $reviewParagraphs),
        ];
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
            $paragraphs[] = $label.': '.($text === '' ? 'No saved response is available.' : self::excerpt($text, 500));
        }

        return $paragraphs;
    }

    public static function excerpt(string $text, int $limit): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
        if (mb_strlen($plain) <= $limit) {
            return $plain;
        }
        $prefix = mb_substr($plain, 0, $limit);
        $break = mb_strrpos($prefix, ' ');

        return rtrim($break === false ? $prefix : mb_substr($prefix, 0, $break)).' [Extract; see full source.]';
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
