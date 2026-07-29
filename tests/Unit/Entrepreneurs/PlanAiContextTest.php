<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\IdeaValidation;
use App\Models\PlanSection;
use App\Models\RatingCriterion;
use App\Services\Entrepreneurs\PlanAiContext;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class PlanAiContextTest extends TestCase
{
    public function test_requirement_assistance_keeps_long_form_founder_content_within_a_fixed_context_budget(): void
    {
        $context = (new PlanAiContext)->requirementAssistance(
            plan: $this->planWithSections(),
            requirement: [
                'key' => 'revenue-model',
                'title' => 'Revenue model',
                'description' => 'Explain pricing, margin, cost drivers, cash cycle, and early revenue assumptions.',
                'phase_title' => 'Financial',
            ],
            ideaValidation: new IdeaValidation([
                'problem' => $this->longText('Problem evidence', 10_000),
                'target_customer' => $this->longText('Target customer evidence', 10_000),
                'solution' => $this->longText('Solution evidence', 10_000),
                'value_proposition' => $this->longText('Value proposition evidence', 10_000),
                'demand_signal' => $this->longText('Demand signal evidence', 10_000),
                'revenue_model' => $this->longText('Revenue model evidence', 10_000),
            ]),
            currentDraft: $this->longText('Revenue model current draft', PlanAiContext::PLAN_SECTION_BODY_MAX_LENGTH),
        );

        $this->assertSame(25_000, PlanAiContext::PLAN_SECTION_BODY_MAX_LENGTH);
        $this->assertLessThanOrEqual(4_000, strlen($context['current_draft']));
        $this->assertCount(6, $context['idea_validation']);
        $this->assertLessThanOrEqual(4_800, array_sum(array_map('strlen', $context['idea_validation'])));
        $this->assertCount(5, $context['existing_sections']);
        $this->assertLessThanOrEqual(
            3_200,
            array_sum(array_map(fn (array $section): int => strlen($section['body_excerpt']), $context['existing_sections'])),
        );
    }

    public function test_criterion_assessment_prefers_the_mapped_requirement_without_sending_the_full_plan(): void
    {
        $plan = $this->planWithSections();
        $context = (new PlanAiContext)->criterionAssessment(
            plan: $plan,
            criterion: new RatingCriterion([
                'number' => 11,
                'name' => 'Legal Environment',
            ]),
            budgetSummary: $this->longText('Budget runway and funding summary', 3_000),
        );

        $this->assertSame('legal-environment', $context['relevant_sections'][0]['requirement_key']);
        $this->assertLessThanOrEqual(6_000, strlen($context['relevant_sections'][0]['body_excerpt']));
        $this->assertLessThanOrEqual(
            12_010,
            strlen((new PlanAiContext)->assessmentText($context)),
        );
    }

    private function planWithSections(): BusinessPlan
    {
        $requirements = [
            'business-type-location',
            'mission-vision',
            'industry-context',
            'differentiation',
            'success-factors',
            'goals-objectives',
            'culture',
            'intellectual-property',
            'legal-environment',
            'systems-software-processes',
            'financial-assumptions',
            'revenue-model',
            'launch-funding',
        ];
        $sections = collect($requirements)
            ->map(fn (string $requirementKey): PlanSection => new PlanSection([
                'title' => str_replace('-', ' ', $requirementKey),
                'body' => $this->longText($requirementKey.' detailed founder evidence', PlanAiContext::PLAN_SECTION_BODY_MAX_LENGTH),
                'metadata' => ['requirement_key' => $requirementKey],
            ]));
        $plan = new BusinessPlan;
        $plan->setRelation('sections', new Collection($sections));

        return $plan;
    }

    private function longText(string $prefix, int $length): string
    {
        $text = str_repeat($prefix.' with assumptions, evidence, risks, and decisions. ', (int) ceil($length / (strlen($prefix) + 58)));

        return substr($text, 0, $length);
    }
}
