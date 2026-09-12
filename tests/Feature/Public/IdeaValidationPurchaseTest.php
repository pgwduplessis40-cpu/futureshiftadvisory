<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\IdeaValidationEmailVerificationNotification;
use App\Notifications\IdeaValidationPurchaseAdvisorNotification;
use App\Notifications\IdeaValidationPurchaseConfirmedNotification;
use App\Services\Payments\PaymentWebhookReconciler;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IdeaValidationPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        $this->withoutVite();
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_public_policy_page_and_json_are_derived_from_the_published_version(): void
    {
        $terms = $this->publishedTerms();

        $this->get(route('public.terms-and-privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/terms-and-privacy')
                ->where('document.published', true)
                ->where('document.title', $terms->title)
                ->where('document.version', $terms->version)
                ->has('document.clauses', 1));

        $this->getJson(route('public.terms-and-privacy.json'))
            ->assertOk()
            ->assertHeaderContains('cache-control', 'max-age=300')
            ->assertHeader('access-control-allow-origin', '*')
            ->assertJsonPath('document.published', true)
            ->assertJsonPath('document.title', $terms->title)
            ->assertJsonPath('document.clauses.0.title', 'Acceptance');
    }

    public function test_public_policy_page_never_uses_published_proposal_terms(): void
    {
        $proposal = TermsVersion::query()->create([
            'document_scope' => TermsVersion::SCOPE_PROPOSAL,
            'version' => 'proposal-v1',
            'title' => 'Proposal terms',
            'material' => true,
            'published_at' => now()->subMinute(),
            'notice_period_days' => 30,
        ]);
        $proposal->clauses()->create([
            'clause_number' => 1,
            'title' => 'Proposal-only clause',
            'body' => 'This content must not be exposed as the website policy.',
            'material' => true,
        ]);

        $this->get(route('public.terms-and-privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.published', false)
                ->where('document.version', null));

        $this->getJson(route('public.terms-and-privacy.json'))
            ->assertOk()
            ->assertJsonPath('document.published', false)
            ->assertJsonPath('document.version', null);
    }

    public function test_verified_buyer_can_complete_fixture_checkout_and_is_activated_with_a_receipt(): void
    {
        Notification::fake();
        [, $buyer] = $this->registerAndVerifyBuyer();

        $purchase = IdeaValidationPurchase::query()->where('user_id', $buyer->getKey())->firstOrFail();
        $client = Client::query()->findOrFail($purchase->client_id);
        $this->assertSame(ClientStatus::PAUSED, $client->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('service_activations', 0);

        $intent = $this->postJson(route('public.validate-idea.purchase.payment-intent'))
            ->assertOk()
            ->assertJsonPath('fixture', true)
            ->assertJsonPath('amount_ex_gst', 1650)
            ->assertJsonPath('gst_amount', 247.5)
            ->assertJsonPath('amount_including_gst', 1897.5)
            ->json();

        $purchase->refresh();
        $payment = Payment::query()->findOrFail($purchase->payment_id);
        $this->assertNull($payment->payment_schedule_id);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame($intent['payment_intent_id'], $purchase->stripe_payment_intent_ref);
        $this->assertDatabaseCount('service_activations', 0);

        $this->postJson(route('public.validate-idea.purchase.confirm-fixture-payment'))
            ->assertOk()
            ->assertJsonPath('paid', true)
            ->assertJsonPath('next_url', '/mfa/setup');

        $purchase->refresh();
        $payment->refresh();
        $client->refresh();
        $activation = ServiceActivation::query()->findOrFail($purchase->service_activation_id);
        $profile = EntrepreneurProfile::query()->where('user_id', $buyer->getKey())->firstOrFail();

        $this->assertSame(IdeaValidationPurchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(ClientStatus::ACTIVE, $client->status);
        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);
        $this->assertSame($purchase->stripe_payment_intent_ref, $activation->payment_reference);
        $this->assertSame('idea_validation', $profile->stage->value);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->getKey()]);
        $this->assertDatabaseHas('terms_acceptances', [
            'user_id' => $buyer->getKey(),
            'terms_version_id' => $purchase->terms_version_id,
        ]);

        Notification::assertSentTo($buyer, IdeaValidationPurchaseConfirmedNotification::class);
        Notification::assertSentTo($purchase->advisor, IdeaValidationPurchaseAdvisorNotification::class);
    }

    public function test_signed_stripe_webhook_activates_a_verified_purchase_when_the_browser_does_not_return(): void
    {
        Notification::fake();
        [, $buyer] = $this->registerAndVerifyBuyer();

        $intent = $this->postJson(route('public.validate-idea.purchase.payment-intent'))
            ->assertOk()
            ->json();
        $purchase = IdeaValidationPurchase::query()->where('user_id', $buyer->getKey())->firstOrFail();
        $payment = Payment::query()->findOrFail($purchase->payment_id);

        app(PaymentWebhookReconciler::class)->handleStripe([
            'id' => 'evt_idea_validation_purchase_succeeded',
            'type' => 'payment_intent.succeeded',
            'created' => now()->getTimestamp(),
            'data' => [
                'object' => [
                    'id' => $intent['payment_intent_id'],
                    'currency' => 'nzd',
                    'amount_received' => 189750,
                    'metadata' => ['payment_id' => $payment->getKey()],
                ],
            ],
        ]);

        $purchase->refresh();
        $this->assertSame(IdeaValidationPurchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->service_activation_id);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->getKey()]);
        Notification::assertSentTo($buyer, IdeaValidationPurchaseConfirmedNotification::class);
    }

    /** @return array{0: TermsVersion, 1: User} */
    private function registerAndVerifyBuyer(): array
    {
        $terms = $this->publishedTerms();
        $this->advisor();
        $this->ideaValidationRate();

        $this->post(route('public.validate-idea.purchase.register'), [
            'name' => 'Idea Validation Buyer',
            'email' => 'idea-validation-buyer@example.com',
            'password' => 'IdeaValidation1!',
            'password_confirmation' => 'IdeaValidation1!',
            'terms_version_id' => $terms->getKey(),
            'terms_accepted' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('public.validate-idea.purchase'));

        $buyer = User::query()->where('email', 'idea-validation-buyer@example.com')->firstOrFail();
        $purchase = IdeaValidationPurchase::query()->where('user_id', $buyer->getKey())->firstOrFail();
        $verificationUrl = null;
        Notification::assertSentTo(
            $buyer,
            IdeaValidationEmailVerificationNotification::class,
            function (IdeaValidationEmailVerificationNotification $notification, array $channels) use (&$verificationUrl, $buyer): bool {
                $verificationUrl = $notification->toMail($buyer)->actionUrl;

                return $channels === ['mail'];
            },
        );

        $this->assertIsString($verificationUrl);
        $this->get($verificationUrl)
            ->assertRedirect(route('public.validate-idea.purchase'));

        $buyer->refresh();
        $purchase->refresh();
        $this->assertNotNull($buyer->email_verified_at);
        $this->assertNotNull($purchase->email_verified_at);
        $this->assertSame(IdeaValidationPurchase::STATUS_PAYMENT_PENDING, $purchase->status);
        $this->assertDatabaseHas('terms_acceptances', [
            'user_id' => $buyer->getKey(),
            'terms_version_id' => $terms->getKey(),
        ]);

        return [$terms, $buyer];
    }

    private function advisor(): User
    {
        return User::factory()->create([
            'name' => 'Default Idea Validation Advisor',
            'email' => 'idea-validation-advisor@example.com',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
    }

    private function ideaValidationRate(): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1650,
            'deposit_percent' => 100,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-reviewed idea validation.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);
    }

    private function publishedTerms(): TermsVersion
    {
        $terms = TermsVersion::query()->create([
            'document_scope' => TermsVersion::SCOPE_WEBSITE,
            'version' => 'idea-validation-terms-v1',
            'title' => 'Future Shift Advisory Terms and Privacy Policy',
            'material' => true,
            'published_at' => now()->subMinute(),
            'notice_period_days' => 30,
        ]);
        $terms->clauses()->create([
            'clause_number' => 1,
            'title' => 'Acceptance',
            'body' => 'The client accepts the published terms and privacy policy before purchase.',
            'material' => true,
        ]);

        return $terms->refresh()->load('clauses');
    }
}
