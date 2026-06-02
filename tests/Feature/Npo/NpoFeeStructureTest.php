<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Services\Fees\FeeCalculator;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

final class NpoFeeStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
        Config::set('fees.sme.retainer_monthly.foundation', 1000);
        Config::set('fees.sme.retainer_monthly.growth', 2000);
        Config::set('fees.sme.retainer_monthly.scale', 3000);
        Config::set('fees.npo.service_rate_discount_percent', 30);
        Config::set('fees.npo.retainer_discount_percent', 35);
        Config::set('fees.npo.bespoke_accountability_report_addon', 500);
        Config::set('fees.npo.pro_bono.max_per_year', 2);
    }

    public function test_npo_retainer_applies_discount_to_configured_sme_tier_and_addons(): void
    {
        [$client, $engagement] = $this->npoClient();

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::NpoRetainer, [
            'annual_operating_budget' => 800000,
            'retainer_months' => 12,
            'bespoke_accountability_reports' => 2,
        ], [
            'npo_engagement_id' => $engagement->id,
        ]);

        $this->assertSame(FeeMethod::NpoRetainer, $calculation->method);
        $this->assertSame($engagement->id, $calculation->npo_engagement_id);
        $this->assertEquals(650.0, $calculation->justification['monthly_retainer_fee']);
        $this->assertSame(0.35, $calculation->justification['npo_discount_rate']);
        $this->assertTrue($calculation->justification['npo_discount_applied']);
        $this->assertEquals(1000.0, $calculation->justification['bespoke_accountability_report_addon']['total']);
        $this->assertSame(8800.0, $calculation->suggested_mid);
        $this->assertSame(7920.0, $calculation->suggested_low);
        $this->assertSame(9680.0, $calculation->suggested_high);
    }

    public function test_npo_hours_based_fee_uses_discounted_admin_service_rate(): void
    {
        Config::set('fees.service.default_hourly_rate', 250);
        Config::set('fees.npo.service_rate_discount_percent', 30);
        [$client, $engagement] = $this->npoClient('Hourly NPO Trust');

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::HoursBased, [
            'hourly_rate' => 999,
            'services' => [
                ['name' => 'NPO advisory support', 'hours' => 8, 'rate' => 999],
            ],
        ], [
            'npo_engagement_id' => $engagement->id,
        ]);

        $this->assertSame(1400.0, $calculation->suggested_mid);
        $this->assertEquals(250.0, $calculation->justification['services'][0]['base_rate']);
        $this->assertEquals(175.0, $calculation->justification['services'][0]['rate']);
        $this->assertEquals(30.0, $calculation->justification['services'][0]['npo_service_discount_percent']);
        $this->assertTrue($calculation->justification['services'][0]['npo_discount_applied']);
        $this->assertArrayNotHasKey('hourly_rate', $calculation->inputs);
        $this->assertArrayNotHasKey('rate', $calculation->inputs['services'][0]);
    }

    public function test_pro_bono_npo_provisions_are_flagged_tracked_and_capped_per_year(): void
    {
        [$client, $engagement] = $this->npoClient('Pro Bono Trust');
        $calculator = app(FeeCalculator::class);
        $input = [
            'budget_band' => 'small',
            'pro_bono' => true,
            'pro_bono_year' => 2026,
        ];
        $options = ['npo_engagement_id' => $engagement->id];

        $first = $calculator->calculate($client, FeeMethod::NpoRetainer, $input, $options);
        $second = $calculator->calculate($client, FeeMethod::NpoRetainer, $input, $options);

        $this->assertSame(0.0, $first->suggested_mid);
        $this->assertSame(0.0, $second->suggested_mid);
        $this->assertTrue($first->justification['pro_bono']['flagged']);
        $this->assertTrue($first->justification['pro_bono']['tracked_separately']);
        $this->assertTrue($first->justification['pro_bono']['full_functionality']);
        $this->assertEquals(7800.0, $first->justification['pro_bono']['nominal_value']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pro-bono NPO provision limit reached');

        $calculator->calculate($client, FeeMethod::NpoRetainer, $input, $options);
    }

    public function test_social_enterprise_rate_rule_requires_rationale_and_uses_commercial_rate_when_commercial_primary(): void
    {
        [$client, $engagement] = $this->npoClient(
            clientName: 'Commercial Primary Social Enterprise',
            subType: NpoEngagementSubType::SocialEnterprise,
            socialEnterprise: true,
        );
        $calculator = app(FeeCalculator::class);

        try {
            $calculator->calculate($client, FeeMethod::NpoRetainer, [
                'budget_band' => 'medium',
                'social_enterprise_rate_basis' => 'commercial_primary',
            ], [
                'npo_engagement_id' => $engagement->id,
            ]);
            $this->fail('Social enterprise rate rule accepted a missing rationale.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('advisor-recorded rationale', $exception->getMessage());
        }

        $calculation = $calculator->calculate($client, FeeMethod::NpoRetainer, [
            'budget_band' => 'medium',
            'social_enterprise_rate_basis' => 'commercial_primary',
            'social_enterprise_rate_rationale' => 'Earned revenue is the primary driver for this engagement.',
        ], [
            'npo_engagement_id' => $engagement->id,
        ]);

        $this->assertEquals(2000.0, $calculation->justification['monthly_retainer_fee']);
        $this->assertFalse($calculation->justification['npo_discount_applied']);
        $this->assertSame('commercial_primary', $calculation->justification['social_enterprise_rate_rule']['basis']);
        $this->assertStringContainsString('Earned revenue', $calculation->justification['social_enterprise_rate_rule']['rationale']);
    }

    /**
     * @return array{0: Client, 1: NpoEngagement}
     */
    private function npoClient(
        string $clientName = 'NPO Fee Trust',
        NpoEngagementSubType $subType = NpoEngagementSubType::StandardNpo,
        bool $socialEnterprise = false,
    ): array {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => $subType,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'social_enterprise' => $socialEnterprise,
            'social_enterprise_type' => $socialEnterprise ? NpoSocialEnterpriseType::CrossSubsidy : null,
            'commercial_weight' => $socialEnterprise ? 70 : null,
            'mission_weight' => $socialEnterprise ? 30 : null,
        ]);

        return [$client, $engagement];
    }
}
