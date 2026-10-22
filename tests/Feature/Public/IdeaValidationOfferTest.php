<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\ServiceRatePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdeaValidationOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_idea_validation_page_hides_a_price_when_no_current_rate_is_available(): void
    {
        $this->get('/services/entrepreneur')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/services/entrepreneur')
                ->where('ideaValidationOffer.available', false)
                ->where('ideaValidationOffer.amount_ex_gst', null));
    }

    public function test_the_home_and_idea_validation_pages_read_the_live_price_from_service_rates(): void
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

        $this->get('/services/entrepreneur')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/services/entrepreneur')
                ->where('ideaValidationOffer.available', true)
                ->where('ideaValidationOffer.amount_ex_gst', 1650)
                ->where('ideaValidationOffer.currency', 'NZD'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/home')
                ->where('ideaValidationOffer.available', true)
                ->where('ideaValidationOffer.amount_ex_gst', 1650));
    }

    public function test_the_old_validate_idea_url_permanently_redirects_to_the_idea_validation_page(): void
    {
        $this->get('/validate-idea')
            ->assertStatus(301)
            ->assertRedirect('/services/entrepreneur');
    }
}
