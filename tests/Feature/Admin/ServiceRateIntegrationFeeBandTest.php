<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\IntegrationFeeBand;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'complexity_band,delivery_mode,fee_low,fee_mid,fee_high,currency,is_active',
            'M,lowcode,6500,8000,9500,NZD,true',
            'XL,partner,30000,40000,50000,NZD,false',
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
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('integration_fee_bands', [
            'complexity_band' => 'XL',
            'delivery_mode' => 'partner',
            'is_active' => false,
        ]);
    }
}
