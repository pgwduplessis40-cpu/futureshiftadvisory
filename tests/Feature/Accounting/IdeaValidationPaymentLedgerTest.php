<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\PaymentAccountingSync;
use App\Models\PaymentRefund;
use App\Models\PracticeAccountingConnection;
use App\Models\ServiceActivation;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Accounting\IdeaValidationPaymentLedger;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IdeaValidationPaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Config::set('integrations.accounting.xero.live', true);
        Config::set('integrations.accounting.xero.client_id', 'xero-test-client');
        Config::set('integrations.accounting.xero.client_secret', 'xero-test-secret');
        Config::set('integrations.accounting.xero.sales_account_code', '200');
        Config::set('integrations.accounting.xero.sales_tax_type', 'OUTPUT2');
        Config::set('integrations.accounting.xero.stripe_clearing_account_code', '090');
        Config::set('integrations.retry.attempts', 1);
    }

    public function test_settled_idea_validation_payment_creates_one_gst_correct_xero_invoice_and_receipt(): void
    {
        [$actor, $payment] = $this->settledPayment();
        $this->practiceXeroConnection($actor);
        $this->fakeSaleResponses();

        $sync = app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $this->assertInstanceOf(PaymentAccountingSync::class, $sync);
        $this->assertSame(PaymentAccountingSync::STATUS_SYNCED, $sync->status);
        $this->assertSame('xero-contact-idea', $sync->external_contact_id);
        $this->assertSame('xero-invoice-idea', $sync->external_invoice_id);
        $this->assertSame('xero-payment-idea', $sync->external_payment_id);
        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Invoices'
                && (float) data_get($request->data(), 'Invoices.0.LineItems.0.UnitAmount') === 100.0
                && data_get($request->data(), 'Invoices.0.LineItems.0.TaxType') === 'OUTPUT2'
                && data_get($request->data(), 'Invoices.0.Status') === 'AUTHORISED';
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Payments'
                && (float) data_get($request->data(), 'Payments.0.Amount') === 115.0
                && data_get($request->data(), 'Payments.0.Account.Code') === '090';
        });

        app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $this->assertSame(1, PaymentAccountingSync::query()->count());
        Http::assertSentCount(3);
    }

    public function test_missing_xero_connection_remains_visible_and_can_be_retried_without_duplicate_payment(): void
    {
        [$actor, $payment] = $this->settledPayment();

        $failed = app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $this->assertInstanceOf(PaymentAccountingSync::class, $failed);
        $this->assertSame(PaymentAccountingSync::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('not connected', (string) $failed->error_message);
        $this->assertCount(1, app(IdeaValidationPaymentLedger::class)->outstanding());

        $this->practiceXeroConnection($actor);
        $this->fakeSaleResponses();

        $retried = app(IdeaValidationPaymentLedger::class)->retry($failed, $actor);

        $this->assertSame(PaymentAccountingSync::STATUS_SYNCED, $retried->status);
        $this->assertSame(1, PaymentAccountingSync::query()->count());
        Http::assertSentCount(3);
    }

    public function test_accepted_stripe_refund_creates_one_linked_xero_credit_note_and_refund_payment(): void
    {
        [$actor, $payment, $client, $buyer] = $this->settledPayment();
        $this->practiceXeroConnection($actor);
        $this->fakeSaleResponses(withRefund: true);
        app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'advisor_id' => $actor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Idea Validation',
            'status' => ServiceActivation::STATUS_ACTIVE,
        ]);
        $refund = PaymentRefund::query()->create([
            'client_id' => $client->getKey(),
            'service_activation_id' => $activation->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'gateway' => 'stripe',
            'payment_reference' => $payment->gateway_ref,
            'gateway_ref' => 're_idea_refund',
            'amount' => '115.00',
            'currency' => 'NZD',
            'status' => PaymentRefund::STATUS_ACCEPTED,
            'idempotency_key' => 'idea-ledger-refund-'.$payment->getKey(),
            'processed_at' => now(),
            'metadata' => ['provider_status' => 'succeeded'],
        ]);
        $sync = app(IdeaValidationPaymentLedger::class)->recordAcceptedRefund($refund, $actor);

        $this->assertInstanceOf(PaymentAccountingSync::class, $sync);
        $this->assertSame(PaymentAccountingSync::REFUND_SYNCED, $sync->refund_status);
        $this->assertSame('xero-credit-idea', $sync->external_credit_note_id);
        $this->assertSame('xero-credit-refund-payment-idea', $sync->external_credit_note_payment_id);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/CreditNotes'
                && data_get($request->data(), 'CreditNotes.0.Type') === 'ACCRECCREDIT'
                && (float) data_get($request->data(), 'CreditNotes.0.LineItems.0.UnitAmount') === 100.0;
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Payments'
                && data_get($request->data(), 'Payments.0.CreditNote.CreditNoteID') === 'xero-credit-idea'
                && data_get($request->data(), 'Payments.0.Account.Code') === '090'
                && (float) data_get($request->data(), 'Payments.0.Amount') === 115.0;
        });
    }

    public function test_retry_refunds_an_existing_xero_credit_note_without_recreating_the_paid_invoice(): void
    {
        [$actor, $payment, $client, $buyer] = $this->settledPayment();
        $this->practiceXeroConnection($actor);
        $this->fakeSaleResponses(withRefund: true);
        $sync = app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'advisor_id' => $actor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Idea Validation',
            'status' => ServiceActivation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $refund = PaymentRefund::query()->create([
            'client_id' => $client->getKey(),
            'service_activation_id' => $activation->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'gateway' => 'stripe',
            'payment_reference' => $payment->gateway_ref,
            'gateway_ref' => 're_idea_existing_credit_note',
            'amount' => '115.00',
            'currency' => 'NZD',
            'status' => PaymentRefund::STATUS_ACCEPTED,
            'idempotency_key' => 'idea-ledger-existing-credit-note-'.$payment->getKey(),
            'processed_at' => now(),
            'metadata' => ['provider_status' => 'succeeded'],
        ]);
        $sync->forceFill([
            'payment_refund_id' => $refund->getKey(),
            'refund_status' => PaymentAccountingSync::REFUND_FAILED,
            'refund_error_message' => 'Xero credit-note allocation failed.',
            'external_credit_note_id' => 'xero-existing-credit-note',
            'external_credit_note_number' => 'CN-EXISTING',
        ])->save();
        $retried = app(IdeaValidationPaymentLedger::class)->retry($sync, $actor);

        $this->assertSame(PaymentAccountingSync::STATUS_SYNCED, $retried->status);
        $this->assertSame(PaymentAccountingSync::REFUND_SYNCED, $retried->refund_status);
        $this->assertSame('xero-credit-refund-payment-idea', $retried->external_credit_note_payment_id);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Payments'
                && data_get($request->data(), 'Payments.0.CreditNote.CreditNoteID') === 'xero-existing-credit-note'
                && data_get($request->data(), 'Payments.0.Account.Code') === '090';
        });
        Http::assertSentCount(4);
    }

    public function test_historical_backfill_links_a_preexisting_accepted_refund_to_the_same_xero_reversal(): void
    {
        [$actor, $payment, $client, $buyer] = $this->settledPayment();
        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'advisor_id' => $actor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Idea Validation',
            'status' => ServiceActivation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $refund = PaymentRefund::query()->create([
            'client_id' => $client->getKey(),
            'service_activation_id' => $activation->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'gateway' => 'stripe',
            'payment_reference' => $payment->gateway_ref,
            'gateway_ref' => 're_idea_historical_refund',
            'amount' => '115.00',
            'currency' => 'NZD',
            'status' => PaymentRefund::STATUS_ACCEPTED,
            'idempotency_key' => 'idea-ledger-historical-refund-'.$payment->getKey(),
            'processed_at' => now(),
            'metadata' => ['provider_status' => 'succeeded'],
        ]);
        $this->practiceXeroConnection($actor);
        $this->fakeSaleResponses(withRefund: true);

        $sync = app(IdeaValidationPaymentLedger::class)->recordSucceededPayment($payment, $actor);

        $this->assertInstanceOf(PaymentAccountingSync::class, $sync);
        $this->assertSame((string) $refund->getKey(), (string) $sync->payment_refund_id);
        $this->assertSame(PaymentAccountingSync::STATUS_SYNCED, $sync->status);
        $this->assertSame(PaymentAccountingSync::REFUND_SYNCED, $sync->refund_status);
        Http::assertSentCount(5);
    }

    /** @return array{0:User,1:Payment,2:Client,3:User} */
    private function settledPayment(): array
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $buyer = User::factory()->create([
            'name' => 'Adele Customer',
            'email' => 'adele@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $buyer->assignRole(User::TYPE_ENTREPRENEUR);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
            'status' => ClientStatus::ACTIVE,
            'legal_name' => $buyer->name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['source' => 'idea-payment-ledger-test'],
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $buyer->getKey(),
        ]);
        $terms = TermsVersion::query()->create([
            'document_scope' => TermsVersion::SCOPE_WEBSITE,
            'version' => 'idea-payment-ledger-terms-v1',
            'title' => 'Idea payment ledger terms',
            'material' => true,
            'published_at' => now()->subMinute(),
            'notice_period_days' => 30,
        ]);
        $payment = Payment::query()->create([
            'client_id' => $client->getKey(),
            'payment_schedule_id' => null,
            'amount' => '115.00',
            'currency' => 'NZD',
            'gateway' => 'stripe',
            'gateway_ref' => 'pi_idea_payment_ledger',
            'idempotency_key' => 'idea-payment-ledger-'.$buyer->getKey(),
            'status' => Payment::STATUS_SUCCEEDED,
            'attempt' => 1,
            'processed_at' => now(),
        ]);
        $purchase = new IdeaValidationPurchase;
        $purchase->forceFill([
            'user_id' => $buyer->getKey(),
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'terms_version_id' => $terms->getKey(),
            'payment_id' => $payment->getKey(),
            'status' => IdeaValidationPurchase::STATUS_PAID,
            'amount_ex_gst' => '100.00',
            'gst_amount' => '15.00',
            'amount_including_gst' => '115.00',
            'currency' => 'NZD',
            'stripe_payment_intent_ref' => $payment->gateway_ref,
            'paid_at' => now(),
        ])->save();

        return [$advisor, $payment, $client, $buyer];
    }

    private function practiceXeroConnection(User $actor): void
    {
        $envelope = app(KeyEnvelope::class)->encrypt(json_encode([
            'access_token' => 'xero-access-token',
            'expires_at' => now()->addHour()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        PracticeAccountingConnection::query()->create([
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'xero-tenant-idea',
            'status' => PracticeAccountingConnection::STATUS_CONNECTED,
            'token_envelope' => $envelope,
            'token_envelope_meta' => app(KeyEnvelope::class)->inspect($envelope),
            'scopes' => ['accounting.contacts', 'accounting.invoices', 'accounting.payments', 'offline_access'],
            'connected_by_user_id' => $actor->getKey(),
            'connected_at' => now(),
        ]);
    }

    private function fakeSaleResponses(bool $withRefund = false): void
    {
        $responses = [
            'https://api.xero.com/api.xro/2.0/Contacts' => Http::response([
                'Contacts' => [['ContactID' => 'xero-contact-idea']],
            ], 200),
            'https://api.xero.com/api.xro/2.0/Invoices' => Http::response([
                'Invoices' => [[
                    'InvoiceID' => 'xero-invoice-idea',
                    'InvoiceNumber' => 'INV-IDEA-0001',
                    'Status' => 'AUTHORISED',
                ]],
            ], 200),
            'https://api.xero.com/api.xro/2.0/Payments' => $withRefund
                ? Http::sequence()
                    ->push(['Payments' => [['PaymentID' => 'xero-payment-idea']]], 200)
                    ->push(['Payments' => [['PaymentID' => 'xero-credit-refund-payment-idea']]], 200)
                : Http::response([
                    'Payments' => [['PaymentID' => 'xero-payment-idea']],
                ], 200),
        ];
        if ($withRefund) {
            $responses['https://api.xero.com/api.xro/2.0/CreditNotes'] = Http::response([
                'CreditNotes' => [[
                    'CreditNoteID' => 'xero-credit-idea',
                    'CreditNoteNumber' => 'CN-0001',
                ]],
            ], 200);
        }

        Http::fake($responses);
    }
}
