<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EntrepreneurServiceOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_active_idea_validation_price(): void
    {
        // service_rate_packages is row-level-security protected, so forScope
        // must read inside a system context. This guards that the wrap still
        // resolves an active package for the public price display.
        ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation Sprint',
            'client_label' => 'Idea Validation Sprint',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 100,
            'deposit_percent' => 100,
            'currency' => 'NZD',
            'scope_description' => 'Idea validation viability review.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);

        $offer = app(EntrepreneurServiceOffer::class)
            ->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION);

        $this->assertTrue($offer['available']);
        $this->assertSame(100.0, $offer['amount_ex_gst']);
        $this->assertSame('Idea Validation Sprint', $offer['label']);
        $this->assertSame('NZD', $offer['currency']);
    }

    public function test_it_reports_unavailable_when_no_active_package_exists(): void
    {
        $offer = app(EntrepreneurServiceOffer::class)
            ->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION);

        $this->assertFalse($offer['available']);
        $this->assertNull($offer['amount_ex_gst']);
    }
}
