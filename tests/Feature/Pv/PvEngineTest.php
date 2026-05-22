<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\EconomicIndicator;
use App\Models\PvCalculation;
use App\Services\Pv\DiscountRateResolver;
use App\Services\Pv\PvEngine;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PvEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_discount_rate_resolver_supports_all_four_methods_with_attributions(): void
    {
        $client = $this->client();
        $ocr = $this->ocr(5.5, '2026-05-20');
        $resolver = app(DiscountRateResolver::class);

        $ocrLinked = $resolver->resolve($client, DiscountMethod::OcrLinked, ['risk_premium' => 0.06]);
        $this->assertSame(0.115, $ocrLinked->rate);
        $this->assertSame("economic_indicator:{$ocr->id}", $ocrLinked->sourceAttributions[0]['source_reference']);

        $industry = $resolver->resolve($client, DiscountMethod::IndustryWacc, [
            'rate' => 0.14,
            'rationale' => 'Industry WACC benchmark for consulting firms.',
            'source_reference' => 'valuation_multiple:M6962',
        ]);
        $this->assertSame(0.14, $industry->rate);
        $this->assertSame('valuation_multiple:M6962', $industry->sourceAttributions[0]['source_reference']);

        $advisor = $resolver->resolve($client, DiscountMethod::AdvisorConfigured, [
            'rate' => 0.16,
            'rationale' => 'Advisor adjusted rate for concentration risk.',
        ]);
        $this->assertSame(0.16, $advisor->rate);
        $this->assertStringContainsString('concentration risk', $advisor->rationale);

        $clientInput = $resolver->resolve($client, DiscountMethod::ClientInputted, [
            'rate' => 0.1,
            'rationale' => 'Client supplied hurdle rate from board planning.',
            'source_reference' => 'questionnaire:discount-rate',
        ]);
        $this->assertSame(0.1, $clientInput->rate);
        $this->assertSame('questionnaire:discount-rate', $clientInput->sourceAttributions[0]['source_reference']);
    }

    public function test_ocr_linked_rate_uses_latest_indicator(): void
    {
        $client = $this->client();
        $this->ocr(5.5, '2026-05-20');
        $latest = $this->ocr(6.0, '2026-06-01');

        $result = app(DiscountRateResolver::class)->resolve($client, DiscountMethod::OcrLinked, ['risk_premium' => 0.05]);

        $this->assertSame(0.11, $result->rate);
        $this->assertSame("economic_indicator:{$latest->id}", $result->sourceAttributions[0]['source_reference']);
    }

    public function test_present_value_math_and_persistence_include_attributions(): void
    {
        $client = $this->client();
        $ocr = $this->ocr(5.0, '2026-05-20');
        $engine = app(PvEngine::class);

        $this->assertSame(240.18, $engine->presentValue([1 => 100, 2 => 100, 3 => 100], 0.12));
        $this->assertSame(688.47, $engine->terminalValue(120, 0.14, 0.02, 3));

        $calculation = $engine->calculate(
            client: $client,
            type: PvType::ImprovementOpportunity,
            discountMethod: DiscountMethod::OcrLinked,
            cashFlows: [1 => 100, 2 => 100, 3 => 100],
            discountOptions: ['risk_premium' => 0.07],
        );

        $this->assertInstanceOf(PvCalculation::class, $calculation);
        $this->assertSame(PvType::ImprovementOpportunity, $calculation->type);
        $this->assertSame(DiscountMethod::OcrLinked, $calculation->discount_method);
        $this->assertSame(0.12, $calculation->discount_rate);
        $this->assertSame(240.18, $calculation->result['present_value']);
        $this->assertSame("economic_indicator:{$ocr->id}", $calculation->source_attributions[0]['source_reference']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'pv_calculation.created',
            'subject_type' => PvCalculation::class,
            'subject_id' => $calculation->id,
            'client_id' => $client->id,
        ]);
    }

    private function client(): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'PV Engine Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
    }

    private function ocr(float $value, string $periodDate): EconomicIndicator
    {
        return EconomicIndicator::query()->create([
            'indicator' => EconomicIndicator::OCR,
            'label' => 'Official Cash Rate',
            'value' => $value,
            'unit' => 'percent',
            'period_date' => $periodDate,
            'source' => 'rbnz',
            'source_badge' => 'stub',
            'degraded' => false,
            'fetched_at' => now(),
            'payload' => ['series' => 'OCR'],
        ]);
    }
}
