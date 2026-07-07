<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\Proposal;
use App\Models\ServiceRatePackage;
use App\Models\ServiceRateSetting;
use App\Models\User;
use App\Services\Fees\FeeCalculator;
use App\Services\Fees\ServiceRateManager;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceRateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_update_service_rate(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 325,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
                'notes' => 'Updated for current operating cost conditions.',
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false));

        $rate = ServiceRateSetting::query()->firstOrFail();

        $this->assertSame(325.0, $rate->hourly_rate);
        $this->assertSame('NZD', $rate->currency);
        $this->assertSame(30.0, $rate->npo_service_discount_percent);
        $this->assertSame(35.0, $rate->npo_retainer_discount_percent);
        $this->assertTrue($rate->is_active);
        $this->assertSame($admin->getKey(), $rate->created_by_user_id);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_rate.updated',
            'subject_id' => $rate->id,
        ]);
    }

    public function test_index_renders_current_rate_and_history(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 310,
                'npo_service_discount_percent' => 25,
                'npo_retainer_discount_percent' => 40,
                'notes' => 'Initial admin rate.',
            ]);

        $rate = ServiceRateSetting::query()->firstOrFail();

        $this->actingAsMfa($admin)
            ->get(route('admin.service-rates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/service-rates/Index')
                ->where('current.hourly_rate', 310)
                ->where('current.npo_service_discount_percent', 25)
                ->where('current.npo_retainer_discount_percent', 40)
                ->where('current.is_active', true)
                ->where('current.toggle_url', route('admin.service-rates.toggle', $rate, absolute: false))
                ->where('fallback.currency', 'NZD')
                ->has('history', 1)
                ->where('history.0.is_active', true)
                ->where('storeUrl', route('admin.service-rates.store', absolute: false))
            );
    }

    public function test_super_admin_can_activate_and_deactivate_service_rate(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 325,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
                'notes' => 'Toggle this admin rate.',
            ]);

        $rate = ServiceRateSetting::query()->firstOrFail();

        $this->actingAsMfa($admin)
            ->patch(route('admin.service-rates.toggle', $rate), [
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false));

        $this->assertFalse($rate->refresh()->is_active);
        $this->assertNull(app(ServiceRateManager::class)->current());
        $this->assertTrue(app(ServiceRateManager::class)->freeAccessModeActive());
        $this->assertSame(0.0, app(ServiceRateManager::class)->currentHourlyRate());

        $this->actingAsMfa($admin)
            ->get(route('admin.service-rates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current', null)
                ->has('history', 1)
                ->where('history.0.is_active', false)
            );

        $this->actingAsMfa($admin)
            ->patch(route('admin.service-rates.toggle', $rate), [
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false));

        $this->assertTrue($rate->refresh()->is_active);
        $this->assertSame($rate->id, app(ServiceRateManager::class)->current()?->id);
        $this->assertFalse(app(ServiceRateManager::class)->freeAccessModeActive());
        $this->assertSame(325.0, app(ServiceRateManager::class)->currentHourlyRate());

        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_rate.toggled',
            'subject_id' => $rate->id,
        ]);
    }

    public function test_super_admin_can_update_workspace_package(): void
    {
        $admin = $this->superAdmin();
        $package = ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'package_name' => 'Stage 3 - Bundle',
            'client_label' => 'Bundle - Idea + Business Plan + Budget',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 4450,
            'hourly_rate' => null,
            'retainer_amount' => null,
            'purchase_price_min' => null,
            'purchase_price_max' => null,
            'currency' => 'NZD',
            'scope_description' => 'Platform validation, graded plan, budget/runway and revision.',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_by_user_id' => $admin->getKey(),
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.service-rates.packages.update', $package), [
                'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
                'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
                'package_name' => 'Stage 2 - Full plan + assessment + runway',
                'client_label' => 'Full plan + assessment + runway',
                'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
                'fixed_fee' => 3450,
                'hourly_rate' => null,
                'retainer_amount' => null,
                'purchase_price_min' => null,
                'purchase_price_max' => null,
                'scope_description' => 'Business plan workspace, budget/runway builder, advisor assessment, and revision round.',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false));

        $package->refresh();

        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $package->package_scope);
        $this->assertSame('Stage 2 - Full plan + assessment + runway', $package->package_name);
        $this->assertSame('Full plan + assessment + runway', $package->client_label);
        $this->assertSame(3450.0, $package->fixed_fee);
        $this->assertTrue($package->is_active);
        $this->assertNull($package->effective_to);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_rate_package.updated',
            'subject_id' => $package->id,
        ]);
    }

    public function test_hours_based_fees_only_use_admin_service_rate(): void
    {
        $admin = $this->superAdmin();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Service Rate Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 300,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
                'notes' => 'Used by default fee calculations.',
            ]);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::HoursBased, [
            'hourly_rate' => 125,
            'services' => [
                ['name' => 'Standard advisory review', 'hours' => 6, 'rate' => 125],
            ],
        ]);

        $this->assertSame(1800.0, $calculation->suggested_mid);
        $this->assertEquals(300.0, $calculation->justification['services'][0]['rate']);
        $this->assertEquals(300.0, $calculation->justification['services'][0]['base_rate']);
        $this->assertSame('admin_service_rate', $calculation->justification['services'][0]['rate_source']);
        $this->assertSame('NZD', $calculation->justification['services'][0]['currency']);
        $this->assertEquals(0.0, $calculation->justification['services'][0]['npo_service_discount_percent']);
        $this->assertFalse($calculation->justification['services'][0]['npo_discount_applied']);
        $this->assertArrayNotHasKey('hourly_rate', $calculation->inputs);
        $this->assertArrayNotHasKey('rate', $calculation->inputs['services'][0]);
    }

    public function test_npo_hours_based_fees_use_admin_npo_service_rate_discount(): void
    {
        $admin = $this->superAdmin();
        [$client, $engagement] = $this->npoClient();

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 250,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
                'notes' => 'NPO hourly discount setting.',
            ]);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::HoursBased, [
            'hourly_rate' => 999,
            'services' => [
                ['name' => 'NPO implementation support', 'hours' => 8, 'rate' => 999],
            ],
        ], [
            'npo_engagement_id' => $engagement->id,
        ]);

        $this->assertSame(1400.0, $calculation->suggested_mid);
        $this->assertEquals(250.0, $calculation->justification['services'][0]['base_rate']);
        $this->assertEquals(175.0, $calculation->justification['services'][0]['rate']);
        $this->assertEquals(30.0, $calculation->justification['services'][0]['npo_service_discount_percent']);
        $this->assertTrue($calculation->justification['services'][0]['npo_discount_applied']);
    }

    public function test_npo_retainer_uses_admin_retainer_discount(): void
    {
        $admin = $this->superAdmin();
        [$client, $engagement] = $this->npoClient();

        config()->set('fees.sme.retainer_monthly.foundation', 1000);

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 250,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 20,
                'notes' => 'NPO retainer discount setting.',
            ]);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::NpoRetainer, [
            'budget_band' => 'small',
            'npo_discount_rate' => 0.99,
        ], [
            'npo_engagement_id' => $engagement->id,
        ]);

        $this->assertSame(9600.0, $calculation->suggested_mid);
        $this->assertEquals(800.0, $calculation->justification['monthly_retainer_fee']);
        $this->assertSame(0.2, $calculation->justification['npo_discount_rate']);
        $this->assertEquals(20.0, $calculation->justification['npo_retainer_discount_percent']);
        $this->assertSame('admin_service_rate', $calculation->justification['npo_retainer_discount_source']);
        $this->assertArrayNotHasKey('npo_discount_rate', $calculation->inputs);
    }

    public function test_signed_proposal_keeps_accepted_rate_after_admin_rate_changes(): void
    {
        $admin = $this->superAdmin();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Accepted Rate Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 300,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
                'notes' => 'Accepted proposal rate.',
            ]);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::HoursBased, [
            'services' => [
                ['name' => 'Accepted implementation support', 'hours' => 6],
            ],
        ]);
        $proposal = app(ProposalBuilder::class)->generate($client, $calculation);
        $proposal = app(ProposalBuilder::class)->release($proposal, $admin);

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $admin): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();

            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now(),
                'signed_by_user_id' => $admin->getKey(),
            ])->save();
        });

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 450,
                'npo_service_discount_percent' => 10,
                'npo_retainer_discount_percent' => 20,
                'notes' => 'Future proposals only.',
            ]);

        $proposal = $proposal->refresh()->load('feeCalculation');

        $this->assertSame(ProposalStatus::Signed, $proposal->status);
        $this->assertSame(1800.0, $proposal->feeCalculation->suggested_mid);
        $this->assertEquals(300.0, $proposal->services[0]['rate']);
        $this->assertSame('admin_service_rate', $proposal->services[0]['rate_source']);
    }

    public function test_non_super_admin_cannot_manage_service_rates(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('admin.service-rates.index'))
            ->assertForbidden();

        $this->actingAsMfa($advisor)
            ->post(route('admin.service-rates.store'), [
                'hourly_rate' => 300,
                'npo_service_discount_percent' => 30,
                'npo_retainer_discount_percent' => 35,
            ])
            ->assertForbidden();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    /**
     * @return array{0: Client, 1: NpoEngagement}
     */
    private function npoClient(): array
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'NPO Service Rate Trust',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$client, $engagement];
    }
}
