<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\IdeaValidation;
use App\Models\PlanSection;
use App\Models\RatingCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type BudgetEvidence array{forecast_years:int|float|null,expected_runway_months:int|float|null,assumptions:array<array-key,mixed>,launch_costs:array<array-key,mixed>,monthly_fixed_costs:array<array-key,mixed>,future_costs:array<array-key,mixed>,revenue_forecast:array<array-key,mixed>,funding_sources:array<array-key,mixed>,funding_scenarios:array<array-key,mixed>,computed:array<array-key,mixed>,flags:array<array-key,mixed>}
 * @phpstan-type CriterionEvidenceSection array{section_id:string,phase_key:string,phase_title:string,title:string,requirement_key:string|null,updated_at:string|null,attached_document_ids:list<int|string>,body:string}
 * @phpstan-type CriterionPlanContext array{evidence_mode:'criterion_scoped_submitted_snapshot',criterion_focus:array{number:int,name:string,preferred_requirement_keys:list<string>},scope_fallback:bool,criterion_focus_sections:list<CriterionEvidenceSection>,budget_evidence:BudgetEvidence|null,evidence_hash:string}
 */
final class PlanAiContext
{
    public const PLAN_SECTION_BODY_MAX_LENGTH = 25000;

    private const REQUIREMENT_CURRENT_DRAFT_MAX_LENGTH = 4000;

    private const IDEA_VALIDATION_FIELD_MAX_LENGTH = 800;

    private const EXISTING_SECTION_EXCERPT_MAX_LENGTH = 640;

    private const EXISTING_SECTION_CONTEXT_MAX_LENGTH = 3200;

    private const ASSESSMENT_PRIMARY_EXCERPT_MAX_LENGTH = 6000;

    private const ASSESSMENT_SECONDARY_EXCERPT_MAX_LENGTH = 2400;

    private const ASSESSMENT_SUPPORTING_EXCERPT_MAX_LENGTH = 650;

    private const ASSESSMENT_SUPPORTING_SECTION_COUNT = 4;

    private const BUDGET_SUMMARY_MAX_LENGTH = 1000;

    /**
     * @var array<string, array<int, string>>
     */
    private const CRITERION_REQUIREMENT_KEYS = [
        'type of business' => ['business-type-location'],
        'location' => ['business-type-location'],
        'means of doing business' => ['business-type-location'],
        'discuss the industry' => ['industry-context', 'differentiation'],
        'what sets the business apart' => ['differentiation'],
        'describe unique success factors' => ['success-factors'],
        'mission and vision statement' => ['mission-vision'],
        'intellectual property' => ['intellectual-property'],
        'goals and objectives' => ['goals-objectives'],
        'culture' => ['culture'],
        'legal environment' => ['legal-environment', 'systems-software-processes'],
        'budget' => ['financial-assumptions', 'revenue-model', 'launch-funding'],
    ];

    /**
     * @param  array<string, mixed>  $requirement
     * @return array{idea_validation:array<string, string>|null,current_draft:string,existing_sections:array<int, array{section_id:string,title:string,body_excerpt:string,requirement_key:string|null,updated_at:string|null}>}
     */
    public function requirementAssistance(
        BusinessPlan $plan,
        array $requirement,
        ?IdeaValidation $ideaValidation,
        string $currentDraft,
    ): array {
        $requirementKey = (string) ($requirement['key'] ?? '');
        $sections = $this->rankedSections(
            plan: $plan,
            terms: $this->keywords(implode(' ', [
                (string) ($requirement['title'] ?? ''),
                (string) ($requirement['description'] ?? ''),
                (string) ($requirement['phase_title'] ?? ''),
            ])),
            preferredRequirementKeys: [],
            excludedRequirementKey: $requirementKey,
        );

        return [
            'idea_validation' => $this->ideaValidationContext($ideaValidation),
            'current_draft' => $this->contextualExcerpt(
                $currentDraft,
                $this->keywords((string) ($requirement['title'] ?? '')),
                self::REQUIREMENT_CURRENT_DRAFT_MAX_LENGTH,
            ),
            'existing_sections' => $this->sectionCards(
                sections: $sections,
                limits: array_fill(0, 5, self::EXISTING_SECTION_EXCERPT_MAX_LENGTH),
                totalExcerptLimit: self::EXISTING_SECTION_CONTEXT_MAX_LENGTH,
            ),
        ];
    }

    public function guidanceSection(PlanSection $section): string
    {
        return $this->contextualExcerpt(
            (string) $section->body,
            $this->keywords(implode(' ', [$section->title, (string) $this->requirementKey($section)])),
            self::REQUIREMENT_CURRENT_DRAFT_MAX_LENGTH,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function ideaValidationPromptInput(array $payload): array
    {
        return collect([
            'problem' => $payload['problem'] ?? '',
            'target_customer' => $payload['target_customer'] ?? '',
            'solution' => $payload['solution'] ?? '',
            'value_proposition' => $payload['value_proposition'] ?? '',
            'demand_signal' => $payload['demand_signal'] ?? '',
            'revenue_model' => $payload['revenue_model'] ?? '',
        ])
            ->map(fn (mixed $value): string => $this->contextualExcerpt(
                (string) $value,
                [],
                self::IDEA_VALIDATION_FIELD_MAX_LENGTH,
            ))
            ->all();
    }

    /**
     * @return array{relevant_sections:array<int, array{section_id:string,title:string,body_excerpt:string,requirement_key:string|null,updated_at:string|null}>,supporting_section_summaries:array<int, array{section_id:string,title:string,body_excerpt:string,requirement_key:string|null,updated_at:string|null}>,budget_summary:string}
     */
    public function criterionAssessment(
        BusinessPlan $plan,
        RatingCriterion $criterion,
        string $budgetSummary,
    ): array {
        $criterionName = (string) $criterion->name;
        $sections = $this->rankedSections(
            plan: $plan,
            terms: $this->keywords($criterionName),
            preferredRequirementKeys: self::CRITERION_REQUIREMENT_KEYS[strtolower(trim($criterionName))] ?? [],
        );
        $relevantSections = $sections->take(2)->values();

        return [
            'relevant_sections' => $this->sectionCards(
                sections: $relevantSections,
                limits: [
                    self::ASSESSMENT_PRIMARY_EXCERPT_MAX_LENGTH,
                    self::ASSESSMENT_SECONDARY_EXCERPT_MAX_LENGTH,
                ],
                totalExcerptLimit: self::ASSESSMENT_PRIMARY_EXCERPT_MAX_LENGTH + self::ASSESSMENT_SECONDARY_EXCERPT_MAX_LENGTH,
            ),
            'supporting_section_summaries' => $this->sectionCards(
                sections: $sections->skip($relevantSections->count())->take(self::ASSESSMENT_SUPPORTING_SECTION_COUNT)->values(),
                limits: array_fill(0, self::ASSESSMENT_SUPPORTING_SECTION_COUNT, self::ASSESSMENT_SUPPORTING_EXCERPT_MAX_LENGTH),
                totalExcerptLimit: self::ASSESSMENT_SUPPORTING_SECTION_COUNT * self::ASSESSMENT_SUPPORTING_EXCERPT_MAX_LENGTH,
            ),
            'budget_summary' => $this->contextualExcerpt(
                $budgetSummary,
                $this->keywords($criterionName),
                self::BUDGET_SUMMARY_MAX_LENGTH,
            ),
        ];
    }

    /**
     * Builds the immutable, criterion-scoped evidence pack used for a scored
     * assessment round. The full submitted snapshot remains on the assessment
     * for audit, but it is deliberately not supplied to unrelated criteria.
     *
     * @param  array<array-key, mixed>  $snapshot
     * @return CriterionPlanContext
     */
    public function criterionAssessmentFromSnapshot(array $snapshot, RatingCriterion $criterion): array
    {
        $sections = collect($snapshot['phases'] ?? [])
            ->flatMap(function (mixed $phase): array {
                if (! is_array($phase)) {
                    return [];
                }

                return collect($phase['sections'] ?? [])
                    ->filter(fn (mixed $section): bool => is_array($section))
                    ->map(fn (array $section): array => [
                        'section_id' => (string) ($section['id'] ?? ''),
                        'phase_key' => (string) ($phase['key'] ?? ''),
                        'phase_title' => (string) ($phase['title'] ?? ''),
                        'title' => (string) ($section['title'] ?? ''),
                        'requirement_key' => isset($section['requirement_key'])
                            ? (string) $section['requirement_key']
                            : null,
                        'updated_at' => isset($section['updated_at'])
                            ? (string) $section['updated_at']
                            : null,
                        'attached_document_ids' => array_values(array_filter(
                            (array) ($section['attached_document_ids'] ?? []),
                            fn (mixed $id): bool => is_string($id) || is_int($id),
                        )),
                        'body' => (string) ($section['body'] ?? ''),
                    ])
                    ->all();
            })
            ->values()
            ->all();
        $preferredRequirementKeys = self::CRITERION_REQUIREMENT_KEYS[strtolower(trim((string) $criterion->name))] ?? [];
        $criterionFocusSections = collect($sections)
            ->filter(fn (array $section): bool => in_array($section['requirement_key'] ?? null, $preferredRequirementKeys, true));
        $scopeFallback = false;

        if ($criterionFocusSections->isEmpty()) {
            $criterionFocusSections = collect($sections);
            $scopeFallback = true;
        }

        $budgetEvidence = (int) $criterion->number === 12
            ? data_get($snapshot, 'budget.assessment_evidence')
            : null;
        $scopedSections = $criterionFocusSections->values()->all();

        return [
            'evidence_mode' => 'criterion_scoped_submitted_snapshot',
            'criterion_focus' => [
                'number' => (int) $criterion->number,
                'name' => (string) $criterion->name,
                'preferred_requirement_keys' => $preferredRequirementKeys,
            ],
            'scope_fallback' => $scopeFallback,
            'criterion_focus_sections' => $scopedSections,
            'budget_evidence' => is_array($budgetEvidence) ? $budgetEvidence : null,
            'evidence_hash' => $this->criterionEvidenceHash(
                criterion: $criterion,
                sections: $scopedSections,
                budgetEvidence: is_array($budgetEvidence) ? $budgetEvidence : null,
                scopeFallback: $scopeFallback,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function assessmentText(array $context): string
    {
        if (($context['evidence_mode'] ?? null) === 'criterion_scoped_submitted_snapshot') {
            return json_encode([
                'criterion_focus' => $context['criterion_focus'] ?? [],
                'criterion_focus_sections' => $context['criterion_focus_sections'] ?? [],
                'budget_evidence' => $context['budget_evidence'] ?? [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        return collect([
            ...collect($context['relevant_sections'] ?? [])->pluck('body_excerpt')->all(),
            ...collect($context['supporting_section_summaries'] ?? [])->pluck('body_excerpt')->all(),
            (string) ($context['budget_summary'] ?? ''),
        ])
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->implode("\n");
    }

    /**
     * @param  list<CriterionEvidenceSection>  $sections
     * @param  BudgetEvidence|null  $budgetEvidence
     */
    private function criterionEvidenceHash(
        RatingCriterion $criterion,
        array $sections,
        ?array $budgetEvidence,
        bool $scopeFallback,
    ): string {
        $stableSections = collect($sections)
            ->map(fn (array $section): array => [
                'section_id' => $section['section_id'],
                'requirement_key' => $section['requirement_key'],
                'title' => $section['title'],
                'body' => $section['body'],
                'attached_document_ids' => $section['attached_document_ids'],
            ])
            ->sortBy(fn (array $section): string => $section['section_id'].'|'.$section['requirement_key'])
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'contract' => 'criterion_evidence_v1',
            'criterion_number' => (int) $criterion->number,
            'criterion_name' => (string) $criterion->name,
            'scope_fallback' => $scopeFallback,
            'sections' => $stableSections,
            'budget_evidence' => $budgetEvidence,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, string>|null
     */
    private function ideaValidationContext(?IdeaValidation $ideaValidation): ?array
    {
        if (! $ideaValidation instanceof IdeaValidation) {
            return null;
        }

        return $this->ideaValidationPromptInput([
            'problem' => $ideaValidation->problem,
            'target_customer' => $ideaValidation->target_customer,
            'solution' => $ideaValidation->solution,
            'value_proposition' => $ideaValidation->value_proposition,
            'demand_signal' => $ideaValidation->demand_signal,
            'revenue_model' => $ideaValidation->revenue_model,
        ]);
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $preferredRequirementKeys
     * @return Collection<int, PlanSection>
     */
    private function rankedSections(
        BusinessPlan $plan,
        array $terms,
        array $preferredRequirementKeys,
        ?string $excludedRequirementKey = null,
    ): Collection {
        return $this->sections($plan)
            ->filter(function (PlanSection $section) use ($excludedRequirementKey): bool {
                return $excludedRequirementKey === null
                    || $excludedRequirementKey === ''
                    || $this->requirementKey($section) !== $excludedRequirementKey;
            })
            ->sortByDesc(fn (PlanSection $section): int => $this->relevanceScore(
                section: $section,
                terms: $terms,
                preferredRequirementKeys: $preferredRequirementKeys,
            ))
            ->values();
    }

    /**
     * @param  Collection<int, PlanSection>  $sections
     * @param  array<int, int>  $limits
     * @return array<int, array{section_id:string,title:string,body_excerpt:string,requirement_key:string|null,updated_at:string|null}>
     */
    private function sectionCards(Collection $sections, array $limits, int $totalExcerptLimit): array
    {
        $cards = [];
        $remaining = $totalExcerptLimit;

        foreach ($sections->values() as $index => $section) {
            $limit = min((int) ($limits[$index] ?? 0), $remaining);
            if ($limit < 1) {
                break;
            }

            $excerpt = $this->contextualExcerpt(
                (string) $section->body,
                $this->keywords(implode(' ', [
                    $section->title,
                    (string) $this->requirementKey($section),
                ])),
                $limit,
            );
            if ($excerpt === '') {
                continue;
            }

            $cards[] = [
                'section_id' => (string) $section->getKey(),
                'title' => (string) $section->title,
                'body_excerpt' => $excerpt,
                'requirement_key' => $this->requirementKey($section),
                'updated_at' => $section->updated_at?->toIso8601String(),
            ];
            $remaining -= Str::length($excerpt);
        }

        return $cards;
    }

    /**
     * @return Collection<int, PlanSection>
     */
    private function sections(BusinessPlan $plan): Collection
    {
        if ($plan->relationLoaded('sections')) {
            return $plan->sections
                ->filter(fn (mixed $section): bool => $section instanceof PlanSection)
                ->values();
        }

        return $plan->sections()->get();
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $preferredRequirementKeys
     */
    private function relevanceScore(PlanSection $section, array $terms, array $preferredRequirementKeys): int
    {
        $requirementKey = $this->requirementKey($section);
        $title = strtolower(implode(' ', [$section->title, (string) $requirementKey]));
        $body = strtolower((string) $section->body);
        $score = in_array($requirementKey, $preferredRequirementKeys, true) ? 1000 : 0;

        foreach ($terms as $term) {
            if (str_contains($title, $term)) {
                $score += 80;
            }
            if (str_contains($body, $term)) {
                $score += 8;
            }
        }

        return $score;
    }

    private function requirementKey(PlanSection $section): ?string
    {
        $key = data_get($section->metadata, 'requirement_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $text): array
    {
        $ignored = [
            'about', 'and', 'business', 'describe', 'for', 'from', 'into', 'its', 'plan', 'section',
            'that', 'the', 'this', 'what', 'will', 'with', 'your',
        ];

        return collect(preg_split('/[^a-z0-9]+/', strtolower($text)) ?: [])
            ->filter(fn (string $word): bool => strlen($word) >= 2 && ! in_array($word, $ignored, true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * Preserve the opening, closing, and the most relevant paragraphs when
     * the full founder text is too large for an AI request.
     *
     * @param  array<int, string>  $terms
     */
    private function contextualExcerpt(string $text, array $terms, int $limit): string
    {
        $text = trim($text);
        if ($text === '' || Str::length($text) <= $limit) {
            return $text;
        }

        $paragraphs = preg_split('/(?:\R\s*){2,}/', $text) ?: [];
        if (count($paragraphs) > 1) {
            $ranked = collect($paragraphs)
                ->map(fn (string $paragraph, int $index): array => [
                    'index' => $index,
                    'paragraph' => trim($paragraph),
                    'score' => $this->paragraphScore($paragraph, $terms),
                ])
                ->filter(fn (array $paragraph): bool => $paragraph['paragraph'] !== '')
                ->values();
            $selectedIndexes = $ranked
                ->sortByDesc('score')
                ->take(4)
                ->pluck('index')
                ->push($ranked->first()['index'])
                ->push($ranked->last()['index'])
                ->unique()
                ->sort()
                ->values();
            $ranked = $selectedIndexes
                ->map(fn (int $index): string => (string) data_get($ranked->firstWhere('index', $index), 'paragraph'))
                ->implode("\n\n");

            if ($ranked !== '') {
                $text = $ranked;
            }
        }

        if (Str::length($text) <= $limit) {
            return $text;
        }

        $marker = "\n[...]\n";
        $available = max(1, $limit - Str::length($marker));
        $openingLength = (int) floor($available * 0.65);
        $closingLength = $available - $openingLength;

        return rtrim(Str::substr($text, 0, $openingLength))
            .$marker
            .ltrim(Str::substr($text, -$closingLength));
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function paragraphScore(string $paragraph, array $terms): int
    {
        $haystack = strtolower($paragraph);

        return collect($terms)
            ->sum(fn (string $term): int => str_contains($haystack, $term) ? 1 : 0);
    }
}
