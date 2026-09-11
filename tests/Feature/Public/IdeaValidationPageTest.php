<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\ServiceRatePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdeaValidationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_live_idea_validation_price_and_self_serve_checkout_link(): void
    {
        $this->activeIdeaValidationPackage(750);

        $this->get(route('public.idea-validation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/idea-validation')
                ->where('offer.available', true)
                ->where('offer.price', 750)
                ->where('offer.priceFormatted', '$750')
                ->where('offer.currency', 'NZD')
                ->where('checkoutUrl', '/validate-idea?source=website&service=idea_validation'));
    }

    public function test_it_falls_back_to_no_price_when_no_active_rate_exists(): void
    {
        $this->get(route('public.idea-validation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/idea-validation')
                ->where('offer.available', false)
                ->missing('offer.price'));
    }

    public function test_the_faq_shows_the_live_price_and_the_self_serve_wording(): void
    {
        $this->activeIdeaValidationPackage(750);

        $response = $this->get(route('public.faq'))->assertOk();

        $faqs = collect($response->viewData('page')['props']['faqs']);
        $cost = $faqs->firstWhere('question', 'What does it cost to validate my business idea?');
        $becomeClient = $faqs->firstWhere('question', 'How do I become a client?');

        $this->assertStringContainsString('$750 + GST', $cost['answer']);
        $this->assertStringContainsString('Idea validation you can start yourself', $becomeClient['answer']);
        $this->assertStringNotContainsString('We do not run open sign-ups', $becomeClient['answer']);
    }

    private function activeIdeaValidationPackage(float $fixedFee): ServiceRatePackage
    {
        $admin = User::factory()->create();

        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Stage 1 - Idea Validation',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => $fixedFee,
            'hourly_rate' => null,
            'retainer_amount' => null,
            'purchase_price_min' => null,
            'purchase_price_max' => null,
            'currency' => 'NZD',
            'scope_description' => 'Evidence-based read on whether the idea holds up.',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_by_user_id' => $admin->getKey(),
        ]);
    }
}
