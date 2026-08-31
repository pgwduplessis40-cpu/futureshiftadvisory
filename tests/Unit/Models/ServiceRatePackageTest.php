<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ServiceRatePackage;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServiceRatePackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_scope_helpers_normalise_every_supported_service_path(): void
    {
        $this->assertSame([
            ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
        ], ServiceRatePackage::entrepreneurPackageScopes());
        $this->assertSame([
            ServiceRatePackage::SCOPE_DD_UNDER_300K,
            ServiceRatePackage::SCOPE_DD_300K_1M,
            ServiceRatePackage::SCOPE_DD_1M_3M,
        ], ServiceRatePackage::dueDiligencePackageScopes());
        $this->assertSame([
            ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            ServiceRatePackage::SCOPE_DD_UNDER_300K,
            ServiceRatePackage::SCOPE_DD_300K_1M,
            ServiceRatePackage::SCOPE_DD_1M_3M,
            ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
        ], ServiceRatePackage::packageScopes());
        $this->assertCount(3, ServiceRatePackage::entrepreneurPackageScopeOptions());
        $this->assertCount(3, ServiceRatePackage::dueDiligencePackageScopeOptions());

        foreach ([
            ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION => 'Idea Validation',
            ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET => 'Business Plan + Budget',
            ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO => 'Idea + Business Plan + Budget',
            ServiceRatePackage::SCOPE_DD_UNDER_300K => 'Purchase price below $300k',
            ServiceRatePackage::SCOPE_DD_300K_1M => 'Purchase price $300k-$1m',
            ServiceRatePackage::SCOPE_DD_1M_3M => 'Purchase price $1m-$3m',
            ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON => 'Business Plan + Budget add-on',
            'unrecognised' => 'Standard workspace',
        ] as $scope => $label) {
            $this->assertSame($label, ServiceRatePackage::packageScopeLabel($scope));
        }

        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO, ServiceRatePackage::normaliseEntrepreneurScope(null));
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, ServiceRatePackage::normaliseEntrepreneurScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_UNDER_300K, ServiceRatePackage::normaliseDueDiligenceScope(null, null, null, 'Up to $300k acquisition'));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_300K_1M, ServiceRatePackage::normaliseDueDiligenceScope(null, null, null, 'Purchase between $300k and $1m'));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_1M_3M, ServiceRatePackage::normaliseDueDiligenceScope(null, null, null, 'Purchase between $1m and $3m'));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_UNDER_300K, ServiceRatePackage::normaliseDueDiligenceScope(null, null, 300000));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_1M_3M, ServiceRatePackage::normaliseDueDiligenceScope(null, 1000000));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_300K_1M, ServiceRatePackage::normaliseDueDiligenceScope(null, 300000));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_300K_1M, ServiceRatePackage::normaliseDueDiligenceScope(null));
        $this->assertSame(ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON, ServiceRatePackage::normaliseDdPlanBudgetScope('unexpected'));

        $dueDiligence = ServiceRatePackage::accessFor(ServiceRatePackage::SERVICE_DUE_DILIGENCE, ServiceRatePackage::SCOPE_DD_UNDER_300K);
        $ddPlanBudget = ServiceRatePackage::accessFor(ServiceRatePackage::SERVICE_DD_PLAN_BUDGET, ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON);
        $entrepreneurIdea = ServiceRatePackage::accessFor(ServiceRatePackage::SERVICE_ENTREPRENEUR, ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION);
        $entrepreneurPlan = ServiceRatePackage::accessFor(ServiceRatePackage::SERVICE_ENTREPRENEUR, ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET);
        $entrepreneurCombo = ServiceRatePackage::accessFor(ServiceRatePackage::SERVICE_ENTREPRENEUR, ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO);
        $unknown = ServiceRatePackage::accessFor('unrecognised_service', 'unrecognised');

        $this->assertFalse($dueDiligence['includes_plan_budget']);
        $this->assertTrue($ddPlanBudget['includes_plan_budget']);
        $this->assertTrue($entrepreneurIdea['includes_idea_validation']);
        $this->assertFalse($entrepreneurIdea['includes_plan_budget']);
        $this->assertFalse($entrepreneurPlan['includes_idea_validation']);
        $this->assertTrue($entrepreneurPlan['includes_plan_budget']);
        $this->assertTrue($entrepreneurCombo['includes_idea_validation']);
        $this->assertTrue($entrepreneurCombo['includes_plan_budget']);
        $this->assertSame([], $unknown['included_stages']);
        $this->assertSame([], $unknown['client_outcomes']);
    }

    public function test_snapshot_preserves_normalised_scope_and_payment_split_rules(): void
    {
        $dueDiligence = new ServiceRatePackage([
            'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
            'package_scope' => null,
            'package_name' => 'Mid-market DD',
            'client_label' => 'Purchase price $300k-$1m',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 8500,
            'deposit_percent' => 25,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-led diligence.',
            'purchase_price_min' => 300000,
            'purchase_price_max' => 1000000,
        ]);
        $dueDiligence->setAttribute('id', '11111111-1111-4111-8111-111111111111');

        $snapshot = $dueDiligence->snapshot();

        $this->assertSame(ServiceRatePackage::SCOPE_DD_300K_1M, $snapshot['package_scope']);
        $this->assertSame(25.0, $snapshot['payment_split']['deposit_percent']);
        $this->assertSame(2125.0, $snapshot['payment_split']['card_deposit_amount']);
        $this->assertSame(6375.0, $snapshot['payment_split']['bank_transfer_amount']);
        $this->assertTrue($snapshot['payment_split']['requires_bank_transfer']);
        $this->assertNotEmpty($dueDiligence->includedStages());
        $this->assertNotEmpty($dueDiligence->clientOutcomes());
        $this->assertSame(ServiceRatePackage::SCOPE_DD_300K_1M, $dueDiligence->accessPayload()['package_scope']);
        $this->assertSame('created_by_user_id', $dueDiligence->createdBy()->getForeignKeyName());
        $this->assertSame('service_rate_package_id', $dueDiligence->serviceActivations()->getForeignKeyName());

        $ddPlanBudget = new ServiceRatePackage([
            'service_type' => ServiceRatePackage::SERVICE_DD_PLAN_BUDGET,
            'package_scope' => ServiceRatePackage::SCOPE_DD_UNDER_300K,
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 3000,
            'deposit_percent' => 0,
        ]);
        $hourly = new ServiceRatePackage([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'billing_model' => ServiceRatePackage::BILLING_HOURLY_RETAINER,
            'hourly_rate' => 325,
        ]);
        $noFixedFee = new ServiceRatePackage([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
        ]);
        $other = new ServiceRatePackage([
            'service_type' => ServiceRatePackage::SERVICE_INTEGRATION_SCOPING,
            'package_scope' => 'other_scope',
            'billing_model' => ServiceRatePackage::BILLING_PROPOSAL,
        ]);

        $this->assertSame(ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON, $ddPlanBudget->packageScope());
        $this->assertNotEmpty($ddPlanBudget->includedStages());
        $this->assertNotEmpty($ddPlanBudget->clientOutcomes());
        $this->assertSame(0.0, $ddPlanBudget->paymentSplit()['card_deposit_amount']);
        $this->assertTrue($ddPlanBudget->paymentSplit()['requires_bank_transfer']);
        $this->assertSame(100.0, $hourly->depositPercent());
        $this->assertNull($hourly->paymentSplit()['card_deposit_amount']);
        $this->assertSame(100.0, $noFixedFee->depositPercent());
        $this->assertNull($noFixedFee->paymentSplit()['bank_transfer_amount']);
        $this->assertSame('other_scope', $other->packageScope());
    }

    public function test_invite_options_use_only_active_current_packages_and_safe_rate_summaries(): void
    {
        $this->assertSame(
            ServiceRatePackage::entrepreneurPackageScopeOptions(),
            ServiceRatePackage::entrepreneurInviteServiceOptions(),
        );

        $this->package([
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'client_label' => 'Idea validation sprint',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1200,
            'effective_from' => now()->subMinutes(3),
        ]);

        $this->assertSame([
            ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
        ], array_column(ServiceRatePackage::entrepreneurInviteServiceOptions(), 'value'));

        $this->package([
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            'client_label' => 'Business plan and runway',
            'billing_model' => ServiceRatePackage::BILLING_HOURLY_RETAINER,
            'hourly_rate' => 325,
            'effective_from' => now()->subMinutes(2),
        ]);
        $this->package([
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'client_label' => 'Combined founder pathway',
            'billing_model' => ServiceRatePackage::BILLING_PROPOSAL,
            'effective_from' => now()->subMinute(),
        ]);
        $this->package([
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            'client_label' => 'Expired package',
            'effective_to' => now()->subSecond(),
        ]);
        $this->package([
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'client_label' => 'Inactive package',
            'is_active' => false,
        ]);

        $options = ServiceRatePackage::entrepreneurInviteServiceOptions();

        $this->assertSame([
            ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
        ], array_column($options, 'value'));
        $this->assertSame('Idea validation sprint', $options[0]['label']);
        $this->assertStringContainsString('NZD 1,200.00 ex GST.', $options[0]['description']);
        $this->assertStringContainsString('NZD 325.00 per hour.', $options[1]['description']);
        $this->assertStringContainsString('Pricing confirmed by your advisor.', $options[2]['description']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function package(array $overrides = []): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'package_name' => 'Founder package',
            'client_label' => 'Founder package',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 2000,
            'deposit_percent' => 100,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-guided delivery.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
            ...$overrides,
        ]);
    }
}
