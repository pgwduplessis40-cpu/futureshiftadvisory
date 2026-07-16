<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\IntegrationFeeBand;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceRateIntegrationFeeBandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_can_import_integration_pricing_csv(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $csv = implode("\n", [
            'complexity_band,delivery_mode,fee_low,fee_mid,fee_high,currency,scope_description,hosting_monthly_cost,hosting_markup_percent,is_active',
            'M,lowcode,6500,8000,9500,NZD,"Two systems with monitoring and hypercare.",20.66,150,true',
            'XL,partner,30000,40000,50000,NZD,"Phased programme with partner governance.",149.52,250,false',
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.integration-fee-bands.import'), [
                'pricing_file' => UploadedFile::fake()->createWithContent('integration-pricing.csv', $csv),
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, IntegrationFeeBand::query()->count());
        $this->assertDatabaseHas('integration_fee_bands', [
            'complexity_band' => 'M',
            'delivery_mode' => 'lowcode',
            'fee_low' => 6500,
            'fee_mid' => 8000,
            'fee_high' => 9500,
            'currency' => 'NZD',
            'scope_description' => 'Two systems with monitoring and hypercare.',
            'hosting_monthly_cost' => 20.66,
            'hosting_markup_percent' => 150,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('integration_fee_bands', [
            'complexity_band' => 'XL',
            'delivery_mode' => 'partner',
            'scope_description' => 'Phased programme with partner governance.',
            'hosting_monthly_cost' => 149.52,
            'hosting_markup_percent' => 250,
            'is_active' => false,
        ]);
    }

    public function test_service_rates_page_provides_band_scope_defaults(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        $this->actingAsMfa($admin)
            ->get(route('admin.service-rates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/service-rates/Index')
                ->where('integrationFeeBandScopeDefaults.S', IntegrationFeeBand::defaultScopeDescriptionFor(IntegrationFeeBand::BAND_S))
                ->where('integrationFeeBandScopeDefaults.XL', IntegrationFeeBand::defaultScopeDescriptionFor(IntegrationFeeBand::BAND_XL))
                ->where('integrationFeeBandHostingDefaults.M.monthly_cost', 20.66)
                ->where('integrationFeeBandHostingDefaults.M.markup_percent', 100)
            );
    }

    public function test_super_admin_can_update_an_existing_integration_fee_band(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $band = IntegrationFeeBand::query()->create([
            'complexity_band' => IntegrationFeeBand::BAND_M,
            'delivery_mode' => 'inhouse',
            'fee_low' => 6_500,
            'fee_mid' => 8_000,
            'fee_high' => 9_500,
            'currency' => 'NZD',
            'scope_description' => 'Initial medium-complexity integration scope.',
            'hosting_monthly_cost' => 20.66,
            'hosting_markup_percent' => 100,
            'is_active' => true,
            'updated_by_user_id' => $admin->getKey(),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.service-rates.integration-fee-bands.store'), [
                'complexity_band' => IntegrationFeeBand::BAND_M,
                'delivery_mode' => 'inhouse',
                'fee_low' => 7_500,
                'fee_mid' => 9_000,
                'fee_high' => 10_500,
                'currency' => 'NZD',
                'scope_description' => 'Updated medium-complexity integration scope.',
                'hosting_monthly_cost' => 28.50,
                'hosting_markup_percent' => 125,
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.service-rates.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, IntegrationFeeBand::query()->count());
        $this->assertDatabaseHas('integration_fee_bands', [
            'id' => $band->getKey(),
            'fee_low' => 7_500,
            'fee_mid' => 9_000,
            'fee_high' => 10_500,
            'scope_description' => 'Updated medium-complexity integration scope.',
            'hosting_monthly_cost' => 28.50,
            'hosting_markup_percent' => 125,
            'is_active' => false,
        ]);
    }
}
