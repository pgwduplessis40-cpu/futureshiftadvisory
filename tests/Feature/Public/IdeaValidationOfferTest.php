<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\ServiceRatePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdeaValidationOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_idea_hides_a_price_when_no_current_rate_is_available(): void
    {
        $this->get(route('public.validate-idea'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/validate-idea')
                ->where('offer.available', false)
                ->where('offer.amount_ex_gst', null));
    }

    public function test_validate_idea_reads_its_live_price_from_service_rates(): void
    {
        ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation Sprint',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1650,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-reviewed idea validation.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);

        $this->get(route('public.validate-idea'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/validate-idea')
                ->where('offer.available', true)
                ->where('offer.amount_ex_gst', 1650)
                ->where('offer.currency', 'NZD'));
    }
}
