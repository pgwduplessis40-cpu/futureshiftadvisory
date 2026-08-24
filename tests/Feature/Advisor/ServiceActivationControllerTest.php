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

    private function package(string $serviceType, float $depositPercent = 100): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => $serviceType,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'package_name' => 'Entrepreneur plan and budget',
            'client_label' => 'Entrepreneur plan and budget',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1650,
            'deposit_percent' => $depositPercent,
            'currency' => 'NZD',
            'scope_description' => 'Advisor review, plan support, and a budget runway assessment.',
            'is_active' => true,
            'effective_from' => now(),
        ]);
    }
}
