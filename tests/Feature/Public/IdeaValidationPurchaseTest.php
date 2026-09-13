<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\ClientStatus;
use App\Models\AuditEvent;
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
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Stripe\LiveStripeClient;
use App\Services\Payments\PaymentWebhookReconciler;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
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

        $this->get(route('public.terms-and-privacy', ['return_to' => 'idea-validation']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('returnToIdeaValidation', true));

        $this->get(route('public.terms-and-privacy', ['return_to' => 'https://untrusted.example']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('returnToIdeaValidation', false));
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

    public function test_an_authenticated_user_without_an_idea_validation_purchase_sees_a_safe_stop(): void
    {
        $administrator = $this->advisor();

        $this->actingAs($administrator)
            ->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/idea-validation-purchase')
                ->where('state', 'existing_session')
                ->where('purchase', null));
    }

    public function test_an_authenticated_user_cannot_submit_a_new_idea_validation_registration(): void
    {
        Notification::fake();
        $terms = $this->publishedTerms();
        $administrator = $this->advisor();

        $this->actingAs($administrator)
            ->post(route('public.validate-idea.purchase.register'), [
                'name' => 'Blocked Buyer',
                'email' => 'blocked-buyer@example.com',
                'password' => 'IdeaValidation1!',
                'password_confirmation' => 'IdeaValidation1!',
                'terms_version_id' => $terms->getKey(),
                'terms_accepted' => '1',
            ])
            ->assertRedirect(route('public.validate-idea.purchase'))
            ->assertSessionHasErrors('checkout');

        $this->assertAuthenticatedAs($administrator);
        $this->assertDatabaseCount('idea_validation_purchases', 0);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('terms_acceptances', 0);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-buyer@example.com']);
        Notification::assertNothingSent();
    }

    public function test_an_existing_account_is_directed_to_logon_or_password_recovery_without_creating_a_duplicate(): void
    {
        Notification::fake();
        $terms = $this->publishedTerms();
        $this->advisor();
        User::factory()->create(['email' => 'existing-account@example.com']);

        $this->post(route('public.validate-idea.purchase.register'), [
            'name' => 'Existing Account',
            'email' => 'Existing-Account@example.com',
            'password' => 'IdeaValidation1!',
            'password_confirmation' => 'IdeaValidation1!',
            'terms_version_id' => $terms->getKey(),
            'terms_accepted' => '1',
        ])
            ->assertRedirect(route('public.validate-idea.purchase'))
            ->assertSessionHas('idea_validation_account_conflict', 'existing_account');

        $this->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'register')
                ->where('accountConflict', 'existing_account'));

        $this->assertDatabaseCount('idea_validation_purchases', 0);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('terms_acceptances', 0);
        Notification::assertNothingSent();
    }

    public function test_an_existing_profile_without_a_login_is_directed_to_contact_support_without_creating_a_duplicate(): void
    {
        Notification::fake();
        $terms = $this->publishedTerms();
        $advisor = $this->advisor();
        EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Existing Profile',
            'email' => 'existing-profile@example.com',
            'concept_summary' => 'A profile created before account access was set up.',
        ]);

        $this->post(route('public.validate-idea.purchase.register'), [
            'name' => 'Existing Profile',
            'email' => 'Existing-Profile@example.com',
            'password' => 'IdeaValidation1!',
            'password_confirmation' => 'IdeaValidation1!',
            'terms_version_id' => $terms->getKey(),
            'terms_accepted' => '1',
        ])
            ->assertRedirect(route('public.validate-idea.purchase'))
            ->assertSessionHas('idea_validation_account_conflict', 'existing_profile');

        $this->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'register')
                ->where('accountConflict', 'existing_profile'));

        $this->assertDatabaseCount('idea_validation_purchases', 0);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('terms_acceptances', 0);
        Notification::assertNothingSent();
    }

    public function test_a_verification_link_confirms_the_email_without_switching_an_existing_session(): void
    {
        Notification::fake();
        $terms = $this->publishedTerms();
        $advisor = $this->advisor();
        $this->ideaValidationRate();

        $this->post(route('public.validate-idea.purchase.register'), [
            'name' => 'Idea Validation Buyer',
            'email' => 'idea-validation-buyer@example.com',
            'password' => 'IdeaValidation1!',
            'password_confirmation' => 'IdeaValidation1!',
            'terms_version_id' => $terms->getKey(),
            'terms_accepted' => '1',
        ])->assertSessionHasNoErrors();

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
        $this->actingAs($advisor)
            ->get($verificationUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/idea-validation-email-verified'));

        $this->assertAuthenticatedAs($advisor);
        $buyer->refresh();
        $purchase->refresh();
        $this->assertNotNull($buyer->email_verified_at);
        $this->assertNotNull($purchase->email_verified_at);
        $this->assertSame(IdeaValidationPurchase::STATUS_PAYMENT_PENDING, $purchase->status);
    }

    public function test_checkout_session_detects_email_verification_without_a_portal_login(): void
    {
        Notification::fake();
        $terms = $this->publishedTerms();
        $this->advisor();
        $this->ideaValidationRate();

        $this->post(route('public.validate-idea.purchase.register'), [
            'name' => 'Checkout-only buyer',
            'email' => 'checkout-only@example.com',
            'password' => 'IdeaValidation1!',
            'password_confirmation' => 'IdeaValidation1!',
            'terms_version_id' => $terms->getKey(),
            'terms_accepted' => '1',
        ])->assertRedirect(route('public.validate-idea.purchase'));

        $this->assertGuest();
        $this->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'verify_email'));

        $buyer = User::query()->where('email', 'checkout-only@example.com')->firstOrFail();
        $purchase = IdeaValidationPurchase::query()->where('user_id', $buyer->getKey())->firstOrFail();
        app(IdeaValidationCheckout::class)->markEmailVerified($purchase, sha1($buyer->email));

        $this->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'checkout')
                ->where('purchase.email_verified_at', fn (?string $value): bool => $value !== null));
        $this->assertGuest();
    }

    public function test_a_historical_unpaid_buyer_session_is_logged_out_and_restored_to_checkout(): void
    {
        Notification::fake();
        [, $buyer] = $this->registerAndVerifyBuyer();

        $this->actingAs($buyer)
            ->get(route('dashboard'))
            ->assertRedirect(route('public.validate-idea.purchase'));

        $this->assertGuest();
        $this->get(route('public.validate-idea.purchase'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'checkout'));
    }

    public function test_payment_setup_failure_is_safe_for_the_buyer_and_retryable(): void
    {
        Notification::fake();
        [, $buyer] = $this->registerAndVerifyBuyer();
        $purchase = IdeaValidationPurchase::query()->where('user_id', $buyer->getKey())->firstOrFail();

        $this->mock(StripeClient::class, function (MockInterface $stripe): void {
            $stripe->shouldReceive('createIdeaValidationPaymentIntent')
                ->once()
                ->andThrow(new RuntimeException('Stripe connection failed.'));
        });

        $response = $this->postJson(route('public.validate-idea.purchase.payment-intent'))
            ->assertStatus(503)
            ->assertJsonPath('code', 'payment_setup_unavailable')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'no payment has been taken'))
            ->assertJsonPath('support_reference', fn (string $reference): bool => str_starts_with($reference, 'IV-'));

        $purchase->refresh();
        $this->assertSame(IdeaValidationPurchase::STATUS_PAYMENT_PENDING, $purchase->status);
        $this->assertDatabaseHas('payments', [
            'id' => $purchase->payment_id,
            'status' => Payment::STATUS_PENDING,
        ]);
        $audit = AuditEvent::query()
            ->where('action', 'idea_validation.purchase_payment_setup_failed')
            ->firstOrFail();
        $this->assertSame((string) $purchase->getKey(), $audit->subject_id);
        $this->assertSame((string) $buyer->getKey(), $audit->actor_user_key);
        $this->assertSame('stripe', data_get($audit->after, 'gateway'));
        $this->assertSame($response->json('support_reference'), data_get($audit->after, 'support_reference'));
        $this->assertSame(IdeaValidationPurchase::STATUS_PAYMENT_PENDING, data_get($audit->after, 'status'));
        $this->assertFalse(data_get($audit->after, 'payment_taken'));
    }

    public function test_verified_buyer_can_start_live_checkout_with_vaulted_stripe_credentials(): void
    {
        Notification::fake();
        [, $buyer] = $this->registerAndVerifyBuyer();
        $context = app(RequestContext::class);

        $context->withSystemContext(function (): void {
            $administrator = User::factory()->superAdmin()->create();
            $credentials = app(IntegrationCredentials::class);

            $credentials->set('stripe', 'secret', 'sk_live_checkout', $administrator);
            $credentials->set('stripe', 'publishable_key', 'pk_live_checkout', $administrator);
            $credentials->set('stripe', 'webhook_secret', 'whsec_live_checkout', $administrator);
            app(IntegrationActivationResolver::class)->activate('stripe', $administrator);
        });

        Config::set('integrations.payments.stripe.live', false);
        Config::set('integrations.payments.stripe.secret', null);
        Config::set('integrations.payments.stripe.publishable_key', null);
        Config::set('integrations.payments.stripe.webhook_secret', null);
        Config::set('integrations.retry.attempts', 1);
        $this->app->bind(StripeClient::class, LiveStripeClient::class);
        $this->app->forgetInstance(StripeClient::class);
        $this->app->forgetInstance(LiveStripeClient::class);

        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_live_idea_validation',
                'client_secret' => 'pi_live_idea_validation_secret',
            ]),
        ]);

        $context->apply(RequestContext::ROLE_GUEST, []);

        $this->postJson(route('public.validate-idea.purchase.payment-intent'))
            ->assertOk()
            ->assertJsonPath('fixture', false)
            ->assertJsonPath('publishable_key', 'pk_live_checkout')
            ->assertJsonPath('payment_intent_id', 'pi_live_idea_validation');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.stripe.com/v1/payment_intents'
                && $request->hasHeader('Authorization', 'Bearer sk_live_checkout');
        });
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
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/idea-validation-email-verified'));

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
