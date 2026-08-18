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

    private const SUMMARY_AREA_REQUIREMENTS = [
        [
            'title' => 'Type of business',
            'keys' => ['business-type-location'],
            'terms' => ['business', 'company', 'service', 'product', 'advisory', 'consulting'],
        ],
        [
            'title' => 'Location',
            'keys' => ['business-type-location'],
            'terms' => ['location', 'located', 'based', 'premises', 'home', 'regional', 'online', 'new zealand'],
        ],
        [
            'title' => 'Means of doing business',
            'keys' => ['business-type-location'],
            'terms' => ['operate', 'operating', 'delivery', 'deliver', 'online', 'in-person', 'model', 'customer'],
        ],
        [
            'title' => 'Discuss the industry',
            'keys' => ['industry-context', 'differentiation'],
            'terms' => ['industry', 'market', 'customer', 'demand', 'timing', 'trend'],
        ],
        [
            'title' => 'What sets the business apart',
            'keys' => ['differentiation', 'competitor-comparison'],
            'terms' => ['different', 'competitor', 'alternative', 'advantage', 'choose', 'sets'],
        ],
        [
            'title' => 'Describe unique success factors',
            'keys' => ['success-factors', 'organisation-management'],
            'terms' => ['success', 'capability', 'relationship', 'asset', 'experience', 'responsibility'],
        ],
        [
            'title' => 'Mission and vision statement',
            'keys' => ['mission-vision'],
            'terms' => ['mission', 'vision', 'problem', 'purpose', 'exists'],
        ],
        [
            'title' => 'Intellectual property',
            'keys' => ['intellectual-property'],
            'terms' => ['intellectual', 'property', 'brand', 'data', 'method', 'contract', 'protect'],
        ],
        [
            'title' => 'Goals and objectives',
            'keys' => ['goals-objectives'],
            'terms' => ['goal', 'objective', 'milestone', 'measure', 'decision', 'launch'],
        ],
        [
            'title' => 'Culture',
            'keys' => ['culture'],
            'terms' => ['culture', 'values', 'behaviour', 'promise', 'team'],
        ],
        [
            'title' => 'Legal environment',
            'keys' => ['legal-environment', 'systems-software-processes'],
            'terms' => ['legal', 'privacy', 'compliance', 'supplier', 'employment', 'system', 'process'],
        ],
        [
            'title' => 'Budget',
            'keys' => ['financial-assumptions', 'revenue-model', 'launch-funding'],
            'terms' => ['budget', 'revenue', 'funding', 'runway', 'cost', 'cash', 'margin', 'price'],
        ],
    ];

    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly SimpleTextPdf $fallbackPdf,
        private readonly BrandedReportLayout $layout,
        private readonly BusinessPlanIdentity $identity,
        private readonly EntrepreneurDocumentTemplate $templates,
        private readonly PlanIssueReadiness $issueReadiness,
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
        $sectionBodies = $this->sectionBodies($phases);
        $businessName = $this->identity->businessName($profile, $plan, $sectionBodies);
        $executiveSummary = $this->executiveSummary($profile, $plan, $phases, $documentSections);
        $issueReadiness = $plan instanceof BusinessPlan
            ? $this->issueReadiness->evaluate($plan)
            : ['external_issue_ready' => false, 'reasons' => ['No saved plan is available yet.']];
        $draftNotice = $this->draftNoticeHtml($issueReadiness);
        $authorshipNotice = $this->clientAuthorshipNoticeHtml($profile, $businessName);
        $sectionHtml = collect($documentSections)
            ->map(fn (array $section, int $index): string => $this->documentSectionHtml($section, $index + 1))
            ->implode('');
        $contentHtml = $sectionHtml !== ''
            ? $draftNotice.$authorshipNotice.$this->overviewHtml($documentSections, $executiveSummary).$sectionHtml
            : $this->layout->section(
                'Plan content not completed yet',
                $draftNotice.$authorshipNotice.'<p class="body">No completed business-plan sections are available yet.</p>',
                'missing-panel',
            );
        $generatedAt = now()->format('M j, Y g:i A');
        $template = $this->templates->businessPlan();
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
        $index = collect($this->indexEntries($sections, $executiveSummary))
            ->map(function (array $entry, int $index): string {
                return sprintf(
                    '<tr><td class="reader-index-number">%02d</td><td><strong>%s</strong></td></tr>',
                    $index + 1,
                    $this->escape($entry['title']),
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
<h2>Index</h2>
<table class="reader-index"><tbody>%s</tbody></table>
</article>
HTML,
            $this->escape($summaryHeading),
            $this->escape($summaryHeading),
            $summaryBody,
            $index,
        );
    }

    /**
     * @param  array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>  $sections
     * @param  array{key:string,title:string,body:string,evidence_count:int}|null  $executiveSummary
     * @return array<int, array{key:string,title:string,body:string,evidence_count:int}>
     */
    private function indexEntries(array $sections, ?array $executiveSummary): array
    {
        $entries = collect($sections)
            ->flatMap(fn (array $section): array => $section['entries'])
            ->values();

        if ($executiveSummary !== null) {
            $entries->prepend($executiveSummary);
        }

        return $entries->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array{key:string,title:string,body:string,evidence_count:int}|null
     */
    private function executiveSummaryEntry(array $phases): ?array
    {
        foreach ($phases as $phase) {
            $section = collect($phase['sections'] ?? [])
                ->first(fn (array $candidate): bool => $this->isExecutiveSummary($candidate));

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
     * @param  array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>  $sections
     * @return array{key:string,title:string,body:string,evidence_count:int}
     */
    private function executiveSummary(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases, array $sections): array
    {
        $authored = $this->executiveSummaryEntry($phases);
        if ($authored !== null) {
            return $authored;
        }

        $businessName = $this->identity->businessName($profile, $plan, $this->sectionBodies($phases));
        $opening = $businessName === null
            ? 'This business plan is prepared by '.$profile->name.'.'
            : 'This business plan is prepared for '.$businessName.', led by '.$profile->name.'.';
        $summary = [$opening];

        if (filled($profile->concept_summary)) {
            $summary[] = Str::limit(trim((string) $profile->concept_summary), 360, '...');
        }

        $areaBullets = $this->summaryAreaBullets($sections, $plan);
        if ($areaBullets !== []) {
            $summary[] = 'It draws on the founder\'s current responses across the 12 assessment areas used for review:';
            $summary[] = collect($areaBullets)
                ->map(fn (string $bullet): string => '- '.$bullet)
                ->implode("\n");
        }

        $computed = (array) ($plan?->budgetRunway?->computed ?? []);
        if ($computed !== []) {
            $breakEven = data_get($computed, 'break_even_year');
            $cashPositive = data_get($computed, 'cash_flow_positive_year');
            $summary[] = 'The forecast currently shows break-even in '.($breakEven === null ? 'the forecast horizon not yet confirmed' : 'Year '.$breakEven).' and cash-positive timing in '.($cashPositive === null ? 'the forecast horizon not yet confirmed' : 'Year '.$cashPositive).'.';
        }

        return [
            'key' => 'executive-summary',
            'title' => 'Executive summary',
            'body' => implode("\n\n", $summary),
            'evidence_count' => 0,
        ];
    }

    /**
     * @param  array<int, array{title:string,entries:array<int, array{key:string,title:string,body:string,evidence_count:int}>}>  $sections
     * @return array<int, string>
     */
    private function summaryAreaBullets(array $sections, ?BusinessPlan $plan): array
    {
        $entriesByKey = collect($sections)
            ->flatMap(fn (array $section): array => $section['entries'])
            ->keyBy('key')
            ->all();
        $computed = (array) ($plan?->budgetRunway?->computed ?? []);

        return collect(self::SUMMARY_AREA_REQUIREMENTS)
            ->map(fn (array $area): string => $area['title'].': '.$this->summaryAreaText($area, $entriesByKey, $computed))
            ->values()
            ->all();
    }

    /**
     * @param  array{title:string,keys:array<int, string>,terms:array<int, string>}  $area
     * @param  array<string, array{key:string,title:string,body:string,evidence_count:int}>  $entriesByKey
     * @param  array<string, mixed>  $computed
     */
    private function summaryAreaText(array $area, array $entriesByKey, array $computed): string
    {
        $bodies = collect($area['keys'])
            ->map(fn (string $key): ?string => isset($entriesByKey[$key]) ? (string) $entriesByKey[$key]['body'] : null)
            ->filter(fn (?string $body): bool => is_string($body) && trim($body) !== '')
            ->values()
            ->all();
        $sentences = $this->summarySentences(implode("\n", $bodies));
        $terms = collect($area['terms'])
            ->map(fn (string $term): string => Str::lower($term))
            ->filter()
            ->values()
            ->all();
        $sentence = collect($sentences)
            ->first(function (string $candidate) use ($terms): bool {
                $candidate = Str::lower($candidate);

                return collect($terms)->contains(fn (string $term): bool => str_contains($candidate, $term));
            }) ?? ($sentences[0] ?? '');

        if ((string) $area['title'] === 'Budget') {
            $budgetSentence = $this->budgetSummarySentence($computed);
            $sentence = trim($sentence.' '.$budgetSentence);
        }

        if ($sentence === '') {
            return 'No completed response is captured yet.';
        }

        return Str::limit($sentence, 210, '...');
    }

    /**
     * @return array<int, string>
     */
    private function summarySentences(string $body): array
    {
        $plain = preg_replace('/\s+/', ' ', $this->markdownPlainText($body)) ?? '';
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($plain)) ?: [];

        return collect($sentences)
            ->map(fn (string $sentence): string => trim($sentence, " \t\n\r\0\x0B-.,;:!?\"'`()[]{}"))
            ->filter(fn (string $sentence): bool => strlen($sentence) >= 24)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function budgetSummarySentence(array $computed): string
    {
        if ($computed === []) {
            return '';
        }

        $signals = [];
        $runwayMonths = data_get($computed, 'runway_months');
        $breakEvenYear = data_get($computed, 'break_even_year');
        $cashPositiveYear = data_get($computed, 'cash_flow_positive_year');

        if (is_numeric($runwayMonths)) {
            $signals[] = 'runway of '.(int) round((float) $runwayMonths).' month'.((int) round((float) $runwayMonths) === 1 ? '' : 's');
        }

        if (is_numeric($breakEvenYear)) {
            $signals[] = 'break-even in Year '.(int) $breakEvenYear;
        }

        if (is_numeric($cashPositiveYear)) {
            $signals[] = 'cash-positive timing in Year '.(int) $cashPositiveYear;
        }

        return $signals === []
            ? ''
            : 'Forecast signals include '.implode(', ', $signals).'.';
    }

    /**
     * Snapshots created before requirement metadata was normalised can still
     * contain the executive summary under the legacy section key or title.
     *
     * @param  array<string, mixed>  $section
     */
    private function isExecutiveSummary(array $section): bool
    {
        if ((string) ($section['requirement_key'] ?? '') === 'executive-summary') {
            return true;
        }

        if ((string) ($section['key'] ?? '') === 'founder-financial-executive-summary') {
            return true;
        }

        return strcasecmp(trim((string) ($section['title'] ?? '')), 'Executive summary') === 0;
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
        $executiveSummary = $this->executiveSummary($profile, $plan, $phases, $sections);
        $issueReadiness = $plan instanceof BusinessPlan
            ? $this->issueReadiness->evaluate($plan)
            : ['external_issue_ready' => false, 'reasons' => ['No saved plan is available yet.']];
        $businessName = $this->identity->businessName($profile, $plan, $this->sectionBodies($phases));
        $title = 'Business Plan'.($businessName === null ? '' : ' - '.$businessName);
        $blocks = [
            [
                'type' => 'cover',
                'document_tag' => (string) ($documentMeta['document_tag'] ?? 'Business plan'),
                'title' => $title,
                'subtitle' => 'Founder - '.$profile->name.((bool) ($issueReadiness['external_issue_ready'] ?? false) ? '' : ' | INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE'),
            ],
            ['type' => 'page_break'],
            [
                'type' => 'callout',
                'title' => 'Founder responsibility note',
                'text' => $this->clientAuthorshipNoticeText($profile, $businessName),
            ],
            ['type' => 'section', 'text' => 'Executive summary'],
            [
                'type' => 'paragraph',
                'text' => $this->markdownPlainText($executiveSummary['body']),
            ],
            ['type' => 'page_break'],
            [
                'type' => 'toc',
                'heading' => 'Index',
                'items' => collect($this->indexEntries($sections, $executiveSummary))
                    ->map(fn (array $entry): array => ['title' => $entry['title']])
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

    /**
     * @param  array<string, mixed>  $issueReadiness
     */
    private function draftNoticeHtml(array $issueReadiness): string
    {
        if ((bool) ($issueReadiness['external_issue_ready'] ?? false)) {
            return '';
        }

        $reasons = collect((array) ($issueReadiness['reasons'] ?? []))
            ->take(4)
            ->map(fn (string $reason): string => '<li>'.$this->escape($reason).'</li>')
            ->implode('');

        return '<div class="internal-draft-watermark">INTERNAL DRAFT</div><article class="report-section external-issue-warning"><h2>Internal draft - not for external issue</h2><p>Resolve the listed readiness items before sharing this document with a lender, investor, or other external audience.</p>'.($reasons === '' ? '' : '<ul>'.$reasons.'</ul>').'</article>';
    }

    private function clientAuthorshipNoticeHtml(EntrepreneurProfile $profile, ?string $businessName): string
    {
        return '<article class="client-authorship-note"><h2>Founder responsibility note</h2><p>'.$this->escape($this->clientAuthorshipNoticeText($profile, $businessName)).'</p></article>';
    }

    private function clientAuthorshipNoticeText(EntrepreneurProfile $profile, ?string $businessName): string
    {
        $name = trim($profile->name) !== '' ? trim($profile->name) : 'the founder';
        $subject = $businessName === null ? 'This business plan' : 'This business plan for '.$businessName;

        return $subject.' was prepared with Future Shift Advisory assistance in the FSA workspace, based on information supplied by '.$name.'. '.$name.', as the client and founder, remains responsible for the plan content, assumptions, evidence, and decisions.';
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
.client-authorship-note { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: avoid; margin-bottom: 16px; padding: 13px 15px; }
.client-authorship-note h2 { color: #1c2f4a; font-size: 14px; margin: 0 0 6px; }
.client-authorship-note p { color: #34443c; font-size: 10.5px; line-height: 1.55; margin: 0; }
.reader-overview { background: #f8f5ee; border: 1px solid #ded6c7; border-left: 5px solid #b8860b; break-inside: auto; margin-bottom: 20px; padding: 22px 24px; }
.reader-overview h2 { color: #13233a; font-size: 24px; margin: 0 0 14px; }
.reader-overview p { margin: 0; }
.summary-copy { color: #1f2937; font-size: 12px; line-height: 1.7; max-width: 74ch; }
.summary-copy p { margin: 0 0 11px; }
.summary-copy ul { margin: 0 0 11px; padding-left: 17px; }
.summary-copy li { margin: 0 0 5px; }
.question-label { color: #667282; display: block; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 6px; text-transform: uppercase; }
.reader-roadmap { border-top: 1px solid #ded6c7; break-after: page; padding-top: 18px; }
.reader-roadmap h2 { color: #1c2f4a; font-size: 18px; margin: 0 0 12px; }
.reader-index { border-collapse: collapse; margin: 0; width: 100%; }
.reader-index td { border: 0; border-top: 1px solid #eee7db; padding: 9px 0; text-align: left; vertical-align: middle; }
.reader-index-number { color: #0d7a7a; font-weight: 700; line-height: 1.25; width: 38px; }
.reader-index strong { color: #13233a; display: block; font-size: 12px; line-height: 1.28; overflow-wrap: anywhere; }
.plan-phase { border: 0; border-left: 0; break-inside: auto; margin: 0 0 18px; page-break-inside: auto; padding: 0; }
.plan-phase + .plan-phase { margin-top: 18px; }
.phase-heading { border-bottom: 1px solid #ded6c7; break-after: avoid; break-inside: avoid; margin-bottom: 12px; page-break-after: avoid; page-break-inside: avoid; padding-bottom: 9px; }
.phase-heading p { color: #0d7a7a; font-size: 8.5px; font-weight: 700; letter-spacing: 0; margin: 0 0 4px; text-transform: uppercase; }
.phase-heading h2 { color: #13233a; font-size: 21px; margin: 0 0 4px; }
.phase-heading span { color: #667282; font-size: 10px; }
.plan-subsection { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid #0d7a7a; break-inside: auto; margin: 0 0 10px; page-break-inside: auto; padding: 12px 14px; }
.plan-subsection header { align-items: flex-start; break-after: avoid; display: flex; gap: 12px; justify-content: space-between; margin-bottom: 8px; page-break-after: avoid; }
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
.internal-draft-watermark { color: #b42318; font-size: 42px; font-weight: 700; left: 19%; letter-spacing: 0; opacity: 0.11; pointer-events: none; position: fixed; top: 46%; transform: rotate(-28deg); z-index: 0; }
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
