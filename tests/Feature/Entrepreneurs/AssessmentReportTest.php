<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\NzResourceSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AssessmentReportTest extends TestCase
{
    use RefreshDatabase;

    private object $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        $this->seed(NzResourceSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return "%PDF-1.4\n".strip_tags($html);
            }
        };
        $this->app->instance(PdfRenderer::class, $this->renderer);
        $this->app->instance(PptxGenerator::class, new class implements PptxGenerator
        {
            public function render(Report $report): string
            {
                return "PPTX\n".$report->title;
            }
        });
    }

    public function test_assessment_report_contains_four_parts_notations_and_concept_pv(): void
    {
        [$advisor, $plan] = $this->plan();
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);
        app(Assessment::class)->adjustScore($assessment, 1, 78, 'Advisor confirmed the business type evidence.', $advisor);

        $report = app(ReportComposer::class)->composeEntrepreneurAssessment($assessment->refresh(), $advisor);

        $this->assertSame(ReportType::EntrepreneurAssessment, $report->type);
        $this->assertSame($plan->entrepreneur_profile_id, $report->entrepreneur_profile_id);
        $this->assertCount(4, $report->sections);
        foreach ([
            'entrepreneur_criterion_scores',
            'entrepreneur_criterion_feedback',
            'entrepreneur_overall_grade',
            'entrepreneur_improvement_actions',
        ] as $key) {
            $this->assertTrue($report->sections->contains('key', $key), "Missing assessment report section {$key}.");
        }

        $scoreSection = $report->sections->firstWhere('key', 'entrepreneur_criterion_scores');
        $this->assertStringContainsString('document support:', $scoreSection->body);
        $this->assertStringContainsString('data quality:', $scoreSection->body);
        $this->assertStringContainsString('advisor adjustment: 78/100', $scoreSection->body);
        $this->assertNotNull($report->metadata['concept_pv_calculation_id']);
        $this->assertGreaterThanOrEqual(0, $report->metadata['concept_pv_present_value']);
        $this->assertDatabaseHas('pv_calculations', [
            'id' => $report->metadata['concept_pv_calculation_id'],
            'type' => PvType::EntrepreneurConceptProjection->value,
            'entrepreneur_profile_id' => $plan->entrepreneur_profile_id,
        ]);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        $this->assertStringContainsString('Concept PV projection', $this->renderer->html);
    }

    public function test_overall_grade_thresholds_are_honoured_in_reports(): void
    {
        foreach ([92 => 'exceptional', 76 => 'strong', 61 => 'developing', 45 => 'needs_work'] as $score => $expectedGrade) {
            [$advisor, $plan] = $this->plan("grade-{$score}@example.test");
            $assessment = app(Assessment::class)->firstPass($plan, $advisor);
            $assessment->forceFill([
                'ai_scores' => $this->scoresAt($assessment, $score),
                'advisor_scores' => [],
                'concept_pv_calculation_id' => null,
            ])->save();

            $report = app(ReportComposer::class)->composeEntrepreneurAssessment($assessment->refresh(), $advisor);

            $this->assertSame($expectedGrade, $report->metadata['overall_grade']);
        }
    }

    public function test_report_tone_stays_honest_for_weak_plans(): void
    {
        [$advisor, $plan] = $this->plan('weak-report-founder@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);
        $assessment->forceFill([
            'ai_scores' => $this->scoresAt($assessment, 38),
            'advisor_scores' => [],
            'concept_pv_calculation_id' => null,
        ])->save();

        $report = app(ReportComposer::class)->composeEntrepreneurAssessment($assessment->refresh(), $advisor);
        $gradeSection = $report->sections->firstWhere('key', 'entrepreneur_overall_grade');

        $this->assertSame('needs_work', $report->metadata['overall_grade']);
        $this->assertStringContainsString('not ready for launch', strtolower($gradeSection->body));
        $this->assertStringNotContainsString('excellent', strtolower($gradeSection->body));
        $this->assertStringNotContainsString('ready for focused', strtolower($gradeSection->body));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scoresAt(PlanAssessment $assessment, int $score): array
    {
        return collect($assessment->ai_scores)
            ->map(fn (array $row): array => [
                ...$row,
                'score' => $score,
                'rationale' => $score < 60
                    ? 'The evidence is too thin to rely on yet.'
                    : 'The evidence supports the selected grade band.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function plan(string $email = 'assessment-report-founder@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Assessment Report Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Retail concept for regional operators.',
        ]);
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Retail service operators need clearer launch and compliance decisions.',
            'target_customer' => 'Regional retail service owners.',
            'solution' => 'A guided plan with market, legal, culture, and financial milestones.',
            'value_proposition' => 'The owner focuses effort and reduces launch risk.',
            'demand_signal' => 'Pilot interviews and customer evidence are complete.',
            'revenue_model' => 'Subscription revenue with onboarding support.',
        ], $advisor);
        app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, 'Ready for assessment reporting.');
        $plan = app(PlanBuilder::class)->start($profile, $advisor);

        foreach ([
            ['market', 'market-demand', 'Market demand', 'The industry, location, customer segment, competitors, demand, revenue, and goals are described with pilot evidence.'],
            ['strategy', 'strategy-goals', 'Strategy goals', 'The mission and vision statement, culture, goals and objectives, and unique success factors are connected to milestones.'],
            ['legal_operations', 'legal-environment', 'Legal environment', 'The legal environment, intellectual property, contracts, privacy duties, and means of doing business are listed.'],
            ['financial', 'financial-model', 'Financial model', 'The plan explains pricing, cash needs, margin, revenue, and support required to operate.'],
        ] as [$phase, $key, $title, $body]) {
            $section = app(PlanBuilder::class)->upsertSection(
                plan: $plan,
                phaseKey: $phase,
                key: $key,
                title: $title,
                body: $body,
                actor: $advisor,
            );

            if ($key === 'market-demand') {
                $section->forceFill(['attached_document_ids' => [(string) Str::uuid()]])->save();
            }
        }

        return [$advisor, $plan->refresh()->load('sections')];
    }
}
