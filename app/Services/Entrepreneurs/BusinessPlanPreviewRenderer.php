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
        return Str::slug($profile->name ?: 'entrepreneur-business-plan').'-preview.pdf';
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
        $missingHtml = $requirements
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => '<li>'.$this->escape(($requirement['phase_title'] ?? 'Plan').': '.$requirement['title']).'</li>')
            ->implode('');
        $phaseHtml = collect($phases)
            ->map(fn (array $phase): string => $this->phaseHtml($phase))
            ->implode('');
        $generatedAt = now()->format('M j, Y g:i A');

        return $this->layout->document(
            title: 'Business plan preview - '.$profile->name,
            templateKey: 'business-plan-preview',
            documentTag: 'Business plan preview',
            eyebrow: 'Entrepreneur business plan',
            heading: 'Business plan preview',
            subheading: $profile->name,
            meta: [
                'Plan status' => $this->formatLabel($plan?->status ?? 'not started'),
                'Requirements' => "{$completed}/{$total} complete",
                'Stage' => $this->formatLabel($profile->currentStageValue()),
            ],
            contentHtml: ($missingHtml === '' ? '' : '<article class="report-section missing-panel"><h2>Open items before finalising</h2><ul>'.$missingHtml.'</ul></article>').$phaseHtml,
            footer: 'Generated '.$generatedAt.' using Future Shift Advisory business plan preview',
        );
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function phaseHtml(array $phase): string
    {
        $sections = collect($phase['sections'] ?? []);
        $requirementHtml = collect($phase['requirements'] ?? [])
            ->map(fn (array $requirement): string => $this->requirementHtml($requirement, $sections))
            ->implode('');

        return sprintf(
            '<article class="report-section"><h2>%s</h2>%s</article>',
            $this->escape((string) $phase['title']),
            $requirementHtml,
        );
    }

    private function requirementHtml(array $requirement, Collection $sections): string
    {
        $section = $sections->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key']);
        $complete = (bool) ($requirement['complete'] ?? false);
        $content = is_array($section)
            ? $this->sectionContentHtml($section)
            : '<p class="body">Pending input: '.$this->escape((string) $requirement['description']).'</p>';

        return sprintf(
            '<article class="requirement"><h3>%s <span class="status %s">%s</span></h3>%s</article>',
            $this->escape((string) $requirement['title']),
            $complete ? 'complete' : 'pending',
            $complete ? 'Complete' : 'Pending',
            $content,
        );
    }

    private function sectionContentHtml(array $section): string
    {
        $documentCount = count((array) ($section['attached_document_ids'] ?? []));
        $docs = $documentCount === 1 ? '1 supporting document' : "{$documentCount} supporting documents";

        return sprintf(
            '<div class="body">%s</div><p class="note">Evidence: %s.</p>',
            $this->markdownBodyHtml((string) ($section['body'] ?? '')),
            $this->escape($docs),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function fallbackPdf(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases): string
    {
        $paragraphs = [
            'Browser-formatted PDF generation was temporarily unavailable. This fallback preserves the current plan content.',
            'Plan status: '.$this->formatLabel($plan?->status ?? 'not started'),
            'Stage: '.$this->formatLabel($profile->currentStageValue()),
        ];

        foreach ($phases as $phase) {
            $paragraphs[] = (string) ($phase['title'] ?? 'Plan phase');

            foreach ((array) ($phase['requirements'] ?? []) as $requirement) {
                $section = collect((array) ($phase['sections'] ?? []))
                    ->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) ($requirement['key'] ?? ''));
                $status = (bool) ($requirement['complete'] ?? false) ? 'Complete' : 'Pending';
                $body = is_array($section)
                    ? trim(strip_tags($this->markdownBodyHtml((string) ($section['body'] ?? ''))))
                    : 'Pending input: '.(string) ($requirement['description'] ?? '');

                $paragraphs[] = sprintf('%s (%s): %s', (string) ($requirement['title'] ?? 'Requirement'), $status, $body);
            }
        }

        return $this->fallbackPdf->render(
            'Business plan preview - '.($profile->name ?: 'Entrepreneur'),
            $paragraphs,
        );
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

    private function formatLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
