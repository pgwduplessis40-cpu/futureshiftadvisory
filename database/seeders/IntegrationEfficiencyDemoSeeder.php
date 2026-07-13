<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Models\Client;
use App\Services\Integrations\IntegrationScopeService;
use Illuminate\Database\Seeder;

final class IntegrationEfficiencyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()
            ->whereIn('user_type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR])
            ->oldest()
            ->first();
        $bands = [
            ['S', 'inhouse', 3500, 4500, 5500], ['M', 'inhouse', 6500, 8000, 9500], ['L', 'inhouse', 12000, 15000, 18000], ['XL', 'inhouse', 45000, 45000, 45000],
            ['S', 'lowcode', 3000, 4000, 5000], ['M', 'lowcode', 5500, 7000, 8500], ['L', 'lowcode', 9500, 12000, 15000], ['XL', 'lowcode', 35000, 35000, 35000],
            ['S', 'partner', 4500, 5500, 6500], ['M', 'partner', 7500, 9000, 11000], ['L', 'partner', 14000, 17000, 21000], ['XL', 'partner', 50000, 50000, 50000],
            ['S', 'mixed', 4000, 5000, 6000], ['M', 'mixed', 7000, 8500, 10000], ['L', 'mixed', 13000, 16000, 19000], ['XL', 'mixed', 47000, 47000, 47000],
        ];

        foreach ($bands as [$band, $deliveryMode, $low, $mid, $high]) {
            IntegrationFeeBand::query()->updateOrCreate([
                'complexity_band' => $band,
                'delivery_mode' => $deliveryMode,
            ], [
                'fee_low' => $low,
                'fee_mid' => $mid,
                'fee_high' => $high,
                'currency' => 'NZD',
                'is_active' => true,
                'updated_by_user_id' => $actor?->getKey(),
            ]);
        }

        ServiceRatePackage::query()->updateOrCreate([
            'service_type' => ServiceRatePackage::SERVICE_INTEGRATION_SCOPING,
            'package_name' => 'Integration Scoping Workshop',
        ], [
            'package_scope' => null,
            'client_label' => 'Systems & Integration Efficiency Scoping',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1200,
            'deposit_percent' => 100,
            'hourly_rate' => null,
            'retainer_amount' => null,
            'purchase_price_min' => null,
            'purchase_price_max' => null,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-led systems inventory, duplicate-entry analysis, integration complexity assessment, and Quote Pack.',
            'is_active' => true,
            'effective_from' => now(),
            'effective_to' => null,
            'created_by_user_id' => $actor?->getKey(),
        ]);

        $client = Client::query()->oldest()->first();
        if ($client instanceof Client && ! IntegrationScope::query()->where('client_id', $client->getKey())->exists()) {
            app(IntegrationScopeService::class)->create($client, [
                'systems' => [
                    ['id' => 'xero', 'name' => 'Xero', 'vendor' => 'Xero', 'role' => 'Accounting and invoice ledger', 'api_quality' => 'rest_public', 'auth' => 'oauth', 'monthly_records' => 2800, 'confidence' => 'known', 'source' => 'manual'],
                    ['id' => 'field-service', 'name' => 'Field Service Board', 'vendor' => 'Legacy vendor', 'role' => 'Job completion and time capture', 'api_quality' => 'none', 'auth' => 'none', 'monthly_records' => 12500, 'confidence' => 'estimate', 'source' => 'manual'],
                    ['id' => 'crm', 'name' => 'Client CRM', 'vendor' => 'CRM vendor', 'role' => 'Customer and sales record', 'api_quality' => 'rest_partner', 'auth' => 'oauth', 'monthly_records' => 8400, 'confidence' => 'estimate', 'source' => 'manual'],
                ],
                'tasks' => [
                    ['id' => 'invoice-rekeying', 'description' => 'Re-key completed field jobs into Xero invoices', 'minutes_per_occurrence' => 14, 'occurrences_per' => 'day', 'people_count' => 2, 'hourly_cost' => 52, 'confidence' => 'known', 'source' => 'manual'],
                    ['id' => 'crm-status', 'description' => 'Re-key job progress into the CRM', 'minutes_per_occurrence' => 9, 'occurrences_per' => 'day', 'people_count' => 2, 'hourly_cost' => 48, 'confidence' => 'estimate', 'source' => 'manual'],
                ],
                'connections' => [
                    ['id' => 'field-to-xero', 'from_system' => 'field-service', 'to_system' => 'xero', 'direction' => 'one_way', 'transform_complexity' => 'med', 'confidence' => 'estimate', 'source' => 'manual'],
                    ['id' => 'crm-to-field', 'from_system' => 'crm', 'to_system' => 'field-service', 'direction' => 'two_way', 'transform_complexity' => 'high', 'confidence' => 'estimate', 'source' => 'manual'],
                ],
                'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
                'capture_percent' => 80,
                'savings_horizon_years' => 3,
                'discount_rate_percent' => 12,
            ], $actor);
        }
    }
}
