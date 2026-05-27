<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\User;
use App\Services\Npo\NpoBoardAccess;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoValueCalculator;
use App\Services\Pdf\PdfRenderer;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class NpoReportSuiteTest extends TestCase
{
    use RefreshDatabase;

    private object $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return 'npo-report-pdf';
            }
        };
        $this->app->instance(PdfRenderer::class, $this->renderer);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_health_and_advisor_reports_render_required_sections_and_board_policy_gates(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient();
        $this->recordHealth($engagement, $advisor);
        app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_expenditure' => 60000,
            'beneficiary_count' => 100,
            'programme_type' => 'community_services',
        ], $advisor);

        $health = app(ReportComposer::class)->composeNpoHealth($engagement, $advisor);
        $advisorReport = app(ReportComposer::class)->composeNpoAdvisor($engagement, $advisor);

        $this->assertSame(ReportType::NpoHealth, $health->type);
        $this->assertSame($engagement->id, $health->npo_engagement_id);
        $this->assertTrue($health->sections->contains('key', 'health_snapshot'));
        $this->assertTrue($health->sections->contains('key', 'dimension_scores'));
        $this->assertStringContainsString('plain-English client summary', $health->sections->firstWhere('key', 'health_snapshot')->data_quality_note);
        $this->assertStringNotContainsString('EBITDA', $health->sections->pluck('body')->implode("\n"));

        $this->assertSame(ReportType::NpoAdvisor, $advisorReport->type);
        $this->assertTrue((bool) $advisorReport->metadata['confidential']);
        $this->assertSame('cognac', $advisorReport->metadata['header_colour']);
        $this->assertStringContainsString('CONFIDENTIAL', $advisorReport->sections->firstWhere('key', 'confidential_header')->title);
        $this->assertStringContainsString('mission ROI', $advisorReport->sections->firstWhere('key', 'mission_roi_value_workings')->data_quality_note);

        $board = $this->boardUser();
        app(NpoBoardAccess::class)->activate($engagement, $board, $advisor);

        $this->assertTrue(Gate::forUser($board)->allows('view', $health));
        $this->assertFalse(Gate::forUser($board)->allows('view', $advisorReport));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.health_report_generated',
            'subject_id' => $health->id,
        ]);
    }

    public function test_social_enterprise_dual_report_requires_reviewed_evidenced_tensions(): void
    {
        [$advisor, , $engagement] = $this->npoClient(
            advisorEmail: 'dual-report-advisor@example.test',
            clientName: 'Dual Impact Trust',
            subType: NpoEngagementSubType::SocialEnterprise,
            socialEnterprise: true,
        );
        $scorecard = NpoSocialEnterpriseScorecard::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'commercial_score' => 72,
            'mission_score' => 84,
            'commercial_weight' => 40,
            'mission_weight' => 60,
            'blended_score' => 79.2,
            'commercial_axes' => [['dimension' => 'cash', 'label' => 'Cash', 'score' => 72]],
            'mission_axes' => [['dimension' => 'mission', 'label' => 'Mission', 'score' => 84]],
            'source_attributions' => [['claim' => 'Scorecard fixture', 'source_reference' => 'test']],
            'calculated_at' => now(),
        ]);

        try {
            app(ReportComposer::class)->composeSocialEnterpriseDual($engagement, $advisor);
            $this->fail('Dual Impact report generated without reviewed tensions.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('scorecard and advisor-reviewed evidenced tensions', $exception->getMessage());
        }

        NpoTensionAnalysis::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'npo_social_enterprise_scorecard_id' => $scorecard->id,
            'review_status' => NpoTensionAnalysis::REVIEW_REVIEWED,
            'tensions' => [[
                'type' => NpoTensionAnalysis::TYPE_REVENUE_VS_ACCESS,
                'title' => 'Access price tension',
                'commercial_implication' => 'Higher earned revenue improves resilience.',
                'mission_implication' => 'Higher prices may reduce access for priority communities.',
                'strategic_options' => ['Use tiered pricing'],
                'advisor_recommended_path' => 'Use tiered pricing with board thresholds.',
                'data_points' => [[
                    'key' => 'mission_score',
                    'label' => 'Mission score',
                    'value' => 84,
                    'source_reference' => 'npo_social_enterprise_scorecard:mission',
                ]],
            ]],
            'ai_response' => ['model' => 'test'],
            'source_attributions' => [['claim' => 'Reviewed tension', 'source_reference' => 'test']],
            'reviewed_by_user_id' => $advisor->id,
            'reviewed_at' => now(),
            'generated_at' => now(),
        ]);

        $report = app(ReportComposer::class)->composeSocialEnterpriseDual($engagement, $advisor);

        $this->assertSame(ReportType::SocialEnterpriseDual, $report->type);
        $this->assertTrue($report->sections->contains('key', 'dual_scorecard'));
        $this->assertTrue($report->sections->contains('key', 'evidenced_tensions'));
        $this->assertTrue($report->sections->contains('key', 'tension_evidence'));
        $this->assertStringContainsString('Access price tension', $report->sections->firstWhere('key', 'evidenced_tensions')->body);
        $this->assertStringContainsString('advisor-reviewed data points', $report->sections->firstWhere('key', 'evidenced_tensions')->data_quality_note);
    }

    public function test_advisor_route_generates_npo_health_report_with_engagement_stamp(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient(
            advisorEmail: 'route-npo-health-advisor@example.test',
            clientName: 'Route NPO Health Trust',
        );
        $this->recordHealth($engagement, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::NpoHealth->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertDatabaseHas('reports', [
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'type' => ReportType::NpoHealth->value,
            'review_status' => 'not_required',
        ]);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(
        string $advisorEmail = 'npo-report-advisor@example.test',
        string $clientName = 'NPO Report Trust',
        NpoEngagementSubType $subType = NpoEngagementSubType::StandardNpo,
        bool $socialEnterprise = false,
    ): array {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->id,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => $subType,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'tiriti_mode' => NpoTiritiMode::Standalone,
            'social_enterprise' => $socialEnterprise,
            'social_enterprise_type' => $socialEnterprise ? NpoSocialEnterpriseType::CrossSubsidy : null,
            'commercial_weight' => $socialEnterprise ? 40 : null,
            'mission_weight' => $socialEnterprise ? 60 : null,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function recordHealth(NpoEngagement $engagement, User $advisor): void
    {
        app(NpoHealthScorer::class)->recordAssessment($engagement, [
            'mission_strategy' => 80,
            'service_operations' => 70,
            'governance_compliance' => 65,
            'financial_sustainability' => 72,
            'people_capability' => 68,
            'impact_measurement' => 78,
            'funding_resilience' => 58,
            'te_tiriti' => 75,
        ], [
            'funding_resilience' => [[
                'id' => (string) Str::uuid(),
                'severity' => 'medium',
                'title' => 'Funding concentration',
                'body' => 'Main funder concentration needs a board threshold.',
                'attributions' => [['claim' => 'Funder concentration', 'source_reference' => 'client_funder_records:test']],
            ]],
        ], $advisor);
    }

    private function boardUser(): User
    {
        $user = User::factory()->create([
            'email' => 'npo-report-board@example.test',
            'user_type' => User::TYPE_NPO_BOARD_MEMBER,
            'primary_role' => User::TYPE_NPO_BOARD_MEMBER,
        ]);
        $user->assignRole(User::TYPE_NPO_BOARD_MEMBER);

        return $user;
    }
}
