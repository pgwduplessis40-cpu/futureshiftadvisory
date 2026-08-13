<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\SimpleTextPdf;
use App\Services\Reports\BrandedReportLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

final class BusinessPlanPreviewRenderer
{
    private const BUDGET_UNLOCK_REQUIREMENT_KEY = 'business-type-location';

    private const BUDGET_ASSUMPTIONS_REQUIREMENT_KEY = 'financial-assumptions';

    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly SimpleTextPdf $fallbackPdf,
        private readonly BrandedReportLayout $layout,
    ) {}

    public function pdf(EntrepreneurProfile $profile, ?BusinessPlan $plan): string
    {
        $phases = $plan instanceof BusinessPlan
            ? $this->planPhases($plan)
            : $this->templatePreviewPhases();
        $html = $this->html($profile, $plan, $phases);

        try {
            return $this->pdf->render($html);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackPdf($profile, $plan, $phases);
        }
    }

    public function filename(EntrepreneurProfile $profile): string
    {
        return Str::slug($profile->name ?: 'entrepreneur').'-business-plan.pdf';
    }

    public function budgetUnlocked(BusinessPlan $plan): bool
    {
        $plan->loadMissing('sections');

        return $this->requirementComplete($plan, 'foundation', self::BUDGET_UNLOCK_REQUIREMENT_KEY)
            && $this->requirementComplete($plan, 'financial', self::BUDGET_ASSUMPTIONS_REQUIREMENT_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planPhases(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections', 'sections', 'budgetRunway');
        $phasesByKey = $plan->phases->keyBy('key');
        $requirements = $this->requirements($plan);

        return collect(PlanRequirements::definitions())
            ->map(function (array $definition, string $phaseKey) use ($phasesByKey, $requirements): array {
                $phase = $phasesByKey->get($phaseKey);

                return [
                    'id' => (string) ($phase?->id ?? $phaseKey),
                    'key' => $phaseKey,
                    'title' => (string) ($phase?->title ?? $definition['title']),
                    'status' => (string) ($phase?->status ?? 'pending'),
                    'requirements' => $requirements[$phaseKey] ?? [],
                    'sections' => $phase?->sections
                        ->sortBy('created_at')
                        ->map(fn (PlanSection $section): array => [
                            'id' => $section->id,
                            'title' => $section->title,
                            'body' => $section->body,
                            'attached_document_ids' => $section->attached_document_ids ?? [],
                            'requirement_key' => data_get($section->metadata, 'requirement_key'),
                        ])
                        ->values()
                        ->all() ?? [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templatePreviewPhases(): array
    {
        return collect(PlanRequirements::definitions())
            ->map(fn (array $definition, string $phaseKey): array => [
                'id' => $phaseKey,
                'key' => $phaseKey,
                'title' => $definition['title'],
                'status' => 'pending',
                'requirements' => collect($definition['requirements'])
                    ->map(fn (array $requirement): array => [
                        ...$requirement,
                        'phase_key' => $phaseKey,
                        'phase_title' => $definition['title'],
                        'complete' => false,
                        'section_id' => null,
                    ])
                    ->values()
                    ->all(),
                'sections' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function requirements(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $sections = $plan->sections;
        $budget = $plan->budgetRunway;

        return collect(PlanRequirements::definitions())
            ->mapWithKeys(function (array $definition, string $phaseKey) use ($sections, $budget): array {
                return [
                    $phaseKey => collect($definition['requirements'])
                        ->map(function (array $requirement) use ($phaseKey, $definition, $sections, $budget): array {
                            $section = $sections->first(fn (PlanSection $candidate): bool => (
                                (string) data_get($candidate->metadata, 'requirement_key') === $requirement['key']
                                || $candidate->key === 'founder-'.$phaseKey.'-'.$requirement['key']
                            ));
                            $isBudget = ($requirement['type'] ?? null) === 'budget';

                            return [
                                ...$requirement,
                                'phase_key' => $phaseKey,
                                'phase_title' => $definition['title'],
                                'complete' => $isBudget
                                    ? $budget instanceof EntrepreneurBudget && $budget->status === EntrepreneurBudget::STATUS_COMPLETE
                                    : $section instanceof PlanSection && $section->completeness_status === PlanSection::STATUS_COMPLETE,
                                'section_id' => $section?->id,
                                'section_title' => $section?->title,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function html(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases): string
    {
        $requirements = collect($phases)->flatMap(fn (array $phase): array => $phase['requirements'] ?? []);
        $total = $requirements->count();
        $completed = $requirements->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))->count();
        $documentSections = $this->documentSections($phases);
        $missingItems = $this->missingRequirements($requirements);
        $sectionHtml = collect($documentSections)
            ->map(fn (array $section, int $index): string => $this->documentSectionHtml($section, $index + 1))
            ->implode('');
        $missingHtml = $missingItems === []
            ? ''
            : '<article class="report-section missing-panel"><h2>Items still to complete before external issue</h2><ul>'.collect($missingItems)->map(fn (string $item): string => '<li>'.$this->escape($item).'</li>')->implode('').'</ul></article>';
        $contentHtml = $sectionHtml !== ''
            ? $this->overviewHtml($documentSections, $completed, $total, $missingItems).$sectionHtml.$missingHtml
            : $this->layout->section(
                'Plan content not completed yet',
                '<p class="body">No completed business-plan sections are available yet. Complete the founder plan sections before issuing this document externally.</p>'.$missingHtml,
                'missing-panel',
            );
        $generatedAt = now()->format('M j, Y g:i A');

        return $this->layout->document(
            title: 'Business plan - '.$profile->name,
            templateKey: 'entrepreneur-business-plan',
            documentTag: 'Business plan',
            eyebrow: 'Prepared for lender and investor review',
            heading: 'Business plan',
            subheading: $profile->name,
            meta: [
                'Plan status' => $this->formatLabel($plan?->status ?? 'not started'),
                'Requirements' => "{$completed}/{$total} complete",
                'Stage' => $this->formatLabel($profile->currentStageValue()),
            ],
            contentHtml: $contentHtml,
            footer: 'Generated '.$generatedAt.' using Future Shift Advisory business-plan workspace',
            extraCss: $this->businessPlanCss(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array<int, array{title:string,entries:array<int, array{title:string,body:string,evidence_count:int}>}>
     */
    private function documentSections(array $phases): array
    {
        return collect($phases)
            ->map(function (array $phase): ?array {
                $sections = collect($phase['sections'] ?? []);
                $entries = collect($phase['requirements'] ?? [])
                    ->map(function (array $requirement) use ($sections): ?array {
                        $section = $sections->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key']);

                        if (! is_array($section) || trim((string) ($section['body'] ?? '')) === '') {
                            return null;
                        }

                        return [
                            'title' => (string) $requirement['title'],
                            'body' => (string) ($section['body'] ?? ''),
                            'evidence_count' => count((array) ($section['attached_document_ids'] ?? [])),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($entries === []) {
                    return null;
                }

                return [
                    'title' => (string) ($phase['title'] ?? 'Plan section'),
                    'entries' => $entries,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array{title:string,entries:array<int, array{title:string,body:string,evidence_count:int}>}  $section
     */
    private function documentSectionHtml(array $section, int $position): string
    {
        $entries = collect($section['entries'])
            ->map(function (array $entry): string {
                $evidence = $entry['evidence_count'] === 1
                    ? '1 supporting document referenced'
                    : $entry['evidence_count'].' supporting documents referenced';
                $keyPoints = $this->keyPoints($entry['body']);
                $keyPointHtml = $keyPoints === []
                    ? ''
                    : '<aside class="key-points"><p>Key points</p><ul>'.collect($keyPoints)
                        ->map(fn (string $point): string => '<li>'.$this->escape($point).'</li>')
                        ->implode('').'</ul></aside>';

                return sprintf(
                    '<section class="plan-subsection"><header><div><p class="question-label">Plan response</p><h3>%s</h3></div><span>%s</span></header>%s<div class="detail-copy">%s</div></section>',
                    $this->escape($entry['title']),
                    $this->escape($evidence),
                    $keyPointHtml,
                    $this->markdownBodyHtml($entry['body']),
                );
            })
            ->implode('');

        return sprintf(
            '<article class="report-section plan-phase"><div class="phase-heading"><p>Section %02d</p><h2>%s</h2><span>%d completed response%s</span></div>%s</article>',
            $position,
            $this->escape($section['title']),
            count($section['entries']),
            count($section['entries']) === 1 ? '' : 's',
            $entries,
        );
    }

    /**
     * @param  array<int, array{title:string,entries:array<int, array{title:string,body:string,evidence_count:int}>}>  $sections
     * @param  array<int, string>  $missing
     */
    private function overviewHtml(array $sections, int $completed, int $total, array $missing): string
    {
        $responses = collect($sections)->sum(fn (array $section): int => count($section['entries']));
        $evidence = collect($sections)
            ->flatMap(fn (array $section): array => $section['entries'])
            ->sum(fn (array $entry): int => $entry['evidence_count']);
        $roadmap = collect($sections)
            ->map(function (array $section, int $index): string {
                return sprintf(
                    '<li><span>%02d</span><div><strong>%s</strong><p>%d completed response%s</p></div></li>',
                    $index + 1,
                    $this->escape($section['title']),
                    count($section['entries']),
                    count($section['entries']) === 1 ? '' : 's',
                );
            })
            ->implode('');
        $summary = $missing === []
            ? 'The plan has no outstanding requirement gaps recorded in the workspace.'
            : 'The plan still has '.count($missing).' open requirement'.(count($missing) === 1 ? '' : 's').' before it should be issued externally.';

        return sprintf(
            <<<'HTML'
<article class="reader-overview">
<div class="overview-copy">
<p class="question-label">Reader summary</p>
<h2>What this plan gives the reviewer</h2>
<p>%s It contains %d completed response%s across %d plan section%s.</p>
</div>
<div class="overview-cards">
<div><span>Requirements</span><strong>%d/%d</strong><p>completed</p></div>
<div><span>Plan responses</span><strong>%d</strong><p>included</p></div>
<div><span>Evidence</span><strong>%d</strong><p>document references</p></div>
<div><span>Open items</span><strong>%d</strong><p>before issue</p></div>
</div>
</article>
<article class="reader-roadmap">
<h2>Reader roadmap</h2>
<ol>%s</ol>
</article>
HTML,
            $this->escape($summary),
            $responses,
            $responses === 1 ? '' : 's',
            count($sections),
            count($sections) === 1 ? '' : 's',
            $completed,
            $total,
            $responses,
            $evidence,
            count($missing),
            $roadmap,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requirements
     * @return array<int, string>
     */
    private function missingRequirements(Collection $requirements): array
    {
        return $requirements
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => (string) (($requirement['phase_title'] ?? 'Plan').': '.$requirement['title']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function fallbackPdf(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases): string
    {
        $requirements = collect($phases)->flatMap(fn (array $phase): array => $phase['requirements'] ?? []);
        $total = $requirements->count();
        $completed = $requirements->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))->count();
        $sections = $this->documentSections($phases);
        $missing = $this->missingRequirements($requirements);
        $responses = collect($sections)->sum(fn (array $section): int => count($section['entries']));
        $evidence = collect($sections)
            ->flatMap(fn (array $section): array => $section['entries'])
            ->sum(fn (array $entry): int => $entry['evidence_count']);
        $blocks = [
            ['type' => 'meta', 'text' => 'Prepared by Future Shift Advisory'],
            ['type' => 'meta', 'text' => 'Founder: '.$profile->name],
            ['type' => 'meta', 'text' => 'Plan status: '.$this->formatLabel($plan?->status ?? 'not started')],
            ['type' => 'meta', 'text' => 'Requirements: '.$completed.'/'.$total.' complete'],
            ['type' => 'meta', 'text' => 'Stage: '.$this->formatLabel($profile->currentStageValue())],
            ['type' => 'spacer'],
            [
                'type' => 'callout',
                'title' => 'Reader summary',
                'text' => $this->fallbackSummary($responses, count($sections), count($missing)),
            ],
            [
                'type' => 'summary_cards',
                'cards' => [
                    ['label' => 'Requirements', 'value' => $completed.'/'.$total, 'note' => 'completed in the workspace'],
                    ['label' => 'Plan responses', 'value' => (string) $responses, 'note' => 'founder-written sections included'],
                    ['label' => 'Evidence', 'value' => (string) $evidence, 'note' => 'supporting document references'],
                    ['label' => 'Open items', 'value' => (string) count($missing), 'note' => 'resolve before external issue'],
                ],
            ],
            [
                'type' => 'toc',
                'items' => collect($sections)
                    ->map(fn (array $section): array => [
                        'title' => $section['title'],
                        'detail' => count($section['entries']).' completed response'.(count($section['entries']) === 1 ? '' : 's'),
                    ])
                    ->values()
                    ->all(),
            ],
        ];

        foreach ($sections as $index => $section) {
            $blocks[] = ['type' => 'page_break'];
            $blocks[] = ['type' => 'section', 'text' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).' '.$section['title']];
            $blocks[] = [
                'type' => 'callout',
                'title' => 'Section readout',
                'text' => $section['title'].' includes '.count($section['entries']).' completed response'.(count($section['entries']) === 1 ? '' : 's').' from the business-plan workspace. Key points are pulled forward for quick review, with the founder detail retained below.',
            ];

            foreach ($section['entries'] as $entry) {
                $blocks[] = [
                    'type' => 'entry',
                    'kicker' => 'Business-plan requirement',
                    'title' => $entry['title'],
                    'key_points' => $this->keyPoints($entry['body']),
                    'body' => $this->markdownPlainText($entry['body']),
                    'note' => 'Evidence: '.$entry['evidence_count'].' supporting document'.($entry['evidence_count'] === 1 ? '' : 's').' referenced.',
                ];
            }
        }

        if ($missing !== []) {
            $blocks[] = ['type' => 'page_break'];
            $blocks[] = ['type' => 'section', 'text' => 'Items still to complete before external issue'];
            $blocks[] = [
                'type' => 'callout',
                'title' => 'Advisor action',
                'text' => 'These gaps should be closed or clearly marked before the plan is shared with a lender, investor, or external partner.',
            ];
            $blocks[] = ['type' => 'bullets', 'items' => $missing];
        }

        return $this->fallbackPdf->renderStructured('Business Plan - '.($profile->name ?: 'Entrepreneur'), $blocks);
    }

    private function fallbackSummary(int $responses, int $sections, int $missing): string
    {
        $summary = 'This business plan has been reorganised for review rather than exported as raw workspace text. ';
        $summary .= 'It contains '.$responses.' completed response'.($responses === 1 ? '' : 's').' across '.$sections.' plan section'.($sections === 1 ? '' : 's').'. ';

        return $summary.($missing === 0
            ? 'No open plan requirements are recorded.'
            : $missing.' open requirement'.($missing === 1 ? '' : 's').' still need attention before external issue.');
    }

    /**
     * @return array<int, string>
     */
    private function keyPoints(string $body, int $limit = 3): array
    {
        $plain = $this->markdownPlainText($body);
        $candidates = preg_split('/(?:\R+|(?<=[.!?])\s+)/', $plain) ?: [];

        return collect($candidates)
            ->map(fn (string $candidate): string => trim(preg_replace('/^[-*]\s*/', '', $candidate) ?? $candidate))
            ->filter(fn (string $candidate): bool => strlen($candidate) >= 24)
            ->map(fn (string $candidate): string => Str::limit($candidate, 190, '...'))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    private function businessPlanCss(): string
    {
        return <<<'CSS'
.report-content { display: block; }
.reader-overview { background: #f8f5ee; border: 1px solid #ded6c7; border-left: 5px solid #b8860b; break-after: page; display: grid; gap: 18px; grid-template-columns: 1.1fr 1fr; margin-bottom: 18px; padding: 18px; }
.reader-overview h2 { color: #13233a; font-size: 18px; margin: 0 0 8px; }
.reader-overview p { margin: 0; }
.overview-copy > p:last-child { color: #39465a; font-size: 12px; line-height: 1.6; }
.overview-cards { display: grid; gap: 9px; grid-template-columns: repeat(2, 1fr); }
.overview-cards div { background: #fff; border: 1px solid #ded6c7; padding: 10px; }
.overview-cards span, .question-label { color: #667282; display: block; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 4px; text-transform: uppercase; }
.overview-cards strong { color: #0d7a7a; display: block; font-size: 18px; line-height: 1.1; }
.overview-cards p { color: #667282; font-size: 9px; margin: 3px 0 0; }
.reader-roadmap { break-after: page; }
.reader-roadmap h2 { color: #1c2f4a; font-size: 18px; margin: 0 0 12px; }
.reader-roadmap ol { display: grid; gap: 8px; list-style: none; margin: 0; padding: 0; }
.reader-roadmap li { align-items: flex-start; border-top: 1px solid #eee7db; display: grid; gap: 12px; grid-template-columns: 32px 1fr; padding: 10px 0; }
.reader-roadmap li span { color: #0d7a7a; font-weight: 700; }
.reader-roadmap li strong { color: #13233a; display: block; font-size: 12px; }
.reader-roadmap li p { color: #667282; margin: 2px 0 0; }
.plan-phase { border: 0; border-left: 0; break-before: page; padding: 0; }
.phase-heading { border-bottom: 1px solid #ded6c7; margin-bottom: 14px; padding-bottom: 9px; }
.phase-heading p { color: #0d7a7a; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 4px; text-transform: uppercase; }
.phase-heading h2 { color: #13233a; font-size: 21px; margin: 0 0 4px; }
.phase-heading span { color: #667282; font-size: 10px; }
.plan-subsection { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: avoid; margin: 0 0 14px; padding: 13px 15px; }
.plan-subsection header { align-items: flex-start; display: flex; gap: 12px; justify-content: space-between; margin-bottom: 9px; }
.plan-subsection h3 { color: #13233a; font-size: 14px; line-height: 1.35; margin: 0; }
.plan-subsection header span { background: #f8f5ee; border: 1px solid #ded6c7; color: #667282; flex: 0 0 auto; font-size: 8.5px; padding: 4px 7px; }
.key-points { background: #f8f5ee; border: 1px solid #eee7db; margin: 8px 0 10px; padding: 9px 11px; }
.key-points p { color: #b8860b; font-size: 8.5px; font-weight: 700; margin: 0 0 5px; text-transform: uppercase; }
.key-points ul { margin: 0; padding-left: 16px; }
.key-points li { margin: 0 0 4px; }
.detail-copy { color: #192333; font-size: 10.5px; line-height: 1.65; max-width: 72ch; }
.detail-copy p { margin: 0 0 8px; }
.detail-copy ul, .detail-copy ol { margin-top: 0; padding-left: 17px; }
.missing-panel { break-before: page; }
CSS;
    }

    private function requirementComplete(BusinessPlan $plan, string $phaseKey, string $requirementKey): bool
    {
        $plan->loadMissing('sections');

        return $plan->sections->contains(fn (PlanSection $section): bool => (
            $section->completeness_status === PlanSection::STATUS_COMPLETE
            && (
                (string) data_get($section->metadata, 'requirement_key') === $requirementKey
                || $section->key === 'founder-'.$phaseKey.'-'.$requirementKey
            )
        ));
    }

    private function markdownBodyHtml(string $body): string
    {
        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function markdownPlainText(string $body): string
    {
        $html = $this->markdownBodyHtml($body);
        $withBreaks = preg_replace('/<\/?(?:br|li|p|div|h[1-6]|blockquote)\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return trim(preg_replace('/[\t ]+/', ' ', $text) ?? $text);
    }

    private function formatLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
