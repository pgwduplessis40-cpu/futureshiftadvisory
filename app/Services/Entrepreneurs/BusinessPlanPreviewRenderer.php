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
        $sectionHtml = collect($documentSections)
            ->map(fn (array $section): string => $this->documentSectionHtml($section))
            ->implode('');
        $missingItems = $this->missingRequirements($requirements);
        $missingHtml = $missingItems === []
            ? ''
            : '<article class="report-section missing-panel"><h2>Items still to complete before external issue</h2><ul>'.collect($missingItems)->map(fn (string $item): string => '<li>'.$this->escape($item).'</li>')->implode('').'</ul></article>';
        $contentHtml = $sectionHtml !== ''
            ? $sectionHtml.$missingHtml
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
            extraCss: '.plan-subsection { border-top: 1px solid #eee7db; padding: 10px 0; } .plan-subsection:first-of-type { border-top: 0; padding-top: 0; } .plan-subsection h3 { color: #13233a; font-size: 13px; margin: 0 0 5px; } .plan-subsection .body { margin-top: 0; }',
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
    private function documentSectionHtml(array $section): string
    {
        $entries = collect($section['entries'])
            ->map(function (array $entry): string {
                $evidence = $entry['evidence_count'] === 1
                    ? '1 supporting document referenced'
                    : $entry['evidence_count'].' supporting documents referenced';

                return sprintf(
                    '<section class="plan-subsection"><h3>%s</h3><div class="body">%s</div><p class="note">%s.</p></section>',
                    $this->escape($entry['title']),
                    $this->markdownBodyHtml($entry['body']),
                    $this->escape($evidence),
                );
            })
            ->implode('');

        return sprintf(
            '<article class="report-section"><h2>%s</h2>%s</article>',
            $this->escape($section['title']),
            $entries,
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
        $blocks = [
            ['type' => 'meta', 'text' => 'Prepared by Future Shift Advisory'],
            ['type' => 'meta', 'text' => 'Founder: '.$profile->name],
            ['type' => 'meta', 'text' => 'Plan status: '.$this->formatLabel($plan?->status ?? 'not started')],
            ['type' => 'meta', 'text' => 'Stage: '.$this->formatLabel($profile->currentStageValue())],
            ['type' => 'spacer'],
        ];

        foreach ($this->documentSections($phases) as $section) {
            $blocks[] = ['type' => 'section', 'text' => $section['title']];

            foreach ($section['entries'] as $entry) {
                $blocks[] = ['type' => 'subsection', 'text' => $entry['title']];
                $blocks[] = ['type' => 'paragraph', 'text' => $this->markdownPlainText($entry['body'])];

                if ($entry['evidence_count'] > 0) {
                    $blocks[] = ['type' => 'meta', 'text' => 'Evidence: '.$entry['evidence_count'].' supporting document'.($entry['evidence_count'] === 1 ? '' : 's').' referenced.'];
                }
            }
        }

        $missing = $this->missingRequirements($requirements);
        if ($missing !== []) {
            $blocks[] = ['type' => 'section', 'text' => 'Items still to complete before external issue'];
            $blocks[] = ['type' => 'bullets', 'items' => $missing];
        }

        return $this->fallbackPdf->renderStructured('Business Plan - '.($profile->name ?: 'Entrepreneur'), $blocks);
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
