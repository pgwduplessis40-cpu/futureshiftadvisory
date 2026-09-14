<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\PlanSection;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\ApprovedIdeaPlanStarter;
use App\Services\Entrepreneurs\EntrepreneurPlanBudgetCheckout;
use App\Services\Entrepreneurs\EntrepreneurPlanBudgetProvisioner;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Payments\PaymentWebhookReconciler;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MakesIdeaReviewEligible;
use Tests\TestCase;

final class EntrepreneurPlanBudgetCheckoutTest extends TestCase
{
    use MakesIdeaReviewEligible;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_an_idea_validation_client_cannot_start_bpb_checkout_before_advisor_approval(): void
    {
        [, $founder, $profile] = $this->profile();
        $this->planBudgetRate();

        try {
            app(EntrepreneurPlanBudgetCheckout::class)->beginPayment($founder, $profile);
            $this->fail('Expected checkout to remain unavailable before advisor approval.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('after your advisor approves the Idea Validation', $exception->getMessage());
        }

        $this->assertDatabaseCount('entrepreneur_plan_budget_purchases', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_approved_idea_validation_direct_checkout_settles_and_seeds_a_single_snapshot_into_bpb(): void
    {
        [$advisor, $founder, $profile] = $this->profile();
        $this->planBudgetRate();
        $approved = $this->ideaValidation($profile, $advisor, approved: true);

        $checkout = app(EntrepreneurPlanBudgetCheckout::class);
        $intent = $checkout->beginPayment($founder, $profile);

        $this->assertTrue($intent->fixture);
        $purchase = $checkout->confirmFixturePayment($founder, $profile);
        $payment = Payment::query()->findOrFail($purchase->payment_id);
        $plan = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->firstOrFail();
        $section = PlanSection::query()
            ->where('business_plan_id', $plan->getKey())
            ->where('key', 'idea-validation-summary')
            ->firstOrFail();

        $this->assertSame(EntrepreneurPlanBudgetPurchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertNotNull($purchase->activated_at);
        $this->assertSame((string) $approved->getKey(), (string) $purchase->approved_idea_validation_id);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->getKey()]);
        $this->assertStringContainsString('Demand evidence: Eight customer interviews', $section->body);
        $this->assertStringContainsString('Revenue model: Monthly subscriptions', $section->body);
        $this->assertSame((string) $approved->getKey(), data_get($section->metadata, 'idea_validation_id'));
        $this->assertTrue((bool) data_get($section->metadata, 'seeded_as_immutable_source'));

        $section->forceFill(['body' => 'Founder-owned BP&B content must not be overwritten.'])->save();
        $laterApproved = $this->ideaValidation($profile, $advisor, approved: true, revision: 2);
        app(ApprovedIdeaPlanStarter::class)->start($profile, $laterApproved, $founder);

        $this->assertSame('Founder-owned BP&B content must not be overwritten.', $section->refresh()->body);
    }

    public function test_paid_bpb_purchase_waits_for_advisor_approval_before_creating_the_plan(): void
    {
        [$advisor, $founder, $profile] = $this->profile();
        $payment = Payment::query()->create([
            'client_id' => $profile->client_id,
            'payment_schedule_id' => null,
            'amount' => '115.00',
            'currency' => 'NZD',
            'gateway' => 'stripe',
            'gateway_ref' => 'pi_bpb_waiting_for_advisor',
            'idempotency_key' => 'bpb-waiting-for-advisor-'.$profile->getKey(),
            'status' => Payment::STATUS_SUCCEEDED,
            'attempt' => 1,
            'processed_at' => now(),
        ]);
        $purchase = new EntrepreneurPlanBudgetPurchase;
        $purchase->forceFill([
            'user_id' => $founder->getKey(),
            'client_id' => $profile->client_id,
            'entrepreneur_profile_id' => $profile->getKey(),
            'advisor_id' => $advisor->getKey(),
            'payment_id' => $payment->getKey(),
            'status' => EntrepreneurPlanBudgetPurchase::STATUS_PAID,
            'amount_ex_gst' => '100.00',
            'gst_amount' => '15.00',
            'amount_including_gst' => '115.00',
            'currency' => 'NZD',
            'stripe_payment_intent_ref' => $payment->gateway_ref,
            'paid_at' => now(),
        ])->save();

        $this->assertNull(app(EntrepreneurPlanBudgetProvisioner::class)->provision($profile, $advisor));
        $this->assertDatabaseCount('business_plans', 0);
        $this->assertNull($purchase->refresh()->activated_at);

        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Regional owner-managed businesses lose time coordinating critical recurring operations.',
            'target_customer' => 'Owner-managed service businesses with small teams and recurring client work.',
            'solution' => 'A guided operating workflow that makes customer follow-up and team priorities visible.',
            'value_proposition' => 'Owners save administration time while delivering more reliable service to clients.',
            'demand_signal' => 'Eight customer interviews and two paid pilots confirmed recurring demand for the workflow.',
            'revenue_model' => 'Monthly subscriptions plus a setup fee charged to each customer business.',
        ], $advisor);
        $approved = app(IdeaValidationService::class)->passAdvisorGate(
            $this->completedIdeaReview($validation),
            $advisor,
            'Advisor approved the minimum viable evidence.',
        );

        $this->assertDatabaseHas('business_plans', [
            'entrepreneur_profile_id' => $profile->getKey(),
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
        ]);
        $this->assertSame((string) $approved->getKey(), (string) $purchase->refresh()->approved_idea_validation_id);
        $this->assertNotNull($purchase->activated_at);
    }

    public function test_signed_stripe_webhook_settles_an_approved_bpb_direct_checkout_when_the_browser_does_not_return(): void
    {
        [$advisor, $founder, $profile] = $this->profile();
        $this->planBudgetRate();
        $this->ideaValidation($profile, $advisor, approved: true);
        $intent = app(EntrepreneurPlanBudgetCheckout::class)->beginPayment($founder, $profile);
        $purchase = EntrepreneurPlanBudgetPurchase::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->firstOrFail();
        $payment = Payment::query()->findOrFail($purchase->payment_id);

        $event = app(PaymentWebhookReconciler::class)->handleStripe([
            'id' => 'evt_bpb_direct_checkout_succeeded',
            'type' => 'payment_intent.succeeded',
            'created' => now()->getTimestamp(),
            'data' => [
                'object' => [
                    'id' => $intent->paymentIntentRef,
                    'currency' => 'nzd',
                    'amount_received' => 11500,
                    'metadata' => ['payment_id' => $payment->getKey()],
                ],
            ],
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame(EntrepreneurPlanBudgetPurchase::STATUS_PAID, $purchase->refresh()->status);
        $this->assertNotNull($purchase->activated_at);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->getKey()]);
        $this->assertDatabaseHas('business_plans', [
            'entrepreneur_profile_id' => $profile->getKey(),
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
        ]);
    }

    /** @return array{0: User, 1: User, 2: EntrepreneurProfile} */
    private function profile(): array
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $founder = User::factory()->create([
            'name' => 'Approved BP&B Founder',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $founder->assignRole(User::TYPE_ENTREPRENEUR);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
            'status' => ClientStatus::ACTIVE,
            'legal_name' => $founder->name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['source' => 'bpb-direct-checkout-test'],
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $founder->getKey(),
        ]);

        return [$advisor, $founder, EntrepreneurProfile::query()->create([
            'user_id' => $founder->getKey(),
            'client_id' => $client->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => $founder->name,
            'email' => $founder->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'A founder journey that begins with Idea Validation.',
            'intended_service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
        ])];
    }

    private function planBudgetRate(): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            'package_name' => 'Business Plan & Budget add-on',
            'client_label' => 'Business Plan & Budget',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => '100.00',
            'deposit_percent' => 100,
            'currency' => 'NZD',
            'scope_description' => 'A structured plan and budget following Idea Validation approval.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);
    }

    private function ideaValidation(EntrepreneurProfile $profile, User $advisor, bool $approved, int $revision = 1): IdeaValidation
    {
        return IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'revision_number' => $revision,
            'problem' => 'Regional owner-managed businesses lose time coordinating critical recurring operations.',
            'target_customer' => 'Owner-managed service businesses with small teams and recurring client work.',
            'solution' => 'A guided operating workflow that makes customer follow-up and team priorities visible.',
            'value_proposition' => 'Owners save administration time while delivering more reliable service to clients.',
            'demand_signal' => 'Eight customer interviews and two paid pilots confirmed recurring demand for the workflow.',
            'revenue_model' => 'Monthly subscriptions plus a setup fee charged to each customer business.',
            'ai_evaluation' => ['metadata' => ['refresh_status' => 'completed']],
            'viability_alerts' => [],
            'evaluated_at' => now(),
            'evaluated_by_user_id' => $advisor->getKey(),
            'advisor_gate_passed_at' => $approved ? now() : null,
            'advisor_gate_passed_by_user_id' => $approved ? $advisor->getKey() : null,
            'advisor_gate_note' => $approved ? 'Advisor approved the minimum viable evidence.' : null,
        ]);
    }
}
