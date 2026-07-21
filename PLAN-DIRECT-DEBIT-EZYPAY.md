# PLAN — Direct Debit payment rail (first provider: Ezypay)

**Plan version:** 1.11 — review-fixed: `PaymentAuthority::STATUS_PENDING` added so a captured-but-unconfirmed
mandate has somewhere to live (§5.5 previously claimed "no schema change" while §4.5 required exactly that), and
the portability rule restated as name-things-not-decide-things so it stops contradicting the credential scaffold.
*(v1.10: mandate capture as a real second UI path, `GATEWAY_EZYPAY` removed, `switchActiveProvider()` gate.)* *(v1.9: activation wording
corrected to E9, E1 short-circuits before the registry exists.)* *(v1.8: activation extracted into E9 with a go-live checklist; §10.7 corrected to the registry
model. v1.7: settlement storage split into E3b behind the E0 payload answer, R8 so a reversal never retries
against a dead mandate.)* *(v1.6: settlement
reconciled rather than gross-matched, `credit_note_*` demoted to `unmapped`, new mandates resolved from the
registry. v1.5: R7 first-debit-date floor, stale `retired`-rejection test corrected, E0 gate on the
`TRANSACTION_SETTLED` subscription.)* *(v1.4: notices re-keyed to the scheduled collection with the amount
frozen at notice, `closed` state for late reversals, scheduling corrected to `bootstrap/app.php`. v1.3: E1
fail-closed, notice as precondition, `rail` invariant, DB-managed provider lifecycle. v1.2: context-specific
provider resolution, rail-filtered recovery loop, fee line-item surface, E0 scoped. v1.1: installment path,
provider-agnostic scaffold, `requestId` dedupe, three-layer validation, in-flight uniqueness and result
counters.)*
*(Build target: Codex, into the test env, then push to live.)*

**One-line intent:** Give clients who will not pay by card a **bank direct debit** option, built as a
**provider-agnostic second rail** alongside the existing Stripe/Windcave card rail — with Ezypay as the first
adapter — without weakening the card path, the receipt/audit contract, or the RLS and credential baselines.

---

## 0. Premise corrections — read before designing anything

Five assumptions a reader is likely to bring to this work are wrong. Each one, uncorrected, produces a defect.

**0.1 "Direct debit isn't built yet."** Partly false. `PaymentAuthority::TYPE_DIRECT_DEBIT` already exists
([PaymentAuthority.php:18](app/Models/PaymentAuthority.php:18)) and is returned by `types()`
([:42](app/Models/PaymentAuthority.php:42)). The portal renders a **"Direct debit"** option
([ProposalSignoff.tsx:1270](resources/js/pages/portal/ProposalSignoff.tsx:1270)), and tests already construct
direct-debit authorities ([ProposalSignoffFlowTest.php:251](tests/Feature/Proposals/ProposalSignoffFlowTest.php:251)).

What does **not** exist is any gateway that can collect one. `PaymentAuthority::gateways()` returns
`[stripe, windcave]` — **both card gateways**. Today a client can select "Direct debit" and get an authority with
no collection mechanism behind it. **This is a live defect, not new work** — see §10.1.

**0.2 "Ezypay is a third gateway; add it to `gateways()` and reuse the failover."** Actively dangerous.
`Gateway::secondaryGateway()` is a **binary flip** — `stripe ? windcave : stripe`
([Gateway.php:214-219](app/Services/Payments/Gateway.php:214)). Adding a direct-debit provider to `gateways()`
means a failed debit **silently fails over to a card charge the client never authorised for that payment**. See
§10.2.

**0.3 "A charge is a charge."** False, and this is the heart of the work. `Gateway::charge()` is **synchronous**
([Gateway.php:35-101](app/Services/Payments/Gateway.php:35)). Direct debit is **asynchronous**: the outcome arrives
days later by webhook, and can be **reversed after that**. `Payment` status is `pending | succeeded | failed |
retrying` ([Payment.php:16-22](app/Models/Payment.php:16)) — **no state for "lodged, awaiting settlement"**.
Mapping "debit lodged" onto `succeeded` issues a **receipt for money FSA does not have**. See §6.

**0.4 "`PaymentProcessor::processDue()` is the collection path."** It is the *fallback* path, not the main one.
`processDue()` **first delegates to `InstallmentPaymentProcessor`**
([PaymentProcessor.php:45](app/Services/Payments/PaymentProcessor.php:45)) and then only scans schedules that
**have no installments** (`->whereDoesntHave('installments')`,
[:49](app/Services/Payments/PaymentProcessor.php:49)). `InstallmentPaymentProcessor` calls the **card**
`Gateway::charge()` unconditionally ([:80](app/Services/Payments/InstallmentPaymentProcessor.php:80)) and records
`$this->gateway->preferredGateway()` as `attempted_gateway`
([:301](app/Services/Payments/InstallmentPaymentProcessor.php:301)). **Wiring only `PaymentProcessor` would leave
every installment-based proposal — the modern path — bypassing the new rail entirely.** See §4.3.

**0.5 "Ezypay webhook dedupe works like Stripe's."** False. `PaymentWebhookReconciler::eventId()` reads
`$payload['id']` ([:419](app/Services/Payments/PaymentWebhookReconciler.php:419)). Ezypay identifies webhook
deliveries by **`requestId`**, and its docs direct integrators to use that field to detect duplicate retries.
Reusing the Stripe extractor yields a null/absent event id, which collapses the
`unique(gateway, event_id)` replay defence. See §7.2.

---

## 1. Verified anchors

| Anchor | Verified at | Consequence |
|---|---|---|
| `Gateway` is a **concrete `final class`**, not an interface | [Gateway.php:23-30](app/Services/Payments/Gateway.php:23) | No plug-in seam. Add a **parallel rail** (§4), do not retrofit one. |
| Collection runs **installments first**, legacy schedules second | [PaymentProcessor.php:45,49](app/Services/Payments/PaymentProcessor.php:45) | Both processors need rail dispatch (§4.3). |
| `InstallmentPaymentProcessor` hardcodes the card gateway and its preferred-gateway label | [:80,301](app/Services/Payments/InstallmentPaymentProcessor.php:80) | Primary integration point for this work. |
| Installment confirmation deadline is **`now + 48 hours`**; exceeding it forces `manual_review` | [:304,178-179](app/Services/Payments/InstallmentPaymentProcessor.php:304) | **48h is shorter than direct-debit settlement.** Must be rail-aware or every debit lands in manual review (§6.6). |
| `PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION` already exists | [PaymentInstallment.php:20](app/Models/PaymentInstallment.php:20) | Reuse for lodged debits — do **not** invent a new installment status. But see §6.7: the reuse is only safe once the loop is rail-filtered. |
| `confirmAmbiguous()` scans that status and calls the **card** `Gateway::findCharge()`, which returns `unknown()` for non-card gateways | [InstallmentPaymentProcessor.php:163,185](app/Services/Payments/InstallmentPaymentProcessor.php:163), [Gateway.php:106-108](app/Services/Payments/Gateway.php:106) | Lodged debits would be re-deferred until `manual_review`. Loop must be rail-filtered (§6.7). |
| Both processors return `array{scanned,succeeded,retrying,failed,receipts}` and do `$result[$outcome['status']]++` | [PaymentProcessor.php:40,58](app/Services/Payments/PaymentProcessor.php:40), [InstallmentPaymentProcessor.php:34-35](app/Services/Payments/InstallmentPaymentProcessor.php:34) | A `submitted` outcome with no matching key is an **undefined-index break**, not a miscount (§5.4). |
| Two partial unique indexes on `payments`: legacy `(payment_schedule_id, attempt) WHERE payment_installment_id IS NULL`; installment `(payment_installment_id) WHERE status IN ('pending','retrying','succeeded')` | [migration:147-148](database/migrations/2026_07_13_000100_create_integration_efficiency_service_tables.php:147) | `submitted` **must** join the installment index's status list, or two live debits per installment can coexist (§5.3). |
| Type/gateway validated **independently at three layers** | [ProposalSignoffController.php:342-343](app/Http/Controllers/Portal/ProposalSignoffController.php:342), [SignoffFlow.php:190-200](app/Services/Proposals/SignoffFlow.php:190), [AuthorityCapture.php:34-40](app/Services/Payments/AuthorityCapture.php:34) | E1 must fix all three, not just the controller (§10.1). |
| Integration clients follow `Contracts/XClient` + `Fake` + `Fallback` + `Live` | `app/Services/Integration/Windcave/` | The direct-debit client mirrors this shape, keyed by provider (§4.2). |
| `payment_webhook_events` has `unique(['gateway','event_id'])` + `payload_hash` | [2026_06_22_000000](database/migrations/2026_06_22_000000_create_payment_webhook_events_table.php) | The replay defence — but only if the event id is extracted correctly (§7.2). |
| Webhook reconciliation runs inside `withSystemContext()` | [PaymentWebhookReconciler.php:30](app/Services/Payments/PaymentWebhookReconciler.php:30) | Mandatory; webhooks carry no user and RLS would hide the row. |
| Reconciler cross-checks amount + currency | [:292-306](app/Services/Payments/PaymentWebhookReconciler.php:292) | Load-bearing compensation for SHA-1 (§7.2) — but **reuse only for `lodged`**. Settlement is net of fees and must be reconciled, not matched (§7.2a). |
| Verifier enforces a timestamp window per gateway | [PaymentWebhookVerifier.php:19-84](app/Services/Payments/PaymentWebhookVerifier.php:19) | Ezypay **cannot** use it. Do not fake one (§7.2). |
| `payments`/`receipts` RLS is client-scoped, `FORCE ROW LEVEL SECURITY` | [2026_05_23_050000](database/migrations/2026_05_23_050000_create_payments_and_receipts_tables.php) | Every new table mirrors this policy shape. |
| `Gateway::assertNoRawPan()` rejects card numbers in metadata | [Gateway.php:239-258](app/Services/Payments/Gateway.php:239) | Bank accounts need the equivalent (§9.3). |
| `BillingAdjustment` is **credit-only** — sole type `TYPE_SCOPING_FEE_CREDIT`, allocator only reduces | [BillingAdjustment.php:16](app/Models/BillingAdjustment.php:16), [BillingAdjustmentAllocator.php:53](app/Services/Payments/BillingAdjustmentAllocator.php:53) | The dishonour fee is a **debit**. Do not invert this abstraction (§8.2). |
| All external calls via `ResilientHttp`; credentials via `IntegrationCredentials`/`KeyEnvelope` | CLAUDE.md baseline | Non-negotiable. Note the retry hazard in §7.4. |

---

## 2. Provider facts

### 2.1 Verified from provider documentation

| Fact | Source |
|---|---|
| **OAuth 2.0**, partner **and** merchant credentials; access token **3600s**; refresh token **7 days** | [authentication](https://developer.ezypay.com/docs/authentication-1) |
| Webhook signature header **`X-Ezypay-Signature`**, **HMAC-SHA1** over the **raw unmodified body**, keyed by a registered `clientKey`. **No timestamp, no replay protection.** | [webhook security](https://developer.ezypay.com/docs/webhook-security) |
| Webhook deliveries carry **`requestId`**; duplicate retries are detected via that field | [webhook](https://developer.ezypay.com/docs/webhook) |
| Events include `transaction_settled`, `invoice_created`, `invoice_paid`, `invoice_past_due`, `credit_note_created/paid/failed`, `payment_method_linked/valid/invalid/replaced` | [webhook](https://developer.ezypay.com/docs/webhook) |
| Non-2xx responses are treated as failed delivery and **retried** | [webhook](https://developer.ezypay.com/docs/webhook) |
| NZ is a supported market with bank direct debit | [ezypay.com/nz](https://www.ezypay.com/nz) |

### 2.2 NOT verified — confirm in sandbox/contract before E2. Do not code against these.

Record answers in this file as they land. **Prior plan versions listed the first two as verified facts; they were
taken from search summaries and could not be reconfirmed against Ezypay's live pricing pages. They are treated as
unverified until the contract says otherwise.**

- **Settlement time** (~3 business days was the indicative figure). §6.6 and the retry delay in §6.4 depend on the
  real number.
- **Fee schedule** — transaction rate and failed-payment fee. §8 bills the fee **as reported by the provider**,
  never a hardcoded constant, precisely because of this.
- The **exact event name and payload for a dishonour/reversal**. The published event list is conspicuously light on
  failure events; `invoice_past_due` and `credit_note_*` may be the mechanism, or there may be an unlisted event.
  **The entire §6 state machine depends on this.** Blocking for E2.
- Whether a **one-off invoice** can be created without an enclosing subscription, and which field carries our
  `payment_id` back for `matchingPayment()`. Ezypay's Create Invoice reference exposes an **`externalInvoiceId`**,
  which is the likely candidate — confirm it round-trips on webhooks.
- Whether invoice creation accepts an **idempotency key** (§7.4).
- The **hosted payment-method capture** flow, and confirmation FSA never receives raw bank account numbers (§9.3).
- **The settlement payload shape** — confirm `transactionAmount` is net of provider fees, which field carries the
  fee, and critically whether settlement arrives **one event per payment or batched** across many. Batched
  settlement turns §7.2a's three columns into a `payment_settlements` child table and changes E6.
- **Which single event carries a completed reversal**, and its terminal state — `credit_note_created` /
  `credit_note_paid` / `credit_note_failed` mean different things and only one (if any) is a reversal (§7.3).
- The **reversal window** — how long after settlement a reversal can still arrive. Sets
  `DIRECT_DEBIT_REVERSAL_WINDOW_DAYS`, which gates `retired → closed` (§4.4). Retiring a provider before this
  elapses drops late corrections silently.
- **Whether `TRANSACTION_SETTLED` can actually be subscribed.** The reconciliation guide directs partners to
  subscribe to it, but the Create Webhook reference's supported `eventTypes` list **omits** it. R2 makes this the
  receipt boundary, so if the subscription cannot be created there is no push signal for settlement at all. E0 must
  create the subscription in sandbox and observe a real delivery — not merely read that it exists.
  **Fallback if unsubscribable:** settlement becomes **pull-based**. The §6.5 sweep stops being a safety net and
  becomes the primary settlement mechanism, polling `findInvoice()` / the settlement report on a cadence tight
  enough to keep receipts timely (hours, not daily). That materially changes E6 and E7 scope, so it must be
  resolved at E0, not discovered during the build.

---

## 3. Decided: FSA owns the billing calendar

**D1 — resolved (owner).** FSA remains the **single scheduler**. For each due payment it creates a **one-off
provider invoice**. Provider-side subscriptions are **not** used.

Rationale: `payment_schedules.next_run_at` + `collection_day`, and the installment tables, already own the cycle,
with pause/retry/revoke implemented against them. A provider subscription would create a **second calendar**, and
every pause, revocation, fee change, or pilot-fee waiver would have to be mirrored across both — with
double-billing as the failure mode when they drift.

**Consequence:** the provider's own dunning/retry must be **disabled** on FSA invoices. If the provider retries
while FSA also retries, the client is debited twice. Confirm and assert in E2.

---

## 4. Architecture — a provider-agnostic second rail

### 4.1 Portability requirement

Ezypay is the **first adapter, not the architecture**. FSA must be able to replace the direct-debit provider
without touching the payment state machine, the schema, or the UI.

The rule is not "the string `ezypay` appears nowhere" — a provider must be nameable somewhere or it cannot be
addressed at all. The rule is about **where a provider name may be a decision**:

| Provider names **allowed** — naming a thing | Provider names **forbidden** — deciding something |
|---|---|
| adapter class + registration | domain models and their constants |
| `direct_debit_providers.slug` (registry) | the payment state machine |
| webhook route `{provider}` segment | validation, sign-off, or portal UI choices |
| credential config keys (§9.2) | any `if`/`match` branching on the literal |
| stored `gateway` values on authorities/payments | reports, dashboards, or accounting logic |

The left column is data and wiring; a swap edits it. The right column is behaviour; a swap must not touch it. Test
40 is the enforcement — the whole rail must run through a non-Ezypay fake provider.

### 4.2 Shape

```
App\Services\Payments\DirectDebit\
    DirectDebitCollector          lodge(Payment): DirectDebitLodgement — never returns "succeeded"
    DirectDebitProviderResolver   context-specific resolution — see §4.4, NOT a single "active provider"
    DirectDebitMandateService     customer + hosted capture + token storage
    DirectDebitTokenStore         OAuth lifecycle; KeyEnvelope-encrypted; never logged (§9.2)
    DirectDebitWebhookReconciler  provider-neutral; delegates parsing to the adapter
    DishonourHandler              §6.4 policy + §8 fee record

App\Services\Integration\DirectDebit\
    Contracts\DirectDebitProviderClient   createCustomer, captureUrl, lodgeInvoice, findInvoice, revokeMandate
    Contracts\ProviderWebhookAdapter      verify(Request): bool
                                          eventId(array): ?string      ← §7.2, per-provider
                                          eventType(array): ?string
                                          toCanonicalEvent(array): CanonicalDirectDebitEvent
    FakeDirectDebitProviderClient         the test binding — no test reaches a live or sandbox endpoint
    FallbackDirectDebitProviderClient
    Ezypay\LiveEzypayClient               first adapter
    Ezypay\EzypayWebhookAdapter           HMAC-SHA1, requestId, Ezypay event-name mapping
```

`ProviderWebhookAdapter::toCanonicalEvent()` is the portability seam: every provider's vocabulary is normalised
into one internal event set (`mandate_linked`, `mandate_invalid`, `lodged`, `settled`, `dishonoured`, `reversed`,
`unmapped`). §6 and §7.3 are written against the **canonical** set only.

**Persisted provider identity.** `payment_authorities.gateway` and `payments.gateway` both continue to store the
**concrete provider slug** (`ezypay`), because mandates and `gateway_ref` values are provider-specific and must
remain interpretable — and collectable — after a provider swap. **A provider change never rewrites stored
`gateway` values.** These stored values, not config, are what resolve the provider for existing mandates and
payments (§4.4). Code must never branch on the literal — always resolve through `DirectDebitProviderResolver`.

Model constants:

**No provider constant on the domain model.** v1.8 proposed `PaymentAuthority::GATEWAY_EZYPAY`. That was a direct
breach of §10.6: the argument for storing `'ezypay'` in `payments.gateway` is that historical rows must stay
interpretable (data), and it does **not** extend to declaring a constant for it on a shared domain model (code).
A constant is a name the whole application can reach for and branch on, which is precisely the coupling this rail
is built to avoid. Provider slugs live in **`direct_debit_providers.slug`** and in adapter registration — nowhere
else.

```php
// No GATEWAY_EZYPAY, or any other provider constant, on PaymentAuthority.

public static function gateways(): array             // UNCHANGED: [stripe, windcave] — card rail only
public static function directDebitGateways(): array  // read from the registry — never a hardcoded list
public static function allGateways(): array          // union; display and lookup only, never failover
public static function gatewaysForType(string $type): array   // the rail's valid providers — use in validation
```

`gateways()` keeping its current meaning is load-bearing: it feeds `Gateway::findCharge()`
([:107](app/Services/Payments/Gateway.php:107)), `preferredGateway()` ([:209](app/Services/Payments/Gateway.php:209)),
`secondaryGateway()` ([:214](app/Services/Payments/Gateway.php:214)), and validation at all three layers in §10.1.
Widening it in place changes eight call sites at once, most of them silently. **Do not widen it.**

`Gateway` itself is **not modified** (§10.3).

### 4.3 Dispatch — both processors

Rail dispatch keys on **`PaymentAuthority.type`**, never on a gateway string, and must be added in **both** places:

```
InstallmentPaymentProcessor::processDue()      ← PRIMARY path (§0.4)
    └─ TYPE_CARD         → Gateway::charge()                  [unchanged]
    └─ TYPE_DIRECT_DEBIT → DirectDebitCollector::lodge()
                           installment → STATUS_AWAITING_GATEWAY_CONFIRMATION
                           attempted_gateway → the AUTHORITY's stored provider (NOT preferredGateway(),
                                               NOT the registry's active provider — see §4.4)
                           confirmation_deadline → rail-aware (§6.6)

PaymentProcessor::processSchedule()            ← legacy no-installment path
    └─ same branch
```

`attempted_gateway` is currently set from `$this->gateway->preferredGateway()`
([:301](app/Services/Payments/InstallmentPaymentProcessor.php:301)), which returns a **card** gateway. For a
direct debit it must record the direct-debit provider, or the installment's audit trail names the wrong rail.

### 4.4 Provider resolution is context-specific, never global

A single "active provider" lookup is **wrong** and breaks the portability case this rail exists to support. The
moment FSA switches providers, three populations coexist: existing mandates held at the old provider, in-flight
collections against those mandates, and late settlement/reversal webhooks from the old provider arriving days
after the switch. Resolving everything to "the active provider" would lodge a debit against a provider that has no
mandate for that client, and reject genuine old-provider webhooks.

`DirectDebitProviderResolver` therefore exposes **four** distinct entry points, and callers must pick deliberately:

| Method | Source of truth | Used by |
|---|---|---|
| `forNewMandate(): DirectDebitProviderClient` | the single **`direct_debit_providers.status = 'active'`** row (§5.9) — **not** config | `DirectDebitMandateService` — new mandates only |
| `forAuthority(PaymentAuthority): DirectDebitProviderClient` | **`payment_authorities.gateway`** | `DirectDebitCollector::lodge()`, mandate revocation |
| `forPayment(Payment): DirectDebitProviderClient` | **`payments.gateway`**, falling back to the authority's | stale-lodgement sweep, `findInvoice()`, historical lookups |
| `adapterForProvider(string $slug): ProviderWebhookAdapter` | route segment, validated against the registry | webhook verification (§7.1) |

**Collections always follow the mandate, never the config.** A mandate held at provider A is collected through
provider A for its entire life, even after the registry's active provider becomes B. Changing the active provider
affects **new mandates only**; it never migrates an existing one. Migrating a client between providers is a
deliberate re-mandate (new authority, new client authorisation under §9.1), never a config flip.

**Provider lifecycle states.** Each registered provider carries a status, so a drain period is expressible:

| Status | New mandates | Collect existing | Accept webhooks |
|---|---|---|---|
| `active` | yes | yes | yes |
| `draining` | **no** | yes | yes |
| `retired` | no | **no** | **yes** — corrections only |
| `closed` | no | no | **no** — rejected at the route |

**`retired` must keep accepting webhooks.** A reversal can arrive well after settlement (§6.3), so a provider that
has stopped taking new work can still owe FSA a correction. If retirement closed the webhook door, a late
dishonour or reversal would be **silently dropped** — leaving FSA holding a receipt, a `succeeded` payment, and an
advanced schedule for money that was clawed back. That is a financial-correctness failure with no alert attached to
it.

`retired` therefore blocks new mandates **and new lodgements**, but continues to verify and process canonical
events for payments that already exist. Only `closed` rejects at the route, and the `retired → closed` transition
is gated on a **reversal window** (`DIRECT_DEBIT_REVERSAL_WINDOW_DAYS`, set from the provider's contractual
reversal SLA — §2.2) having elapsed since the most recent settlement for that provider, with no `submitted`
payments outstanding.

**Lifecycle lives in the database, not config.** This is deliberate and worth stating plainly: the constraint that
guards retirement — *no unrevoked authorities, no `submitted` payments* — is a **data** condition, and config
cannot be validated against data at deploy time. An env-driven status means a deploy can flip Ezypay to `retired`
while live mandates still need collecting and in-flight settlement webhooks are still arriving, and the failure is
**silent**: debits stop and webhooks 404 with nothing to alert on.

So a `direct_debit_providers` table (§5.9) holds `slug` + `status`, and transitions go through a service that
takes a row lock and asserts the invariant **inside the transaction**. Config retains only **credentials** — the
separation is that secrets belong in env, and data-dependent state belongs in the database.

Enforce the rest as database invariants rather than application checks:

- **Exactly one `active`** — partial unique index `unique(status) WHERE status = 'active'`. Zero-active is caught
  by the readiness check below, since an index cannot assert existence.
- **`switchActiveProvider($slug)`** — becoming active is its own guarded transition, not two independent status
  edits. It **verifies the incoming provider first** — credentials resolve, OAuth token obtains, webhook
  registration exists and points at that provider's route segment, hosted capture is reachable, and settlement
  reporting is available — then, **in one audited transaction**, moves the outgoing provider `active → draining`
  and the incoming `→ active`. Doing this as two separate updates would either leave zero active providers (new
  mandates fail) or momentarily two (the unique index rejects it, half-applied). E9's checklist covers rail
  readiness; **this** covers whether a *specific new provider* is safe to receive mandates, which is the question
  a future swap actually asks.
- **`draining → retired`** — transactional preflight: no unrevoked authorities and no `submitted` payments.
- **`retired → closed`** — transactional preflight: additionally, the reversal window has elapsed since the
  provider's most recent settlement. This is the transition that can lose money if rushed.
- All three raise and roll back on violation; none may be reached by editing config or updating the model directly.
- **Readiness check** — the API health dashboard reports a fault when a provider has credentials but no registry
  row, a registry row but no credentials, or when no provider is `active` while `DIRECT_DEBIT_ENABLED` is true.
  A misconfiguration must be **loud at runtime**, not discovered when a debit silently stops.

### 4.5 Mandate capture is a second UI path, not a config change

"Hosted capture" understates the work. The entire authority-capture flow is **Stripe-specific today**, on both
sides:

- `ProposalSignoffController::paymentSetup()` constructor-injects `StripeClient` and returns a Stripe SetupIntent
  payload — `publishable_key`, `client_secret`, `setup_intent_ref`
  ([:97,143-148](app/Http/Controllers/Portal/ProposalSignoffController.php:97)) — and hard-rejects anything that
  is not Stripe + card ([:120-124](app/Http/Controllers/Portal/ProposalSignoffController.php:120)).
- `AuthorityFields` mounts **Stripe Elements** directly, holding `Stripe`, `StripeElements`, and
  `StripePaymentElement` refs ([ProposalSignoff.tsx:826-840](resources/js/pages/portal/ProposalSignoff.tsx:826)).

A bank mandate is not a card token and cannot be captured through Stripe Elements. So E4/E8 must deliver a
**parallel capture path**:

1. `paymentSetup()` branches on rail. Card keeps the SetupIntent response byte-for-byte. Direct debit returns a
   **provider hosted-capture handle** (URL or redirect payload) obtained through `DirectDebitMandateService` →
   `DirectDebitProviderResolver::forNewMandate()`, and **never** a `client_secret`.
2. The §10.1-era guard at [:120](app/Http/Controllers/Portal/ProposalSignoffController.php:120) relaxes for the
   direct-debit branch **only**, and only when the rail is enabled. Its card behaviour is unchanged.
3. `AuthorityFields` branches on rail: Stripe Elements for card, hosted redirect/embed for direct debit. Stripe.js
   must **not** load on the direct-debit path.
4. The mandate is confirmed by the `mandate_linked` webhook (§7.3), not by the browser returning from the hosted
   page — a redirect is a hint, not proof. Capture creates the authority as **`pending`** (§5.5); it becomes
   `active` only when the webhook lands, and sign-off signature stays blocked with an explicit waiting state until
   then.

Because the sign-off UI already lists "Direct debit"
([ProposalSignoff.tsx:1270](resources/js/pages/portal/ProposalSignoff.tsx:1270)), shipping E9 with this path
incomplete would present the option and then tell the client "Online card setup is currently available for Stripe
card payments." E9's checklist therefore gates on this explicitly.

---

## 5. Data model

### 5.1 `payments` — new columns

| Column | Notes |
|---|---|
| `rail` (string 20, `NOT NULL`, **no default**) | `card` \| `direct_debit`. Add with a **temporary** `card` default to backfill existing rows, then **drop the default within the same migration** — the shipped column is `NOT NULL` with no default and is immutable after insert (§5.8). |
| `submitted_at` (timestamptz, nullable) | When the debit was lodged. |
| `settled_at` (timestamptz, nullable) | Settlement confirmed. **Receipt trigger** (R2). |
| `dishonoured_at` (timestamptz, nullable) | Dishonour/reversal confirmed. |
| `dishonour_reason` (text, nullable) | Provider reason code + description, through `Redactor`. |

Index `['rail','status','submitted_at']` for the stale-lodgement sweep (§6.5).

Settlement storage — `settled_net_amount`, `provider_fee_amount`, `settlement_ref` — is specified in §7.2a,
because its justification is inseparable from why settlement must not be amount-matched. It ships in **E3b, not
E3**: E0 decides whether settlement is per-payment or batched, and a batched answer makes it a child table instead
of columns.

### 5.2 `payment_dishonour_fees` (new, client-scoped)

Separate table, not a `BillingAdjustment` type — see §8.2.

| Column | Notes |
|---|---|
| `id`, `client_id` FK | RLS mirroring the `payments` policy verbatim |
| `payment_id` FK **unique** | one fee per dishonoured payment; the index is the double-billing guard |
| `amount`, `currency` | **as reported by the provider**, never a hardcoded constant (§2.2) |
| `gst_treatment` (string) | §8.3 — **no silent default** |
| `status` | `pending` \| `billed` \| `waived` \| `absorbed` |
| `applied_to_payment_id` FK nullable | the later payment that carried the fee |
| `waived_by_user_id`, `waived_reason` | advisor waiver; audited |

### 5.3 In-flight uniqueness — both indexes

Adding `submitted` requires **both** existing partial indexes to be revisited
([migration:147-148](database/migrations/2026_07_13_000100_create_integration_efficiency_service_tables.php:147)):

- **Installment index** — `payments_installment_active_or_succeeded_unique` currently covers
  `status IN ('pending','retrying','succeeded')`. It **must** be recreated to include `'submitted'`. Without this,
  two live debits can exist for one installment — the exact double-debit this plan is designed to prevent.
- **Legacy index** — `payments_legacy_schedule_attempt_unique` is `(payment_schedule_id, attempt)
  WHERE payment_installment_id IS NULL`, which does not constrain in-flight count. Add a second partial index
  `(payment_schedule_id) WHERE payment_installment_id IS NULL AND status = 'submitted'` to enforce R4 on the
  legacy path.

Both are database invariants, not check-then-act guards.

### 5.4 Processor result contract

Both processors return `array{scanned,succeeded,retrying,failed,receipts}` and increment via
`$result[$outcome['status']]++` ([PaymentProcessor.php:58](app/Services/Payments/PaymentProcessor.php:58)). A
`submitted` outcome against that array is an **undefined index**, not merely a miscount.

Add a **`submitted`** key to the return shape of **both** processors, initialise it to `0`, and update every
consumer — the docblocks at [PaymentProcessor.php:40](app/Services/Payments/PaymentProcessor.php:40) and
[InstallmentPaymentProcessor.php:34](app/Services/Payments/InstallmentPaymentProcessor.php:34), the console command
output, and any dashboard tile reading these counters. A lodged debit is neither a success nor a failure and must
not be counted as either.

### 5.5 `payment_authorities` — one new status, no new columns

**Columns are unchanged.** `type`, `gateway`, and `gateway_token_envelope` already carry what is needed; the
provider customer id and mandate token go **inside** the existing encrypted envelope (shape at
[Gateway.php:190-203](app/Services/Payments/Gateway.php:190)), never as new plaintext columns.

**The status set is not.** v1.10 said "no schema change" while §4.5 simultaneously required an authority that
exists but is not yet active — those cannot both be true. `PaymentAuthority` names only `active`, `failed`, and
`revoked` ([PaymentAuthority.php:24-28](app/Models/PaymentAuthority.php:24)), so direct-debit capture has nowhere
to land:

- Create it `active` on redirect → contradicts §4.5, and FSA holds an authority the provider may never have
  confirmed.
- Create nothing until the webhook → there is no row for `mandate_linked` to attach to.
- Create it `failed` → semantically false and it would never recover.

Worse, `SignoffFlow::signature()` requires an **active** authority
([SignoffFlow.php:247-251](app/Services/Proposals/SignoffFlow.php:247)). A client who has just completed the
mandate form would hit *"A tokenised payment authority is required before signature"* — a message that reads as a
bug and invites someone to "fix" it by activating early.

**Add `STATUS_PENDING`** (`pending`):

| Transition | Trigger |
|---|---|
| → `pending` | direct-debit hosted capture initiated (§4.5) |
| `pending` → `active` | `mandate_linked` / `mandate_valid` webhook (§7.3) |
| `pending` → `failed` | `mandate_invalid`, or expiry (below) |

Rules:

- **Signature stays blocked while `pending`**, but with an honest waiting state — *"Waiting on your bank to confirm
  the direct debit authority"* — distinguished from "no authority at all". `activeAuthority()` must not treat
  `pending` as active.
- **Only `active` authorities are chargeable.** `Gateway::charge()` already enforces this
  ([Gateway.php:39-41](app/Services/Payments/Gateway.php:39)); `DirectDebitCollector` must match it, and R8's
  "live mandate" check (§6.4) means `active`, never `pending`.
- **`pending` expires.** If no confirming webhook arrives within `DIRECT_DEBIT_MANDATE_PENDING_DAYS` (default 5),
  the authority moves to `failed` and the advisor is alerted. Without this a client waits forever on a mandate the
  provider never created, and the proposal silently stalls at signature with nothing overdue to report.
- Card authorities are **unaffected** — they continue to be created `active` on tokenisation.

### 5.6 `payment_installments` — a real surface for the fee

v1.1 said the fee appears on the next installment "as a distinct line". **There is no line-item model to carry
it.** `payment_installments` holds three scalars — `base_amount`, `credit_applied`, `net_amount`
([migration:91-93](database/migrations/2026_07_13_000100_create_integration_efficiency_service_tables.php:91)) —
and `ReceiptGenerator` renders a single `Amount`
([ReceiptGenerator.php:100-105](app/Services/Payments/ReceiptGenerator.php:100)). Folding the fee into
`base_amount` or `net_amount` would make the client's next payment silently larger with no disclosure and no audit
trail — the opposite of what D4 requires.

Rather than invent a general line-item system for a single fee type, mirror the existing `credit_applied`
precedent — that column is already exactly this pattern, a scalar adjustment carried alongside the base:

| Column | Notes |
|---|---|
| `fee_applied` (decimal 12,2, default 0) | dishonour fees applied to this installment. Symmetric with `credit_applied`: credit reduces, fee increases. |

`net_amount` becomes `base_amount - credit_applied + fee_applied`. Every writer of `net_amount` must be updated —
including `BillingAdjustmentAllocator`, which currently computes it on the assumption that credit is the only
adjustment ([BillingAdjustmentAllocator.php:53](app/Services/Payments/BillingAdjustmentAllocator.php:53)). A
`fee_applied` that is silently dropped by an existing writer is the failure mode to test for.

**Receipt and disclosure.** `ReceiptGenerator` gains an explicit fee line, rendered **only** when
`fee_applied > 0`, showing the amount, the reason, and the originating dishonoured payment id. The single `Amount`
line stays as the total. A fee that appears in the total but not as its own line does not satisfy §8.3's
disclosure requirement — it must be visible, attributable, and traceable back to the dishonour that caused it.

### 5.7 `direct_debit_notices` (new, client-scoped) — the pre-debit notice must be a record, not an intention

§9.1 requires advance notice of amount and date. v1.2 stated the requirement but gave it no schema, no state, and
no guard — so nothing would have stopped a lodgement from going out un-noticed. A compliance obligation that
exists only as prose in a plan is not implemented.

**The notice cannot be keyed to a payment.** Both processors create the `Payment` row **at collection time**
([InstallmentPaymentProcessor.php:284](app/Services/Payments/InstallmentPaymentProcessor.php:284),
[PaymentProcessor.php:160](app/Services/Payments/PaymentProcessor.php:160)), but R6 requires a delivered notice
*before* lodgement. Keying notices to `payment_id` would be circular: the notice needs an id that only exists once
the thing it authorises has already begun. An implementer hitting that wall would either block every lodgement
forever or pre-create `pending` payments — and a pre-created `pending` row collides with
`payments_installment_active_or_succeeded_unique` (§5.3), blocking the real attempt.

Notices therefore key to the **scheduled collection**, which is what the client is actually being told about, and
which exists days ahead of any payment attempt. A retry after a dishonour is a different debit on a different date
and needs its own notice regardless, so nothing is lost.

| Column | Notes |
|---|---|
| `id`, `client_id` FK | RLS mirroring the `payments` policy verbatim |
| `payment_installment_id` FK nullable | installment path (primary) |
| `payment_schedule_id` FK nullable | legacy no-installment path |
| — | `CHECK`: **exactly one** of the two is non-null |
| `notified_amount` (decimal 12,2) | **the exact amount to be debited, inclusive of `fee_applied`** |
| `notified_debit_date` (date) | the exact date to be debited |
| `channel` (string) | resolved from the client's channel preferences |
| `sent_at` (timestamptz, nullable) | when delivery was **confirmed** — not when it was queued |
| `superseded_at` (timestamptz, nullable) | set when the amount or date changes (§6.8) |
| `payment_id` FK nullable | back-link **stamped at lodgement**, for audit only — never a lookup key |
| `notice_ref` (string, nullable) | notification/message id for the audit trail |

One live notice per collection, enforced as a database invariant:

```sql
CREATE UNIQUE INDEX direct_debit_notices_live_installment
    ON direct_debit_notices (payment_installment_id, notified_debit_date)
    WHERE superseded_at IS NULL AND payment_installment_id IS NOT NULL;
CREATE UNIQUE INDEX direct_debit_notices_live_schedule
    ON direct_debit_notices (payment_schedule_id, notified_debit_date)
    WHERE superseded_at IS NULL AND payment_schedule_id IS NOT NULL;
```

**Consequence — the amount must be frozen before the notice is sent.** `InstallmentPaymentProcessor` applies
billing adjustments *at collection time*, immediately before creating the payment
([:281-284](app/Services/Payments/InstallmentPaymentProcessor.php:281)). On the direct-debit rail that ordering is
fatal to R6: an adjustment landing at lodgement changes the amount after the notice went out, so the notice can
**never** match and the collection blocks permanently.

For direct debit, credits and `fee_applied` must therefore be resolved **at notice generation**, and the resulting
`net_amount` treated as frozen for that collection. Any adjustment arriving in the notice→lodgement window
supersedes the notice and restarts the period (§6.8) rather than silently changing the amount. **Do not change the
card rail's ordering** — it has no notice obligation and adjusting at collection time is correct there.

### 5.8 `rail` must be written atomically, never defaulted then corrected

The §6.7 rail filter is only sound if a direct-debit payment is **never** visible as a card payment, even
briefly. `rail` defaults to `card` (§5.1), so a create-then-update sequence would leave a window in which a lodged
debit is indistinguishable from an ambiguous card charge — and `confirmAmbiguous()` runs on a schedule, so that
window is reachable.

Enforce it in the database rather than by convention: after backfilling existing rows, **drop the default**.

```sql
ALTER TABLE payments ALTER COLUMN rail DROP DEFAULT;
```

Every subsequent insert must state `rail` explicitly or fail loudly. Combined with `NOT NULL`, this makes
"forgot to set the rail" impossible to ship rather than merely discouraged. `rail` is **immutable after insert** —
no code path updates it.

### 5.9 `direct_debit_providers` (new, **not** client-scoped)

The provider registry and lifecycle state (§4.4). This is operator configuration, not client data, so it is the
one new table here that is **not** client-scoped: readable under any authenticated context, writable only by
`super_admin` / `system`. Every transition is audited through `AuditWriter`.

| Column | Notes |
|---|---|
| `slug` (PK) | matches the credentials key in config and the webhook route segment |
| `status` | `active` \| `draining` \| `retired` \| `closed` (§4.4) |
| `activated_at`, `draining_from`, `retired_at`, `closed_at` | transition history |
| `notes` | operator context for the change |

Invariants:

```sql
CREATE UNIQUE INDEX direct_debit_providers_single_active ON direct_debit_providers (status) WHERE status = 'active';
```

Transitions run through `DirectDebitProviderLifecycle`, which locks the row (`SELECT … FOR UPDATE`) and asserts
the §4.4 preconditions **within the transaction** — `retired` requires no unrevoked authority and no `submitted`
payment; `closed` additionally requires the reversal window to have elapsed. Never expose raw status updates on
the model.

---

## 6. Payment state machine

### 6.1 States

```
              lodge()                 settled (canonical)
   pending ───────────► submitted ──────────────────────► succeeded
      │                     │                                  │
      │                     │ dishonoured                      │ reversed
      │                     ▼                                  ▼
      └──────────────► dishonoured ◄─────────────────────── dishonoured
                            │
                            ├─ attempt < max ──► retrying ──► (new payment row, attempt+1)
                            └─ attempt = max ──► failed  + schedule paused + advisor notified
```

Two new `Payment` statuses: **`submitted`** and **`dishonoured`**. The four existing statuses keep their exact
current meanings so the card path is untouched. Installments reuse the existing
`STATUS_AWAITING_GATEWAY_CONFIRMATION`.

### 6.2 Hard rules

**R1 — `submitted` is never success.** No receipt, no schedule advance, no client-facing "paid".

**R2 — Receipts issue on settlement only.** `ReceiptGenerator` fires when `settled_at` is set. **D2 resolved
(owner):** clients wait for settlement before the PDF. FSA never issues a receipt for funds it has not received.

**R3 — Schedules advance on settlement only.** `advanceSchedule()`
([PaymentWebhookReconciler.php:313](app/Services/Payments/PaymentWebhookReconciler.php:313)) must not run at
`submitted`.

**R4 — At most one in-flight direct debit per installment / per schedule.** R3 makes this necessary: the next due
date can arrive while the prior debit is unsettled. Both processors must **skip** (not fail) such a row and log the
skip. Enforced by the two partial indexes in §5.3.

**R5 — Never cross rails on failure.** See §10.2.

**R6 — No lodgement without a matching delivered notice.** See §6.8.

**R7 — The first direct-debit date must clear the notice window.** See §6.9.

**R8 — No retry without a live mandate on a collecting provider.** See §6.4.

### 6.3 Dishonour after settlement

A reversal can arrive after `succeeded`. Because of the partial unique indexes, the settled row must **not** be
rewritten as a fresh attempt: set `status = dishonoured` and `dishonoured_at` on the existing row, leaving
`attempt` intact, and let the retry policy create attempt+1 as a **new row** — **subject to R8** (§6.4), which
withholds the retry entirely when the mandate or its provider can no longer collect. If a receipt was already issued,
generate a **reversal notice** — never delete the receipt (`receipts` is append-only by intent; `audit_events`
forbids mutation outright).

### 6.4 Dishonour policy — **D3 resolved (owner): retry once, then pause + alert**

Reuse the card policy rather than inventing a parallel one: `PAYMENT_MAX_ATTEMPTS`
([config/integrations.php:306](config/integrations.php:306), default 2) and `scheduleRetryOrPause()`
([PaymentProcessor.php:328-337](app/Services/Payments/PaymentProcessor.php:328)).

One rail-specific override: `PAYMENT_RETRY_DELAY_MINUTES` (default 60) is **wrong here** — a 60-minute retry on a
bank debit dishonours again and stacks a second fee. Add `DIRECT_DEBIT_RETRY_DELAY_DAYS`, set clear of the
confirmed settlement window (§2.2), default **5** pending that confirmation. Retry delay resolves per rail.

**R8 — retry requires a collectible mandate.** The retry path in §6.3 creates attempt+1, but a reversal can arrive
**after the means of collection is gone**. The clearest case is the one the lifecycle creates: a `retired` provider
cannot collect (§4.4), and reaching `retired` required every authority against it to be revoked (§4.4 preflight) —
so attempt+1 would lodge against a dead mandate at a provider that refuses new work. Two sections of this plan
pointed in opposite directions; this rule resolves them.

Before scheduling any retry, assert **both**:

1. the authority is `active` and not revoked; **and**
2. its provider's status permits collection (`active` or `draining`).

If either fails, **do not retry**. Instead:

- correct the payment (`dishonoured`, reversal notice per §6.3) — the money is genuinely gone;
- pause the schedule and **alert the advisor**;
- surface the amount as an **outstanding balance requiring a new mandate**, not a pending collection. The client
  still owes it; FSA simply has no authority to take it.

Re-collection requires a **fresh mandate on a collecting provider**, which means fresh client authorisation under
§9.1 — a revoked or stranded mandate cannot be silently resurrected. Any `pending` dishonour fee (§8.1) rides
along in that outstanding balance rather than being billed against a mandate that no longer exists.

This generalises beyond retirement: a client-revoked authority and a `mandate_invalid` event produce the same
condition and take the same path. Retry is a privilege of having a live mandate, never an assumption.

### 6.5 Stale lodgement sweep

If no terminal event arrives, a payment sits in `submitted` forever and R4 blocks the schedule. A scheduled command
reconciles anything `submitted` longer than `DIRECT_DEBIT_STALE_AFTER_DAYS` (default **10**) by querying the
provider, and alerts if still indeterminate. Daily is sufficient.

**Registration.** Payment scheduling lives in **`bootstrap/app.php`**, not `routes/console.php` —
`ProcessScheduledPayments` is registered at [bootstrap/app.php:240](bootstrap/app.php:240) with `->name()` and
`->withoutOverlapping()`. Both new commands go **beside it**, following the same conventions:

- the **stale-lodgement sweep** (this section), daily; and
- the **notice generator** (§5.7), which must run far enough ahead of `collection_day` to satisfy
  `DIRECT_DEBIT_NOTICE_DAYS` — a notice generated late cannot be cured, it just blocks the collection under R6.

`routes/console.php` carries unrelated learning/inspiration schedules; splitting payment work across both files
would hide half the payment pipeline from anyone reading the other.

### 6.6 The 48-hour confirmation deadline is a card assumption

`InstallmentPaymentProcessor` sets `confirmation_deadline = now + 48 hours`
([:304](app/Services/Payments/InstallmentPaymentProcessor.php:304)), and exceeding it forces the installment into
`manual_review` with reason `confirmation_deadline_exceeded` ([:178-179](app/Services/Payments/InstallmentPaymentProcessor.php:178)).

Direct debit settlement is longer than 48 hours (§2.2). Left as-is, **every direct debit lands in manual review
before it can possibly settle** — a silent operational flood. The deadline must be **rail-aware**:
`DIRECT_DEBIT_CONFIRMATION_DEADLINE_DAYS`, set from the confirmed settlement time plus margin, default **7**
pending §2.2. Do not change the card default of 48 hours.

### 6.7 The ambiguous-confirmation loop must not touch lodged debits

Reusing `STATUS_AWAITING_GATEWAY_CONFIRMATION` (§6.1) has a consequence that must be handled explicitly, or the
reuse becomes a defect. `confirmAmbiguous()` scans exactly that status
([InstallmentPaymentProcessor.php:163](app/Services/Payments/InstallmentPaymentProcessor.php:163)) and calls the
**card** `Gateway::findCharge()` ([:185](app/Services/Payments/InstallmentPaymentProcessor.php:185)). For a
direct-debit payment, `findCharge()` returns `unknown()` — its guard only recognises card gateways
([Gateway.php:106-108](app/Services/Payments/Gateway.php:106)), and §4.2 deliberately keeps `gateways()` card-only.

`unknown()` is neither succeeded nor not-charged, so the installment is simply re-deferred each pass until
`confirmation_deadline` elapses and it is dumped into `manual_review` as
`confirmation_deadline_exceeded`. Every direct debit would grind through the card recovery loop and land in manual
review — the §6.6 flood, arriving by a second route even after the deadline is made rail-aware.

The semantic point matters: that status means *"we do not know whether the card charge went through"*. A lodged
direct debit is not ambiguous — it is a **known, expected** wait. The two must not share a recovery path.

**Required in E5:**

- `confirmAmbiguous()` filters to the **card rail only**, via the `activePayment` relation
  (`whereHas('activePayment', fn ($q) => $q->where('rail', 'card'))`). Installments in this status always have an
  active payment — the loop's own first guard sends any without one to `manual_review`
  ([:172-176](app/Services/Payments/InstallmentPaymentProcessor.php:172)) — so the filter cannot silently drop rows.
- Lodged direct debits are reconciled **solely** by the §6.5 stale sweep, which calls the provider's
  `findInvoice()` through `DirectDebitProviderResolver::forPayment()` (§4.4) — never `Gateway::findCharge()`.
- `Gateway::findCharge()` keeps returning `unknown()` for non-card gateways. Do **not** make it
  rail-polymorphic; that would drag the direct-debit provider back into `Gateway` and violate §10.3.

### 6.8 Notice is a precondition of lodgement, enforced in code

**R6 — no lodgement without a matching delivered notice.**

**Sequencing matters.** The check runs **before the `Payment` row is created**, keyed by
`payment_installment_id` (or `payment_schedule_id`) + intended debit date — never by `payment_id`, which does not
exist yet (§5.7). Both processors must therefore evaluate R6 at the **top** of the direct-debit branch, ahead of
the payment insert at [InstallmentPaymentProcessor.php:284](app/Services/Payments/InstallmentPaymentProcessor.php:284)
and [PaymentProcessor.php:160](app/Services/Payments/PaymentProcessor.php:160). A blocked collection must leave
**no** payment row behind — otherwise it occupies the in-flight index (§5.3) and blocks the retry it was supposed
to permit. Once lodgement succeeds, stamp `payment_id` onto the notice for audit.

The notice must be:

1. **unsuperseded** (`superseded_at IS NULL`),
2. **delivered** (`sent_at IS NOT NULL`) at least `DIRECT_DEBIT_NOTICE_DAYS` before `notified_debit_date`,
3. **exact on amount** — `notified_amount` equals the amount about to be lodged, **including `fee_applied`**, and
4. **exact on date** — `notified_debit_date` equals the debit date being submitted.

Any mismatch means the client was told something different from what is about to happen, which is the specific
harm the notice requirement exists to prevent.

**Amount changes invalidate the notice.** This is the trap worth naming: a dishonour fee applied to an installment
(§8.1) changes `fee_applied`, and therefore the amount, **after** a notice may already have gone out. Any change
to amount or date must set `superseded_at`, issue a fresh notice, and restart the notice period. A fee cannot be
debited on the strength of a notice that predates it.

**A blocked lodgement is a skip, not a failure.** Like R4, it must not consume a retry attempt or push the schedule
toward `paused` — the client did nothing wrong. Log the skip, and alert the advisor if the same payment is blocked
past its due date, since a persistent block means the notice pipeline is broken.

### 6.9 The first debit date must clear the notice window

R6 blocks a lodgement whose notice period has not elapsed. That is correct as a guard, but it is **not** a
scheduling strategy: today's `ScheduleBuilder` will happily create direct-debit schedules that R6 can never
release.

- **One-off** schedules default `next_run_at` to **`now()`**
  ([ScheduleBuilder.php:200](app/Services/Payments/ScheduleBuilder.php:200)) — immediately due, so a client signing
  off today gets a debit that is due before any notice can be delivered. The notice generator cannot cure it
  either: a notice for a date already past can never satisfy "delivered `DIRECT_DEBIT_NOTICE_DAYS` before the
  debit date". The collection is **permanently blocked**, and the only symptom is a skip in a log.
- **Monthly retainer** schedules take the next `collection_day`
  ([:196-198,203-213](app/Services/Payments/ScheduleBuilder.php:196)). If sign-off happens within
  `DIRECT_DEBIT_NOTICE_DAYS` of that day — signing on the 31st with `collection_day = 1` — the first debit lands
  inside the window and is blocked the same way.

**R7 — for `TYPE_DIRECT_DEBIT`, the first debit date is floored at `today + DIRECT_DEBIT_NOTICE_DAYS + margin`.**
For monthly cadences, a `collection_day` falling inside the window rolls to the **following** month's collection
day rather than being nudged off the agreed date — the collection day is what the client agreed to, and silently
shifting it would itself be a notice discrepancy.

Where an explicit date is supplied and validated against the agreed collection date
([:190](app/Services/Payments/ScheduleBuilder.php:190)), a direct-debit date inside the notice window is
**rejected at sign-off** with an explicit message, not silently accepted and then blocked at collection. The
advisor finds out while they can still act.

**Card behaviour is unchanged** — R7 is rail-conditional. Cards have no notice obligation and `now()` is correct
for them.

---

## 7. Webhooks

### 7.1 Route and controller

`POST /api/webhooks/payments/direct-debit` → `PaymentWebhookController::directDebit()`, following the existing
`stripe()`/`windcave()` shape ([routes/api.php:21-25](routes/api.php:21),
[PaymentWebhookController.php:22-45](app/Http/Controllers/Webhook/PaymentWebhookController.php:22)). The private
`handle()` already takes a verifier + reconciler closure pair, so the new rail slots in without changing it.

The path carries a **provider segment** — `/api/webhooks/payments/direct-debit/{provider}` — validated against the
registered provider slugs. This is not a portability leak: the segment is a registry key, and the controller,
reconciler, and state machine remain provider-neutral.

A single neutral URL would be wrong here. Signature schemes differ per provider, so verification must know **which**
adapter to use before it can trust anything in the body — and inferring the provider *from* an unverified payload
means trusting attacker-controlled input to choose the key that authenticates it. Trying each registered adapter in
turn is no better: it turns verification into an oracle and makes a signature-confusion attack across providers
possible during a drain period, exactly when two adapters are live.

The segment also makes drain behaviour expressible: `active`, `draining`, **and `retired`** providers accept
deliveries — a retired provider can still owe a late reversal (§4.4) — while only `closed` is rejected at the
route. Each provider's webhook registration points at its own path, so switching providers means registering a new
URL, not repointing an existing one; old-provider deliveries keep arriving and verifying correctly throughout the
drain and the reversal window.

### 7.2 Verification and dedupe — both differ from Stripe

**Signature.** `EzypayWebhookAdapter::verify()` computes **HMAC-SHA1** over `$request->getContent()` — the **raw
body**. Never a decoded-and-re-encoded array; key order or whitespace changes break the hash.

**No timestamp window.** Ezypay's signature carries no timestamp, so `timestampOutOfWindow()`
([PaymentWebhookVerifier.php:84](app/Services/Payments/PaymentWebhookVerifier.php:84)) cannot apply. **Do not
fabricate a timestamp to reuse it.**

**Event id — the fix.** `PaymentWebhookReconciler::eventId()` reads `$payload['id']`
([:419](app/Services/Payments/PaymentWebhookReconciler.php:419)). Ezypay uses **`requestId`**. Extraction moves to
`ProviderWebhookAdapter::eventId()` so each provider names its own field. If the adapter returns `null`, the event
is **rejected**, not stored with a synthesised id — an event without a stable id cannot be deduplicated, and
accepting it would silently disable replay protection.

Replay protection therefore rests on two mechanisms, both of which must be asserted in tests:

1. **`unique(['gateway','event_id'])`** on `payment_webhook_events` — a replayed delivery is a duplicate-key no-op.
2. **Amount cross-check** — but see §7.2a: this is **not** a single comparison against `payments.amount`.

Record in the WO that SHA-1 is the provider's protocol choice, not FSA's, and that the above are the compensating
controls. If the provider later offers SHA-256, switch.

### 7.2a Settlement is net of provider fees — do not amount-match it against the invoice

`paymentMismatchReason()` compares the event amount against `payments.amount`
([:292-306](app/Services/Payments/PaymentWebhookReconciler.php:292)). That is correct for card events, where the
charged amount and the notified amount are the same number. **It is wrong for settlement.** Ezypay's settlement
payload reports the **actually deposited** amount, net of Ezypay's transaction fee — so it will legitimately
differ from the gross invoice amount, *every time*. Reusing the card check unchanged would reject **every**
settlement webhook: no receipt, no schedule advance, R2 never satisfied, and the whole rail silently dead with
nothing but `amount_mismatch` in the log.

Validation therefore splits by canonical event:

| Event | Amount rule |
|---|---|
| `lodged` (invoice created) | **Gross match** — must equal `payments.amount`. Reject on mismatch, as today. |
| `settled` | **Reconcile, don't match** — `settled_net + provider_fee` must equal the gross within a tolerance. A break is an **alert**, never a rejection of the settlement. |
| `dishonoured` / `reversed` | Correlate by reference; amount is recorded, not gate-checked. |

New columns on `payments`, all nullable and set at settlement:

| Column | Notes |
|---|---|
| `settled_net_amount` (decimal 12,2) | what actually landed in FSA's account |
| `provider_fee_amount` (decimal 12,2) | the provider's transaction fee, netted at settlement |
| `settlement_ref` (string) | provider settlement/batch reference for reconciliation |

**The client's receipt still shows the gross `payments.amount`.** The provider's transaction fee is FSA's cost of
collection, not something the client paid — putting it on their receipt would be wrong. `settled_net_amount` and
`provider_fee_amount` exist for FSA's own bank reconciliation and expense recognition.

**Do not confuse the two fee types.** The provider **transaction fee** here is netted automatically from
settlement and is FSA's expense. The **dishonour fee** in §8 is a separate charge, billed to the client under D4
via `fee_applied`. They are different amounts, different directions, and different accounting treatment.

If E0 finds that settlement arrives batched across multiple payments rather than one event per payment, these
columns become a `payment_settlements` child table instead — flag it at E0 rather than discovering it in E6.

### 7.3 Canonical event mapping

The reconciler handles **canonical** events only; provider names are the adapter's problem.

| Canonical | FSA effect |
|---|---|
| `mandate_linked` | Authority → `active`; token stored in envelope |
| `mandate_invalid` | Authority → `failed`; pause schedule; notify advisor |
| `lodged` | Correlate to `Payment`; confirm `submitted` |
| `settled` | **`succeeded`** → `settled_at`, receipt, advance schedule, installment → `settled` |
| `dishonoured` | → §6.4 policy + §8 fee record |
| `reversed` | §6.3 handling. **No Ezypay event maps here until E0 confirms which one** — see below. |
| `unmapped` | Recorded and ignored, never inferred — `ignore($event, 'unmapped_event_type')` following the existing pattern ([:392](app/Services/Payments/PaymentWebhookReconciler.php:392)) |

Ezypay's first-adapter mapping: `payment_method_linked`/`payment_method_valid` → `mandate_linked`;
`payment_method_invalid`/`payment_method_replaced` → `mandate_invalid`; `invoice_created` → `lodged`;
`transaction_settled` → `settled`; dishonour event *(name TBC — §2.2)* → `dishonoured`.

**`credit_note_*` stays `unmapped` until E0.** v1.4 mapped the whole family to `reversed`; that was a wildcard over
three separately documented events — `credit_note_created`, `credit_note_paid`, `credit_note_failed` — with
materially different meanings. A **failed** credit note in particular means the reversal did *not* complete, so
treating it as `reversed` would mark a payment clawed back when the money is still with FSA, and would trigger a
retry and a dishonour fee against a client who owes neither.

This is exactly the "recorded and ignored, never inferred" rule applied to a case where the plan was doing the
inferring. E0 confirms which single event carries a completed reversal and what terminal state it implies; only
that one gets mapped. The rest stay `unmapped` — visible in `payment_webhook_events`, acting on nothing.

### 7.4 Retry hazard on lodgement

CLAUDE.md requires `ResilientHttp`, and `RetryPolicy` retries. **A retried invoice-creation POST can debit the
client twice.** Confirm idempotency support (§2.2), then:

- If supported — send a key derived from `payment_id` + `attempt`, matching the existing convention
  ([PaymentProcessor.php:112](app/Services/Payments/PaymentProcessor.php:112)).
- If not — lodgement runs at **`maxAttempts: 1`**, and any ambiguous outcome (timeout, 5xx, connection error)
  raises `AmbiguousPaymentOutcome` and leaves the payment `submitted` for the §6.5 sweep. **Never auto-retry an
  ambiguous lodgement.** This mirrors `Gateway::charge()` already refusing to fail over on
  `PaymentGatewayException` ([Gateway.php:93-100](app/Services/Payments/Gateway.php:93)).

---

## 8. The dishonour fee — **D4 resolved (owner): passed to the client**

### 8.1 Mechanism

`DishonourHandler` writes a `payment_dishonour_fees` row (`status = pending`) using the **amount the provider
reported**. The next installment for that client accumulates any `pending` fees into `fee_applied` (§5.6),
recomputes `net_amount`, and flips each fee to `billed` with `applied_to_payment_id` set. The
`unique(payment_id)` index prevents the same dishonour being billed twice; `fee_applied` is the visible surface,
and `payment_dishonour_fees` is the audit record linking each cent back to the dishonour that produced it.

The two must reconcile: the sum of `billed` fees pointing at a payment must equal that installment's
`fee_applied`. E7 ships an assertion for this — a drift between the visible charge and the audit trail is exactly
the failure this split is designed to make detectable.

### 8.2 Why not `BillingAdjustment`

It is **credit-only**: sole type `TYPE_SCOPING_FEE_CREDIT` ([BillingAdjustment.php:16](app/Models/BillingAdjustment.php:16)),
and the allocator only ever reduces, clamping at zero ([BillingAdjustmentAllocator.php:53](app/Services/Payments/BillingAdjustmentAllocator.php:53)).
A debit type would invert that invariant for every existing consumer — a negative `available` flowing through
arithmetic written to assume non-negative. A separate table is safer and more honest about what the record is.

### 8.3 Two things that must not be assumed

**GST treatment.** NZ GST on dishonour fees is genuinely contested — treatment differs depending on whether the fee
is consideration for a supply or a damages-style recovery. `GstCalculator` must **not** be given a silent default.
`gst_treatment` is explicit (§5.2) and requires **sign-off from FSA's accountant** before E7 ships.

**Disclosure.** Passing a fee to a client requires disclosure **before** they authorise the debit — in the proposal
fee section and the DDA wording (§9.1). A fee billed without prior disclosure is a Fair Trading Act exposure. E7
does not ship without E4's disclosure copy.

---

## 9. NZ compliance and security

### 9.1 Direct Debit Authority

A NZ direct debit requires the payer's authority to the initiator, and the initiator must give **advance notice of
amount and date**.

- Authority wording, initiator identity, and **fee disclosure** (§8.3) are captured at sign-off in the existing
  consent/T&C machinery — no parallel consent store.
- A **pre-debit notification** goes to the client ahead of each collection via the existing channel-preference
  notifications. `DIRECT_DEBIT_NOTICE_DAYS` (default 3), never zero. This is **not advisory**: every notice is a
  durable record (§5.7) and lodgement is **blocked** without a matching, unsuperseded, delivered one — see R6
  (§6.8). Changing the amount or date supersedes the notice and restarts the period.
- Revocation already exists (`ScheduleBuilder::revokeAuthority()`
  [ScheduleBuilder.php:91](app/Services/Payments/ScheduleBuilder.php:91)) and must also revoke the **provider-side
  mandate**, not just the local row.

Exact DDA wording is a **legal review item**, not a developer decision. E4 carries placeholder copy marked
`[NEEDS LEGAL REVIEW]`.

### 9.2 Credentials

Provider credentials live in `IntegrationCredentials` + `KeyEnvelope`, under a provider-keyed config block
following the Stripe/Windcave shape at [config/integrations.php:304-323](config/integrations.php:304):

```php
'direct_debit' => [
    // Rail master switch. Stays false through E1-E8; flipped ONLY in E9 after the
    // go-live checklist (§10.8). E1 fails closed on it (§10.1).
    'enabled' => (bool) env('DIRECT_DEBIT_ENABLED', false),

    // CREDENTIALS ONLY. Lifecycle status lives in direct_debit_providers (§4.4, §5.9),
    // because the retirement guard is a data condition config cannot check.
    // This does NOT decide how existing mandates or payments are collected —
    // those follow their own stored gateway slug (§4.4).
    'providers' => [
        'ezypay' => [
            'live' => (bool) env('FEATURE_EZYPAY_LIVE', false),
            'base_url' => env('EZYPAY_BASE_URL'),
            'partner_client_id' => env('EZYPAY_PARTNER_CLIENT_ID'),
            'partner_client_secret' => env('EZYPAY_PARTNER_CLIENT_SECRET'),
            'merchant_id' => env('EZYPAY_MERCHANT_ID'),
            'webhook_client_key' => env('EZYPAY_WEBHOOK_CLIENT_KEY'),
        ],
    ],
],
```

Access and refresh tokens are KeyEnvelope-encrypted at rest, never logged, never placed in audit `before`/`after`
payloads. Refresh-token rotation must handle the 7-day expiry without a gap. `FEATURE_EZYPAY_LIVE=false` by
default; sandbox first. **Keys go in `.env` only and are never committed.**

### 9.3 Bank account numbers never touch FSA

Capture runs through the provider's **hosted** flow; FSA stores only the returned token. Add
`assertNoRawBankAccount()` alongside `assertNoRawPan()` ([Gateway.php:239](app/Services/Payments/Gateway.php:239)),
rejecting NZ bank account patterns (`BB-bbbb-AAAAAAA-SS`, 15–16 digits) in any persisted metadata or webhook
payload. Same failure mode: throw, don't sanitise silently.

---

## 10. Hard rules

**10.1 Fix the type/gateway mismatch at all three layers first (E1, blocking).** Type and gateway are validated
**independently** in three places — [ProposalSignoffController.php:342-343](app/Http/Controllers/Portal/ProposalSignoffController.php:342),
[SignoffFlow.php:190-200](app/Services/Proposals/SignoffFlow.php:190), and
[AuthorityCapture.php:34-40](app/Services/Payments/AuthorityCapture.php:34) — so `direct_debit` + `stripe` is
storable through any of them. All three move to a cross-field check against `gatewaysForType($type)`.
`AuthorityCapture` is the last line of defence and must not be skipped because the controller was fixed.

[ProposalSignoffFlowTest.php:251](tests/Feature/Proposals/ProposalSignoffFlowTest.php:251) currently **asserts that
`direct_debit` + `windcave` is accepted**. That test encodes the defect; **invert it** to assert rejection. This is
the one place §10.5's "no test should need editing" does not apply, and it is deliberate.

Audit existing rows for invalid combinations before migrating.

**E1 must fail closed.** This is the trap in shipping E1 early: the sign-off UI **already** offers "Direct debit"
([ProposalSignoff.tsx:1270](resources/js/pages/portal/ProposalSignoff.tsx:1270)). If E1 introduces
`gatewaysForType(TYPE_DIRECT_DEBIT)` returning a live provider, then `direct_debit` + `ezypay` becomes a **valid,
storable combination before E4's mandate and E5's collector exist** — turning today's inert-but-harmless record
into a client who has signed off on a debit FSA cannot lawfully take or technically collect. That is strictly worse
than the current defect.

Therefore:

- `directDebitGateways()` **short-circuits on the flag first**: if `DIRECT_DEBIT_ENABLED` is false it returns
  **`[]` immediately, without touching the provider registry**. This ordering is not stylistic — E1 ships before
  E3 creates `direct_debit_providers` (§5.9), so a resolver call on the public validation path would query a table
  that does not yet exist. Only when the flag is true does it consult the resolver for a ready provider (§4.4).
- Default `DIRECT_DEBIT_ENABLED=false`; it flips **only in E9**, after the go-live checklist (§10.8) — not when the
  collector lands.
- The **readiness check stays loud** either way (§4.4): a flag/registry/credentials mismatch faults on the health
  dashboard. Failing closed on the public path must not mean failing quietly to operators.
- With the rail disabled, `gatewaysForType(TYPE_DIRECT_DEBIT)` is empty, so validation rejects the type outright at
  all three layers. The rejection message must be the honest one — *"Direct debit is not currently available"* —
  not a generic "invalid gateway", which would read as a bug to an advisor.
- The sign-off UI hides the option based on a **server-provided** flag, never a client-side constant. The server
  check is the gate; the UI change is courtesy.
- Disabling gates **writes only**. Reading, collecting, and reconciling existing direct-debit authorities must keep
  working once the rail is enabled — do not route the disable flag through the collection path.

**10.2 Never cross rails on failure.** A failed direct debit must **never** be retried on a card, and vice versa.
`Gateway::secondaryGateway()` stays a card-only flip; `DirectDebitCollector` has **no failover path at all**. D3's
"retry once then pause" means retry *the same rail*. The rejected alternative — falling back to a card authority —
would charge a rail the client did not authorise for that payment.

**10.3 Do not modify `Gateway`.** The card rail's behaviour, tests, and failover semantics are out of scope. If a
change to `Gateway` seems necessary, the rail boundary is being violated — raise it rather than edit it.

**10.4 `succeeded` means settled.** Every consumer — receipts, dashboards, revenue reporting, accounting export —
keeps its current meaning. New states are additive.

**10.5 Existing card behaviour is unchanged.** No test should need editing to accommodate this work, with the single
deliberate exception in §10.1. Anything else is a regression, not a fixture update.

**10.6 A provider name may name things, never decide things.** See the §4.1 table for the exact boundary.
Permitted: adapter registration, registry slugs, the webhook route segment, credential config keys, and stored
`gateway` values. Forbidden: domain model constants, the payment state machine, validation, UI choices, reporting,
and any branch on the literal. Code resolves through `DirectDebitProviderResolver`.

**10.7 Never resolve a provider globally.** The **registry's `active` row** (`direct_debit_providers`, §5.9)
selects the provider for **new mandates only**; config supplies **credentials only** and never selects a provider.
Existing mandates and payments resolve from their **own stored slug**, for life (§4.4). Neither a config change nor
a registry change may alter how an existing mandate is collected, or orphan in-flight settlement webhooks.

**10.8 The rail is activated last, once, in E9.** `DIRECT_DEBIT_ENABLED` is flipped by **no other work order**
(§11, E9). Any WO that would make the rail reachable before settlement reconciliation is live is wrong — see the
E9 rationale.

---

## 11. Work orders

| WO | Title | Depends on | Notes |
|---|---|---|---|
| **E0** | Sandbox + contract: resolve every §2.2 unknown, including **creating a `TRANSACTION_SETTLED` subscription and observing a live delivery**, and the **reversal window** | — | **Blocks E2 onward.** No **provider adapter, mandate, lodgement, or webhook** code until the dishonour event, settlement time, fees, reversal window, settlement subscription, and one-off invoice shape are confirmed. **E1 is explicitly exempt** and may ship first — it fixes a live defect and touches no provider surface. Record answers in §2. |
| **E1** | Cross-field type/gateway validation at all **three** layers; `directDebitGateways()` **short-circuiting on the flag before any registry lookup**, `allGateways()`, `gatewaysForType()`; **`DIRECT_DEBIT_ENABLED` fail-closed + server-driven UI flag**; invert the stale test | — | §10.1. Shippable alone; fixes a live defect independent of any provider. Ships **before** the registry exists (E3), so it must not resolve providers. **Must leave direct debit unselectable** until E9. |
| **E2** | `DirectDebitProviderClient` + `ProviderWebhookAdapter` contracts, `Fake`/`Fallback`, `Ezypay` adapter, `DirectDebitTokenStore`, **four-mode resolver (§4.4)**, **`DirectDebitProviderLifecycle` with transactional `switchActiveProvider()` + retirement/closure guards + readiness check (§4.4, §5.9)**, credentials config | E0, E1, E3 | §4.2, §4.4, §9.2. OAuth lifecycle + refresh rotation; §7.4 retry rule. |
| **E3** | Schema: `payments` columns + **`rail` DROP DEFAULT (§5.8)**, **`PaymentAuthority::STATUS_PENDING` (§5.5)**, `payment_dishonour_fees`, **`payment_installments.fee_applied` (§5.6)**, **`direct_debit_notices` keyed to installment/schedule + live-notice indexes (§5.7)**, **`direct_debit_providers` + single-active index (§5.9)**, **both** partial indexes (§5.3), **`submitted` result key in both processors** (§5.4), RLS | E1 | §5. Notices are **never** keyed to `payment_id` (§5.7). Deliberately excludes settlement storage — see E3b. |
| **E3b** | **Settlement storage (§7.2a)**: three columns on `payments` **or** a `payment_settlements` child table | **E0** | Split from E3 on purpose. E0 decides whether settlement is one event per payment or **batched**, and that answer changes the shape. Building this before E0 answers means migrating live financial data later. E3 does not wait for it. |
| **E4** | Mandate setup: **rail-branched `paymentSetup()` returning a hosted-capture handle, never a `client_secret` (§4.5)**, **capture creates a `pending` authority; signature blocked with a waiting state; promotion on `mandate_linked`; pending expiry + advisor alert (§5.5)**, DDA wording, fee disclosure, **pre-debit notice generator scheduled in `bootstrap/app.php` (§6.5) + amount frozen at notice + delivery recording + supersede-on-change (§5.7)**, **R7 first-debit-date floor in `ScheduleBuilder` (§6.9)**, provider-side revocation | E2, E3 | §9.1, §6.9. `[NEEDS LEGAL REVIEW]` copy. |
| **E5** | Rail dispatch in **`InstallmentPaymentProcessor`** (primary) **and** `PaymentProcessor` (legacy); `DirectDebitCollector`; rail-aware `confirmation_deadline`; **rail-filtered `confirmAmbiguous()` (§6.7)**; **R6 notice precondition evaluated *before* the payment insert, leaving no row when blocked (§6.8)**; R4 in-flight guard; `attempted_gateway` from the authority (§4.4) | E3, E4 | §4.3, §6.2, §6.6, §6.7, §6.8. **Both processors, or integration-scope proposals bypass the rail.** |
| **E6** | `DirectDebitWebhookReconciler` + adapter-based verify/eventId + route + canonical mapping + **per-event amount rules (§7.2a)** | E2, E3, **E3b** | §7. |
| **E7** | `DishonourHandler`, fee billing via `fee_applied` + **receipt fee line (§5.6)** + fee/audit reconciliation assertion, stale-lodgement sweep using `forPayment()`/`findInvoice()` (§6.7), advisor alerts | E5, E6 | §6.4, §6.5, §8. Gated on §8.3 GST sign-off. |
| **E8** | Advisor + client UI: **rail-branched `AuthorityFields` — hosted capture for direct debit, Stripe.js not loaded on that path (§4.5)**, "Direct debit" selection, settlement-pending visibility, dishonour surfacing | E5, E6 | Client UI selects the **rail**, never the provider (§13 D7). "Awaiting settlement" must be visibly distinct from "Paid". |
| **E9** | **Activation only.** Flip `DIRECT_DEBIT_ENABLED` after the go-live checklist below passes | E5, E6, E7, E8 | §10.8. **No other WO touches this flag.** |

### E9 — why activation is its own work order

Flipping the flag is the moment the rail becomes reachable by real clients, and the failure mode if it happens too
early is **silent**. If the flag flips at E5 — where the collector lands but the reconciler does not — FSA would
lodge real debits against real bank accounts with **nothing listening for the outcome**. Every payment would sit in
`submitted` forever: no receipts (R2 never fires), schedules frozen behind the R4 in-flight guard, and clients
debited with no confirmation. The money moves; the system never notices. Nothing would alert, because from the
platform's point of view each lodgement succeeded.

The sign-off UI **already** exposes a "Direct debit" option
([ProposalSignoff.tsx:1270](resources/js/pages/portal/ProposalSignoff.tsx:1270)), so the server flag is the only
thing standing between a half-built rail and a live client. It flips **once**, last.

**Go-live checklist — every item verified before the flip:**

1. Webhook route live, signature verified, `requestId` dedupe proven (E6).
2. Per-event amount rules live — `lodged` gross-matched, `settled` reconciled (§7.2a, E3b + E6).
3. Notice generator scheduled and delivering; R6 and R7 enforced (E4, E5).
4. Dishonour handling, fee application, and the stale-lodgement sweep live (E7).
5. Advisor and client UI distinguish "awaiting settlement" from "paid" (E8).
6. **A sandbox end-to-end run observed**: mandate → notice → lodge → `transaction_settled` → receipt issued for the
   gross amount, plus a dishonour path producing a fee and an advisor alert.
7. Registry has exactly one `active` provider with matching credentials; readiness check green (§4.4).
8. **Mandate capture works end-to-end in the portal** (§4.5): selecting "Direct debit" reaches hosted capture, not
   the "Stripe card setup only" message, and the authority activates on `mandate_linked` rather than on redirect.

Until then, every test asserting the public flow must confirm direct debit remains **rejected**, not merely
unused.

---

## 12. Testing

Every test binds `FakeDirectDebitProviderClient`; no test reaches a live or sandbox endpoint.

**Must-pass negative tests** — these encode the failure modes above; a green suite without them is not evidence:

1. A `submitted` payment produces **no receipt** and does **not** advance `next_run_at` (R1, R3).
2. `settled` produces exactly one receipt; a **replayed** delivery produces **none** (§7.2 dedupe).
3. A **`lodged`** webhook whose gross amount or currency disagrees with the `Payment` row is **rejected** (§7.2a).
3a. A **`settled`** webhook whose net amount is **less than** the gross by exactly the provider fee is
    **accepted**, sets `settled_net_amount` / `provider_fee_amount`, and issues a receipt for the **gross** — the
    regression that would otherwise kill every settlement (§7.2a).
3b. A `settled` webhook where `net + fee` does **not** reconcile to gross raises an **alert** but is still
    processed — settlement is never rejected on an amount difference (§7.2a).
4. A webhook with a tampered body fails HMAC verification (§7.2 raw-body rule).
5. A delivery with **no `requestId`** is rejected, not stored with a synthesised id (§7.2).
6. A dishonour after `succeeded` marks the original row `dishonoured` **without** altering `attempt`; the retry
   lands as a new row (§6.3).
7. A second collection is **skipped, not failed**, while a prior debit is `submitted`; the installment partial
   index **rejects** a concurrent second `submitted` row (R4, §5.3).
8. A direct-debit failure **never** produces a card charge — assert `Gateway::charge()` is not called (R5).
9. An ambiguous lodgement (timeout) leaves the payment `submitted` and is **not** retried (§7.4).
10. One dishonour yields exactly one fee row; replaying the dishonour yields no second fee (§8.1).
11. Bank account digits in metadata or a webhook payload are rejected (§9.3).
12. `direct_debit` + `stripe` is rejected at **each** of the three layers independently (§10.1).
13. **An installment-backed** direct-debit proposal collects via the new rail — not `Gateway::charge()` — proving
    §0.4 is covered.
14. A lodged debit does **not** hit `confirmation_deadline_exceeded` / `manual_review` before its settlement window
    elapses (§6.6).
15. Both processors return a `submitted` counter and no undefined-index warning is emitted (§5.4).
16. After the **registry's `active` provider** changes (§5.9), an existing mandate still collects through **its
    own** provider, and `attempted_gateway` records that provider — not the newly active one (§4.4).
17. A webhook from a `draining` provider verifies and processes normally; **`retired` also accepts** (late
    corrections), and only a **`closed`** provider's path is rejected at the route (§4.4, §7.1).
18. Retiring a provider that still has unrevoked authorities or `submitted` payments **raises and rolls back**,
    leaving the registry row unchanged (§4.4, §5.9). A second `active` provider is rejected by the unique index,
    and the readiness check faults when no provider is `active` while the rail is enabled.
19. A lodged direct debit is **not** selected by `confirmAmbiguous()`, never calls `Gateway::findCharge()`, and does
    not reach `manual_review` by that path (§6.7).
20. A dishonour fee lands in `fee_applied`, renders as its **own line** on the receipt, and the sum of `billed`
    fees reconciles exactly with the installment's `fee_applied` (§5.6, §8.1).
21. Applying a `BillingAdjustment` credit to an installment **preserves** `fee_applied` and recomputes
    `net_amount` correctly — the silent-drop regression (§5.6).
22. With `DIRECT_DEBIT_ENABLED=false`, `direct_debit` is rejected at **all three** validation layers with the
    "not currently available" message, and the server-provided UI flag is false — E1 cannot make the rail
    selectable before it is collectible (§10.1).
22a. With the flag false and **no `direct_debit_providers` table present** (the E1-before-E3 state),
    `directDebitGateways()` returns `[]` **without erroring** — it must not touch the registry (§10.1).
23. `lodge()` **refuses** when no notice exists, when the notice is superseded, when it was delivered inside the
    notice window, or when `notified_amount` disagrees with the amount being lodged (R6, §6.8).
24. Applying a dishonour fee to an installment with an existing notice **supersedes** it; the next lodgement is
    blocked until a fresh notice has been delivered for the new, fee-inclusive amount (§6.8).
25. A blocked lodgement does **not** consume a retry attempt and does **not** move the schedule toward `paused`
    (§6.8).
26. Inserting a `payments` row without an explicit `rail` **fails** (§5.8), and a direct-debit payment is never
    observable with `rail = 'card'` at any point — the §6.7 filter's underlying invariant.
27. R6 resolves a notice for a due collection **before any `Payment` row exists**, keyed by installment/schedule +
    date (§5.7), and a blocked lodgement leaves **no** payment row behind — so the in-flight index stays free for
    the retry (§6.8).
28. An adjustment landing between notice and lodgement **supersedes** the notice rather than silently changing the
    amount; the frozen `net_amount` is what gets lodged (§5.7).
29. A **`retired`** provider still verifies and processes a late `reversed` event, correcting a `succeeded`
    payment; the same delivery to a **`closed`** provider is rejected at the route (§4.4, §7.1).
30. `retired → closed` is **refused** while the reversal window is still open, and permitted once it has elapsed
    with no `submitted` payments (§4.4).
31. **Signing off a one-off direct debit today** produces a first debit date at least `DIRECT_DEBIT_NOTICE_DAYS`
    out — never `now()` — so the very first collection is not born permanently blocked (R7, §6.9).
32. A monthly direct-debit sign-off falling **inside** the notice window rolls to the **following** month's
    `collection_day`, rather than shifting off the agreed day (R7, §6.9).
33. An explicit direct-debit date inside the notice window is **rejected at sign-off** with a clear message, not
    accepted and then silently blocked at collection (§6.9).
34. A **card** one-off schedule still defaults to `now()` — R7 is rail-conditional (§6.9, §10.5).
35. A `credit_note_failed` (or any unconfirmed `credit_note_*`) event is recorded as **`unmapped`** and changes
    **no** payment state — it must not mark a payment `reversed`, trigger a retry, or create a dishonour fee
    (§7.3).
36. `forNewMandate()` resolves from the `direct_debit_providers` active row; changing credentials config alone
    does **not** change which provider new mandates use (§4.4).
37. A late `reversed` event against a **`retired`** provider corrects the payment and raises an outstanding
    balance + advisor alert, and creates **no** attempt+1 against the dead mandate (R8, §6.4).
38. A reversal against a **client-revoked** authority takes the same no-retry path, and a `pending` dishonour fee
    rides in the outstanding balance rather than being billed to the revoked mandate (R8, §8.1).
39. **At every WO before E9**, the public sign-off flow still **rejects** direct debit — asserted after E5 and
    after E6 specifically, so a collector without a reconciler can never be reachable (§10.8, E9).
40. **Portability proof — a non-Ezypay fake provider** is registered as `active` and the whole rail works through
    it end-to-end: mandate capture, lodgement, settlement, dishonour. Nothing outside the adapter layer references
    a provider by name, and no `PaymentAuthority` constant exists for one (§4.1, §4.2, §10.6). This is the only
    test that actually proves the scaffold swaps rather than asserting it.
41. `switchActiveProvider()` **verifies the incoming provider before** any status change, moves both rows in **one**
    transaction, and rolls back leaving the original active provider untouched when verification fails — never
    zero-active or two-active (§4.4).
42. Selecting "Direct debit" at sign-off reaches **hosted capture**, not the Stripe-only rejection; the response
    carries no `client_secret`; Stripe.js is not loaded on that path; and the authority stays inactive until
    `mandate_linked` arrives — a browser redirect alone does not activate it (§4.5).
43. Direct-debit capture creates a **`pending`** authority; sign-off signature is blocked with the waiting message
    (not "authority required"); `mandate_linked` promotes it to `active` and unblocks signature; `mandate_invalid`
    fails it (§5.5).
44. A `pending` authority is **never chargeable** — `DirectDebitCollector` refuses it, and R8's live-mandate check
    treats `pending` as not-live (§5.5, §6.4).
45. A `pending` authority with no confirming webhook **expires** to `failed` after
    `DIRECT_DEBIT_MANDATE_PENDING_DAYS` and alerts the advisor — the proposal does not stall silently (§5.5).
46. Card authorities are still created **`active`** on tokenisation, and the card sign-off path is untouched
    (§5.5, §10.5).
47. Full card-path regression suite passes **unmodified**, except the deliberate inversion in §10.1 (§10.5).

---

## 13. Open decisions

| # | Decision | Blocks | Status |
|---|---|---|---|
| D1 | Who owns the billing cycle | E5 | **Resolved** — FSA; one-off invoices (§3) |
| D2 | Receipt timing | E6 | **Resolved** — on settlement only (R2) |
| D3 | Dishonour policy | E7 | **Resolved** — retry once, then pause + alert (§6.4) |
| D4 | Dishonour fee incidence | E7 | **Resolved** — passed to client (§8) |
| D5 | **GST treatment of the dishonour fee** | E7 | **Open — FSA's accountant** (§8.3) |
| D6 | **DDA + fee disclosure wording** | E4 | **Open — legal review** (§9.1) |
| D7 | Provider selection exposure | E8 | **Resolved (reviewer)** — client UI selects "Direct debit"; provider lives in config/admin (§4.1, §10.6) |
| D8 | Do existing card clients get offered a switch, or new proposals only? | E8 | **Open** |
| D9 | Contracted settlement time and fee schedule | E0 | **Open** — §2.2 |

---

## 14. Out of scope

Card rail changes; provider-side subscriptions or native dunning; multi-currency (NZD only, per
[Gateway.php:49-51](app/Services/Payments/Gateway.php:49)); non-NZ direct debit; BECS/SEPA; replacing Windcave;
refunds beyond the reversal handling in §6.3; migrating existing card authorities.

---

## 15. Block for CLAUDE.md (add on **E5** merge — these are code rules and apply as soon as the rail's code exists; the rail itself is not *activated* until **E9**)

```markdown
## Direct Debit Rules

- Card and direct debit are separate rails. `PaymentAuthority.type` selects the rail; a failure on one rail never
  falls back to the other. `Gateway::gateways()` stays card-only — validate with `gatewaysForType()`.
- Rail dispatch belongs in BOTH `InstallmentPaymentProcessor` (primary) and `PaymentProcessor` (legacy).
  Installment-backed proposals are the main path.
- The direct-debit provider is pluggable. A provider name may NAME things — adapter registration, registry slugs,
  the webhook route segment, credential config keys, stored `gateway` values — but may never DECIDE things: no
  provider constant on `PaymentAuthority`, no provider in the state machine, validation, UI, or reporting, and no
  branch on the literal. Resolve via `DirectDebitProviderResolver`. A swap never rewrites stored values.
- Direct-debit capture creates a `pending` authority. Only `mandate_linked` promotes it to `active`; a browser
  redirect does not. Signature stays blocked meanwhile with an explicit waiting state, `pending` is never
  chargeable, and it expires to `failed` with an advisor alert if the webhook never arrives.
- Becoming the active provider goes through `switchActiveProvider()`: verify the incoming provider's credentials,
  webhook registration, capture and settlement capability, then move old→draining and new→active in ONE audited
  transaction. Never two status edits.
- Mandate capture is a separate UI path from card capture. `paymentSetup()` and `AuthorityFields` branch on rail;
  direct debit gets a hosted-capture handle and never a `client_secret`, and Stripe.js does not load there. The
  authority activates on `mandate_linked`, never on a browser redirect.
- Provider resolution is context-specific, never "the active provider". New mandates resolve from the single
  `direct_debit_providers.status = 'active'` row; config supplies credentials ONLY and never selects a provider.
  Existing mandates and payments collect through their own stored provider for life; webhooks resolve by
  route segment. Providers are `active` / `draining` / `retired` / `closed`; lifecycle lives in
  `direct_debit_providers`, NOT config, and both transitions are guarded transactionally. `retired` still ACCEPTS
  webhooks — a retired provider can owe a late reversal. Only `closed` rejects, and only once the reversal window
  has elapsed.
- Direct debit fails closed. `DIRECT_DEBIT_ENABLED` gates selectability at all three validation layers and drives
  the sign-off UI from the server. It is flipped ONCE, in the activation work order, only after webhook
  reconciliation and settlement rules are live — a collector without a reconciler lodges real debits that never
  settle, and nothing alerts.
- A pre-debit notice is a precondition, not a courtesy. `lodge()` refuses without an unsuperseded, delivered notice
  matching the exact amount (including `fee_applied`) and date. Changing either supersedes the notice and restarts
  the notice period. A blocked lodgement is a skip, never a failed attempt.
- Notices key to `payment_installment_id` / `payment_schedule_id` + debit date, NEVER `payment_id` — the payment
  does not exist until collection time. R6 is evaluated before the payment insert and leaves no row when blocked.
- On the direct-debit rail the amount is frozen at notice generation. Credits and fees resolve then, not at
  collection. Do not move the card rail's collection-time adjustment ordering.
- Payment scheduling lives in `bootstrap/app.php` beside `ProcessScheduledPayments`, not `routes/console.php`.
- A direct-debit schedule's first debit date is floored at `today + DIRECT_DEBIT_NOTICE_DAYS`. Never let a
  direct-debit schedule default to `now()` — R6 would block it permanently, visible only as a log skip. Monthly
  cadences roll to the next month rather than shifting off the agreed collection day. Card schedules are unchanged.
- `payments.rail` has no database default and is immutable after insert. Every insert states it explicitly, so a
  direct-debit payment is never briefly observable as a card payment.
- `confirmAmbiguous()` is the CARD recovery loop and must stay rail-filtered. Lodged direct debits are reconciled
  only by the stale-lodgement sweep via the provider's `findInvoice()`. Never make `Gateway::findCharge()`
  rail-polymorphic.
- Dishonour fees are visible: `payment_installments.fee_applied` carries them, the receipt shows them as their own
  line, and `payment_dishonour_fees` is the audit trail. The two must reconcile. Never fold a fee into
  `base_amount` or `net_amount` alone.
- `Payment::STATUS_SUBMITTED` is not success. No receipt, no schedule advance, no "paid" shown to the client until
  settlement sets `settled_at`.
- At most one `submitted` debit per installment and per legacy schedule, enforced by partial unique indexes.
  `submitted` must be included in the installment in-flight index.
- Confirmation deadlines are rail-aware. The 48-hour card default is shorter than direct-debit settlement.
- Webhook event ids are extracted per provider via `ProviderWebhookAdapter::eventId()` (Ezypay: `requestId`, not
  `id`). An event with no id is rejected, never stored with a synthesised one.
- Ezypay signs HMAC-SHA1 over the raw body with no timestamp; replay protection is `unique(gateway, event_id)` plus
  an event-appropriate amount cross-check. Never fabricate a timestamp window.
- Settlement amounts are NET of provider fees. Gross-match `lodged` events; RECONCILE `settled` events
  (`net + fee ≈ gross`) and alert on a break — never reject a settlement on an amount difference. Client receipts
  always show the gross. The provider transaction fee is FSA's expense; the dishonour fee is the client's charge.
- Map only events whose meaning is confirmed. `credit_note_*` stays `unmapped` until E0 identifies which single
  event is a completed reversal — a failed credit note is not a reversal.
- Retry requires a live mandate on a collecting provider. A reversal arriving after the authority is revoked or its
  provider is retired produces an outstanding balance + advisor alert and a required re-mandate — never an
  automatic attempt+1 against a mandate that can no longer be collected.
- Never auto-retry an ambiguous lodgement — leave it `submitted` for the stale-lodgement sweep.
- FSA owns the billing calendar. Providers bill one-off invoices only; never provider-side subscriptions.
- Bank account numbers never reach FSA. Capture is hosted; `assertNoRawBankAccount()` guards persistence.
```

---

*Provider sources: [developer hub](https://developer.ezypay.com/), [authentication](https://developer.ezypay.com/docs/authentication-1),
[webhook](https://developer.ezypay.com/docs/webhook), [webhook security](https://developer.ezypay.com/docs/webhook-security),
[Ezypay NZ](https://www.ezypay.com/nz). Settlement time and fee schedule are **contract/sandbox items** (§2.2), not
verified public facts.*
