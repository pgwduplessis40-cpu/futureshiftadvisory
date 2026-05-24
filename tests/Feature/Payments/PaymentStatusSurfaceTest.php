<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PaymentStatusSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        Notification::fake();

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });

        Config::set('integrations.payments.primary_gateway', PaymentAuthority::GATEWAY_STRIPE);
        Config::set('integrations.payments.stripe.live', false);
        Config::set('integrations.payments.windcave.live', false);
        Config::set('integrations.payments.max_attempts', 2);
        Config::set('integrations.payments.retry_delay_minutes', 45);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payment_status_report_surfaces_latest_schedule_rows_with_retry_flags(): void
    {
        Carbon::setTestNow('2026-05-25 09:00:00');
        $advisor = $this->advisor('payment-status-advisor@example.test');
        [, $authority, $failedSchedule] = $this->fixture($advisor, 'Latest Failed Limited', schedule: [
            'next_run_at' => now()->addHour(),
        ]);
        $older = $this->payment($failedSchedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'failed_reason' => 'Older failed attempt',
            'processed_at' => now()->subDays(2),
        ]);
        $latest = $this->payment($failedSchedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 2,
            'failed_reason' => 'Latest failed attempt',
            'processed_at' => now(),
        ]);

        [, $pausedAuthority, $pausedSchedule] = $this->fixture($advisor, 'Paused Retry Limited', schedule: [
            'status' => PaymentSchedule::STATUS_PAUSED,
            'next_run_at' => now()->subDay(),
        ]);
        $pausedPayment = $this->payment($pausedSchedule, $pausedAuthority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'processed_at' => now()->subMinutes(20),
        ]);

        [, $revokedAuthority, $revokedSchedule] = $this->fixture($advisor, 'Revoked Schedule Limited', schedule: [
            'status' => PaymentSchedule::STATUS_REVOKED,
        ]);
        $revokedPayment = $this->payment($revokedSchedule, $revokedAuthority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'processed_at' => now()->subMinutes(10),
        ]);

        [, $failedAuthority, $authoritySchedule] = $this->fixture($advisor, 'Failed Authority Limited', authority: [
            'status' => PaymentAuthority::STATUS_FAILED,
        ]);
        $authorityPayment = $this->payment($authoritySchedule, $failedAuthority, [
            'status' => Payment::STATUS_RETRYING,
            'attempt' => 1,
            'processed_at' => now(),
        ]);

        $otherAdvisor = $this->advisor('payment-status-other@example.test');
        [, $otherAuthority, $otherSchedule] = $this->fixture($otherAdvisor, 'Other Advisor Payments Limited');
        $otherPayment = $this->payment($otherSchedule, $otherAuthority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
        ]);

        $payload = app(PaymentStatusReport::class)->forClientIds($advisor->accessibleClientIds());
        $ids = collect($payload['items'])->pluck('id')->all();

        $this->assertContains($latest->id, $ids);
        $this->assertNotContains($older->id, $ids);
        $this->assertNotContains($otherPayment->id, $ids);
        $this->assertSame(3, $payload['summary']['failed']);
        $this->assertSame(1, $payload['summary']['retrying']);
        $this->assertSame(2, $payload['summary']['retryable']);
        $this->assertSame($latest->id, $payload['items'][0]['id']);
        $this->assertNotNull($payload['items'][0]['automatic_next_retry_at']);
        $this->assertTrue($payload['items'][0]['manual_retry_available']);

        $pausedRow = collect($payload['items'])->firstWhere('id', $pausedPayment->id);
        $revokedRow = collect($payload['items'])->firstWhere('id', $revokedPayment->id);
        $authorityRow = collect($payload['items'])->firstWhere('id', $authorityPayment->id);

        $this->assertNull($pausedRow['automatic_next_retry_at']);
        $this->assertTrue($pausedRow['manual_retry_available']);
        $this->assertFalse($revokedRow['manual_retry_available']);
        $this->assertFalse($authorityRow['manual_retry_available']);
        $this->assertNoSensitivePaymentFields($payload);
    }

    public function test_dashboard_and_client_page_expose_payment_payloads_without_gateway_secrets(): void
    {
        $advisor = $this->advisor('payment-payload-advisor@example.test');
        [$client, $authority, $schedule] = $this->fixture($advisor, 'Payload Payments Limited');
        $payment = $this->payment($schedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'failed_reason' => 'Card declined by fixture gateway',
            'processed_at' => now()->subHour(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('paymentStatus.summary.failed', 1)
                ->where('paymentStatus.items.0.id', $payment->id)
                ->where('paymentStatus.items.0.drill_url', route('advisor.clients.show', [
                    'client' => $client,
                    'focus' => 'payments',
                    'highlight' => $payment->id,
                ], absolute: false))
                ->where('paymentStatus.items.0.manual_retry_available', true));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.payments.0.id', $payment->id)
                ->where('client.payments.0.retry_url', route('advisor.payments.retry', $payment, absolute: false))
                ->where('client.payments.0.contact_url', route('advisor.clients.compose', $client, absolute: false))
                ->where('client.payments.0.manual_retry_available', true));

        $this->assertNoSensitivePaymentFields(app(PaymentStatusReport::class)->forClient($client));
    }

    public function test_advisor_manual_retry_runs_one_attempt_past_cap_and_reactivates_paused_schedule(): void
    {
        Carbon::setTestNow('2026-05-25 10:00:00');
        $advisor = $this->advisor('payment-retry-advisor@example.test');
        [$client, $authority, $schedule] = $this->fixture($advisor, 'Manual Retry Limited', schedule: [
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'status' => PaymentSchedule::STATUS_PAUSED,
            'next_run_at' => now()->subDays(3),
        ]);
        $payment = $this->payment($schedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 3,
            'processed_at' => now()->subDay(),
        ]);

        $this->actingAsMfa($advisor)
            ->from(route('advisor.clients.show', $client))
            ->post(route('advisor.payments.retry', $payment))
            ->assertRedirect();

        $newPayment = Payment::query()
            ->where('payment_schedule_id', $schedule->getKey())
            ->where('attempt', 4)
            ->sole();

        $this->assertSame(Payment::STATUS_SUCCEEDED, $newPayment->status);
        $this->assertSame(2, Payment::query()->where('payment_schedule_id', $schedule->getKey())->count());
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $schedule->refresh()->status);
        $this->assertTrue($schedule->next_run_at?->greaterThan(now()));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.retry_requested',
            'subject_id' => $schedule->id,
            'actor_role' => User::TYPE_ADVISOR,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.succeeded',
            'subject_id' => $newPayment->id,
        ]);

        $retryAudit = AuditEvent::query()
            ->where('action', 'payment.retry_requested')
            ->where('subject_id', $schedule->id)
            ->firstOrFail();

        $this->assertNoSensitivePaymentFields($retryAudit->after ?? []);
    }

    public function test_retry_route_rejects_client_primary_and_out_of_scope_advisor_without_charge(): void
    {
        $advisor = $this->advisor('payment-guard-advisor@example.test');
        $clientUser = $this->clientUser('payment-guard-client@example.test');
        [$client, $authority, $schedule] = $this->fixture($advisor, 'Guard Payments Limited', clientUser: $clientUser);
        $payment = $this->payment($schedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
        ]);
        $otherAdvisor = $this->advisor('payment-guard-other@example.test');

        $this->actingAsMfa($clientUser)
            ->post(route('advisor.payments.retry', $payment))
            ->assertForbidden();

        $outOfScope = $this->actingAsMfa($otherAdvisor)
            ->post(route('advisor.payments.retry', $payment));

        $this->assertContains($outOfScope->getStatusCode(), [403, 404]);
        $this->assertSame(1, Payment::query()->where('payment_schedule_id', $schedule->getKey())->count());
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'payment.retry_requested',
            'subject_id' => $schedule->id,
        ]);
        $this->assertSame($client->id, $payment->refresh()->client_id);
    }

    public function test_retry_route_rejects_stale_and_non_retryable_payments_without_charge(): void
    {
        $advisor = $this->advisor('payment-stale-advisor@example.test');
        [, $authority, $schedule] = $this->fixture($advisor, 'Stale Tile Limited');
        $stale = $this->payment($schedule, $authority, [
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'processed_at' => now()->subDay(),
        ]);
        $latest = $this->payment($schedule, $authority, [
            'status' => Payment::STATUS_SUCCEEDED,
            'attempt' => 2,
            'processed_at' => now(),
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.payments.retry', $stale))
            ->assertUnprocessable();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.payments.retry', $latest))
            ->assertUnprocessable();

        $this->assertSame(2, Payment::query()->where('payment_schedule_id', $schedule->getKey())->count());
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'payment.retry_requested',
            'subject_id' => $schedule->id,
        ]);
    }

    public function test_retry_route_rejects_terminal_schedule_or_invalid_authority_without_charge(): void
    {
        $advisor = $this->advisor('payment-terminal-advisor@example.test');

        foreach ([PaymentSchedule::STATUS_REVOKED, PaymentSchedule::STATUS_COMPLETED] as $status) {
            [, $authority, $schedule] = $this->fixture($advisor, 'Terminal '.$status.' Limited', schedule: [
                'status' => $status,
            ]);
            $payment = $this->payment($schedule, $authority, [
                'status' => Payment::STATUS_FAILED,
                'attempt' => 1,
            ]);

            $this->actingAsMfa($advisor)
                ->post(route('advisor.payments.retry', $payment))
                ->assertUnprocessable();

            $this->assertSame(1, Payment::query()->where('payment_schedule_id', $schedule->getKey())->count());
        }

        foreach ([PaymentAuthority::STATUS_FAILED, PaymentAuthority::STATUS_REVOKED] as $status) {
            [, $authority, $schedule] = $this->fixture($advisor, 'Authority '.$status.' Limited', authority: [
                'status' => $status,
                'revoked_at' => $status === PaymentAuthority::STATUS_REVOKED ? now() : null,
            ]);
            $payment = $this->payment($schedule, $authority, [
                'status' => Payment::STATUS_FAILED,
                'attempt' => 1,
            ]);

            $this->actingAsMfa($advisor)
                ->post(route('advisor.payments.retry', $payment))
                ->assertUnprocessable();

            $this->assertSame(1, Payment::query()->where('payment_schedule_id', $schedule->getKey())->count());
        }
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientUser(string $email): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $authority
     * @param  array<string, mixed>  $schedule
     * @return array{0: Client, 1: PaymentAuthority, 2: PaymentSchedule}
     */
    private function fixture(
        User $advisor,
        string $clientName,
        ?User $clientUser = null,
        array $authority = [],
        array $schedule = [],
    ): array {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser?->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        if ($clientUser instanceof User) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $clientUser->getKey(),
                'role' => 'primary_contact',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        $feeCalculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 3000,
            'roi_ratio' => 2.5,
            'justification' => ['services' => []],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $feeCalculation->getKey(),
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => ['summary' => 'Payment surface fixture'],
            'services' => [['name' => 'Advisory fixture', 'line_total' => 10000]],
            'pv_summary' => ['fee_suggested_mid' => 10000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['phase' => 'payment_surface_fixture'],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $tokenEnvelope = app(KeyEnvelope::class)->encrypt(json_encode([
            'token' => 'tok_payment_surface_fixture',
            'customer_ref' => 'cus_payment_surface_fixture',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $paymentAuthority = PaymentAuthority::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'gateway_customer_ref' => 'cus_payment_surface_fixture',
            'gateway_token_envelope' => $tokenEnvelope,
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'authorised_by_user_id' => $clientUser?->getKey() ?? $advisor->getKey(),
            'authorised_at' => now(),
            ...$authority,
        ]);

        $paymentSchedule = PaymentSchedule::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
            'payment_authority_id' => $paymentAuthority->getKey(),
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 1200,
            'currency' => 'NZD',
            'next_run_at' => now()->subDay(),
            'status' => PaymentSchedule::STATUS_ACTIVE,
            'created_by_user_id' => $advisor->getKey(),
            ...$schedule,
        ]);

        return [$client, $paymentAuthority, $paymentSchedule];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(PaymentSchedule $schedule, PaymentAuthority $authority, array $attributes): Payment
    {
        return Payment::query()->create([
            'client_id' => $schedule->client_id,
            'payment_schedule_id' => $schedule->getKey(),
            'payment_authority_id' => $authority->getKey(),
            'amount' => $schedule->amount,
            'currency' => $schedule->currency,
            'status' => Payment::STATUS_FAILED,
            'attempt' => 1,
            'failed_reason' => 'Fixture failure',
            'processed_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoSensitivePaymentFields(array $payload): void
    {
        $encoded = strtolower(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        foreach ([
            'gateway_token_envelope',
            'gateway_customer_ref',
            'gateway_ref',
            'webhook_secret',
            'api_key',
            'card_number',
            '"pan"',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $encoded);
        }
    }
}
