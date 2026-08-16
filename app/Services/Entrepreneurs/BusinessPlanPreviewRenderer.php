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
        private readonly BusinessPlanIdentity $identity,
        private readonly EntrepreneurDocumentTemplate $templates,
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function pdfFromSnapshot(EntrepreneurProfile $profile, BusinessPlan $plan, array $snapshot, int $round): string
    {
        $capturedAt = (string) ($snapshot['captured_at'] ?? '');
        $documentMeta = [
            'document_tag' => 'Submitted plan',
            'eyebrow' => 'Plan snapshot captured for assessment round '.$round,
            'footer' => $capturedAt !== ''
                ? 'Snapshot captured '.$capturedAt.' from the submitted entrepreneur workspace'
                : 'Snapshot captured from the submitted entrepreneur workspace',
        ];
        $phases = $this->snapshotPhases($snapshot);
        $html = $this->html($profile, $plan, $phases, $documentMeta);

        try {
            return $this->pdf->render($html);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackPdf($profile, $plan, $phases, $documentMeta);
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
    private function html(
        EntrepreneurProfile $profile,
        ?BusinessPlan $plan,
        array $phases,
        array $documentMeta = [],
    ): string {
        $documentSections = $this->documentSections($phases);
        $sectionHtml = collect($documentSections)
            ->map(fn (array $section, int $index): string => $this->documentSectionHtml($section, $index + 1))
            ->implode('');
        $contentHtml = $sectionHtml !== ''
            ? $this->overviewHtml($documentSections, $this->executiveSummaryEntry($phases)).$sectionHtml
            : $this->layout->section(
                'Plan content not completed yet',
                '<p class="body">No completed business-plan sections are available yet.</p>',
                'missing-panel',
            );
        $generatedAt = now()->format('M j, Y g:i A');
        $template = $this->templates->businessPlan();
        $businessName = $this->identity->businessName($profile, $plan, $this->sectionBodies($phases));
        $title = 'Business Plan'.($businessName === null ? '' : ' - '.$businessName);

        return $this->layout->document(
            title: $title,
            templateKey: $template?->getKey() ?? EntrepreneurDocumentTemplate::BUSINESS_PLAN,
            documentTag: (string) ($documentMeta['document_tag'] ?? 'Business plan'),
            eyebrow: (string) ($documentMeta['eyebrow'] ?? ''),
            heading: (string) ($documentMeta['heading'] ?? $title),
            subheading: (string) ($documentMeta['subheading'] ?? 'Founder - '.$profile->name),
            meta: [],
            contentHtml: $contentHtml,
            footer: (string) ($documentMeta['footer'] ?? 'Generated '.$generatedAt.' using Future Shift Advisory business-plan workspace'),
            template: $template,
            snapshotTitle: '',
            extraCss: $this->businessPlanCss(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>
     */
    private function documentSections(array $phases): array
    {
        $documentSections = collect($phases)
            ->map(function (array $phase): ?array {
                $sections = collect($phase['sections'] ?? []);
                $entries = collect($phase['requirements'] ?? [])
                    ->reject(fn (array $requirement): bool => (string) ($requirement['key'] ?? '') === 'executive-summary')
                    ->map(function (array $requirement) use ($sections): ?array {
                        $section = $sections->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key']);

                        $body = $this->cleanResponseBody(is_array($section) ? (string) ($section['body'] ?? '') : '');

                        if (! is_array($section) || $body === '') {
                            return null;
                        }

                        return [
                            'key' => (string) $requirement['key'],
                            'title' => (string) $requirement['title'],
                            'body' => $body,
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
            ->values();

        return $documentSections->all();
    }

    /**
     * @param  array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}  $section
     */
    private function documentSectionHtml(array $section, int $position): string
    {
        $entries = collect($section['entries'])
            ->map(function (array $entry): string {
                $keyPoints = $this->keyPoints($entry['body']);
                $keyPointHtml = $keyPoints === []
                    ? ''
                    : '<aside class="key-points"><p>Key points</p><ul>'.collect($keyPoints)
                        ->map(fn (string $point): string => '<li>'.$this->escape($point).'</li>')
                        ->implode('').'</ul></aside>';

                return sprintf(
                    '<section class="plan-subsection"><header><div><p class="question-label">Business-plan requirement</p><h3>%s</h3></div></header>%s<div class="detail-copy">%s</div></section>',
                    $this->escape($entry['title']),
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
     * @param  array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>  $sections
     * @param  array{key:string,title:string,body:string,evidence_count:int}|null  $executiveSummary
     */
    private function overviewHtml(array $sections, ?array $executiveSummary): string
    {
        $roadmap = collect($sections)
            ->map(function (array $section, int $index): string {
                return sprintf(
                    '<li><span>%02d</span><div><strong>%s</strong></div></li>',
                    $index + 1,
                    $this->escape($section['title']),
                );
            })
            ->implode('');
        $summaryHeading = $executiveSummary === null ? 'Plan overview' : 'Executive summary';
        $summaryBody = $executiveSummary === null
            ? '<p>'.$this->escape($this->planOverviewText($sections)).'</p>'
            : $this->markdownBodyHtml($executiveSummary['body']);

        return sprintf(
            <<<'HTML'
<article class="reader-overview">
<div class="overview-copy">
<p class="question-label">%s</p>
<h2>%s</h2>
<div class="summary-copy">%s</div>
</div>
</article>
<article class="reader-roadmap">
<p class="question-label">Contents</p>
<h2>Business plan contents</h2>
<ol>%s</ol>
</article>
HTML,
            $this->escape($summaryHeading),
            $this->escape($summaryHeading),
            $summaryBody,
            $roadmap,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array{key:string,title:string,body:string,evidence_count:int}|null
     */
    private function executiveSummaryEntry(array $phases): ?array
    {
        foreach ($phases as $phase) {
            $section = collect($phase['sections'] ?? [])
                ->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === 'executive-summary');

            if (! is_array($section)) {
                continue;
            }

            $body = $this->cleanResponseBody((string) ($section['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            return [
                'key' => 'executive-summary',
                'title' => 'Executive summary',
                'body' => $body,
                'evidence_count' => count((array) ($section['attached_document_ids'] ?? [])),
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array<int, string>
     */
    private function sectionBodies(array $phases): array
    {
        return collect($phases)
            ->flatMap(fn (array $phase): array => (array) ($phase['sections'] ?? []))
            ->map(fn (array $section): string => (string) ($section['body'] ?? ''))
            ->all();
    }

    /**
     * @param  array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>  $sections
     */
    private function planOverviewText(array $sections): string
    {
        $sectionNames = collect($sections)
            ->pluck('title')
            ->filter()
            ->implode(', ');

        return $sectionNames === ''
            ? 'This document presents the founder\'s current business plan.'
            : 'This document presents the founder\'s current plan across '.$sectionNames.'.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function fallbackPdf(
        EntrepreneurProfile $profile,
        ?BusinessPlan $plan,
        array $phases,
        array $documentMeta = [],
    ): string {
        $sections = $this->documentSections($phases);
        $executiveSummary = $this->executiveSummaryEntry($phases);
        $businessName = $this->identity->businessName($profile, $plan, $this->sectionBodies($phases));
        $title = 'Business Plan'.($businessName === null ? '' : ' - '.$businessName);
        $blocks = [
            [
                'type' => 'cover',
                'document_tag' => (string) ($documentMeta['document_tag'] ?? 'Business plan'),
                'title' => $title,
                'subtitle' => 'Founder - '.$profile->name,
            ],
            ['type' => 'page_break'],
            ['type' => 'section', 'text' => $executiveSummary === null ? 'Plan overview' : 'Executive summary'],
            [
                'type' => 'paragraph',
                'text' => $executiveSummary === null
                    ? $this->planOverviewText($sections)
                    : $this->markdownPlainText($executiveSummary['body']),
            ],
            ['type' => 'page_break'],
            [
                'type' => 'toc',
                'items' => collect($sections)
                    ->map(fn (array $section): array => ['title' => $section['title']])
                    ->values()
                    ->all(),
            ],
        ];

        foreach ($sections as $index => $section) {
            $blocks[] = ['type' => 'page_break'];
            $blocks[] = ['type' => 'section', 'text' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).' '.$section['title']];

            foreach ($section['entries'] as $entry) {
                $blocks[] = [
                    'type' => 'entry',
                    'kicker' => 'Business-plan requirement',
                    'title' => $entry['title'],
                    'key_points' => $this->keyPoints($entry['body']),
                    'body' => $this->markdownPlainText($entry['body']),
                    'note' => '',
                ];
            }
        }

        return $this->fallbackPdf->renderStructured('Business Plan', $blocks);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    private function snapshotPhases(array $snapshot): array
    {
        $phases = $snapshot['phases'] ?? [];

        if (! is_array($phases)) {
            return [];
        }

        return collect($phases)
            ->filter(fn (mixed $phase): bool => is_array($phase))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function keyPoints(string $body, int $limit = 3): array
    {
        $plain = $this->markdownPlainText($body);
        $candidates = preg_split('/(?:\R+|(?<=[.!?])\s+)/', $plain) ?: [];

        return collect($candidates)
            ->map(fn (string $candidate): string => $this->normaliseKeyPoint($candidate))
            ->filter(fn (string $candidate): bool => $this->isUsefulKeyPoint($candidate))
            ->map(fn (string $candidate): string => Str::limit($candidate, 190, '...'))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    private function cleanResponseBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace('/^\s*(?:[,.;:!?]+\s*)+/', '', $body) ?? $body;

        $lines = collect(preg_split('/\R/', $body) ?: [])
            ->map(fn (string $line): string => rtrim($line))
            ->reject(fn (string $line): bool => preg_match('/^\s*(?:[-*]\s*)?[,.;:!?"\'`()\[\]{}\s-]+\s*$/', $line) === 1)
            ->values()
            ->all();

        $body = trim(implode("\n", $lines));
        $body = preg_replace('/(\n\s*)([,.;:!?]+\s*)+/', '$1', $body) ?? $body;

        return trim($body);
    }

    private function normaliseKeyPoint(string $candidate): string
    {
        $candidate = preg_replace('/^\s*(?:[-*]+|\d+[.)])\s*/', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;

        return trim($candidate, " \t\n\r\0\x0B-.,;:!?\"'`()[]{}");
    }

    private function isUsefulKeyPoint(string $candidate): bool
    {
        $candidate = trim($candidate);

        if (strlen($candidate) < 24 || preg_match('/^[a-z]/', $candidate) === 1) {
            return false;
        }

        $ascii = Str::ascii($candidate);
        $letterCount = preg_match_all('/[A-Za-z]/', $ascii) ?: 0;
        $alnumCount = preg_match_all('/[A-Za-z0-9]/', $ascii) ?: 0;

        if ($letterCount < 10 || $alnumCount < 18 || str_word_count($ascii) < 4) {
            return false;
        }

        return preg_match('/\b(?:a|an|and|as|for|from|in|is|of|or|the|to|with)$/i', $candidate) !== 1;
    }

    private function businessPlanCss(): string
    {
        return <<<'CSS'
.report-content { display: block; }
.report-hero { background: #fff; border: 0; border-left: 0; break-after: page; margin: 68px 0 0; min-height: 430px; padding: 0; }
.report-hero .eyebrow:empty { display: none; }
.report-hero h1 { font-size: 31px; margin: 0 0 12px; }
.report-hero p { color: #39465a; font-size: 16px; }
.reader-overview { background: #f8f5ee; border: 1px solid #ded6c7; border-left: 5px solid #b8860b; break-inside: avoid; margin-bottom: 24px; padding: 25px 28px; }
.reader-overview h2 { color: #13233a; font-size: 24px; margin: 0 0 14px; }
.reader-overview p { margin: 0; }
.summary-copy { color: #1f2937; font-size: 12px; line-height: 1.7; max-width: 74ch; }
.summary-copy p { margin: 0 0 11px; }
.question-label { color: #667282; display: block; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 6px; text-transform: uppercase; }
.reader-roadmap { border-top: 1px solid #ded6c7; break-after: page; padding-top: 18px; }
.reader-roadmap h2 { color: #1c2f4a; font-size: 18px; margin: 0 0 12px; }
.reader-roadmap ol { display: grid; gap: 8px; list-style: none; margin: 0; padding: 0; }
.reader-roadmap li { align-items: flex-start; border-top: 1px solid #eee7db; display: grid; gap: 12px; grid-template-columns: 32px 1fr; padding: 10px 0; }
.reader-roadmap li span { color: #0d7a7a; font-weight: 700; }
.reader-roadmap li strong { color: #13233a; display: block; font-size: 12px; }
.plan-phase { border: 0; border-left: 0; break-before: page; padding: 0; }
.phase-heading { border-bottom: 1px solid #ded6c7; margin-bottom: 14px; padding-bottom: 9px; }
.phase-heading p { color: #0d7a7a; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 4px; text-transform: uppercase; }
.phase-heading h2 { color: #13233a; font-size: 21px; margin: 0 0 4px; }
.phase-heading span { color: #667282; font-size: 10px; }
.plan-subsection { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: auto; margin: 0 0 14px; page-break-inside: auto; padding: 13px 15px; }
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
.external-issue-warning { background: #fff1f0; border-left-color: #b42318; margin-bottom: 16px; }
.external-issue-warning h2 { color: #8a1c16; }
.external-issue-warning p { margin: 0 0 8px; }
.external-issue-warning ul { margin: 0; padding-left: 18px; }
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

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
