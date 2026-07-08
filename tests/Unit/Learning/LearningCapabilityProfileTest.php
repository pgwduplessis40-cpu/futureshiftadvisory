<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Models\LearningUpdate;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Learning\LearningCapabilityProfile;
use Tests\TestCase;

final class LearningCapabilityProfileTest extends TestCase
{
    public function test_profiles_financial_analysis_learning_updates_for_review(): void
    {
        $profile = app(LearningCapabilityProfile::class)->forUpdate(new LearningUpdate([
            'layer_id' => 4,
            'source' => [
                'type' => 'analysis_feedback',
                'prompt_id' => 'analysis.financial',
            ],
            'summary' => 'Adjust prompt calibration for financial recommendations.',
            'proposed_change' => [
                'action' => 'revise_prompt',
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'modules' => ['financial'],
            ],
            'evidence' => [
                'samples' => 9,
            ],
        ]));

        $this->assertContains('Finance', $profile['capabilities']);
        $this->assertContains('decision-toolkit', $profile['capabilities']);
        $this->assertContains('analysis_modules', $profile['ai_surfaces']);
        $this->assertFalse($profile['governance']['automatic_application']);
        $this->assertNotEmpty($profile['business_value']);
        $this->assertNotEmpty($profile['review_focus']);
        $this->assertTrue($profile['advice_quality']['methodology_review_required']);
        $this->assertTrue($profile['advice_quality']['calculation_validation_required']);
        $this->assertTrue($profile['advice_quality']['bias_review_required']);
        $this->assertTrue($profile['advice_quality']['truthfulness_review_required']);
        $this->assertNotEmpty($profile['advice_quality']['standards']);
    }

    public function test_profiles_registry_layers_for_budget_and_forecasting_surfaces(): void
    {
        $registry = app(LayerCadenceRegistry::class);
        $profile = app(LearningCapabilityProfile::class)->forLayerDefinition(
            $registry->definition(LayerCadenceRegistry::LAYER_ENTREPRENEUR_BUDGET_MODEL),
        );

        $this->assertContains('Finance', $profile['capabilities']);
        $this->assertContains('Financial Planning and Analysis', $profile['capabilities']);
        $this->assertContains('forecasting-time-series-data', $profile['capabilities']);
        $this->assertContains('budget_forecast', $profile['ai_surfaces']);
        $this->assertTrue($profile['governance']['advisor_review_required']);
        $this->assertTrue($profile['advice_quality']['budget_principle_review_required']);
        $this->assertTrue($profile['advice_quality']['calculation_validation_required']);
    }

    public function test_profiles_methodology_valuation_calculation_and_budget_principle_learning(): void
    {
        $profile = app(LearningCapabilityProfile::class)->forUpdate(new LearningUpdate([
            'summary' => 'Review valuation methodology, DCF discount rate calculation logic, and budget principles after outcome variance.',
            'proposed_change' => [
                'action' => 'revise_methodology',
                'requires_approval' => true,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'modules' => ['valuation', 'budget_forecast'],
            ],
            'evidence' => [
                'outcome_review' => 'actual results underperformed the forecast and valuation sensitivity.',
            ],
        ]));

        $this->assertContains('Finance', $profile['capabilities']);
        $this->assertContains('Financial Planning and Analysis', $profile['capabilities']);
        $this->assertContains('budget_forecast', $profile['ai_surfaces']);
        $this->assertTrue($profile['advice_quality']['methodology_review_required']);
        $this->assertTrue($profile['advice_quality']['valuation_review_required']);
        $this->assertTrue($profile['advice_quality']['calculation_validation_required']);
        $this->assertTrue($profile['advice_quality']['budget_principle_review_required']);
        $this->assertTrue($profile['advice_quality']['truthfulness_review_required']);
        $this->assertFalse($profile['governance']['automatic_application']);
    }
}
