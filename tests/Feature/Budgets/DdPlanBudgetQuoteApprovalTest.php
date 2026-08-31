<?php

declare(strict_types=1);

namespace Tests\Feature\Budgets;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\StrategicBudget;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\ServiceActivations\ServiceActivationManager;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DdPlanBudgetQuoteApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_due_diligence_client_requests_fsa_quote_before_plan_budget_access(): void
    {
        Notification::fake();

        [$client, $clientUser] = $this->dueDiligenceClientFixture();
        $this->ddPlanBudgetPackage();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudgetQuoteApproval')
                ->where('access.allowed', false)
                ->where('access.state', 'not_requested')
                ->where('access.label', 'FSA quote required')
                ->where('target.name', 'Kauri Kitchens Limited')
                ->where('target.vendor_name', 'Kauri Kitchens Group')
                ->where('target.industry', 'Manufacturing')
                ->where('target.asking_price', 750000)
                ->where('workspaces.active_key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->has('workspaces.items', 1)
                ->where('workspaces.items.0.key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('requestQuoteUrl', route('portal.business-plan-budget.quote.store', absolute: false)));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.pdf'))
            ->assertForbidden();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.business-plan.pdf'))
            ->assertForbidden();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.budget-pack.pdf'))
            ->assertForbidden();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.business-plan-budget.quote.store'), [
                'confirm_quote_request' => true,
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
            ->firstOrFail();

        $this->assertSame(ServiceActivation::STATUS_REQUESTED, $activation->status);
        $this->assertSame('DD + Business Plan & Budget', $activation->client_label);
        $this->assertSame('Kauri Kitchens Limited', $activation->intake['target_name']);
        $this->assertSame('Kauri Kitchens Group', $activation->intake['vendor_name']);
        $this->assertSame('guided', $activation->intake['support_level']);
        $this->assertSame('first_time', $activation->intake['dd_experience']);
        $this->assertSame('low', $activation->intake['financial_confidence']);
        $this->assertSame(8500.0, (float) data_get($activation->metadata, 'pre_request_pricing.package.quote_context.dd_package.fixed_fee'));
        $this->assertSame(2400.0, (float) data_get($activation->metadata, 'pre_request_pricing.package.quote_context.plan_budget_fixed_fee'));
        $this->assertSame(10900.0, (float) data_get($activation->metadata, 'pre_request_pricing.package.quote_context.combined_fixed_fee'));
        $this->assertSame(2400.0, (float) data_get($activation->metadata, 'pre_request_pricing.package.quote_context.amount_due_for_this_activation'));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('planBudgetAccess.allowed', false)
                ->where('planBudgetAccess.state', 'quote_requested')
                ->where('planBudgetAccess.label', 'FSA quote requested')
                ->where('planBudgetAccess.activation_url', route('portal.service-activations.show', $activation, absolute: false))
                ->has('workspaces.items', 1)
                ->where('workspaces.items.0.key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('strategicBudget.business_plan_readiness_score', 0)
                ->where('strategicBudget.progress_score', 10));
    }

    public function test_accepted_dd_plan_budget_add_on_unlocks_plan_budget_workspace(): void
    {
        Notification::fake();

        [$client, $clientUser, $advisor] = $this->dueDiligenceClientFixture('accepted-add-on@example.test');
        $manager = app(ServiceActivationManager::class);
        $package = $this->ddPlanBudgetPackage();

        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            'client_label' => 'DD + Business Plan & Budget',
            'status' => ServiceActivation::STATUS_REQUESTED,
            'intake' => [
                'target_name' => 'Kauri Kitchens Limited',
                'asking_price' => 750000,
            ],
            'metadata' => ['source' => 'test'],
        ]);

        $activation = $manager->selectPackage($activation, $package, $advisor);
        $this->assertSame(8500.0, (float) data_get($activation->selected_package_snapshot, 'quote_context.dd_package.fixed_fee'));
        $this->assertSame(2400.0, (float) data_get($activation->selected_package_snapshot, 'quote_context.plan_budget_fixed_fee'));
        $this->assertSame(10900.0, (float) data_get($activation->selected_package_snapshot, 'quote_context.combined_fixed_fee'));
        $activation = $manager->completePayment($activation->refresh(), $clientUser);
        $activation = $manager->accept($activation->refresh(), $clientUser);

        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);
        $this->assertNull($activation->related_dd_engagement_id);
        $this->assertNull($activation->related_entrepreneur_profile_id);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudget')
                ->where('client.engagement_type', EngagementType::DUE_DILIGENCE->value)
                ->where('budget.pathway', 'due_diligence')
                ->where('workspaces.active_key', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
                ->has('workspaces.items', 2)
                ->where('workspaces.items.0.key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('workspaces.items.0.label', 'Due Diligence')
                ->where('workspaces.items.0.href', route('portal.dd-plan.show', ['client' => $client->getKey()], absolute: false))
                ->where('workspaces.items.1.key', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
                ->where('workspaces.items.1.label', 'Business Plan & Budget')
                ->where('workspaces.items.1.href', route('portal.business-plan-budget.show', ['client' => $client->getKey()], absolute: false))
                ->where('businessPlanPdfUrl', route('portal.business-plan-budget.business-plan.pdf', absolute: false))
                ->where('budgetPdfUrl', route('portal.business-plan-budget.budget-pack.pdf', absolute: false)));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('planBudgetAccess.allowed', true)
                ->where('planBudgetAccess.state', 'active_add_on')
                ->where('planBudgetAccess.label', 'Business Plan & Budget active')
                ->where('workspaces.active_key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->has('workspaces.items', 2)
                ->where('workspaces.items.0.key', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('workspaces.items.1.key', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
                ->where('strategicBudget.pathway', 'due_diligence')
                ->where('strategicBudget.business_plan_readiness_score', 0)
                ->where('strategicBudget.progress_score', 10));

        $document = Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => 'target-management-accounts.xlsx',
            'stored_path' => 'tests/target-management-accounts.xlsx',
            'byte_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'sha256' => hash('sha256', 'target-management-accounts'),
            'uploaded_by_user_id' => $clientUser->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        DocumentVerification::query()->create([
            'document_id' => $document->getKey(),
            'client_id' => $client->getKey(),
            'context_hash' => hash('sha256', 'target-management-accounts-verified'),
            'claim_text' => 'Management accounts verified for budget reliance.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.97,
            'verified_at' => now(),
        ]);

        $submittedAt = now()->subHour()->startOfSecond();
        $budget = app(StrategicBudgetService::class)->ensureForClient($client);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            'submitted_at' => $submittedAt,
            'business_plan_submitted_at' => $submittedAt,
        ])->save();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudget')
                ->where('budget.status', StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW)
                ->where('budget.can_submit_for_review', false)
                ->where('budget.review_submitted_or_later', true)
                ->where('budget.review_approved_or_later', false)
                ->where('budget.review_action_label', 'Submitted for review'));

        $this->actingAsMfa($clientUser)
            ->post(route('portal.business-plan-budget.submit'))
            ->assertRedirect(route('portal.business-plan-budget.show'));

        $budget->refresh();
        $this->assertSame(StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW, $budget->status);
        $this->assertSame($submittedAt->toDateTimeString(), $budget->submitted_at?->toDateTimeString());
        $this->assertSame($submittedAt->toDateTimeString(), $budget->business_plan_submitted_at?->toDateTimeString());
    }

    /**
     * @return array{Client, User, User}
     */
    private function dueDiligenceClientFixture(string $clientEmail = 'dd-plan-budget@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
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
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'legal_name' => 'Southern Lights Limited',
            'trading_name' => 'Southern Lights',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal', 'dd'],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal', 'dd'],
        ]);

        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => ['referral_type' => 'due_diligence'],
            'declared_at' => now(),
        ]);

        $engagement = DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => 'Kauri Kitchens Limited',
            'target_details' => [
                'vendor_name' => 'Kauri Kitchens Group',
                'industry' => 'Manufacturing',
                'asking_price' => 750000,
                'client_capability' => [
                    'mode' => 'guided',
                    'support_level' => 'guided',
                    'dd_experience' => 'first_time',
                    'business_ownership_experience' => 'none',
                    'financial_confidence' => 'low',
                    'preferred_guidance' => 'guided',
                    'captured_from' => 'onboarding',
                    'captured_at' => now()->toIso8601String(),
                ],
            ],
            'status' => DdEngagement::STATUS_IN_PROGRESS,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $advisor->getKey(),
            'disclaimer_acknowledged_at' => now(),
        ]);

        $ddPackage = ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
            'package_scope' => ServiceRatePackage::SCOPE_DD_300K_1M,
            'package_name' => 'Purchase price $300k-$1m',
            'client_label' => 'Purchase price $300k-$1m',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 8500,
            'deposit_percent' => 50,
            'purchase_price_min' => 300001,
            'purchase_price_max' => 1000000,
            'currency' => 'NZD',
            'scope_description' => 'DD price band for acquisition targets between $300k and $1m.',
            'is_active' => true,
            'effective_from' => now(),
        ]);

        ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Explore buying a business',
            'service_rate_package_id' => $ddPackage->getKey(),
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_completed_at' => now(),
            'accepted_at' => now(),
            'accepted_by_user_id' => $clientUser->getKey(),
            'related_dd_engagement_id' => $engagement->getKey(),
            'selected_package_snapshot' => $ddPackage->snapshot(),
            'intake' => [
                'target_name' => $engagement->target_name,
                'asking_price' => 750000,
                'preferred_guidance' => 'guided',
            ],
            'metadata' => ['source' => 'test'],
        ]);

        return [$client, $clientUser, $advisor];
    }

    private function ddPlanBudgetPackage(): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_DD_PLAN_BUDGET,
            'package_scope' => ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
            'package_name' => 'Business Plan & Budget add-on',
            'client_label' => 'Business Plan & Budget add-on',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 2400,
            'deposit_percent' => 100,
            'purchase_price_min' => null,
            'purchase_price_max' => null,
            'currency' => 'NZD',
            'scope_description' => 'Single Business Plan & Budget add-on fee added to the matched Explore Buying a Business purchase-price band when BP&B is included.',
            'is_active' => true,
            'effective_from' => now(),
        ]);
    }
}
