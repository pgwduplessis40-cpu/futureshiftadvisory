<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\PvType;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\DdOutcomeRecord;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\LearningUpdate;
use App\Models\PvCalculation;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DdOnboarding;
use App\Services\Learning\Layers\DdLearning;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DdLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_dd_learning_creates_valuation_accuracy_and_checklist_pattern_candidates_only(): void
    {
        [, $engagement] = $this->ddEngagement();

        $this->ddValuation($engagement, 1_000_000);
        DdOutcomeRecord::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'recorded_price' => 1_300_000,
            'actual_outcome' => ['source' => 'settlement_statement', 'outcome' => 'completed_acquisition'],
            'recorded_at' => now(),
        ]);

        foreach (['Financial', 'Legal', 'Tax'] as $workstream) {
            $this->finding(
                engagement: $engagement,
                workstream: $workstream,
                severity: FindingSeverity::High,
                title: 'Supplier concentration risk',
                body: 'Repeated DD finding used to test checklist pattern learning.',
            );
        }

        $run = app(DdLearning::class)->run(
            minOutcomes: 1,
            varianceThreshold: 0.1,
            patternThreshold: 3,
            windowDays: 30,
        );

        $this->assertSame(2, $run->candidates_created);

        $valuationCandidate = LearningUpdate::query()
            ->where('layer_id', DdLearning::LAYER_ID)
            ->where('source->type', 'dd_valuation_accuracy')
            ->firstOrFail();
        $patternCandidate = LearningUpdate::query()
            ->where('layer_id', DdLearning::LAYER_ID)
            ->where('source->type', 'dd_finding_pattern')
            ->firstOrFail();

        $this->assertSame('review_dd_valuation_calibration', $valuationCandidate->proposed_change['action']);
        $this->assertFalse($valuationCandidate->proposed_change['automatic_application']);
        $this->assertSame(0.3, $valuationCandidate->evidence['average_absolute_variance_rate']);
        $this->assertSame('review_dd_checklist_pattern', $patternCandidate->proposed_change['action']);
        $this->assertFalse($patternCandidate->proposed_change['automatic_application']);
        $this->assertSame(3, $patternCandidate->evidence['finding_count']);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        app(DdLearning::class)->run(
            minOutcomes: 1,
            varianceThreshold: 0.1,
            patternThreshold: 3,
            windowDays: 30,
        );

        $this->assertSame(1, LearningUpdate::query()->where('source->type', 'dd_valuation_accuracy')->count());
        $this->assertSame(1, LearningUpdate::query()->where('source->type', 'dd_finding_pattern')->count());
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'dd-learning-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'DD Learning Buyer Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'DD Learning Target Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'industry' => 'Distribution',
            ],
        );

        return [$advisor, $engagement];
    }

    private function finding(
        DdEngagement $engagement,
        string $workstream,
        FindingSeverity $severity,
        string $title,
        string $body,
    ): AnalysisFinding {
        $run = AnalysisRun::query()->create([
            'client_id' => $engagement->client_id,
            'module' => AnalysisModule::DdWorkstream,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => AnalysisLens::values(),
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        DdWorkstream::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'workstream' => $workstream,
            'status' => DdWorkstream::STATUS_COMPLETED,
            'analysis_run_id' => $run->id,
            'data_room_item_ids' => [],
            'verification_weight' => 2,
            'nz_checks' => [],
            'ran_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $engagement->client_id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'dd_learning_test:'.$workstream],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
            'uncertainty' => Uncertainty::Low,
            'data_quality_disclaimer' => 'Data quality note: DD fixture quality is low.',
            'bias_signals' => [],
        ]);
    }

    private function ddValuation(DdEngagement $engagement, float $mid): DdValuation
    {
        $calculation = PvCalculation::query()->create([
            'client_id' => $engagement->client_id,
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'DD learning fixture valuation rate.',
            'inputs' => ['fixture' => true],
            'result' => ['present_value' => $mid],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'DD learning fixture valuation', 'source_reference' => 'dd_learning_test:valuation'],
            ],
        ]);

        $businessValuation = BusinessValuation::query()->create([
            'client_id' => $engagement->client_id,
            'pv_calculation_id' => $calculation->id,
            'sde_value' => ['mid' => $mid * 0.95],
            'ebitda_value' => ['mid' => $mid],
            'dcf_value' => ['mid' => $mid * 1.05],
            'reconciled_low' => $mid * 0.9,
            'reconciled_mid' => $mid,
            'reconciled_high' => $mid * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'DD learning fixture business valuation', 'source_reference' => 'dd_learning_test:business_valuation'],
            ],
            'as_at' => now(),
        ]);

        return DdValuation::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'business_valuation_id' => $businessValuation->id,
            'pv_calculation_id' => $calculation->id,
            'source_currency' => 'NZD',
            'normalised_currency' => 'NZD',
            'source_to_nzd_rate' => 1,
            'normalised_values' => [
                'reconciled' => [
                    'low' => $mid * 0.9,
                    'mid' => $mid,
                    'high' => $mid * 1.1,
                ],
            ],
            'sensitivity' => [
                'base_rate' => ['reconciled' => ['mid' => $mid]],
            ],
            'buyer_position' => [
                'position' => 'within_range',
            ],
            'source_attributions' => [
                ['claim' => 'DD learning fixture valuation', 'source_reference' => 'dd_learning_test:valuation'],
            ],
            'as_at' => now(),
        ]);
    }
}
