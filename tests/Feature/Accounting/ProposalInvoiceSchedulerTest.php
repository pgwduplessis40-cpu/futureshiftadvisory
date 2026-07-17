<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\AccountingConnection;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceBatch;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\PracticeAccountingConnection;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Accounting\ProposalInvoiceScheduler;
use App\Services\Payments\ClientBillingCode;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProposalInvoiceSchedulerTest extends TestCase
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
        Config::set('integrations.accounting.xero.invoice_status', 'DRAFT');
        Config::set('integrations.accounting.xero.sales_account_code', '200');
        Config::set('integrations.accounting.xero.sales_tax_type', 'OUTPUT2');
        Config::set('integrations.retry.attempts', 1);
    }

    public function test_signed_proposal_invoice_payload_uses_client_billing_code_for_xero_reference(): void
    {
        [$advisor, $client, $proposal] = $this->signedProposal();
        $billingCodes = app(ClientBillingCode::class);
        $clientCode = $billingCodes->shortCode($client);
        $this->practiceXeroConnection($advisor);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/Contacts' => Http::response([
                'Contacts' => [[
                    'ContactID' => 'xero-contact-fixture',
                    'ContactNumber' => $billingCodes->xeroContactNumber($client),
                ]],
            ], 200),
            'https://api.xero.com/api.xro/2.0/Invoices' => Http::response([
                'Invoices' => [[
                    'InvoiceID' => 'xero-invoice-fixture',
                    'InvoiceNumber' => 'INV-0001',
                    'Status' => 'DRAFT',
                ]],
            ], 200),
        ]);

        $batch = app(ProposalInvoiceScheduler::class)->sync($proposal, $advisor);

        $this->assertInstanceOf(AccountingInvoiceBatch::class, $batch);
        $this->assertSame(AccountingInvoiceBatch::STATUS_CREATED, $batch->refresh()->status);
        $this->assertSame($clientCode, $client->refresh()->billing_code);

        /** @var AccountingInvoice $invoice */
        $invoice = AccountingInvoice::query()->where('proposal_id', $proposal->getKey())->sole();
        $this->assertStringStartsWith($clientCode.' Proposal v1', (string) data_get($invoice->payload, 'Reference'));

        Http::assertSent(function (Request $request) use ($billingCodes, $client): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Contacts'
                && data_get($request->data(), 'Contacts.0.ContactNumber') === $billingCodes->xeroContactNumber($client);
        });

        Http::assertSent(function (Request $request) use ($clientCode): bool {
            return $request->url() === 'https://api.xero.com/api.xro/2.0/Invoices'
                && str_starts_with((string) data_get($request->data(), 'Invoices.0.Reference'), $clientCode.' Proposal v1');
        });
    }

    /**
     * @return array{0: User, 1: Client, 2: Proposal}
     */
    private function signedProposal(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'billing-code-client@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000000',
            'legal_name' => 'Billing Code Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $feeCalculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 1000,
            'suggested_mid' => 1200,
            'suggested_high' => 1400,
            'improvement_pv_total' => 4000,
            'risk_cost_pv_total' => 500,
            'roi_ratio' => 3.0,
            'justification' => ['retainer' => ['monthly_fee' => 1000, 'months' => 1]],
        ]);

        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $feeCalculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['term_months' => 1],
            'services' => [['name' => 'Billing code advisory', 'line_total' => 1000]],
            'pv_summary' => ['monthly_retainer_fee' => 1000],
            'roi_ratio' => 3.0,
            'acceptance_terms' => ['fixture' => true],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $clientUser): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();

            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now(),
                'signed_by_user_id' => $clientUser->getKey(),
            ])->save();
        });

        return [$advisor, $client, $proposal->refresh()];
    }

    private function practiceXeroConnection(User $advisor): PracticeAccountingConnection
    {
        $token = [
            'access_token' => 'xero-access-token',
            'refresh_token' => 'xero-refresh-token',
            'expires_at' => now()->addHour()->toIso8601String(),
        ];
        $envelope = app(KeyEnvelope::class)->encrypt(json_encode($token, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return PracticeAccountingConnection::query()->create([
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'xero-practice-tenant',
            'external_tenant_name' => 'Future Shift Advisory Limited',
            'external_tenant_type' => 'ORGANISATION',
            'status' => PracticeAccountingConnection::STATUS_CONNECTED,
            'token_envelope' => $envelope,
            'token_envelope_meta' => app(KeyEnvelope::class)->inspect($envelope),
            'scopes' => ['accounting.invoices', 'accounting.contacts', 'offline_access'],
            'connected_by_user_id' => $advisor->getKey(),
            'connected_at' => now(),
        ]);
    }
}
