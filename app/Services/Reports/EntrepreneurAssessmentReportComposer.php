<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\NzResource;
use App\Models\PlanAssessment;
use App\Models\PvCalculation;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AssessmentScoring;
use App\Services\Reports\Contracts\EntrepreneurAssessmentReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\EntrepreneurAssessmentCriterion;
use App\Services\Reports\Data\EntrepreneurAssessmentInputs;
use App\Services\Reports\Data\EntrepreneurDocumentSupport;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns entrepreneur assessment report composition and its typed output contract.
 *
 * @phpstan-type Attribution array{claim: string, source_reference: string}
 * @phpstan-type Criteria Collection<int, EntrepreneurAssessmentCriterion>
 */
final class EntrepreneurAssessmentReportComposer implements EntrepreneurAssessmentReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(PlanAssessment $assessment, ?User $actor = null): Report
    {
        return DB::transaction(function () use ($assessment, $actor): Report {
            $inputs = $this->inputs($assessment);
            $incompleteCriterionNumbers = AssessmentScoring::incompleteCriterionNumbers($inputs->assessment);

            if ($incompleteCriterionNumbers !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Entrepreneur assessment reports require valid scores for every criterion. Missing criterion: %s.',
                    implode(', ', $incompleteCriterionNumbers),
                ));
            }

            $documentSupport = $this->documentSupport($inputs->assessment);
            $criteria = $this->criteria($inputs->assessment);
            $weightedScore = AssessmentScoring::weightedScore($inputs->assessment);
            $overallGrade = (string) $inputs->assessment->ratingFramework->gradeFor($weightedScore);
            $conceptPv = $inputs->assessment->conceptPvCalculation
                ?: $this->createConceptPv($inputs, $weightedScore, $overallGrade, $actor);

            if ($inputs->assessment->overall_grade !== $overallGrade || $inputs->assessment->concept_pv_calculation_id !== $conceptPv->getKey()) {
                $inputs->assessment->forceFill([
                    'overall_grade' => $overallGrade,
                    'concept_pv_calculation_id' => $conceptPv->getKey(),
                ])->save();
            }

            $presentValue = $this->presentValue($conceptPv);
            $report = Report::query()->create([
                'client_id' => $inputs->plan->client_id,
                'entrepreneur_profile_id' => $inputs->profile->getKey(),
                'type' => ReportType::EntrepreneurAssessment,
                'title' => ReportType::EntrepreneurAssessment->label().' - '.$inputs->profile->name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'business_plan_id' => $inputs->plan->getKey(),
                    'plan_assessment_id' => $inputs->assessment->getKey(),
                    'assessment_round' => $inputs->assessment->round,
                    'rating_framework_id' => $inputs->assessment->rating_framework_id,
                    'overall_grade' => $overallGrade,
                    'weighted_score' => $weightedScore,
                    'concept_pv_calculation_id' => $conceptPv->getKey(),
                    'concept_pv_present_value' => $presentValue,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections(
                $report,
                $inputs,
                $this->sections($inputs->assessment, $criteria, $documentSupport, $weightedScore, $overallGrade, $conceptPv),
            );
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'entrepreneur.assessment_report_generated',
                fn (Report $rendered): array => [
                    'business_plan_id' => $inputs->plan->getKey(),
                    'plan_assessment_id' => $inputs->assessment->getKey(),
                    'overall_grade' => $overallGrade,
                    'weighted_score' => $weightedScore,
                    'concept_pv_calculation_id' => $conceptPv->getKey(),
                    'sections' => $rendered->sections()->count(),
                    'pdf_path' => (string) $rendered->pdf_path,
                ],
            );

            return $report->refresh()->load(['entrepreneurProfile', 'sections']);
        });
    }

    private function inputs(PlanAssessment $assessment): EntrepreneurAssessmentInputs
    {
        $assessment = $assessment->refresh()->load([
            'businessPlan.entrepreneurProfile',
            'businessPlan.sections',
            'conceptPvCalculation',
            'ratingFramework.criteria',
        ]);
        $plan = $assessment->businessPlan;
        $profile = $plan?->entrepreneurProfile;

        if (! $plan instanceof BusinessPlan || ! $profile instanceof EntrepreneurProfile || $assessment->ratingFramework === null) {
            throw new InvalidArgumentException('Entrepreneur assessment reports require a plan, profile, and rating framework.');
        }

        return new EntrepreneurAssessmentInputs($assessment, $plan, $profile);
    }

    /**
     * @param  Criteria  $criteria
     * @return list<ReportSectionDraft>
     */
    private function sections(
        PlanAssessment $assessment,
        Collection $criteria,
        EntrepreneurDocumentSupport $documentSupport,
        float $weightedScore,
        string $overallGrade,
        PvCalculation $conceptPv,
    ): array {
        return [
            $this->scoreSection($assessment, $criteria, $documentSupport),
            $this->feedbackSection($assessment, $criteria, $documentSupport),
            $this->gradeSection($assessment, $documentSupport, $weightedScore, $overallGrade, $conceptPv),
            $this->actionsSection($assessment, $criteria, $documentSupport),
        ];
    }

    /** @return Criteria */
    private function criteria(PlanAssessment $assessment): Collection
    {
        return collect(AssessmentScoring::criteriaPayload($assessment))
            ->map(function (array $row): EntrepreneurAssessmentCriterion {
                $advisorScore = $this->intOrNull($row['advisor_score'] ?? null);

                return new EntrepreneurAssessmentCriterion(
                    id: $this->stringValue($row['criterion_id'] ?? null),
                    number: $this->intValue($row['criterion_number'] ?? null),
                    name: $this->stringValue($row['criterion_name'] ?? null),
                    weight: $this->floatValue($row['weight'] ?? null),
                    aiScore: $this->intOrNull($row['ai_score'] ?? null),
                    advisorScore: $advisorScore,
                    score: max(0, min(100, $this->intValue($row['score'] ?? null))),
                    grade: $this->stringValue($row['grade'] ?? null),
                    rationale: $advisorScore !== null
                        ? 'Advisor adjustment: '.$this->stringValue($row['rationale'] ?? null, 'No note recorded.')
                        : $this->stringValue($row['rationale'] ?? null, 'First-pass assessment did not provide a rationale.'),
                    attributions: $this->normalisedAttributions($row['attributions'] ?? null, $this->stringValue($row['criterion_id'] ?? null)),
                );
            })
            ->values();
    }

    /** @param Criteria $criteria */
    private function scoreSection(PlanAssessment $assessment, Collection $criteria, EntrepreneurDocumentSupport $documentSupport): ReportSectionDraft
    {
        $body = $criteria
            ->map(function (EntrepreneurAssessmentCriterion $criterion) use ($documentSupport): string {
                $advisorNotation = $criterion->advisorScore === null
                    ? 'advisor adjustment: none'
                    : 'advisor adjustment: '.$criterion->advisorScore.'/100';

                return sprintf(
                    '%02d. %s - final %d/100 (%s); AI first-pass %s; %s; document support: %s; data quality: %s.',
                    $criterion->number,
                    $criterion->name,
                    $criterion->score,
                    $this->gradeLabel($criterion->grade),
                    $criterion->aiScore === null ? 'n/a' : $criterion->aiScore.'/100',
                    $advisorNotation,
                    $documentSupport->note,
                    $documentSupport->dataQualityIndicator,
                );
            })
            ->implode("\n");

        return $this->section(
            key: 'entrepreneur_criterion_scores',
            title: 'Criterion scores and evidence notation',
            body: $body,
            sourceReference: 'plan_assessment:'.$assessment->getKey(),
            documentSupport: $documentSupport,
            dataQualityNote: 'Data quality note: scores combine AI first-pass scoring, advisor adjustments where present, section document-support notation, and current draft-plan evidence.',
            metadata: [
                'criterion_count' => $criteria->count(),
                'criterion_ids' => $criteria->map(fn (EntrepreneurAssessmentCriterion $criterion): string => $criterion->id)->all(),
                'assessment_round' => $assessment->round,
            ],
        );
    }

    /** @param Criteria $criteria */
    private function feedbackSection(PlanAssessment $assessment, Collection $criteria, EntrepreneurDocumentSupport $documentSupport): ReportSectionDraft
    {
        return $this->section(
            key: 'entrepreneur_criterion_feedback',
            title: 'Written feedback by criterion',
            body: $criteria
                ->map(fn (EntrepreneurAssessmentCriterion $criterion): string => sprintf('%02d. %s: %s', $criterion->number, $criterion->name, $criterion->rationale))
                ->implode("\n"),
            sourceReference: 'plan_assessment_feedback:'.$assessment->getKey(),
            documentSupport: $documentSupport,
            dataQualityNote: 'Data quality note: criterion feedback is intentionally direct and may identify gaps even where the draft is promising.',
            metadata: ['feedback_count' => $criteria->count()],
        );
    }

    private function gradeSection(
        PlanAssessment $assessment,
        EntrepreneurDocumentSupport $documentSupport,
        float $weightedScore,
        string $overallGrade,
        PvCalculation $conceptPv,
    ): ReportSectionDraft {
        $gradeLabel = $this->gradeLabel($overallGrade);
        $presentValue = $this->presentValue($conceptPv);
        $readiness = match ($overallGrade) {
            'exceptional' => 'The plan is ready for focused advisor-supported execution, subject to normal commercial validation.',
            'strong' => 'The plan is close to ready; resolve the listed evidence gaps before relying on it for launch decisions.',
            'developing' => 'The plan is directionally useful but not ready for launch without material revision and evidence.',
            default => 'This plan is not ready for launch or advisory conversion yet; it needs clearer proof before commitment.',
        };

        return $this->section(
            key: 'entrepreneur_overall_grade',
            title: 'Overall grade and concept PV',
            body: sprintf(
                "Overall grade: %s (%0.2f/100 weighted).\nRationale: %s\nConcept PV projection: NZD %s present value using draft-stage cash-flow assumptions and a risk-adjusted discount rate of %0.1f%%.",
                $gradeLabel,
                $weightedScore,
                $readiness,
                number_format($presentValue, 0),
                ((float) $conceptPv->discount_rate) * 100,
            ),
            sourceReference: 'plan_assessment_grade:'.$assessment->getKey(),
            documentSupport: $documentSupport,
            dataQualityNote: 'Data quality note: concept PV is a projection from plan maturity, not a valuation or investment recommendation.',
            metadata: [
                'overall_grade' => $overallGrade,
                'overall_grade_label' => $gradeLabel,
                'weighted_score' => $weightedScore,
                'concept_pv_calculation_id' => (string) $conceptPv->getKey(),
                'concept_pv_present_value' => $presentValue,
                'discount_rate' => (float) $conceptPv->discount_rate,
                'discount_method' => $conceptPv->discount_method->value,
            ],
        );
    }

    /** @param Criteria $criteria */
    private function actionsSection(PlanAssessment $assessment, Collection $criteria, EntrepreneurDocumentSupport $documentSupport): ReportSectionDraft
    {
        /** @var Criteria $priorityCriteria */
        $priorityCriteria = $criteria->sortBy(fn (EntrepreneurAssessmentCriterion $criterion): int => $criterion->score)->take(4)->values();
        $gapTags = $priorityCriteria
            ->flatMap(fn (EntrepreneurAssessmentCriterion $criterion): array => $this->criterionGapTags($criterion->name))
            ->unique()
            ->values()
            ->all();
        $resources = $this->resources($gapTags);
        $resourceLines = $resources->isEmpty()
            ? ['NZ resources: no matching active resources found for these gaps.']
            : $resources->map(fn (NzResource $resource): string => sprintf('NZ resource: %s (%s)', $resource->title, $resource->url))->all();
        $actions = $priorityCriteria
            ->map(function (EntrepreneurAssessmentCriterion $criterion, int $index) use ($resources): string {
                $resource = $resources->get($index % max(1, $resources->count()));
                $resourceText = $resource instanceof NzResource ? ' Use '.$resource->title.'.' : '';

                return sprintf(
                    '%d. Strengthen %s (current %d/100): add specific evidence, owner actions, dates, and decision criteria before treating this as launch-ready.%s',
                    $index + 1,
                    $criterion->name,
                    $criterion->score,
                    $resourceText,
                );
            })
            ->all();

        return $this->section(
            key: 'entrepreneur_improvement_actions',
            title: 'Prioritised improvement actions',
            body: implode("\n", [...$actions, ...$resourceLines]),
            sourceReference: 'plan_assessment_actions:'.$assessment->getKey(),
            documentSupport: $documentSupport,
            dataQualityNote: 'Data quality note: actions prioritise the lowest scoring criteria and cite NZ resource matches where available.',
            metadata: [
                'prioritised_criteria' => $priorityCriteria->map(fn (EntrepreneurAssessmentCriterion $criterion): int => $criterion->number)->all(),
                'gap_tags' => $gapTags,
                'resource_count' => $resources->count(),
                'resource_ids' => $resources->map(fn (NzResource $resource): string => (string) $resource->getKey())->all(),
            ],
        );
    }

    private function createConceptPv(
        EntrepreneurAssessmentInputs $inputs,
        float $weightedScore,
        string $overallGrade,
        ?User $actor,
    ): PvCalculation {
        $discountRate = match ($overallGrade) {
            'exceptional' => 0.14,
            'strong' => 0.16,
            'developing' => 0.21,
            default => 0.28,
        };
        $annualOpportunity = round(max(0.0, ($weightedScore - 45.0) * 2400.0), 2);
        $cashFlows = [
            1 => round($annualOpportunity * 0.45, 2),
            2 => round($annualOpportunity * 0.75, 2),
            3 => round($annualOpportunity, 2),
            4 => round($annualOpportunity * 1.12, 2),
            5 => round($annualOpportunity * 1.22, 2),
        ];
        $discounted = $this->discountedRows($cashFlows, $discountRate);

        return PvCalculation::query()->create([
            'client_id' => $inputs->plan->client_id,
            'entrepreneur_profile_id' => $inputs->profile->getKey(),
            'type' => PvType::EntrepreneurConceptProjection,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => $discountRate,
            'discount_rate_rationale' => 'Draft-stage concept PV uses a conservative risk-adjusted rate based on the assessment grade.',
            'inputs' => [
                'assessment_weighted_score' => $weightedScore,
                'overall_grade' => $overallGrade,
                'cash_flows' => collect($cashFlows)->map(fn (float $amount, int $period): array => ['period' => $period, 'amount' => $amount])->values()->all(),
                'method_note' => 'Indicative concept projection from plan maturity; not a valuation.',
            ],
            'result' => [
                'present_value' => round(collect($discounted)->sum('present_value'), 2),
                'discounted_cash_flows' => $discounted,
                'data_quality_indicator' => 'draft_projection',
            ],
            'as_at' => now(),
            'created_by_user_id' => $actor?->getKey(),
            'source_attributions' => [
                ['claim' => 'Concept PV derived from entrepreneur assessment weighted score.', 'source_reference' => 'plan_assessment:'.$inputs->assessment->getKey()],
                ['claim' => 'Concept PV derived from current business plan draft.', 'source_reference' => 'business_plan:'.$inputs->plan->getKey()],
            ],
        ]);
    }

    /**
     * @param  array<int, float>  $cashFlows
     * @return list<array{period: int, amount: float, present_value: float}>
     */
    private function discountedRows(array $cashFlows, float $discountRate): array
    {
        return collect($cashFlows)
            ->map(fn (float $amount, int $period): array => [
                'period' => $period,
                'amount' => $amount,
                'present_value' => round($amount / ((1 + $discountRate) ** $period), 2),
            ])
            ->values()
            ->all();
    }

    private function documentSupport(PlanAssessment $assessment): EntrepreneurDocumentSupport
    {
        $supportData = $assessment->document_support;
        $count = is_array($supportData) && is_numeric($supportData['attached_document_count'] ?? null)
            ? (int) $supportData['attached_document_count']
            : 0;
        $support = $count > 0 ? AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED : AnalysisFinding::DOCUMENT_SUPPORT_NONE;

        return new EntrepreneurDocumentSupport(
            support: $support,
            note: $count > 0 ? "Backed by {$count} uploaded document".($count === 1 ? '' : 's').'.' : '',
            dataQualityIndicator: $count > 0 ? 'document-supported draft' : 'draft-only evidence',
        );
    }

    /** @return list<Attribution> */
    private function normalisedAttributions(mixed $attributions, string $criterionId): array
    {
        if (! is_array($attributions)) {
            return [[
                'claim' => 'Criterion assessment source.',
                'source_reference' => 'rating_criterion:'.$criterionId,
            ]];
        }

        $normalised = collect($attributions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'claim' => $this->stringValue($item['claim'] ?? null, 'Criterion assessment source.'),
                'source_reference' => $this->stringValue($item['source_reference'] ?? null),
            ])
            ->filter(fn (array $item): bool => $item['source_reference'] !== '')
            ->values()
            ->all();

        return $normalised !== [] ? $normalised : [[
            'claim' => 'Criterion assessment source.',
            'source_reference' => 'rating_criterion:'.$criterionId,
        ]];
    }

    /** @param list<string> $gapTags
     * @return Collection<int, NzResource>
     */
    private function resources(array $gapTags): Collection
    {
        return NzResource::query()
            ->where('active', true)
            ->get()
            ->filter(fn (NzResource $resource): bool => array_intersect($resource->gap_tags ?? [], $gapTags) !== [])
            ->take(4)
            ->values();
    }

    /** @return list<string> */
    private function criterionGapTags(string $criterionName): array
    {
        $name = strtolower($criterionName);

        if (str_contains($name, 'legal') || str_contains($name, 'intellectual') || str_contains($name, 'means')) {
            return ['legal'];
        }

        if (str_contains($name, 'industry') || str_contains($name, 'location') || str_contains($name, 'apart')) {
            return ['market', 'demand'];
        }

        if (str_contains($name, 'goal') || str_contains($name, 'mission') || str_contains($name, 'vision') || str_contains($name, 'culture')) {
            return ['strategy', 'foundation'];
        }

        return ['foundation'];
    }

    private function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'exceptional' => 'Exceptional',
            'strong' => 'Strong',
            'developing' => 'Developing',
            default => 'Needs Work',
        };
    }

    /** @param array<string, bool|float|int|string|list<bool|float|int|string>> $metadata */
    private function section(
        string $key,
        string $title,
        string $body,
        string $sourceReference,
        EntrepreneurDocumentSupport $documentSupport,
        string $dataQualityNote,
        array $metadata,
    ): ReportSectionDraft {
        return new ReportSectionDraft(
            key: $key,
            title: $title,
            body: $body,
            attributions: [['claim' => $title, 'source_reference' => $sourceReference]],
            documentSupport: $documentSupport->support,
            documentSupportNote: $documentSupport->note,
            dataQualityNote: $dataQualityNote,
            metadata: $metadata,
        );
    }

    /** @param list<ReportSectionDraft> $sections */
    private function persistSections(Report $report, EntrepreneurAssessmentInputs $inputs, array $sections): void
    {
        foreach ($sections as $position => $section) {
            ReportSection::query()->create([
                ...$section->toAttributes(),
                'report_id' => $report->getKey(),
                'client_id' => $inputs->plan->client_id,
                'entrepreneur_profile_id' => $inputs->profile->getKey(),
                'position' => $position + 1,
            ]);
        }
    }

    /** @param Closure(Report): array<string, bool|float|int|string> $after */
    private function renderAndAuditAfterCommit(Report $report, ?User $actor, string $action, Closure $after): void
    {
        $callback = function () use ($report, $actor, $action, $after): void {
            $report->refresh()->load(['client', 'entrepreneurProfile', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after($report->refresh()));
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    private function presentValue(PvCalculation $calculation): float
    {
        $result = $calculation->result;

        return is_numeric($result['present_value'] ?? null)
            ? (float) $result['present_value']
            : 0.0;
    }

    private function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_scalar($value) ? (string) $value : $fallback;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function intValue(mixed $value): int
    {
        return $this->intOrNull($value) ?? 0;
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
