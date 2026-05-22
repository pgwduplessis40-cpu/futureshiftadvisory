<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\LearningUpdate;
use App\Models\RiskCost;
use App\Services\Analysis\RegulatoryImpactAssessor;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegulatoryImpactAssessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_legislative_change_generates_client_impact_finding_with_pv_linkage(): void
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000000',
            'legal_name' => 'Regulatory Impact Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
        $candidate = $this->candidate();

        $run = app(RegulatoryImpactAssessor::class)->assess($client, $candidate, [
            'financial_impact' => 60000,
            'probability' => 0.5,
            'duration_years' => 2,
        ]);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(AnalysisModule::RegulatoryImpact, $run->module);
        $this->assertSame([AnalysisLens::Prescriptive->value], $run->framework_lenses);

        $finding = $run->findings->first();
        $this->assertInstanceOf(AnalysisFinding::class, $finding);
        $this->assertStringContainsString('Compliance actions:', $finding->body);
        $this->assertStringContainsString('Review employment-agreement templates.', $finding->body);
        $this->assertNotNull($finding->pv_link_id);

        $risk = RiskCost::query()->findOrFail($finding->pv_link_id);
        $this->assertSame($finding->id, $risk->analysis_finding_id);
        $this->assertSame(60000.0, $risk->financial_impact);
        $this->assertSame(0.5, $risk->probability);
        $this->assertGreaterThan(0, $risk->pv_of_cost);
        $this->assertTrue(collect($finding->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "learning_update:{$candidate->id}",
        ));
    }

    private function candidate(): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => 14,
            'source' => [
                'type' => 'legislative_currency_monitor',
                'change_key' => 'era-impact-test',
                'source_url' => 'https://www.parliament.nz/en/pb/bills-and-laws/',
            ],
            'summary' => 'Employment Relations amendment may affect client obligations.',
            'proposed_change' => [
                'action' => 'review_compliance_checker_statute_currency',
                'statute' => 'Employment Relations Act 2000',
                'automatic_application' => false,
            ],
            'impact_scope' => ['modules' => ['compliance']],
            'clients_affected' => 0,
            'magnitude' => 'medium',
            'confidence' => 0.82,
            'evidence' => [],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
