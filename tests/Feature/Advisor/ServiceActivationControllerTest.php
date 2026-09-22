<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\ServiceActivations\ServiceActivationManager;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceActivationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_assigned_advisor_can_list_and_review_a_client_service_activation(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $package = $this->package(ServiceActivation::SERVICE_ENTREPRENEUR);
        $activation = $this->activation($client, $advisor, $clientUser);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.service-activations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/service-activations/Index')
                ->has('activations', 1)
                ->where('activations.0.id', $activation->getKey())
                ->where('activations.0.client_name', $client->legal_name)
                ->where('activations.0.status', ServiceActivation::STATUS_REQUESTED)
                ->where('activations.0.url', route('advisor.service-activations.show', $activation, absolute: false))
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.service-activations.show', $activation))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/service-activations/Show')
                ->where('activation.id', $activation->getKey())
                ->where('activation.client_id', $client->getKey())
                ->where('activation.payment_status', ServiceActivation::PAYMENT_NOT_REQUIRED)
                ->where('packages.0.id', $package->getKey())
                ->where('urls.index', route('advisor.service-activations.index', absolute: false))
                ->where('urls.package', route('advisor.service-activations.package', $activation, absolute: false))
                ->where('urls.balanceReceived', route('advisor.service-activations.balance-received', $activation, absolute: false))
            );

        $unassignedAdvisor = $this->advisor('unassigned-service-activation@example.test');

        $this->actingAsMfa($unassignedAdvisor)
            ->get(route('advisor.service-activations.show', $activation))
            ->assertNotFound();
    }

    public function test_assigned_advisor_selects_a_package_and_confirms_a_split_payment_balance(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $activation = $this->activation($client, $advisor, $clientUser);
        $package = $this->package(ServiceActivation::SERVICE_ENTREPRENEUR, depositPercent: 25);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.service-activations.package', $activation), [
                'service_rate_package_id' => $package->getKey(),
            ])
            ->assertRedirect(route('advisor.service-activations.show', $activation, absolute: false))
            ->assertSessionHas('status', 'service-activation-package-selected');

        $activation->refresh();
        $this->assertSame(ServiceActivation::STATUS_PACKAGE_SELECTED, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_DEPOSIT_PENDING, $activation->payment_status);

        app(ServiceActivationManager::class)->completePayment($activation, $clientUser);
        $activation->refresh();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.service-activations.balance-received', $activation))
            ->assertRedirect(route('advisor.service-activations.show', $activation, absolute: false))
            ->assertSessionHas('status', 'service-activation-balance-received');

        $activation->refresh();
        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);
        $this->assertNotNull($activation->balance_received_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_activation.balance_received',
            'subject_id' => $activation->getKey(),
        ]);
    }

    public function test_standard_advisory_plan_budget_request_uses_a_single_fixed_fee_without_dd_quote_context(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            'client_label' => 'Business Plan & Budget',
            'status' => ServiceActivation::STATUS_REQUESTED,
            'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
            'intake' => [
                'target_name' => 'Kauri Kitchens Limited',
                'asking_price' => 240000,
                'support_level' => 'guided',
            ],
        ]);
        $addOnPackage = $this->package(
            serviceType: ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            packageScope: ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
            fixedFee: 2400,
        );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.service-activations.show', $activation))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/service-activations/Show')
                ->where('activation.id', $activation->getKey())
                ->where('activation.intake.asking_price', 240000)
                ->where('packages.0.id', $addOnPackage->getKey())
                ->where('packages.0.recommended', true)
                ->where('packages.0.service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
                ->where('packages.0.package_scope', ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON)
                ->where('packages.0.fixed_fee', 2400)
                ->where('packages.0.purchase_price_min', null)
                ->where('packages.0.purchase_price_max', null)
                ->has('packages', 1)
                ->where('urls.serviceRates', null));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.service-activations.package', $activation), [
                'service_rate_package_id' => $addOnPackage->getKey(),
            ])
            ->assertRedirect(route('advisor.service-activations.show', $activation, absolute: false));

        $activation->refresh();

        $this->assertSame(ServiceActivation::STATUS_PACKAGE_SELECTED, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_PENDING, $activation->payment_status);
        $this->assertNull(data_get($activation->selected_package_snapshot, 'quote_context'));
        $this->assertSame(2400.0, (float) data_get($activation->selected_package_snapshot, 'fixed_fee'));
    }

    public function test_due_diligence_client_can_request_the_separate_fixed_fee_plan_budget_service(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $client->forceFill(['engagement_type' => EngagementType::DUE_DILIGENCE])->save();
        $package = $this->package(
            serviceType: ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            packageScope: ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
            fixedFee: 2400,
        );

        $this->actingAsMfa($clientUser)
            ->get(route('portal.service-activations.create', ['serviceType' => ServiceActivation::SERVICE_DD_PLAN_BUDGET]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/ServiceActivationRequest')
                ->where('service.label', 'Business Plan & Budget')
                ->where('pricingPreview.status', 'matched_package')
                ->where('pricingPreview.package.id', $package->getKey())
                ->where('pricingPreview.package.fixed_fee', 2400)
                ->missing('pricingPreview.package.quote_context')
            );

        $this->actingAsMfa($clientUser)
            ->post(route('portal.service-activations.store'), [
                'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
                'target_name' => 'Southern Lights Limited',
                'industry' => 'Manufacturing',
                'timing' => 'This quarter',
                'pricing_acknowledged' => true,
                'pricing_package_id' => $package->getKey(),
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
            ->firstOrFail();

        $this->assertSame('Business Plan & Budget', $activation->client_label);
        $this->assertSame(2400.0, (float) data_get($activation->metadata, 'pre_request_pricing.package.fixed_fee'));
        $this->assertNull(data_get($activation->metadata, 'pre_request_pricing.package.quote_context'));
    }

    public function test_idea_validation_request_uses_its_fixed_fee_not_an_entrepreneur_bundle(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $ideaPackage = $this->package(
            serviceType: ServiceActivation::SERVICE_ENTREPRENEUR,
            packageScope: ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            fixedFee: 1650,
        );
        $this->package(
            serviceType: ServiceActivation::SERVICE_ENTREPRENEUR,
            packageScope: ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            fixedFee: 3450,
        );

        $preview = app(ServiceActivationManager::class)->pricingPreviewForRequest(
            ServiceActivation::SERVICE_ENTREPRENEUR,
            client: $client,
        );

        $this->assertSame('matched_package', $preview['status']);
        $this->assertSame($ideaPackage->getKey(), data_get($preview, 'package.id'));
        $this->assertSame(1650.0, (float) data_get($preview, 'package.fixed_fee'));
        $this->assertNull(data_get($preview, 'package.quote_context'));
    }

    public function test_idea_validation_request_keeps_only_the_idea_or_concept(): void
    {
        [$advisor, $client, $clientUser] = $this->clientFixture();
        $package = $this->package(
            serviceType: ServiceActivation::SERVICE_ENTREPRENEUR,
            packageScope: ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            fixedFee: 1650,
        );

        $this->actingAsMfa($clientUser)
            ->post(route('portal.service-activations.store'), [
                'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'idea_name' => 'A better way for trades to schedule field work',
                'industry' => 'Construction',
                'customer' => 'Trade businesses',
                'problem' => 'Scheduling and invoicing take too long.',
                'timing' => 'This month',
                'notes' => 'Legacy request context that must not be retained.',
                'pricing_acknowledged' => true,
                'pricing_package_id' => $package->getKey(),
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->firstOrFail();

        $this->assertSame([
            'idea_name' => 'A better way for trades to schedule field work',
        ], $activation->intake);
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientFixture(): array
    {
        $advisor = $this->advisor('service-activation-advisor@example.test');
        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'service-activation-client@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Service activation controller client',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        foreach ([[$clientUser, 'primary_contact'], [$advisor, 'lead_advisor']] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => ['portal'],
            ]);
        }

        return [$advisor, $client, $clientUser];
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function activation(Client $client, User $advisor, User $clientUser): ServiceActivation
    {
        return ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Test new Business Idea',
            'status' => ServiceActivation::STATUS_REQUESTED,
            'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
            'intake' => [
                'idea_name' => 'Controller coverage venture',
                'industry' => 'Professional services',
                'customer' => 'New Zealand small businesses',
                'problem' => 'Planning is difficult to maintain.',
            ],
            'metadata' => ['source' => 'controller_test'],
        ]);
    }

    private function package(
        string $serviceType,
        float $depositPercent = 100,
        ?string $packageScope = null,
        ?float $purchasePriceMin = null,
        ?float $purchasePriceMax = null,
        float $fixedFee = 1650,
    ): ServiceRatePackage {
        $packageScope ??= match ($serviceType) {
            ServiceRatePackage::SERVICE_DUE_DILIGENCE => ServiceRatePackage::SCOPE_DD_300K_1M,
            ServiceRatePackage::SERVICE_DD_PLAN_BUDGET => ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
            default => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
        };
        $label = match ($serviceType) {
            ServiceRatePackage::SERVICE_DUE_DILIGENCE => ServiceRatePackage::packageScopeLabel($packageScope),
            ServiceRatePackage::SERVICE_DD_PLAN_BUDGET => 'Business Plan & Budget',
            default => 'Entrepreneur plan and budget',
        };

        return ServiceRatePackage::query()->create([
            'service_type' => $serviceType,
            'package_scope' => $packageScope,
            'package_name' => $label,
            'client_label' => $label,
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => $fixedFee,
            'deposit_percent' => $depositPercent,
            'purchase_price_min' => $purchasePriceMin,
            'purchase_price_max' => $purchasePriceMax,
            'currency' => 'NZD',
            'scope_description' => 'Advisor review, plan support, and a budget runway assessment.',
            'is_active' => true,
            'effective_from' => now(),
        ]);
    }
}
