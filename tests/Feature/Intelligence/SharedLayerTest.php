<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Models\IndustryIntelligenceSignal;
use App\Models\LearningUpdate;
use App\Models\SharedIntelligencePattern;
use App\Services\Intelligence\SharedLayer;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SharedLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('privacy.min_cohort', 3);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_shared_layer_flows_patterns_both_directions_without_record_level_data(): void
    {
        IndustryIntelligenceSignal::query()->create([
            'industry_code' => 'retail',
            'signal_type' => 'repeated_finding_pattern',
            'signal_key' => hash('sha256', 'retail-cash'),
            'aggregate' => [
                'suppressed' => false,
                'cohort_size' => 3,
                'pattern' => 'cash conversion pressure',
                'severity_distribution' => ['high' => 3],
                'privacy' => ['aggregate_only' => true],
            ],
            'cohort_size' => 3,
            'generated_at' => now(),
            'suppressed' => false,
        ]);
        LearningUpdate::query()->create([
            'layer_id' => 20,
            'source' => [
                'type' => 'plan_quality_benchmarks',
                'signal_key' => hash('sha256', 'plan-retail'),
            ],
            'summary' => 'Plan quality benchmark available.',
            'proposed_change' => ['action' => 'review', 'automatic_application' => false],
            'impact_scope' => ['surface' => 'entrepreneur_plan_quality'],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.7,
            'evidence' => [
                'benchmark' => [
                    'suppressed' => false,
                    'cohort_size' => 3,
                    'industry' => 'retail',
                    'average_score' => 76,
                    'distribution' => ['strong' => 3],
                    'privacy' => ['aggregate_only' => true],
                ],
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $patterns = app(SharedLayer::class)->run();

        $this->assertCount(2, $patterns);
        $this->assertDatabaseHas('shared_intelligence_patterns', [
            'source_domain' => SharedLayer::DOMAIN_ADVISORY,
            'target_domain' => SharedLayer::DOMAIN_ENTREPRENEUR,
            'cohort_size' => 3,
        ]);
        $this->assertDatabaseHas('shared_intelligence_patterns', [
            'source_domain' => SharedLayer::DOMAIN_ENTREPRENEUR,
            'target_domain' => SharedLayer::DOMAIN_ADVISORY,
            'cohort_size' => 3,
        ]);

        SharedIntelligencePattern::query()->get()->each(function (SharedIntelligencePattern $pattern): void {
            $this->assertTrue($pattern->pattern['aggregate_only']);
            $this->assertArrayNotHasKey('client_ids', $pattern->pattern);
            $this->assertArrayNotHasKey('plan_ids', $pattern->pattern);
            $this->assertArrayNotHasKey('finding_ids', $pattern->pattern);
            $this->assertArrayNotHasKey('values', $pattern->pattern);
            $this->assertArrayNotHasKey('min', $pattern->pattern);
            $this->assertArrayNotHasKey('max', $pattern->pattern);
        });

        app(SharedLayer::class)->run();

        $this->assertSame(2, SharedIntelligencePattern::query()->count());
    }

    public function test_shared_layer_suppresses_below_cohort(): void
    {
        IndustryIntelligenceSignal::query()->create([
            'industry_code' => 'retail',
            'signal_type' => 'repeated_finding_pattern',
            'signal_key' => hash('sha256', 'retail-small'),
            'aggregate' => ['suppressed' => false, 'cohort_size' => 2, 'pattern' => 'small cohort'],
            'cohort_size' => 2,
            'generated_at' => now(),
            'suppressed' => false,
        ]);

        $patterns = app(SharedLayer::class)->run();

        $this->assertCount(0, $patterns);
        $this->assertSame(0, SharedIntelligencePattern::query()->count());
    }
}
