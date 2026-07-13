<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations;

use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use App\Services\Integrations\IntegrationScopeCalculator;
use PHPUnit\Framework\TestCase;

final class IntegrationScopeCalculatorTest extends TestCase
{
    public function test_it_prices_complexity_independently_from_savings(): void
    {
        $scope = new IntegrationScope([
            'systems' => [
                ['id' => 'source', 'api_quality' => 'none', 'auth' => 'none', 'monthly_records' => 12_000],
                ['id' => 'target', 'api_quality' => 'rest_public', 'auth' => 'oauth', 'monthly_records' => 2_000],
            ],
            'tasks' => [[
                'id' => 'rekeying',
                'description' => 'Re-key daily job records',
                'minutes_per_occurrence' => 15,
                'occurrences_per' => 'day',
                'people_count' => 2,
                'hourly_cost' => 50,
                'confidence' => 'known',
            ]],
            'connections' => [[
                'id' => 'sync',
                'from_system' => 'source',
                'to_system' => 'target',
                'direction' => 'two_way',
                'transform_complexity' => 'high',
            ]],
            'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
            'capture_percent' => 80,
            'savings_horizon_years' => 3,
        ]);
        $bands = [
            new IntegrationFeeBand([
                'complexity_band' => IntegrationFeeBand::BAND_L,
                'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
                'fee_low' => 12_000,
                'fee_mid' => 15_000,
                'fee_high' => 18_000,
                'currency' => 'NZD',
                'is_active' => true,
            ]),
        ];

        $computed = (new IntegrationScopeCalculator)->calculate($scope, $bands);

        $this->assertSame(130.0, $computed['annual_hours_wasted']);
        $this->assertSame(6500.0, $computed['annual_cost_wasted']);
        $this->assertSame(5200.0, $computed['annual_savings']);
        $this->assertSame(IntegrationFeeBand::BAND_L, $computed['complexity_band']);
        $this->assertSame(15_000.0, $computed['quoted_fee']);
        $this->assertSame(34.62, $computed['payback_months']);
        $this->assertTrue(collect($computed['flags'])->contains('code', 'no_api_on_key_system'));
        $this->assertTrue(collect($computed['flags'])->contains('code', 'payback_over_24_months'));
    }
}
