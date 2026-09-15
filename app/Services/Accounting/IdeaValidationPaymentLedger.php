<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\PaymentAccountingSync;
use App\Models\PaymentRefund;
use App\Models\PracticeAccountingConnection;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Xero\LiveXeroClient;
use App\Services\Payments\ClientBillingCode;
use App\Support\RequestContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writes an auditable Xero sales ledger for settled direct-checkout payments.
 *
 * Historical price snapshots are always copied from the purchase; this service
 * never derives an accounting value from the current service-rate settings.
 *
 * The class name is retained for existing reconciliation routes. It also
 * handles the authenticated Business Plan & Budget add-on checkout.
 */
final class IdeaValidationPaymentLedger
{
    public function __construct(
        private readonly PracticeAccountingConnector $connections,
        private readonly LiveXeroClient $xero,
        private readonly ClientBillingCode $billingCodes,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function recordSucceededPayment(Payment $payment, ?User $actor = null): ?PaymentAccountingSync
    {
        return $this->context->withSystemContext(function () use ($payment, $actor): ?PaymentAccountingSync {
            $payment = Payment::query()
                ->with([
                    'ideaValidationPurchase.user',
                    'ideaValidationPurchase.client.primaryContact',
                    'entrepreneurPlanBudgetPurchase.user',
                    'entrepreneurPlanBudgetPurchase.client.primaryContact',
                ])
                ->whereKey($payment->getKey())
                ->first();
            if (! $payment instanceof Payment || $payment->status !== Payment::STATUS_SUCCEEDED) {
                return null;
            }

            $purchase = $payment->ideaValidationPurchase ?? $payment->entrepreneurPlanBudgetPurchase;
            if ((! $purchase instanceof IdeaValidationPurchase && ! $purchase instanceof EntrepreneurPlanBudgetPurchase) || $purchase->paid_at === null) {
                return null;
            }

            $sync = $this->syncRecord($payment, $purchase);
            if (! $this->quoteMatchesPayment($purchase, $payment)) {
                return $this->markPaymentFailure(
                    $sync,
                    'The stored historical quote does not match the settled Stripe payment. Reconcile the price before creating a Xero invoice.',
                );
            }

            $acceptedRefund = $this->acceptedRefundFor($payment);
            if ($acceptedRefund instanceof PaymentRefund) {
                $sync = $this->attachAcceptedRefund($sync, $acceptedRefund);
            }

            return $this->synchronise($sync, $actor);
        });
    }

    public function recordSucceededPaymentById(string $paymentId, ?User $actor = null): ?PaymentAccountingSync
    {
        return $this->context->withSystemContext(function () use ($paymentId, $actor): ?PaymentAccountingSync {
            $payment = Payment::query()->whereKey($paymentId)->first();

            return $payment instanceof Payment
                ? $this->recordSucceededPayment($payment, $actor)
                : null;
        });
    }

    public function recordAcceptedRefund(PaymentRefund $refund, ?User $actor = null): ?PaymentAccountingSync
    {
        return $this->context->withSystemContext(function () use ($refund, $actor): ?PaymentAccountingSync {
            $refund = PaymentRefund::query()->whereKey($refund->getKey())->first();
            if (! $refund instanceof PaymentRefund || $refund->status !== PaymentRefund::STATUS_ACCEPTED) {
                return null;
            }

            $payment = Payment::query()
                ->where('gateway', 'stripe')
                ->where('gateway_ref', $refund->payment_reference)
                ->first();
            if (! $payment instanceof Payment) {
                $this->audit->record('payment_accounting_sync.refund_unmatched', subject: $refund, actor: $actor, after: [
                    'payment_reference' => $refund->payment_reference,
                    'refund_reference' => $refund->gateway_ref,
                ]);

                return null;
            }

            $sync = $this->recordSucceededPayment($payment, $actor);
            if (! $sync instanceof PaymentAccountingSync) {
                return null;
            }

            return $this->synchronise($this->attachAcceptedRefund($sync, $refund), $actor);
        });
    }

    /** @return Collection<int, PaymentAccountingSync> */
    public function outstanding(): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => PaymentAccountingSync::query()
            ->with(['payment.ideaValidationPurchase.user', 'payment.entrepreneurPlanBudgetPurchase.user', 'paymentRefund'])
            ->where(function ($query): void {
                $query->whereIn('status', [PaymentAccountingSync::STATUS_PENDING, PaymentAccountingSync::STATUS_FAILED])
                    ->orWhereIn('refund_status', [PaymentAccountingSync::REFUND_PENDING, PaymentAccountingSync::REFUND_FAILED]);
            })
            ->orderBy('created_at')
            ->get());
    }

    /** @return Collection<int, Payment> */
    public function backfillCandidates(): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => Payment::query()
            ->with(['ideaValidationPurchase.user', 'entrepreneurPlanBudgetPurchase.user'])
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->where('gateway', 'stripe')
            ->where(function ($query): void {
                $query->whereHas('ideaValidationPurchase', fn ($purchase) => $purchase->whereNotNull('paid_at'))
                    ->orWhereHas('entrepreneurPlanBudgetPurchase', fn ($purchase) => $purchase->whereNotNull('paid_at'));
            })
            ->whereDoesntHave('accountingSync')
            ->orderBy('processed_at')
            ->get()
            ->filter(function (Payment $payment): bool {
                $purchase = $payment->ideaValidationPurchase ?? $payment->entrepreneurPlanBudgetPurchase;

                return ($purchase instanceof IdeaValidationPurchase || $purchase instanceof EntrepreneurPlanBudgetPurchase)
                    && $this->quoteMatchesPayment($purchase, $payment);
            })
            ->values());
    }

    /** @return Collection<int, PaymentRefund> */
    public function refundExceptions(): Collection
    {
        return $this->context->withSystemContext(fn (): Collection => PaymentRefund::query()
            ->with('requestedBy')
            ->whereHas('serviceActivation', fn ($query) => $query->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR))
            ->where(function ($query): void {
                $query->whereIn('status', [PaymentRefund::STATUS_PROCESSING, PaymentRefund::STATUS_FAILED])
                    ->orWhere(function ($accepted): void {
                        $accepted->where('status', PaymentRefund::STATUS_ACCEPTED)
                            ->whereDoesntHave('accountingSync');
                    });
            })
            ->orderBy('created_at')
            ->get());
    }

    public function backfill(Payment $payment, User $actor): PaymentAccountingSync
    {
        $sync = $this->recordSucceededPayment($payment, $actor);
        if (! $sync instanceof PaymentAccountingSync) {
            throw ValidationException::withMessages([
                'payment' => 'This payment is not a settled direct-checkout payment that can be exported to Xero.',
            ]);
        }

        return $sync;
    }

    public function retry(PaymentAccountingSync $sync, User $actor): PaymentAccountingSync
    {
        return $this->context->withSystemContext(function () use ($sync, $actor): PaymentAccountingSync {
            $sync = PaymentAccountingSync::query()->whereKey($sync->getKey())->firstOrFail();

            return $this->synchronise($sync, $actor);
        });
    }

    private function syncRecord(Payment $payment, IdeaValidationPurchase|EntrepreneurPlanBudgetPurchase $purchase): PaymentAccountingSync
    {
        return DB::transaction(function () use ($payment, $purchase): PaymentAccountingSync {
            $sync = PaymentAccountingSync::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();
            if ($sync instanceof PaymentAccountingSync) {
                return $sync;
            }

            $purchaseKey = $purchase instanceof EntrepreneurPlanBudgetPurchase
                ? 'entrepreneur_plan_budget_purchase_id'
                : 'idea_validation_purchase_id';

            return PaymentAccountingSync::query()->create([
                'client_id' => $payment->client_id,
                'payment_id' => $payment->getKey(),
                'provider' => AccountingConnection::PROVIDER_XERO,
                'source' => $purchase instanceof EntrepreneurPlanBudgetPurchase ? 'entrepreneur_plan_budget' : 'idea_validation',
                'amount_ex_gst' => $purchase->amount_ex_gst,
                'gst_amount' => $purchase->gst_amount,
                'amount_including_gst' => $purchase->amount_including_gst,
                'currency' => strtoupper((string) $purchase->currency),
                'status' => PaymentAccountingSync::STATUS_PENDING,
                'refund_status' => PaymentAccountingSync::REFUND_NOT_REQUESTED,
                'metadata' => [
                    'payment_reference' => $payment->gateway_ref,
                    $purchaseKey => $purchase->getKey(),
                ],
            ]);
        });
    }

    private function acceptedRefundFor(Payment $payment): ?PaymentRefund
    {
        return PaymentRefund::query()
            ->where('gateway', 'stripe')
            ->where('payment_reference', $payment->gateway_ref)
            ->where('status', PaymentRefund::STATUS_ACCEPTED)
            ->latest('processed_at')
            ->first();
    }

    private function attachAcceptedRefund(PaymentAccountingSync $sync, PaymentRefund $refund): PaymentAccountingSync
    {
        if ($sync->payment_refund_id === $refund->getKey()) {
            return $sync->refresh();
        }

        $sync->forceFill([
            'payment_refund_id' => $refund->getKey(),
            'refund_status' => PaymentAccountingSync::REFUND_PENDING,
            'refund_error_message' => null,
        ])->save();

        if (! $this->refundMatchesSync($sync, $refund)) {
            return $this->markRefundFailure($sync, 'The accepted Stripe refund does not match the original Xero payment amount or currency.');
        }

        return $sync->refresh();
    }

    private function synchronise(PaymentAccountingSync $sync, ?User $actor): PaymentAccountingSync
    {
        try {
            $connection = $this->connections->active(AccountingConnection::PROVIDER_XERO);
            if (! $connection instanceof PracticeAccountingConnection || ! $connection->connected()) {
                return $this->markPaymentFailure($sync, 'Practice Xero is not connected. Connect Xero before exporting this Stripe payment.');
            }

            $clearingAccountCode = trim((string) Config::get('integrations.accounting.xero.stripe_clearing_account_code', ''));
            if ($clearingAccountCode === '') {
                return $this->markPaymentFailure($sync, 'Xero Stripe clearing account code is not configured. Set XERO_STRIPE_CLEARING_ACCOUNT_CODE before exporting payments.');
            }

            $sync->loadMissing([
                'payment.ideaValidationPurchase.user',
                'payment.ideaValidationPurchase.client.primaryContact',
                'payment.entrepreneurPlanBudgetPurchase.user',
                'payment.entrepreneurPlanBudgetPurchase.client.primaryContact',
            ]);
            $payment = $sync->payment;
            $purchase = $payment->ideaValidationPurchase ?? $payment->entrepreneurPlanBudgetPurchase;
            $client = $purchase?->client;
            if (! $payment instanceof Payment
                || (! $purchase instanceof IdeaValidationPurchase && ! $purchase instanceof EntrepreneurPlanBudgetPurchase)
                || ! $client instanceof Client) {
                return $this->markPaymentFailure($sync, 'The payment no longer has the direct-checkout purchase and client required for a Xero export.');
            }

            if (! $this->quoteMatchesPayment($purchase, $payment)) {
                return $this->markPaymentFailure($sync, 'The stored historical quote does not match the settled Stripe payment. Reconcile the price before creating a Xero invoice.');
            }

            $tenantId = trim((string) $connection->external_tenant_id);
            if ($tenantId === '') {
                return $this->markPaymentFailure($sync, 'Practice Xero is connected without an organisation tenant id. Reconnect Xero before exporting payments.');
            }

            $token = $this->connections->freshToken($connection);
            $sync->forceFill([
                'practice_accounting_connection_id' => $connection->getKey(),
                'error_message' => null,
            ])->save();

            $contactId = $this->contactId($sync, $client, $purchase, $token, $tenantId);
            $invoiceId = $this->invoiceId($sync->refresh(), $client, $contactId, $token, $tenantId);
            $this->paymentId($sync->refresh(), $invoiceId, $payment, $clearingAccountCode, $token, $tenantId);

            $sync->forceFill([
                'status' => PaymentAccountingSync::STATUS_SYNCED,
                'error_message' => null,
                'synced_at' => now(),
            ])->save();
            $this->audit->record('payment_accounting_sync.synced', subject: $sync, actor: $actor, after: [
                'payment_id' => $sync->payment_id,
                'provider' => $sync->provider,
                'external_invoice_id' => $sync->external_invoice_id,
                'external_payment_id' => $sync->external_payment_id,
            ]);

            $sync = $sync->refresh();

            return in_array($sync->refund_status, [
                PaymentAccountingSync::REFUND_PENDING,
                PaymentAccountingSync::REFUND_FAILED,
            ], true)
                ? $this->synchroniseRefund($sync, $token, $tenantId, $actor)
                : $sync;
        } catch (Throwable $exception) {
            return $this->markPaymentFailure($sync, $exception->getMessage());
        }
    }

    /** @param array{access_token:string} $token */
    private function contactId(PaymentAccountingSync $sync, Client $client, IdeaValidationPurchase|EntrepreneurPlanBudgetPurchase $purchase, array $token, string $tenantId): string
    {
        if (is_string($sync->external_contact_id) && $sync->external_contact_id !== '') {
            return $sync->external_contact_id;
        }

        $existing = PaymentAccountingSync::query()
            ->where('client_id', $client->getKey())
            ->where('provider', AccountingConnection::PROVIDER_XERO)
            ->whereNotNull('external_contact_id')
            ->latest('synced_at')
            ->value('external_contact_id');
        if (is_string($existing) && $existing !== '') {
            $sync->forceFill(['external_contact_id' => $existing])->save();

            return $existing;
        }

        $contact = $this->firstRow($this->xero->createContact(
            $token,
            $tenantId,
            $this->contactPayload($client, $purchase),
            'fsa-contact-'.$sync->getKey(),
        ), 'Contacts');
        $contactId = trim((string) ($contact['ContactID'] ?? ''));
        if ($contactId === '') {
            throw new \InvalidArgumentException('Xero did not return a contact id for the settled Stripe payment.');
        }

        $sync->forceFill(['external_contact_id' => $contactId])->save();

        return $contactId;
    }

    /** @param array{access_token:string} $token */
    private function invoiceId(PaymentAccountingSync $sync, Client $client, string $contactId, array $token, string $tenantId): string
    {
        if (is_string($sync->external_invoice_id) && $sync->external_invoice_id !== '') {
            return $sync->external_invoice_id;
        }

        $invoice = $this->firstRow($this->xero->createInvoice(
            $token,
            $tenantId,
            $this->invoicePayload($sync, $client, $contactId),
            'fsa-invoice-'.$sync->getKey(),
        ), 'Invoices');
        $invoiceId = trim((string) ($invoice['InvoiceID'] ?? ''));
        if ($invoiceId === '') {
            throw new \InvalidArgumentException('Xero did not return an invoice id for the settled Stripe payment.');
        }

        $sync->forceFill([
            'external_invoice_id' => $invoiceId,
            'external_invoice_number' => $invoice['InvoiceNumber'] ?? null,
        ])->save();

        return $invoiceId;
    }

    /** @param array{access_token:string} $token */
    private function paymentId(PaymentAccountingSync $sync, string $invoiceId, Payment $payment, string $clearingAccountCode, array $token, string $tenantId): void
    {
        if (is_string($sync->external_payment_id) && $sync->external_payment_id !== '') {
            return;
        }

        $xeroPayment = $this->firstRow($this->xero->createPayment($token, $tenantId, [
            'Invoice' => ['InvoiceID' => $invoiceId],
            'Account' => ['Code' => $clearingAccountCode],
            'Date' => ($payment->processed_at ?? now())->toDateString(),
            'Amount' => (float) $sync->amount_including_gst,
            'Reference' => 'Stripe '.Str::limit((string) $payment->gateway_ref, 120, ''),
        ], 'fsa-payment-'.$sync->getKey()), 'Payments');
        $paymentId = trim((string) ($xeroPayment['PaymentID'] ?? ''));
        if ($paymentId === '') {
            throw new \InvalidArgumentException('Xero did not return a payment id for the settled Stripe payment.');
        }

        $sync->forceFill(['external_payment_id' => $paymentId])->save();
    }

    /** @param array{access_token:string} $token */
    private function synchroniseRefund(PaymentAccountingSync $sync, array $token, string $tenantId, ?User $actor): PaymentAccountingSync
    {
        $refund = $sync->paymentRefund;
        $invoiceId = $sync->external_invoice_id;
        $contactId = $sync->external_contact_id;
        if (! $refund instanceof PaymentRefund
            || ! is_string($invoiceId)
            || $invoiceId === ''
            || ! is_string($contactId)
            || $contactId === '') {
            return $this->markRefundFailure($sync, 'The accepted Stripe refund cannot be linked to its Xero invoice and contact yet.');
        }
        if (! $this->refundMatchesSync($sync, $refund)) {
            return $this->markRefundFailure($sync, 'The accepted Stripe refund does not match the original Xero payment amount or currency.');
        }

        try {
            $creditNoteId = $sync->external_credit_note_id;
            if (! is_string($creditNoteId) || $creditNoteId === '') {
                $creditNote = $this->firstRow($this->xero->createCreditNote($token, $tenantId, $this->creditNotePayload($sync, $refund, $contactId), 'fsa-credit-'.$sync->getKey()), 'CreditNotes');
                $creditNoteId = trim((string) ($creditNote['CreditNoteID'] ?? ''));
                if ($creditNoteId === '') {
                    throw new \InvalidArgumentException('Xero did not return a credit note id for the Stripe refund.');
                }
                $sync->forceFill([
                    'external_credit_note_id' => $creditNoteId,
                    'external_credit_note_number' => $creditNote['CreditNoteNumber'] ?? null,
                ])->save();
            }

            $this->refundCreditNote($sync, $refund, $creditNoteId, $token, $tenantId);

            $sync->forceFill([
                'refund_status' => PaymentAccountingSync::REFUND_SYNCED,
                'refund_error_message' => null,
                'refunded_at' => now(),
            ])->save();
            $this->audit->record('payment_accounting_sync.refund_synced', subject: $sync, actor: $actor, after: [
                'payment_refund_id' => $refund->getKey(),
                'external_credit_note_id' => $sync->external_credit_note_id,
            ]);

            return $sync->refresh();
        } catch (Throwable $exception) {
            return $this->markRefundFailure($sync, $exception->getMessage());
        }
    }

    /** @return array{Name:string,ContactNumber:string,EmailAddress?:string} */
    private function contactPayload(Client $client, IdeaValidationPurchase|EntrepreneurPlanBudgetPurchase $purchase): array
    {
        $payload = [
            'Name' => mb_substr($client->legal_name ?: $client->trading_name ?: 'Future Shift customer', 0, 255),
            'ContactNumber' => $this->billingCodes->xeroContactNumber($client),
        ];
        $email = $purchase->user?->email;
        if (is_string($email) && $email !== '') {
            $payload['EmailAddress'] = $email;
        }

        return $payload;
    }

    /** @return array{Type:'ACCREC',Contact:array{ContactID:string},Date:string,DueDate:string,Reference:string,Status:'AUTHORISED',LineAmountTypes:'Exclusive',LineItems:array<int, array{Description:string,Quantity:int,UnitAmount:float,AccountCode:string,TaxType:string}>} */
    private function invoicePayload(PaymentAccountingSync $sync, Client $client, string $contactId): array
    {
        return [
            'Type' => 'ACCREC',
            'Contact' => ['ContactID' => $contactId],
            'Date' => $sync->payment?->processed_at?->toDateString() ?? now()->toDateString(),
            'DueDate' => $sync->payment?->processed_at?->toDateString() ?? now()->toDateString(),
            'Reference' => sprintf('%s %s %s', $this->billingCodes->shortCode($client), $this->serviceLabel($sync), substr((string) $sync->payment_id, 0, 8)),
            'Status' => 'AUTHORISED',
            'LineAmountTypes' => 'Exclusive',
            'LineItems' => [[
                'Description' => 'Future Shift Advisory '.$this->serviceLabel($sync),
                'Quantity' => 1,
                'UnitAmount' => (float) $sync->amount_ex_gst,
                'AccountCode' => (string) Config::get('integrations.accounting.xero.sales_account_code', '200'),
                'TaxType' => (string) Config::get('integrations.accounting.xero.sales_tax_type', 'OUTPUT2'),
            ]],
        ];
    }

    /** @return array{Type:'ACCRECCREDIT',Contact:array{ContactID:string},Date:string,Reference:string,Status:'AUTHORISED',LineAmountTypes:'Exclusive',LineItems:array<int, array{Description:string,Quantity:int,UnitAmount:float,AccountCode:string,TaxType:string}>} */
    private function creditNotePayload(PaymentAccountingSync $sync, PaymentRefund $refund, string $contactId): array
    {
        return [
            'Type' => 'ACCRECCREDIT',
            'Contact' => ['ContactID' => $contactId],
            'Date' => ($refund->processed_at ?? now())->toDateString(),
            'Reference' => 'Stripe refund '.Str::limit((string) $refund->gateway_ref, 120, ''),
            'Status' => 'AUTHORISED',
            'LineAmountTypes' => 'Exclusive',
            'LineItems' => [[
                'Description' => 'Refund: Future Shift Advisory '.$this->serviceLabel($sync),
                'Quantity' => 1,
                'UnitAmount' => (float) $sync->amount_ex_gst,
                'AccountCode' => (string) Config::get('integrations.accounting.xero.sales_account_code', '200'),
                'TaxType' => (string) Config::get('integrations.accounting.xero.sales_tax_type', 'OUTPUT2'),
            ]],
        ];
    }

    /** @param array{access_token:string} $token */
    private function refundCreditNote(PaymentAccountingSync $sync, PaymentRefund $refund, string $creditNoteId, array $token, string $tenantId): void
    {
        if (is_string($sync->external_credit_note_payment_id) && $sync->external_credit_note_payment_id !== '') {
            return;
        }

        $clearingAccountCode = trim((string) Config::get('integrations.accounting.xero.stripe_clearing_account_code', ''));
        if ($clearingAccountCode === '') {
            throw new \InvalidArgumentException('Xero Stripe clearing account code is not configured. Set XERO_STRIPE_CLEARING_ACCOUNT_CODE before exporting refunds.');
        }

        $payment = $this->firstRow($this->xero->createPayment($token, $tenantId, [
            'CreditNote' => ['CreditNoteID' => $creditNoteId],
            'Account' => ['Code' => $clearingAccountCode],
            'Date' => ($refund->processed_at ?? now())->toDateString(),
            'Amount' => (float) $sync->amount_including_gst,
            'Reference' => 'Stripe refund '.Str::limit((string) $refund->gateway_ref, 120, ''),
        ], 'fsa-credit-refund-'.$sync->getKey()), 'Payments');
        $paymentId = trim((string) ($payment['PaymentID'] ?? ''));
        if ($paymentId === '') {
            throw new \InvalidArgumentException('Xero did not return a payment id for the Stripe refund.');
        }

        $sync->forceFill(['external_credit_note_payment_id' => $paymentId])->save();
    }

    /**
     * @param  array{Contacts?:list<array{ContactID?:string}>,Invoices?:list<array{InvoiceID?:string,InvoiceNumber?:string}>,Payments?:list<array{PaymentID?:string}>,CreditNotes?:list<array{CreditNoteID?:string,CreditNoteNumber?:string}>}  $payload
     * @return array{ContactID?:string,InvoiceID?:string,InvoiceNumber?:string,PaymentID?:string,CreditNoteID?:string,CreditNoteNumber?:string}
     */
    private function firstRow(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? null;
        $row = is_array($rows) ? reset($rows) : null;
        if (! is_array($row)) {
            throw new \InvalidArgumentException("Xero did not return {$key}.");
        }

        return $row;
    }

    private function quoteMatchesPayment(IdeaValidationPurchase|EntrepreneurPlanBudgetPurchase $purchase, Payment $payment): bool
    {
        if (! is_numeric($purchase->amount_ex_gst)
            || ! is_numeric($purchase->gst_amount)
            || ! is_numeric($purchase->amount_including_gst)
            || strtoupper((string) $purchase->currency) !== strtoupper((string) $payment->currency)) {
            return false;
        }

        return $this->cents((string) $purchase->amount_ex_gst) > 0
            && $this->cents((string) $purchase->amount_ex_gst) + $this->cents((string) $purchase->gst_amount) === $this->cents((string) $purchase->amount_including_gst)
            && $this->cents((string) $purchase->amount_including_gst) === $this->cents((string) $payment->amount);
    }

    private function refundMatchesSync(PaymentAccountingSync $sync, PaymentRefund $refund): bool
    {
        return strtoupper((string) $sync->currency) === strtoupper((string) $refund->currency)
            && $this->cents((string) $sync->amount_including_gst) === $this->cents((string) $refund->amount);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function serviceLabel(PaymentAccountingSync $sync): string
    {
        return $sync->source === 'entrepreneur_plan_budget'
            ? 'Business Plan & Budget'
            : 'Idea Validation';
    }

    private function markPaymentFailure(PaymentAccountingSync $sync, string $message): PaymentAccountingSync
    {
        $sync->forceFill([
            'status' => PaymentAccountingSync::STATUS_FAILED,
            'error_message' => Str::limit($message, 1000, ''),
        ])->save();

        return $sync->refresh();
    }

    private function markRefundFailure(PaymentAccountingSync $sync, string $message): PaymentAccountingSync
    {
        $sync->forceFill([
            'refund_status' => PaymentAccountingSync::REFUND_FAILED,
            'refund_error_message' => Str::limit($message, 1000, ''),
        ])->save();

        return $sync->refresh();
    }
}
