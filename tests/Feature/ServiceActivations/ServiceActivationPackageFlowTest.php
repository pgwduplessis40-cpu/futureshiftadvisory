<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceActivations;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\ServiceActivations\ServiceActivationManager;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ServiceActivationPackageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_package_payment_must_complete_before_workspace_unlocks(): void
    {
        [$activation, $advisor, $clientUser] = $this->activationFixture();
        $package = $this->package(ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO);
        $manager = app(ServiceActivationManager::class);

        $activation = $manager->selectPackage($activation, $package, $advisor);

        $this->assertSame(ServiceActivation::PAYMENT_PENDING, $activation->payment_status);

        try {
            $manager->accept($activation, $clientUser);
            $this->fail('Workspace access opened before package payment completed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Full package payment must be received before opening this workspace.',
                $exception->errors()['payment'][0],
            );
        }

        $activation = $manager->completePayment($activation->refresh(), $clientUser);
        $activation = $manager->accept($activation->refresh(), $clientUser);

        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);
        $this->assertNotNull($activation->related_entrepreneur_profile_id);
    }

    public function test_deposit_package_stays_locked_until_bank_transfer_balance_confirmed(): void
    {
        [$activation, $advisor, $clientUser] = $this->activationFixture('split-payment@example.test');
        $package = $this->package(ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO, depositPercent: 25);
        $manager = app(ServiceActivationManager::class);

        $activation = $manager->selectPackage($activation, $package, $advisor);

        $this->assertSame(ServiceActivation::PAYMENT_DEPOSIT_PENDING, $activation->payment_status);
        $this->assertSame(412.5, $activation->selected_package_snapshot['payment_split']['card_deposit_amount']);
        $this->assertSame(1237.5, $activation->selected_package_snapshot['payment_split']['bank_transfer_amount']);

        $activation = $manager->completePayment($activation, $clientUser);

        $this->assertSame(ServiceActivation::PAYMENT_BALANCE_PENDING, $activation->payment_status);
        $this->assertNotNull($activation->deposit_paid_at);
        $this->assertNull($activation->payment_completed_at);

        try {
            $manager->accept($activation->refresh(), $clientUser);
            $this->fail('Workspace access opened before bank-transfer balance was confirmed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Full package payment must be received before opening this workspace.',
                $exception->errors()['payment'][0],
            );
        }

        $activation = $manager->confirmBalanceReceived($activation->refresh(), $advisor);

        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);
        $this->assertNotNull($activation->balance_received_at);
        $this->assertNotNull($activation->payment_completed_at);

        $activation = $manager->accept($activation->refresh(), $clientUser);

        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->status);
        $this->assertNotNull($activation->related_entrepreneur_profile_id);
    }

    public function test_idea_validation_package_opens_without_creating_business_plan(): void
    {
        [$activation, $advisor, $clientUser] = $this->activationFixture('idea-only@example.test');
        $package = $this->package(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION);
        $manager = app(ServiceActivationManager::class);

        $activation = $manager->selectPackage($activation, $package, $advisor);
        $activation = $manager->completePayment($activation, $clientUser);
        $activation = $manager->accept($activation, $clientUser);

        $profile = EntrepreneurProfile::query()->findOrFail($activation->related_entrepreneur_profile_id);

        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->stage);
        $this->assertFalse(
            BusinessPlan::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->exists(),
        );
    }

    public function test_plan_budget_package_creates_business_plan_without_idea_gate(): void
    {
        [$activation, $advisor, $clientUser] = $this->activationFixture('plan-budget@example.test');
        $package = $this->package(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET);
        $manager = app(ServiceActivationManager::class);

        $activation = $manager->selectPackage($activation, $package, $advisor);
        $activation = $manager->completePayment($activation, $clientUser);
        $activation = $manager->accept($activation, $clientUser);

        $profile = EntrepreneurProfile::query()->findOrFail($activation->related_entrepreneur_profile_id);
        $plan = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->firstOrFail();

        $this->assertSame(EntrepreneurStage::BUILDING_PHASE_1, $profile->stage);
        $this->assertSame($activation->client_id, $plan->client_id);
        $this->assertSame(BusinessPlan::STATUS_BUILDING, $plan->status);
    }

    /**
     * @return array{0: ServiceActivation, 1: User, 2: User}
     */
    private function activationFixture(string $clientEmail = 'activation-client@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'advisor-'.$clientEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => $clientEmail,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Package Flow Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal'],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal'],
        ]);

        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Test new Business Idea',
            'status' => ServiceActivation::STATUS_REQUESTED,
            'intake' => [
                'idea_name' => 'New venture concept',
                'industry' => 'Retail',
                'customer' => 'Founder-led SMEs',
                'problem' => 'Planning clarity is missing.',
            ],
            'metadata' => ['source' => 'test'],
        ]);

        return [$activation, $advisor, $clientUser];
    }

    private function package(string $scope, float $depositPercent = 100): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => $scope,
            'package_name' => ServiceRatePackage::packageScopeLabel($scope),
            'client_label' => ServiceRatePackage::packageScopeLabel($scope),
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1650,
            'deposit_percent' => $depositPercent,
            'currency' => 'NZD',
            'scope_description' => 'Platform package validation, advisor review, and scoped client output.',
            'is_active' => true,
            'effective_from' => now(),
        ]);
    }
}
