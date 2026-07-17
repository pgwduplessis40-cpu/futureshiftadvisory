<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ProposalStatus;
use App\Models\AccountingConnection;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceBatch;
use App\Models\Client;
use App\Models\PaymentSchedule;
use App\Models\PracticeAccountingConnection;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\Xero\LiveXeroClient;
use App\Services\Payments\ClientBillingCode;
use App\Services\Payments\GstCalculator;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProposalInvoiceScheduler
{
    public function __construct(
        private readonly PracticeAccountingConnector $practiceConnector,
        private readonly LiveXeroClient $xero,
        private readonly GstCalculator $gst,
        private readonly ClientBillingCode $billingCodes,
        private readonly AuditWriter $audit,
        private readonly IntegrationActivationResolver $activations,
        private readonly RequestContext $requestContext,
    ) {}

    public function sync(Proposal $proposal, ?User $actor = null): ?AccountingInvoiceBatch
    {
        return $this->requestContext->withSystemContext(fn (): ?AccountingInvoiceBatch => $this->syncInSystemContext($proposal, $actor));
    }

    private function syncInSystemContext(Proposal $proposal, ?User $actor = null): ?AccountingInvoiceBatch
    {
        $proposal = $proposal->refresh()->loadMissing(['client.primaryContact', 'feeCalculation', 'paymentSchedules']);

        if ($proposal->status !== ProposalStatus::Signed) {
            return null;
        }

        if (! $this->activations->isLive(AccountingConnection::PROVIDER_XERO)) {
            return null;
        }

        $connection = $this->practiceConnector->active(AccountingConnection::PROVIDER_XERO);
        if (! $connection instanceof PracticeAccountingConnection || ! $connection->connected()) {
            $this->audit->record('accounting_invoice_batch.skipped', subject: $proposal, actor: $actor, after: [
                'reason' => 'practice_xero_not_connected',
            ]);

            return null;
        }

        try {
            $batch = $this->prepareBatch($proposal, $connection, $actor);

            if ($batch->status === AccountingInvoiceBatch::STATUS_CREATED) {
                return $batch;
            }

            return $this->pushBatchToXero($batch->refresh()->load(['proposal.client.primaryContact', 'invoices']), $connection, $actor);
        } catch (Throwable $e) {
            Log::warning('Xero invoice sync failed after proposal acceptance.', [
                'proposal_id' => $proposal->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->audit->record('accounting_invoice_batch.failed', subject: $proposal, actor: $actor, after: [
                'provider' => AccountingConnection::PROVIDER_XERO,
                'message' => $e->getMessage(),
            ]);

            return AccountingInvoiceBatch::query()
                ->where('proposal_id', $proposal->getKey())
                ->where('provider', AccountingConnection::PROVIDER_XERO)
                ->first();
        }
    }

    private function prepareBatch(Proposal $proposal, PracticeAccountingConnection $connection, ?User $actor): AccountingInvoiceBatch
    {
        $schedule = $this->paymentSchedule($proposal);
        $termMonths = $this->proposalTermMonths($proposal);
        $monthlyAmount = $schedule instanceof PaymentSchedule
            ? (float) $schedule->amount
            : $this->proposalMonthlyAmount($proposal, $termMonths);
        $gstRate = $this->gst->ratePercent();
        $firstInvoiceDate = $this->firstInvoiceDate($proposal, $schedule);

        return DB::transaction(function () use ($proposal, $connection, $actor, $schedule, $termMonths, $monthlyAmount, $gstRate, $firstInvoiceDate): AccountingInvoiceBatch {
            /** @var AccountingInvoiceBatch $batch */
            $batch = AccountingInvoiceBatch::query()->firstOrNew([
                'proposal_id' => $proposal->getKey(),
                'provider' => AccountingConnection::PROVIDER_XERO,
            ]);

            if ($batch->exists && $batch->status === AccountingInvoiceBatch::STATUS_CREATED) {
                return $batch;
            }

            $batch->forceFill([
                'client_id' => $proposal->client_id,
                'payment_schedule_id' => $schedule?->getKey(),
                'practice_accounting_connection_id' => $connection->getKey(),
                'external_tenant_id' => $connection->external_tenant_id,
                'status' => AccountingInvoiceBatch::STATUS_PENDING,
                'term_months' => $termMonths,
                'monthly_amount' => number_format($monthlyAmount, 2, '.', ''),
                'gst_rate_percent' => number_format($gstRate, 2, '.', ''),
                'currency' => 'NZD',
                'started_at' => $batch->started_at ?? now(),
                'error_message' => null,
                'created_by_user_id' => $batch->created_by_user_id ?? $actor?->getKey(),
            ])->save();

            for ($sequence = 1; $sequence <= $termMonths; $sequence++) {
                $invoiceDate = $firstInvoiceDate->addMonthsNoOverflow($sequence - 1);
                $amountExGst = number_format($monthlyAmount, 2, '.', '');
                $gstAmount = $this->gst->gstFromExclusive($amountExGst);
                $amountIncGst = $this->gst->grossFromExclusive($amountExGst);

                AccountingInvoice::query()->updateOrCreate(
                    [
                        'accounting_invoice_batch_id' => $batch->getKey(),
                        'sequence' => $sequence,
                    ],
                    [
                        'client_id' => $proposal->client_id,
                        'proposal_id' => $proposal->getKey(),
                        'provider' => AccountingConnection::PROVIDER_XERO,
                        'invoice_date' => $invoiceDate->toDateString(),
                        'due_date' => $invoiceDate->copy()->addDays($this->invoiceDueDays())->toDateString(),
                        'amount_ex_gst' => $amountExGst,
                        'gst_amount' => $gstAmount,
                        'amount_inc_gst' => $amountIncGst,
                        'status' => AccountingInvoice::STATUS_PENDING,
                        'error_message' => null,
                    ],
                );
            }

            $this->audit->record('accounting_invoice_batch.prepared', subject: $batch, actor: $actor, after: [
                'proposal_id' => $proposal->getKey(),
                'provider' => AccountingConnection::PROVIDER_XERO,
                'term_months' => $termMonths,
                'monthly_amount' => number_format($monthlyAmount, 2, '.', ''),
            ]);

            return $batch->refresh();
        });
    }

    private function pushBatchToXero(AccountingInvoiceBatch $batch, PracticeAccountingConnection $connection, ?User $actor): AccountingInvoiceBatch
    {
        $tenantId = (string) $connection->external_tenant_id;
        if ($tenantId === '') {
            throw new \InvalidArgumentException('Practice Xero connection is missing a tenant id.');
        }

        $token = $this->practiceConnector->freshToken($connection);
        $contactId = $this->contactId($batch, $token, $tenantId);
        $created = 0;
        $failed = 0;

        /** @var AccountingInvoice $invoice */
        foreach ($batch->invoices()->whereNull('external_invoice_id')->orderBy('sequence')->get() as $invoice) {
            $payload = $this->invoicePayload($batch, $invoice, $contactId);

            try {
                $response = $this->xero->createInvoice($token, $tenantId, $payload);
                $xeroInvoice = $this->firstXeroRow($response, 'Invoices');
                $status = $this->invoiceStatus((string) ($xeroInvoice['Status'] ?? Config::get('integrations.accounting.xero.invoice_status', 'DRAFT')));

                $invoice->forceFill([
                    'external_contact_id' => $contactId,
                    'external_invoice_id' => $xeroInvoice['InvoiceID'] ?? null,
                    'external_invoice_number' => $xeroInvoice['InvoiceNumber'] ?? null,
                    'status' => $status,
                    'payload' => $payload,
                    'response' => $response,
                    'synced_at' => now(),
                    'error_message' => null,
                ])->save();

                $created++;
            } catch (Throwable $e) {
                $invoice->forceFill([
                    'external_contact_id' => $contactId,
                    'payload' => $payload,
                    'status' => AccountingInvoice::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ])->save();

                $failed++;
            }
        }

        $totalCreated = $batch->invoices()->whereNotNull('external_invoice_id')->count();
        $status = $failed > 0
            ? ($totalCreated > 0 ? AccountingInvoiceBatch::STATUS_PARTIAL : AccountingInvoiceBatch::STATUS_FAILED)
            : AccountingInvoiceBatch::STATUS_CREATED;

        $batch->forceFill([
            'status' => $status,
            'completed_at' => $status === AccountingInvoiceBatch::STATUS_CREATED ? now() : null,
            'error_message' => $failed > 0 ? "{$failed} Xero invoice(s) failed to create." : null,
        ])->save();

        $connection->forceFill(['last_invoice_sync_at' => now()])->save();

        $this->audit->record('accounting_invoice_batch.synced', subject: $batch, actor: $actor, after: [
            'provider' => AccountingConnection::PROVIDER_XERO,
            'created' => $created,
            'failed' => $failed,
            'status' => $status,
        ]);

        return $batch->refresh();
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function contactId(AccountingInvoiceBatch $batch, array $token, string $tenantId): string
    {
        $existing = AccountingInvoice::query()
            ->where('client_id', $batch->client_id)
            ->where('provider', AccountingConnection::PROVIDER_XERO)
            ->whereNotNull('external_contact_id')
            ->latest('synced_at')
            ->value('external_contact_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $client = $batch->proposal?->client;
        if (! $client instanceof Client) {
            throw new \InvalidArgumentException('Proposal client is required before creating a Xero contact.');
        }

        $response = $this->xero->createContact($token, $tenantId, $this->contactPayload($client));
        $contact = $this->firstXeroRow($response, 'Contacts');
        $contactId = (string) ($contact['ContactID'] ?? '');

        if ($contactId === '') {
            throw new \InvalidArgumentException('Xero did not return a contact id.');
        }

        return $contactId;
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(Client $client): array
    {
        $client->loadMissing('primaryContact');

        $payload = [
            'Name' => mb_substr($client->legal_name ?: $client->trading_name ?: 'Future Shift client', 0, 255),
            'ContactNumber' => $this->billingCodes->xeroContactNumber($client),
        ];

        $email = $client->primaryContact?->email;
        if (is_string($email) && $email !== '') {
            $payload['EmailAddress'] = $email;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(AccountingInvoiceBatch $batch, AccountingInvoice $invoice, string $contactId): array
    {
        $proposal = $batch->proposal;
        $clientName = $batch->proposal?->client?->legal_name ?? 'Client';
        $clientCode = $batch->proposal?->client instanceof Client
            ? $this->billingCodes->shortCode($batch->proposal->client)
            : $this->billingCodes->shortCode((string) $batch->client_id);
        $reference = sprintf(
            '%s Proposal v%s %s/%s %s',
            $clientCode,
            $proposal?->version ?? 1,
            $invoice->sequence,
            $batch->term_months,
            substr((string) $batch->proposal_id, 0, 8),
        );

        return [
            'Type' => 'ACCREC',
            'Contact' => ['ContactID' => $contactId],
            'Date' => $invoice->invoice_date?->toDateString(),
            'DueDate' => $invoice->due_date?->toDateString(),
            'Reference' => $reference,
            'Status' => $this->xeroInvoiceStatus(),
            'LineAmountTypes' => 'Exclusive',
            'LineItems' => [[
                'Description' => sprintf(
                    'Future Shift Advisory services for %s - month %s of %s',
                    $clientName,
                    $invoice->sequence,
                    $batch->term_months,
                ),
                'Quantity' => 1,
                'UnitAmount' => (float) $invoice->amount_ex_gst,
                'AccountCode' => (string) Config::get('integrations.accounting.xero.sales_account_code', '200'),
                'TaxType' => (string) Config::get('integrations.accounting.xero.sales_tax_type', 'OUTPUT2'),
            ]],
        ];
    }

    private function paymentSchedule(Proposal $proposal): ?PaymentSchedule
    {
        return PaymentSchedule::query()
            ->where('proposal_id', $proposal->getKey())
            ->whereIn('status', [PaymentSchedule::STATUS_ACTIVE, PaymentSchedule::STATUS_PAUSED])
            ->latest('created_at')
            ->first();
    }

    private function firstInvoiceDate(Proposal $proposal, ?PaymentSchedule $schedule): CarbonImmutable
    {
        if ($schedule instanceof PaymentSchedule && $schedule->next_run_at !== null) {
            return CarbonImmutable::parse($schedule->next_run_at)->startOfDay();
        }

        $signedAt = $proposal->signed_at instanceof Carbon ? $proposal->signed_at : now();

        return CarbonImmutable::parse($signedAt)->startOfDay();
    }

    private function proposalTermMonths(Proposal $proposal): int
    {
        $months = data_get($proposal->scope, 'term_months')
            ?? data_get($proposal->acceptance_terms, 'term_months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer.months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer_months');

        return max(1, (int) (is_numeric($months) ? $months : 6));
    }

    private function proposalMonthlyAmount(Proposal $proposal, int $termMonths): float
    {
        $monthly = data_get($proposal->feeCalculation?->justification, 'retainer.monthly_fee')
            ?? data_get($proposal->feeCalculation?->justification, 'monthly_retainer_fee')
            ?? data_get($proposal->pv_summary, 'monthly_retainer_fee');

        if (is_numeric($monthly) && (float) $monthly > 0) {
            return round((float) $monthly, 2);
        }

        $total = $proposal->feeCalculation?->suggested_mid ?? data_get($proposal->pv_summary, 'fee_suggested_mid', 0);

        return round(((float) $total) / max(1, $termMonths), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function firstXeroRow(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? null;
        $first = is_array($rows) ? reset($rows) : null;

        if (! is_array($first)) {
            throw new \InvalidArgumentException("Xero did not return {$key}.");
        }

        return $first;
    }

    private function invoiceStatus(string $status): string
    {
        $status = strtolower($status);

        return match ($status) {
            'authorised' => AccountingInvoice::STATUS_AUTHORISED,
            'draft', 'submitted' => AccountingInvoice::STATUS_DRAFT,
            default => AccountingInvoice::STATUS_DRAFT,
        };
    }

    private function xeroInvoiceStatus(): string
    {
        $status = strtoupper((string) Config::get('integrations.accounting.xero.invoice_status', 'DRAFT'));

        return in_array($status, ['DRAFT', 'SUBMITTED', 'AUTHORISED'], true) ? $status : 'DRAFT';
    }

    private function invoiceDueDays(): int
    {
        return max(0, (int) Config::get('integrations.accounting.xero.invoice_due_days', 0));
    }
}
