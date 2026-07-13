# PLAN — Systems Integration Efficiency Service (scope → quote → deliver → prove)

**Plan version:** 1.17 — owner decisions + seventeen code-grounded review passes (attempt allocator closed — build-ready). *(Build target: Codex, into the test env, then push to live.)*

> **v1.17 revision (review pass — the retry ordinal).** One fix:
> (P1) **One atomic attempt allocator** — every fresh `Payment` (definitive decline **or**
> confirmed-not-charged) increments `attempt_count` and takes the new ordinal inside the claiming
> transaction; transport retries of an ambiguous payment allocate nothing. Timeout → not-charged →
> attempt-2 test added (§3, §9).

> **v1.16 revision (review pass — the inherited uniqueness constraint).** One fix:
> (P1) **Attempt uniqueness split by scope** — the legacy `UNIQUE(payment_schedule_id, attempt)`
> ([payments migration:30](database/migrations/2026_05_23_050000_create_payments_and_receipts_tables.php:30))
> collides when two due installments on one schedule each open a first attempt. Legacy index becomes partial
> (`WHERE payment_installment_id IS NULL`, old rule preserved for legacy schedules); installment-backed
> payments get `UNIQUE(payment_installment_id, attempt)` with per-installment numbering; two-due-installments
> concurrency test added (§3, I4, §9).

> **v1.15 revision (review pass — protocol → migration contract).** One fix:
> (P1) **Write-ahead fields made canonical** — `payment_installments.processing_started_at` added to the §3
> schema line; **`payments.idempotency_key`** as a durable column (computed inline today, persisted nowhere —
> [payments migration:14](database/migrations/2026_05_23_050000_create_payments_and_receipts_tables.php:14));
> I4 step (1) carries both, and I4-4b names the pre-I/O write-ahead transaction + stale-`processing` sweep
> explicitly — the build can no longer satisfy the stated contract while omitting the crash-recovery
> guarantee (§3, I4).

> **v1.14 revision (review pass — the pre-response crash window).** One fix:
> (P1) **Write-ahead correlation** — the `due → processing` transaction itself persists `attempted_gateway`,
> `processing_started_at`, `confirmation_deadline`, and the payment's idempotency key **before any external
> I/O**; a stale-`processing` sweep feeds crashed attempts into the standard confirmation path. Worker-killed
> -mid-charge test: swept → looked up → settled/reopened, never stuck, never double-charged (§3, §9).

> **v1.13 revision (review pass — the two async-confirmation cases).** Two fixes:
> (P1) **`PendingConfirmation` outcome** — Stripe's `processing` status is returned as a normal result
> ([LiveStripeClient:167](app/Services/Integration/Stripe/LiveStripeClient.php:167)) and immediately marked
> succeeded ([PaymentProcessor:122](app/Services/Payments/PaymentProcessor.php:103)) — receipt + activation
> on an unsettled charge. It now parks with the gateway reference persisted; **no receipt/outcome/activation
> until confirmed**; both resolution directions tested (§3, I4-4b).
> (P1) **Executable lookup contract** — gateway clients gain `findCharge(correlation)`
> (interfaces expose only setup/capture/charge, [StripeClient:13](app/Services/Integration/Stripe/Contracts/StripeClient.php:13));
> correlation = persisted gateway ref, or on response loss the **idempotency key + `payment_id` metadata**
> already sent with every charge; temporary `not_found` stays unknown until the deadline — never an
> auto-reopen (§3).

> **v1.12 revision (review pass — the layer below the state machine).** Three fixes:
> (P1) **Typed gateway outcomes; ambiguous never fails over** — the multi-provider `Gateway` catches every
> `PaymentGatewayException` and immediately charges the secondary with the same intent
> ([Gateway.php:57-77](app/Services/Payments/Gateway.php:48)); a primary timeout-after-capture would
> double-charge *below* the installment machinery. `DefinitiveDecline` may fail over; `AmbiguousOutcome`
> parks with the attempted provider recorded. Primary-capture + response-loss test (§3, I4-4b).
> (P1) **Canonical schema completed** — `manual_review`, `attempted_gateway`, `attempt_count`,
> `next_attempt_at`, `confirmation_attempts`, `next_confirmation_at`, `confirmation_deadline` added to the
> §3 table the migration is built from (§3).
> (P1) **Retry clock in the selector** — `AND (next_attempt_at IS NULL OR ≤ now)`; declines set backoff +
> increment attempts; existing max-attempt/pause policy carried forward (§3).

> **v1.11 revision (review pass — making the v1.10 model operationally live).** Three fixes:
> (P1) **Recovery policy for `awaiting_gateway_confirmation`** — persisted confirmation
> attempts/schedule/deadline, idempotent `ConfirmAmbiguousPayments` lookup job with backoff, 48h escalation
> to `manual_review` + alert, audited manual resolution; delayed/missing-webhook tests (§3, I4-4b).
> (P1) **Installments are the only clock** — the collector's `next_run_at` selection/mutation/advancement
> ([PaymentProcessor:50](app/Services/Payments/PaymentProcessor.php:50),
> [PaymentWebhookReconciler:139](app/Services/Payments/PaymentWebhookReconciler.php:139)) is replaced for
> installment-backed schedules by due-installment selection (zero-value included), installment-held retry
> timing, and transition-as-advancement; `next_run_at` remains legacy-only (§3, I4-4b).
> (P1) **Payment lifecycle vs the partial unique** — ambiguous outcomes retain the original non-terminal
> `Payment` (same row, same idempotency key on transport retries); fresh row + fresh key only after the
> original terminalizes on confirmed not-charged/decline; new-row-per-attempt stays legacy-only
> ([PaymentProcessor:154,259](app/Services/Payments/PaymentProcessor.php:145)) (§3, I4-4b).

> **v1.10 revision (review pass — declines vs unknowns).** Two fixes:
> (P1) **Ambiguous gateway outcomes never reopen the installment** — a timeout may have charged; the webhook
> can later succeed the original payment ([PaymentWebhookReconciler:129](app/Services/Payments/PaymentWebhookReconciler.php:129)),
> so reopening invites a double charge. New exclusive `awaiting_gateway_confirmation` state resolved only by
> webhook/provider lookup; only definitive declines reopen `due`; ambiguous retries **reuse the same
> idempotency identity** (the per-attempt key at [PaymentProcessor:109-111](app/Services/Payments/PaymentProcessor.php:103)
> defeats gateway idempotency) (§3, I4-4b).
> (P2) **Canonical installment status set updated** — `processing` + `awaiting_gateway_confirmation` +
> `active_payment_id` now in the §3 schema the migration will be built from (§3).

> **v1.9 revision (review pass — concurrent collection + the sixth link + I4 authority).** Three fixes:
> (P1) **Installment attempt state machine** — a row lock releases before the gateway call
> ([PaymentProcessor:145](app/Services/Payments/PaymentProcessor.php:145)); `due → processing` is an atomic
> conditional transition linking the active attempt, one active/succeeded attempt per installment (partial
> unique), webhook reconciliation settles via the same conditional transition; two-concurrent-collectors
> test asserts one gateway charge (§3, I4).
> (P1) **Sixth tenant guard** — `payments.payment_installment_id` vs the payment's own `client_id`
> ([Payment.php:32](app/Models/Payment.php:32)); mismatches corrupt receipts/webhooks/activation (§3).
> (P2) **I4 re-authorised** — now carries the installments migration, partial unique index, offer-method
> semantics, `payments.payment_installment_id`, zero-payment receipts, the state machine, and six guards (I4).

> **v1.8 revision (review pass — payment identity, races, the fifth link, offer semantics).** Four fixes:
> (P1) **`payments.payment_installment_id`** — receipts/webhooks operate on `Payment` (schedule-only today,
> [Payment.php:40](app/Models/Payment.php:40)); the due installment is **locked before the attempt**, and a
> zero-settlement mints an **internal succeeded `Payment`** so `ReceiptGenerator`
> ([:24-30](app/Services/Payments/ReceiptGenerator.php:24)) and reconciliation work unchanged (§3, I4).
> (P1) **Concurrency guard** — `assertNoBlockingOpenActivation` is check-then-create
> ([ServiceActivationManager:54-59](app/Services/ServiceActivations/ServiceActivationManager.php:50));
> partial unique index on non-terminal `(client_id, service_type)` + clean conflict handling (§3).
> (P1) **Fifth tenant link** — `application → installment → schedule → client` joins the same-client
> write-guard set (now five relations, all cross-client-tested) (§3).
> (P2) **Dedicated `offerIntegrationScoping` manager action** — `request()` stamps `client_self_start`,
> spawns a request thread, and notifies the advisor ([:60-76](app/Services/ServiceActivations/ServiceActivationManager.php:50));
> the offer records `advisor_offer`, audits `integration.scoping_offered`, notifies the **client** (§3).

> **v1.7 revision (review pass — the billing-to-payment bridge + scoping activation transition).** Four fixes:
> (P1) **`payment_installments` as the shared source** — the collector charges the schedule's base amount
> with no installment context ([PaymentProcessor:103](app/Services/Payments/PaymentProcessor.php:103)), so
> Xero would show a credit Stripe ignores. Installments feed invoice generation, card charging
> (`net_amount`), and outcomes; `settled_zero` auto-settlement counts as a payment outcome so a fully
> credited first month still activates (§3, I4).
> (P1) **Scoping activation transition defined** — package payment requires *and keeps* `package_selected`
> ([ServiceActivationManager:170](app/Services/ServiceActivations/ServiceActivationManager.php:170)); the
> offer transaction package-selects, and `activateScopingFromPackagePayment` activates on payment with
> consent evidence persisted — portal accept never involved (§3, I4).
> (P2) **Stable ledger target** — applications key to `payment_installment_id` (payments reference only a
> schedule today, [Payment.php:40](app/Models/Payment.php:40); invoice batches regenerate); unique pair;
> `remaining_amount` derived from the ledger, never independently mutable (§3).
> (P2) **`source_unverified` added to the flag catalogue** — blocking/high, not acknowledgeable,
> auto-clears on allowlisted re-verification, standard re-raise semantics (§6).

> **v1.6 revision (review pass — recurring billing + lifecycle bypasses).** Five fixes:
> (P1) **Per-invoice credit consumption** — signature creates one monthly-retainer schedule whose amount
> repeats every invoice ([SignoffFlow:644-649](app/Services/Proposals/SignoffFlow.php:644)); "reduce the
> first schedule amount" would discount every month, and the credit can exceed an instalment. Now a
> `billing_adjustment_applications` ledger consumes earliest invoices, never negative, atomically once (§3).
> (P1) **Renew/release bypasses closed** — shared `IntegrationScopeProposalGuard` on generate + renew
> ([ProposalController:165](app/Http/Controllers/Advisor/ProposalController.php:165)) + release
> ([ProposalBuilder:139](app/Services/Proposals/ProposalBuilder.php:139)), each path tested (§7).
> (P1) **Scoping entry point defined** — advisor "Offer Integration Scoping" action; portal creation stays
> DD/entrepreneur-only ([Portal ServiceActivationController:28](app/Http/Controllers/Portal/ServiceActivationController.php:28));
> the client's only step is the existing package payment (§3, I4).
> (P1) **Same-client guards on the four financial links** — write-time trigger/service guard + cross-client
> tests (RLS doesn't check cross-row tenancy) (§3).
> (P2) **I4 rewritten as the authoritative ordered deliverable list**; "proposal = sole invoicer" narrowed
> to the build stage (I4, §7).

> **v1.5 revision (review pass — billing records, guard bypass, activation path).** Five fixes:
> (P1) **Credit becomes a `billing_adjustments` record** — workspace-package payments produce
> activation-linked references (no `FeeCalculation`/`Payment`), and schedules/Xero lines are scalar-only;
> the adjustment model + schedule `billing_adjustment_id` + named Xero line + `scoping_credit_adjustment_id`
> on the §4 schema close that (§3, §4, I4).
> (P1) **Guard bypass closed** — `ProposalController` accepts any client-owned `fee_calculation_id`; scopes
> can be flagged *after* calc creation. `fee_calculations.integration_scope_id` (immutable) + proposal-time
> re-check + the create-calc→flag→direct-POST test (§7, I4).
> (P1) **Bespoke activation path** — portal-accept + workspace factory route every non-DD service into the
> entrepreneur workspace ([:556-565](app/Services/ServiceActivations/ServiceActivationManager.php:556));
> `activateFromProposalPayment` with signed-proposal consent evidence + explicit integration workspace
> branches; never mints an entrepreneur profile (§7, I4).
> (P2) **Task-row PV attribution made real** — `PvEngine` never writes the `source_attributions` column its
> model casts ([PvEngine.php:113-127](app/Services/Pv/PvEngine.php:100)); extend `calculate()` with optional
> `sourceAttributions` persisted there (I1) — `computed`-only mapping is not equivalent (§5).
> (P2) **Stale v1.3 phrasing corrected** — the shared gate does not govern proposals; "until resolved" →
> re-verification semantics everywhere (flags, tests, CLAUDE block); CLAUDE block now three-origin.

> **v1.4 revision (review pass — persistence identity + the two commercial state machines).** Seven fixes:
> (P1) **I6 row superseded** — it still carried v1.2's gate/storage language; now states the v1.3/v1.4
> semantics and names §3A as authoritative.
> (P1) **Verification identity** — gate on the verification row for **this claim's `context_hash`**
> ([DocumentVerifier:33-44](app/Services/Ai/Verification/DocumentVerifier.php:33)); join persists
> `document_verification_id`; `resolved_at` never allowlists `accuracy_discrepancy` (resolution controller
> only stamps it, [:28-32](app/Http/Controllers/Advisor/DocumentVerificationController.php:28)) — clearing =
> re-verify the same context (§3A.3).
> (P1) **Provenance contradictions removed** — confirmation, confirmed-row schema, and calculator contract
> all now carry the three-valued origin (`manual|document|description`), never remapped (§3A, §4).
> (P1) **Server-side quote guard** — the `Integration` adapter refuses fee/proposal creation on blocking
> scope flags; direct-POST feature test (§7, I4).
> (P1) **Payment bridge is one idempotent service** — collector ([PaymentProcessor:180](app/Services/Payments/PaymentProcessor.php:180))
> and webhook reconciler ([PaymentWebhookReconciler:129](app/Services/Payments/PaymentWebhookReconciler.php:129))
> both call `ApplyProposalPaymentOutcome`; unique `service_activations.proposal_id`; explicit transitions (§7, I4).
> (P1) **Two-stage commercial model made explicit** — `SERVICE_INTEGRATION_SCOPING` + `SERVICE_INTEGRATION`
> (one-open-per-type guard, [ServiceActivationManager:462](app/Services/ServiceActivations/ServiceActivationManager.php:462));
> scoping closes in the build-accept transaction; the credit is a persisted, once-only, auditable adjustment (§3, I4).
> (P1) **Pre-proposal anchor + tenancy** — client-scoped `quote_intakes` aggregate for surfaces with no
> persisted object; ownership consistency guard (`batch = scopeable = documents` client) + cross-client
> write-time rejection tests (§3A, I7).



> **v1.3 revision (review pass — cross-rail contracts, all verified in code).** Seven fixes:
> (P1) **Per-document, positive-allowlist verification gating** — `verification_error`/pending are not
> "outstanding flags" and would have extracted; the client-wide gate would let unrelated documents block this
> quote. Extraction now gates on the selected document's own outcome allowlist (§3A.3).
> (P1) **Extraction batch model** — `quote_source_extractions` becomes a batch (description snapshot +
> `description_captured_at`) with a document join table; row origin is three-valued
> (`document|description|manual`) and never remapped (§3A, §4).
> (P1) **Integration fee/ROI isolated from the client-wide waterfall** — `FeeCalculator` pulls all active
> `ImprovementOpportunity` PV and `ProposalBuilder` copies it; the `Integration` branch reads the scope's
> saved `PvCalculation` only (§7, I4).
> (P1) **Strategic-budget guard honoured** — standalone quotes go through the existing override
> (category + auto-drafted notes referencing the scope) (§7, I4).
> (P1) **One payment path** — proposal is the sole invoicer; `service_activations.proposal_id` +
> payment-satisfied-by-proposal mode; no duplicate charge, no dead-locked activation (§7, I4).
> (P1) **GoalTracker "unchanged" corrected** — two minimal extensions: `createGoal` attach-existing
> `pv_target_calculation_id`, and a `recordMeasurement` contract for the +90-day top-down re-measure
> (`pvRealisedTotal` stays milestone-based; both shown, labelled) (§2, §7, I5).
> (P2) **WO sequencing fixed** — I2 is the shell; I6 owns *and mounts* the intake (I1 → I2/I3 → I6 → I4 →
> I5 → I7).

> **v1.2 revision (review pass — align with the platform's real rails).** Seven fixes, each verified in code:
> (P1) **Verification gating aligned with the shared gate** — `outstandingFlags()` blocks *both*
> `advisory_flag` and `accuracy_discrepancy`; extraction now consumes `DocumentVerificationGate` semantics
> (advisory proceeds only after acknowledgement) instead of inventing a laxer rule (§3A.3).
> (P1) **Universal storage made real** — polymorphic `quote_source_extractions` table owned by the §3A
> component; I7 surfaces adopt without their own storage (§3A, I6).
> (P1) **Provenance survives confirmation** — `systems`/`tasks`/`connections` row schemas carry
> `source`/`source_reference`/`claim`; `connections` gains `confidence` (§4).
> (P1) **Quote-to-cash adapter specified** — proposals hard-require `fee_calculation_id`
> ([ProposalController:43](app/Http/Controllers/Advisor/ProposalController.php:43)); new
> `FeeMethod::Integration` + `FeeCalculation` path; `ServiceActivation::SERVICE_INTEGRATION` added to the
> DD/entrepreneur-only whitelist ([ServiceActivationManager:451](app/Services/ServiceActivations/ServiceActivationManager.php:451)) (§7, I4).
> (P2) **Stale "optional AI assist" sentence corrected** to core/I6 (§7).
> (P2) **Extraction gets its own chunked text pipeline with stable locators** — the verifier's capped
> excerpt has none ([DocumentVerifier:149](app/Services/Ai/Verification/DocumentVerifier.php:149)) (I6).
> (P2) **Waste formula split** — hours = minutes×occurrences×people/60; dollars = per-task hours × that
> task's hourly cost, summed (§5).

> **v1.1 revision (owner request).** Added **§3A — universal document-assisted quote input**: the advisor (and
> only the advisor) may upload external plans/documents at the quote-input stage; after virus scan + document
> verification, an AI extraction reads them **in conjunction with the advisor's description** and drafts
> scoping rows the advisor confirms. Built as a **shared, service-agnostic component** (universal across FSA
> quote surfaces), wired to Integration Scoping in this plan's WOs. AI extraction (previously optional I6) is
> now **core**. Data model, flags, flow, WOs, tests, and the CLAUDE.md block updated accordingly.

**One-line intent:** A new revenue line — custom integration apps that eliminate duplicate data entry between
client systems — quoted **inside the FSA app** from a structured description of the client's systems and
repetitive tasks: the advisor captures the waste, the platform computes annual cost, PV of savings, a
complexity-banded fixed quote and payback period, flows it into the existing proposal → signoff → payment
pipeline, and **measures realised savings** after delivery through the proof-verified goal loop.

---

## 1. Owner decisions (locked)

| # | Decision | Choice |
|---|---|---|
| D1 | Delivery | **Mixed, per job** — in-house, subcontracted partner, or low-code/iPaaS; the quote engine carries a cost basis per mode and the advisor picks the mode during scoping. |
| D2 | Pricing | **Fixed fee by complexity band**, always presented beside the quantified annual savings + payback so ROI is visible. The fee is **never derived from the savings** (no value-pricing coupling) — savings justify, complexity prices. |
| D3 | Quote flow | **Dedicated Integration Scoping calculator → existing proposal pipeline** (the `BudgetCalculator` pattern reused: pure calculator + persist service + flags). |
| D4 | Proof loop | **Yes** — accepted quotes auto-create a Goal (`pv_target` = PV of savings) with per-connection milestones; post-implementation re-measure confirms realised hours saved (proof-verified). |

---

## 2. Verified platform anchors (what this builds on — all read at HEAD)

- **Calculator pattern:** [BudgetCalculator](app/Services/Entrepreneurs/BudgetCalculator.php) + [EntrepreneurBudgetService](app/Services/Entrepreneurs/EntrepreneurBudgetService.php) — deterministic pure engine, persist service, per-row **confidence tags** (`known/estimate/guess`), flag-and-acknowledge with `first_raised_at`, governed learning signals, plain-language explanations. Mirror all of it.
- **PV machinery:** `PvEngine` (used by [GoalTracker:47-54](app/Services/Goals/GoalTracker.php:40) with `DiscountMethod::AdvisorConfigured`, default 12%, rationale recorded), `PvCalculation` (`as_at`, attributions). Savings PV is a first-class `PvCalculation`, not an inline number.
- **Quote → cash rails:** proposals (`Advisor\ProposalController`), `ProposalSignoffController`, **service-package payment gates** (`403fbb64`), `ServiceActivation`, Xero invoice batches (`a1dac16c`).
- **Proof loop:** [GoalTracker](app/Services/Goals/GoalTracker.php) — `pv_target` + `pv_target_calculation_id`, milestones with `recommendation_ref` + `pv_of_impact`, **proof-verified completion** (document verifier; discrepancies block), `pvRealisedTotal()`, holiday-aware due dates. **Two minimal extensions needed (v1.3):** attach-existing-PV on
  `createGoal`, and a `recordMeasurement` contract for top-down re-measures — the proof/completion flow itself
  is unchanged (§7).
- **Discovery feed (optional):** `SystemsReview` / `OperationalAnalysis` modules + `ImprovementOpportunity` — analysis findings about duplicate entry can pre-fill the scoping tool, but the tool must work standalone (not every integration client buys Standard Advisory first).
- **Conventions:** client-scoped RLS (goals/documents pattern), `Gate::authorize('view', $client)`, `AuditWriter` on every state change, `SecureFileWriter` for any uploads, Wayfinder regen after routes, escape() discipline in any PDF HTML.

---

## 3. Service shape (what the advisor sells)

**"Systems & Integration Efficiency"** — two-stage, mirroring the buy-side pattern:

1. **Integration Scoping (fixed mini-fee, quoted as a workspace package)** — the advisor sits with the client,
   inventories systems and repetitive tasks in the scoping tool, and produces the **Quote Pack** (waste table,
   savings, quote band, payback). Suggested fee: **$950–$1,500 ex GST**, credited 100% against the build if
   accepted *(owner confirms, §10)*.
   **Two-stage state model (v1.4 — the stages must not collide):** activations allow **one open activation
   per client + service type** ([ServiceActivationManager.php:462-472](app/Services/ServiceActivations/ServiceActivationManager.php:462)),
   so scoping and build are **distinct service types** — `SERVICE_INTEGRATION_SCOPING` and
   `SERVICE_INTEGRATION` — and accepting the build proposal **closes the scoping activation** in the same
   transaction that opens the build's.
   **The one-open-per-type rule gets a real guard (v1.8):** `assertNoBlockingOpenActivation` is
   check-then-create *outside* the create transaction ([ServiceActivationManager.php:54-59](app/Services/ServiceActivations/ServiceActivationManager.php:54))
   — concurrent offers could both create **paid** scoping obligations. Add a **partial unique index** on
   `service_activations (client_id, service_type) WHERE status NOT IN (cancelled, closed, rejected)` and
   handle the unique-violation as a clean validation error; the assert stays as the friendly pre-check, the
   index is the guarantee (same DB-enforced-invariant pattern as the platform's other partial uniques).
   **Scoping entry point is advisor-offered, not client-created (v1.6):** the portal's activation creation
   screen permits only DD/entrepreneur ([Portal ServiceActivationController:28](app/Http/Controllers/Portal/ServiceActivationController.php:28))
   and stays that way — clients never self-serve a scoping workspace. A new **advisor action ("Offer
   Integration Scoping")** — a **dedicated manager method (`offerIntegrationScoping`), not a reuse of
   `request()` (v1.8)**: `request()` hardcodes `source: client_self_start`, starts a request thread, and
   notifies the *advisor* ([ServiceActivationManager.php:60-76](app/Services/ServiceActivations/ServiceActivationManager.php:50))
   — all three wrong for an advisor-originated offer. The offer method records `source: advisor_offer` +
   the offering advisor, audits `integration.scoping_offered`, **notifies the client** (their channel
   preferences), and creates the `SERVICE_INTEGRATION_SCOPING` activation **selecting the package in the
   same transaction (v1.7)** — payment completion *requires* `package_selected`
   ([ServiceActivationManager.php:170](app/Services/ServiceActivations/ServiceActivationManager.php:170)) —
   snapshotting the offered terms. The
   client then sees the **offered** activation in the portal and completes the **existing package-payment
   flow**. And because completing payment **leaves the status at `package_selected`** — activation today
   happens only via the separate portal-accept action ([:305](app/Services/ServiceActivations/ServiceActivationManager.php:305))
   — a new transition **`activateScopingFromPackagePayment`** activates the scoping workspace directly on
   payment completion, persisting consent evidence = the payment acceptance + the offered-terms snapshot.
   Payment is genuinely the only client step. Advisor-only initiation, per the universal rule. **The credit is a first-class billing-adjustment record (v1.5 — the
   existing billing rows cannot carry it):** a workspace-package payment produces **activation-linked
   references, not a `FeeCalculation` or `Payment`**
   ([ServiceActivationManager.php:118](app/Services/ServiceActivations/ServiceActivationManager.php:118)),
   and proposal schedules / Xero invoices carry **scalar amounts only**
   ([ScheduleBuilder.php:54](app/Services/Payments/ScheduleBuilder.php:54),
   [ProposalInvoiceScheduler.php:128](app/Services/Accounting/ProposalInvoiceScheduler.php:128)). So: a
   **`billing_adjustments`** table `{id, client_id, type: 'scoping_fee_credit',
   source_service_activation_id, source_payment_reference, amount, status: available|applied|void,
   applied_to_proposal_id, applied_at}` (client-scoped RLS, audited, status transitions once);
   `integration_scopes.scoping_credit_adjustment_id` nullable FK (§4 schema). **Application is per-invoice, not per-schedule (v1.6):** signature creates **one monthly-retainer schedule
   row whose amount repeats on every invoice**
   ([SignoffFlow.php:644-649](app/Services/Proposals/SignoffFlow.php:644),
   [ProposalInvoiceScheduler.php:128](app/Services/Accounting/ProposalInvoiceScheduler.php:128)) — reducing
   "the first schedule amount" would discount **every** month, and the credit can **exceed one instalment**
   (e.g. $950 credit vs a $708 instalment). And crediting only the *accounting* invoices is not enough (v1.7): the card
   collector charges the **schedule's base amount with no installment context**
   ([PaymentProcessor.php:103](app/Services/Payments/PaymentProcessor.php:103)) — Xero would show a credited
   invoice while Stripe charges the uncredited amount. So the bridge is a **shared installment record**:
   **`payment_installments`** `{id, payment_schedule_id, sequence, due_date, base_amount, credit_applied,
   net_amount, status: due|processing|awaiting_gateway_confirmation|manual_review|settled|settled_zero|failed,
   active_payment_id, attempted_gateway, attempt_count, next_attempt_at, **processing_started_at (v1.15)**,
   confirmation_attempts, next_confirmation_at, confirmation_deadline}` — plus **`payments.idempotency_key`
   (v1.15, durable):** the key is currently *computed inline* at charge time and persisted nowhere
   ([payments migration:14](database/migrations/2026_05_23_050000_create_payments_and_receipts_tables.php:14)
   has no such column), but the write-ahead protocol requires it stored **before I/O**.
   **Attempt uniqueness re-scoped (v1.16):** payments today enforce **`UNIQUE(payment_schedule_id,
   attempt)`** ([payments migration:30](database/migrations/2026_05_23_050000_create_payments_and_receipts_tables.php:30))
   — but the installment collector processes **every** due installment, so two overdue installments on one
   schedule would race for the same schedule-level attempt number (or collide on "attempt 1"). The migration
   **splits the constraint by scope**: the legacy index becomes **partial** (`WHERE payment_installment_id
   IS NULL` — the old rule preserved exactly for legacy schedules), and installment-backed payments get
   **`UNIQUE(payment_installment_id, attempt)`**. Attempt numbering for installment-backed payments is
   per-installment (`attempt_count` is the allocator); schedule-level numbering continues untouched for
   legacy rows. Concurrency test: **two due installments on one schedule** charge independently — no unique
   violation, independent attempt sequences, two distinct gateway charges (one per installment, each with
   its own idempotency key). (The canonical
   schema carries **every** state and field the v1.10–v1.15 machine relies on; the migrations are built from
   this line.) — the **single source** consumed by invoice
   generation (`ProposalInvoiceScheduler`), card charging (`PaymentProcessor` resolves the current due
   installment and charges its **`net_amount`**), and payment-outcome handling. The credit is consumed via
   **`billing_adjustment_applications`** `{adjustment_id, payment_installment_id (FK — stable across invoice
   regeneration; payments today reference only a schedule, [Payment.php:40](app/Models/Payment.php:40)),
   amount_applied}` with a **unique `(adjustment_id, payment_installment_id)`** constraint; earliest
   installments absorb first, `net_amount = max(0, base − applied)`, each affected invoice carries the named
   Xero credit line. **`remaining_amount` is derived from the ledger** (computed in the applying
   transaction — never an independently mutable column), and the adjustment reads `applied` when the ledger
   sums to its amount — atomically, once. **The installment must link to the concrete `Payment` (v1.8):** webhook reconciliation and receipts operate
   on `Payment`, which today belongs **only to a schedule** ([Payment.php:40](app/Models/Payment.php:40)), and
   `ReceiptGenerator` requires a **succeeded `Payment`** ([ReceiptGenerator.php:24-30](app/Services/Payments/ReceiptGenerator.php:24)).
   So: **`payments.payment_installment_id`** (nullable FK, set for every installment-backed charge). **A lock
   alone cannot prevent a duplicate charge (v1.9):** the row lock releases at commit — *before* the external
   gateway call ([PaymentProcessor.php:145](app/Services/Payments/PaymentProcessor.php:145)) — leaving the
   installment visible as `due` to a second collector. So the installment status set gains **`processing`**:
   the collector performs an **atomic conditional transition** — and that same transaction is the
   **write-ahead record (v1.14)**: `UPDATE … SET status='processing', active_payment_id=?,
   attempted_gateway=?, processing_started_at=now, confirmation_deadline=? WHERE id=? AND status='due'`
   (zero rows affected = someone else has it), with the payment row already carrying its **idempotency key**
   — all persisted **before any external I/O**. If the worker dies after the charge request but before
   response handling ([PaymentProcessor.php:145](app/Services/Payments/PaymentProcessor.php:145) does I/O
   after commit), everything `ConfirmAmbiguousPayments` needs to recover already exists: a **stale-`processing`
   sweep** (`processing_started_at` older than the attempt timeout) moves the row into
   `awaiting_gateway_confirmation`, where the standard lookup resolves it via the persisted
   provider + idempotency-key correlation. Test: **request sent, worker killed before response handling** →
   swept, looked up, settled or reopened — never stuck, never double-charged. The collector then makes the
   gateway call, then transitions by **outcome class (v1.10 — declines and unknowns are different things):**
   a **definitive no-charge decline** (gateway answered: declined/insufficient funds) → `failed`, installment
   reopens to `due` with the attempt recorded; an **ambiguous outcome** (timeout, network error, unknown
   state — the charge may have gone through) → the installment holds an **exclusive
   `awaiting_gateway_confirmation`** state that **no collector may re-attempt**, resolved only by the webhook
   or an explicit provider lookup confirming succeeded (→ `settled`) or not-charged (→ reopen `due`). The
   existing webhook reconciler can mark the *original* payment succeeded later
   ([PaymentWebhookReconciler.php:129](app/Services/Payments/PaymentWebhookReconciler.php:129)) — which is
   exactly why an ambiguous installment must never have been reopened in the meantime. **Retries of an
   ambiguous attempt reuse the SAME provider idempotency identity** — the current per-attempt key
   (`payment-{id}-attempt-{n}`, [PaymentProcessor.php:109-111](app/Services/Payments/PaymentProcessor.php:103))
   mints a fresh identity per retry and defeats gateway idempotency; the installment-backed path keys on the
   payment, not the attempt number.
   **Typed gateway outcomes — failover must not bypass the ambiguity model (v1.12).** The multi-provider
   `Gateway` catches **every** `PaymentGatewayException` and immediately charges the **secondary provider
   with the same intent** ([Gateway.php:57-77](app/Services/Payments/Gateway.php:48)) — a primary *timeout*
   may already have captured, so failover itself is a double-charge path that fires **before** any
   installment state machinery. Rule: gateway outcomes become **typed, three ways (v1.13)** — `DefinitiveDecline` (provider answered: no
   charge exists), **`PendingConfirmation`** (provider answered *"processing"* — a real Stripe status that
   [LiveStripeClient.php:167](app/Services/Integration/Stripe/LiveStripeClient.php:167) returns as a normal
   result today, which [PaymentProcessor.php:122](app/Services/Payments/PaymentProcessor.php:103) then marks
   **succeeded** — advancing billing, minting a receipt, and activating on an unsettled charge), and
   `AmbiguousOutcome` (timeout / response lost / unknown). **Failover to the secondary is permitted ONLY on
   `DefinitiveDecline`.** `PendingConfirmation` parks the installment in `awaiting_gateway_confirmation`
   **with the gateway reference persisted** (it exists — the response arrived) and **no receipt, no
   `ApplyProposalPaymentOutcome`, no activation** until the webhook/lookup confirms; `AmbiguousOutcome`
   parks it **recording the attempted provider** (no reference — the response was lost). Tests: primary
   captures + response lost → **no secondary charge**; `processing → succeeded` settles with receipt +
   activation *then*; `processing → payment_failed` reopens per the decline path — receipt and activation
   never precede confirmation.
   **Recovery policy — the parked state must not be a dead end (v1.11):** `awaiting_gateway_confirmation`
   carries persisted `confirmation_attempts`, `next_confirmation_at`, `confirmation_deadline`. A scheduled,
   **idempotent `ConfirmAmbiguousPayments` job** performs provider lookups with backoff (5m → 15m → 1h → 6h),
   resolving to `settled` (webhook-equivalent path) or confirmed-not-charged (terminalize + reopen `due`).
   **The lookup contract must be executable (v1.13):** the gateway client interfaces expose only
   setup/capture/charge today ([StripeClient.php:13](app/Services/Integration/Stripe/Contracts/StripeClient.php:13))
   — each gains **`findCharge(ChargeCorrelation): ChargeLookupResult{succeeded|failed|processing|not_found}`**.
   The **correlation identity** is two-tier: when a gateway reference exists (`PendingConfirmation`), the
   lookup uses it directly; on **response loss** (no reference ever received), correlation is
   **recomputable** from what the charge request already carried — the **idempotency key + `payment_id`
   metadata** ([PaymentProcessor.php:112-114](app/Services/Payments/PaymentProcessor.php:103)) — queried via
   the provider's search/list API. Both are persisted on the parked installment/payment at parking time.
   **A temporary `not_found` stays UNKNOWN** — a just-created intent may not be searchable yet, so
   `not_found` never reopens the installment before `confirmation_deadline`; only an authoritative
   not-charged answer reopens, and deadline expiry goes to `manual_review`, never to auto-reopen.
   Past the deadline (default 48h) with the provider unreachable → **`manual_review`** state + advisor/admin
   alert (notification + API-health dashboard surface); manual resolution (mark settled from provider
   evidence / mark not-charged) is audited with the evidence reference. Tests: delayed webhook resolves via
   the job; missing webhook + provider down escalates to `manual_review` and **never** silently blocks or
   auto-reopens.
   **Payment-row lifecycle under the partial unique (v1.11 — the existing retry model inserts a new `Payment`
   per attempt and marks the old one retrying, [PaymentProcessor.php:154,259](app/Services/Payments/PaymentProcessor.php:145)):**
   for installment-backed charges — **ambiguous outcome:** the original `Payment` **remains the active,
   non-terminal row**; a transport-level retry of the *same* charge reuses the **same `Payment` row and the
   same idempotency key** (no insert — this is what makes the partial unique hold); **confirmed
   not-charged / definitive decline:** the original `Payment` terminalizes (`failed`), `active_payment_id`
   clears, the installment reopens `due`, and only then may a **fresh `Payment` with a fresh idempotency
   key** be created (a genuinely new charge). **One atomic attempt allocator (v1.17):** *every* fresh
   `Payment` for an installment — whether the prior attempt ended in a definitive decline **or** an
   ambiguous outcome later confirmed not-charged — is created through a single operation that **increments
   `attempt_count` and uses the new value as the payment's ordinal**, inside the claiming transaction (so it
   can never collide with `UNIQUE(payment_installment_id, attempt)` by reusing the original's ordinal).
   **Transport retries of the same ambiguous `Payment` allocate nothing** — same row, same ordinal, same
   idempotency key. Test: timeout → lookup confirms not-charged → fresh attempt **2** charges cleanly. The new-row-per-attempt behavior remains for **legacy
   (non-installment) schedules only**.
   **One clock (v1.11 — the scheduler must not keep its own):** today the collector selects on
   `payment_schedules.next_run_at`, retries by mutating it, and advances it after both direct and webhook
   success ([PaymentProcessor.php:50](app/Services/Payments/PaymentProcessor.php:50),
   [PaymentWebhookReconciler.php:139](app/Services/Payments/PaymentWebhookReconciler.php:139)). For
   **installment-backed schedules** all of that derives from installments: the collector selects **due
   installments** — `due_date ≤ now AND status = 'due' AND (next_attempt_at IS NULL OR next_attempt_at ≤
   now)` (v1.12 — without the third clause, a declined installment reopened to `due` is retried on the very
   next collector pass, a backoff-free loop; zero-value installments are naturally included, never skipped).
   **On a definitive decline:** reopen sets `next_attempt_at` per the retry backoff and increments
   `attempt_count`; the **existing max-attempt/pause policy carries forward** — attempts exhausted →
   the schedule pauses exactly as today's collector pauses it, surfaced to the advisor. Retry timing lives
   on the installment, never by mutating `next_run_at`; and "advance" *is* the installment transition —
   `next_run_at` is not consulted for installment-backed schedules (kept only for legacy schedules).
   Two-clocks drift is structurally impossible because only one clock exists per schedule type. **Only one active-or-succeeded attempt may exist per installment** (partial unique on
   `payments(payment_installment_id)` for non-terminal/succeeded states), and **webhook reconciliation
   performs the same conditional transition** — never a blind settle. Test: **two concurrent collectors →
   exactly one gateway charge.** A **zero-value settlement creates an internal succeeded `Payment`** (amount
   0, gateway reference `internal_credit`, no gateway call, same conditional transition) — so `settled_zero`
   produces a real receipt and a real outcome record, fires `ApplyProposalPaymentOutcome`, and activation
   proceeds when the credit zeroes the first instalment(s). **Client-consistency guards (v1.6; fifth link v1.8; sixth v1.9):** the financial links —
   `billing_adjustments → service_activation/proposal`, `integration_scopes → adjustment`,
   `fee_calculations → integration_scope`, `billing_adjustment_applications → payment_installment →
   schedule → client`, **and `payments.payment_installment_id` ↔ the payment's own `client_id`**
   ([Payment.php:32](app/Models/Payment.php:32) — a mismatched link corrupts receipts, webhooks, and
   activation) — are enforced same-client by **write-time trigger/service guard** (RLS alone doesn't check
   cross-row tenancy), with **cross-client tests for all six relations**.
   Test: credit > first instalment spreads across invoices, never negative, totals reconcile
   (Σ invoices = fee − credit); re-application impossible; void path audited.
2. **The build** — fixed fee per complexity band + delivery mode (§6), through proposal → signoff → payment
   gate → delivery milestones → realised-savings check.

---

## 3A. Universal document-assisted quote input (advisor-only — applies to ALL services)

A **shared component + service**, not an integration-service special: `ScopeDocumentIntake` (UI) +
`QuoteSourceExtractor` (backend). Any FSA quote/scoping surface can mount it; this plan wires it into
Integration Scoping (I2/I6), and I7 exposes the same seam on the other advisor quote surfaces.

**The pipeline (non-negotiable order, per platform rules):**
1. **Advisor-only initiation.** The upload control renders only for advisor-role users on advisor routes
   (`Gate::authorize('view', $client)` + role check); portal/client/entrepreneur surfaces never mount it.
   A portal user posting to the endpoint is denied (tested).
2. **Virus scan before persistence** — `SecureFileWriter` (existing; infected → audited rejection).
3. **Document verification before consumption — per-document, allowlist-based (v1.3).** `DocumentVerifier`
   runs with a service-specific claim (e.g. *"Business/operations plan describing {client}'s systems and
   processes, used to scope an integration quote"*). Two corrections from review against the real gate:
   - **The gating unit is the selected document, not the client.** `DocumentVerificationGate::blockingFlags()`
     is client-wide ([:30](app/Services/Documents/DocumentVerificationGate.php:30)) — an unrelated flagged
     document elsewhere must **not** block this quote, and that gate keeps governing what it already governs
     (analysis pausing — its existing consumers; it does **not** currently govern proposals). Extraction
     gates on **the uploaded document's own verification for this service's claim context** (see the
     identity bullet below).
   - **Allowlist, not flag-absence.** `outstandingFlags` covers only `advisory_flag`/`accuracy_discrepancy`
     ([DocumentVerification.php:104-112](app/Models/DocumentVerification.php:104)) — **`verification_error`
     and pending are *not* flags**, so "no outstanding flag" would extract from a *failed or unverified*
     document. The rule is therefore positive: extraction runs **iff the verification for this claim's
     context has outcome `verified`, or `advisory_flag` that the advisor has resolved through the
     existing flow**. `pending` → wait; **`verification_error` → blocked with a `source_unverified` state and
     a retry-verification action**; `accuracy_discrepancy` → blocked (spec §9), with the
     `source_discrepancy` flag standing. Mirrors the proof-of-completion gate in
     [GoalTracker:187-203](app/Services/Goals/GoalTracker.php:162).
   - **Verification identity, not "latest" (v1.4).** Verifications are keyed by `(document_id,
     context_hash)` ([DocumentVerifier.php:33-44](app/Services/Ai/Verification/DocumentVerifier.php:33)) — a
     document can hold outcomes for *different claims*. The gate reads **the verification row for THIS
     service's claim context**, and the extraction join persists the exact **`document_verification_id`**
     consumed (not merely an outcome string). And because the resolution flow **only sets `resolved_at` —
     it never changes the outcome** ([DocumentVerificationController.php:28-32](app/Http/Controllers/Advisor/DocumentVerificationController.php:28)):
     `advisory_flag + resolved_at` **is** allowlisted; **`accuracy_discrepancy` is never allowlisted by
     `resolved_at`** — it clears only by **re-running verification for the same context** (the
     `updateOrCreate` on `(document_id, context_hash)` makes re-verification the natural clearing path)
     after the document/data is corrected.
4. **AI extraction reads document(s) + the advisor's description together** — one `AiClient` call per scope
   (prompt registered in the prompt registry, advisor-side classification), input = verified document text +
   the advisor's free-text description; output = **draft rows** (systems/tasks/connections for this service;
   each service defines its own row schema) with **per-row provenance**: `source: document|description`,
   `source_reference` (document id + locator), and a **claim quote** — every factual row must cite where in
   the document it came from (attribution is a hard rule).
5. **Advisor confirms every row.** Draft rows live in a pending state (`extracted_rows`) and **never enter
   the calculator until confirmed** — the platform does not infer missing data as fact. On confirmation the
   row joins the scope inputs tagged **with its extraction origin — `document` or `description`, immutable,
   never remapped (v1.4)** — confidence defaulting to `estimate` (the advisor may upgrade to `known` only
   where the source evidences it, e.g. a wage schedule).

**Storage is owned by the universal component, not each service (v1.2; batch model v1.3).** An extraction is
a **batch over N documents plus the advisor's description** — the schema must represent exactly that:
- **`quote_source_extractions`** (the batch): `{id, scopeable_type, scopeable_id, client_id,
  description_text (verbatim snapshot of the advisor's description at extraction time),
  description_captured_at, status: pending|extracted|blocked, extracted_rows (jsonb drafts with provenance),
  extracted_at, confirmed_row_ids}` — client-scoped RLS, audited.
- **`quote_source_extraction_documents`** (join): `{extraction_id, document_id,
  document_verification_id (FK — the exact verification row for THIS service claim, v1.4),
  verification_outcome_at_use}` — one row per consumed document. Persisting the verification **id**, not
  just an outcome string, makes every extraction auditable back to the precise claim-scoped verification it
  ran under.
Row provenance is three-valued: **`source: document | description | manual`** — `document` rows cite
`source_reference` (document id + locator) + `claim` (quoted text); `description` rows cite the batch's
`description_text` snapshot (+ offset) as their claim; `manual` is advisor-typed with no extraction origin.
There is **no mapping of `description` → `manual`** — the origin persists as-is through confirmation.
`integration_scopes` (and any I7 surface) is just a `scopeable`; adopting services need **no** storage of
their own. `integration_scopes.source_document_ids` remains a denormalised convenience; the extraction
tables are the source of truth.

**Pre-proposal anchor + ownership enforcement (v1.4).** Two structural holes in a bare polymorphic design:
(a) the proposal surface has **no `Proposal` to anchor to before fee calculation**
([ProposalController.php:30+](app/Http/Controllers/Advisor/ProposalController.php:30)) — so I7 introduces a
lightweight **client-scoped `quote_intakes` aggregate** (`{id, client_id, service_context, status}`) created
the moment an advisor starts any non-integration quote; it is the `scopeable` until/unless a service-specific
aggregate exists (integration already has `integration_scopes`). (b) polymorphic `scopeable_type/id` cannot
enforce tenancy through ordinary FKs — so a **consistency guard** (service-level assert + DB trigger, the
same pattern as the budget plan's owner guard) enforces `batch.client_id === scopeable.client_id === every
joined document.client_id`, with **cross-client tests** (a document or scopeable from another client is
rejected at write time, not discovered at read time).

**Confirmed rows keep their provenance (v1.2; three-valued v1.4 — traceability must survive confirmation).**
When a draft row is confirmed into `systems`/`tasks`/`connections`, it carries forward
**`source: manual|document|description`**, `source_reference` (document id + locator, or the batch's
description snapshot + offset) and `claim` (required whenever `source ≠ manual`). Confirmation moves
provenance *with* the row — it never strips or remaps it.

**Calculator contract:** engines read confirmed rows only, and treat rows of **every origin
(`manual`/`document`/`description`) identically in arithmetic** — provenance changes *trust display and
attribution*, never the math. The computed snapshot records `source_document_ids` so every quote is
traceable to its evidence.

**Universality note (honest scope):** the *component and pipeline* are universal by design; in v1 they are
**wired only to Integration Scoping** (the surface this plan builds). I7 mounts the same component on the
existing advisor quote surfaces (proposal creation, fee/service-package quoting) and documents the two-method
contract (`rowSchema()`, `extractionClaim()`) any future service (valuation intake, DD exploration, budget
review) implements to adopt it. No portal surface gets it in any service — advisor-only is the universal rule.

---

## 4. Data model — `integration_scopes` (client-scoped, one active per client engagement)

| Column | Type | Notes |
|---|---|---|
| `id` / `client_id` FK | uuid | client-scoped; RLS mirrors the goals/documents client pattern |
| `systems` | jsonb | rows: `{name, vendor, role, api_quality: rest_public\|rest_partner\|webhook\|csv_export\|none, auth: api_key\|oauth\|none, monthly_records, confidence, source: manual\|document\|description, source_reference?, claim?}` — provenance required when `source ≠ manual` (v1.3: `description` is its own origin, never remapped to `manual`) |
| `tasks` | jsonb | the duplicate-entry inventory: `{description, system_ids[], minutes_per_occurrence, occurrences_per: day\|week\|month, people_count, hourly_cost, confidence, source, source_reference?, claim?}` |
| `connections` | jsonb | the integrations to build: `{from_system, to_system, direction: one_way\|two_way, transform_complexity: low\|med\|high, task_ids[], confidence, source, source_reference?, claim?}` — `confidence` added v1.2 (connections are estimates too) |
| `delivery_mode` | string | `inhouse` \| `partner` \| `lowcode` \| `mixed` (D1) |
| `partner_cost_estimate` / `partner_margin_percent` | decimal | partner mode only; margin default 25% *(owner confirms)* |
| `computed` | jsonb | server-computed snapshot (§5): waste, savings, PV (+`pv_calculation_id`), complexity score + drivers, quote band, payback, ROI |
| `source_document_ids` | jsonb | verified `documents` consumed by extraction (v1.1); every id passed scan + verification |
| `extracted_rows` | jsonb | **pending** AI-drafted rows with provenance/claims — never read by the calculator until advisor-confirmed into `systems`/`tasks`/`connections` (v1.1) |
| `quoted_fee` | decimal nullable | advisor's final fee — must sit inside the band or carry a recorded override reason (audited) |
| `status` | string | `not_started` \| `partial` \| `complete` (complete = ≥1 system, ≥1 task, ≥1 connection, delivery mode set, hourly costs present) |
| `flags` | jsonb | §7 — same shape/semantics as budget flags |
| `proposal_id` / `goal_id` | FK nullable | set as the pipeline progresses |
| `scoping_credit_adjustment_id` | FK nullable → `billing_adjustments` | the scoping-fee credit (v1.5, §3) — absent until the scoping package payment lands |

## 5. The calculator — `IntegrationScopeCalculator` (pure, deterministic, unit-tested)

- **Annual waste (v1.2 — hours and dollars are separate calculations):**
  `annual_hours_wasted = Σ (minutes_per_occurrence × occurrences_per_year × people_count) / 60`, then
  `annual_cost_wasted = Σ (task_hours × hourly_cost)` per task (each task's hours priced at *its own* hourly
  cost, then summed — never a blended rate). Occurrence factors: day=260 (NZ working days), week=52, month=12.
- **Capturable savings:** `annual_cost_wasted × capture_percent` — default **80%**, advisor-adjustable 50–95% (integrations rarely eliminate 100%; the default is honest). Confidence summary (guess-ratio) carried exactly like the budget module.
- **PV of savings:** `PvEngine` over a **3-year horizon** (advisor-adjustable 1–5), advisor-configured discount rate (12% default, rationale recorded) → stored `PvCalculation` **with task-row attributions in its existing `source_attributions` column** — which the engine currently never populates ([PvEngine.php:113-127](app/Services/Pv/PvEngine.php:100) writes only discount rationale/inputs/result, while the model already casts `source_attributions`). **v1.5: extend `PvEngine::calculate()` with an optional `sourceAttributions` parameter** persisted to that column ({claim: task description + annual saving, source_reference: scope id + task row id}); built in I1 where the savings calc is created. Storing the mapping only in `integration_scopes.computed` is **not** equivalent — the attribution must live on the `PvCalculation` the proposal/goal reference.
- **Complexity score** (per connection, summed): api_quality points (`rest_public` 1, `rest_partner` 2, `webhook` 2, `csv_export` 3, `none` 5) + direction (`two_way` ×2) + transform (`low` 0/`med` 1/`high` 3) + auth (`oauth` +1) + volume tier (>10k records/month +1). Score → band: **S ≤6, M 7–14, L 15–26, XL 27+**. Drivers persisted so the quote is *explainable* ("XL because: two-way sync, no API on System X").
- **Quote band:** matrix `band × delivery_mode` from **admin-editable reference data** (§10 seeds; never hardcoded). Partner mode: `max(band_floor, partner_cost_estimate × (1+margin)) + scoping/PM fee`.
- **Payback + ROI:** `payback_months = quoted_fee ÷ (annual_savings/12)`; ROI over horizon. Displayed beside the fee, always.
- **Integrity rules:** the fee comes only from the band matrix + overrides (never scaled by savings, D2); all outputs numeric+flags (no display strings in `computed`); every assumption (capture %, horizon, discount) persisted with the snapshot.

## 6. Flags (flag-and-acknowledge, budget-module semantics)

`no_api_on_key_system` (a `none` api_quality system in a connection — cost/risk driver, high), `payback_over_24_months` (quote hard to justify — reconsider scope, high), `low_confidence_scope` (guess-ratio ≥ 0.5 — get real timings before quoting, medium), `savings_dwarf_quote` (savings > 5× fee — check inputs or scope larger, medium), `single_person_dependency` (one person does all captured tasks — key-person risk worth naming in the proposal, low), `fee_override_outside_band` (auto-raised when `quoted_fee` outside band; requires reason, advisor-visible, audited); **v1.1:** `source_discrepancy` (an uploaded document verified as `accuracy_discrepancy` — **blocking**: extraction and quote generation pause until **re-verification of the same claim context returns an allowlisted outcome** (§3A.3, v1.4 — `resolved_at` alone never clears it), per spec §9), `unconfirmed_extraction` (drafted rows awaiting advisor confirmation — the quote pack cannot generate while any remain, medium); **v1.7:** `source_unverified` (**blocking, high** — raised while any joined document's claim-context verification is `pending`, `verification_error`, or absent; **not acknowledgeable** — blocking flags cannot be waved through; **clears automatically** when that context's verification reaches an allowlisted outcome, and re-raises fresh per the standard lifecycle if a later document joins unverified).

## 7. Flow (end-to-end)

Scoping tool (advisor, client-scoped; optional **pre-fill from SystemsReview findings**/`ImprovementOpportunity` via `recommendation_ref`; **v1.1: optional document upload → scan → verify → AI extraction reads the plan *with* the description → advisor confirms drafted rows**, §3A) → computed panel (waste table, savings, PV, band, payback) → **Quote Pack** (client-facing Inertia page + Browsershot PDF, escape() discipline; systems map, task/waste table, quote, payback, assumptions, confidence disclosure) → **Generate proposal via the fee adapter (v1.2; ROI + guard + payment rails fixed v1.3):**
- **Fee:** proposal creation hard-requires a client-scoped `fee_calculation_id`
  ([ProposalController.php:43-48](app/Http/Controllers/Advisor/ProposalController.php:43)), so "Generate
  proposal" first creates a **`FeeCalculation` via `FeeMethod::Integration`**. **The integration path must
  NOT run the client-wide value basis** — `FeeCalculator::calculate()` derives improvement PV from all
  active `ImprovementOpportunity` records ([FeeCalculator.php:39-40](app/Services/Fees/FeeCalculator.php:37))
  and `ProposalBuilder` copies that PV/ratio into the proposal
  ([ProposalBuilder.php:511](app/Services/Proposals/ProposalBuilder.php:511)) — which would show a Standard
  Advisory client's whole analysis waterfall as this integration's ROI. The `Integration` branch takes its
  **value basis exclusively from the scope's saved savings `PvCalculation`**, and the proposal's ROI framing
  renders from that calc alone. **Server-enforced, not UI-enforced (v1.4):** the existing proposal endpoint
  checks only fee-calculation + Strategic Budget ([ProposalController.php:43-77](app/Http/Controllers/Advisor/ProposalController.php:43))
  — it has no knowledge of scope flags. The **`FeeMethod::Integration` adapter itself refuses** to produce a
  `FeeCalculation` (and therefore any proposal) while the scope carries `source_discrepancy`,
  `source_unverified`, or `unconfirmed_extraction` — feature-tested with a **direct POST**, not through the UI.
  **And creation-time checking alone is bypassable (v1.5):** `ProposalController` accepts **any** client-owned
  `fee_calculation_id` ([:62-64](app/Http/Controllers/Advisor/ProposalController.php:62)) and a scope can
  become blocked **after** its calculation was created. So `fee_calculations` gains an **immutable
  `integration_scope_id`** (set at creation, change-guarded), and the linked-scope flag check lives in a
  **shared `IntegrationScopeProposalGuard` invoked by EVERY proposal lifecycle path (v1.6)** — generate,
  **renew** ([ProposalController.php:165](app/Http/Controllers/Advisor/ProposalController.php:165) renews
  directly off the existing calculation), and **release** ([ProposalBuilder.php:139](app/Services/Proposals/ProposalBuilder.php:139)
  releases a draft later without recreating it) — a generate-only check is bypassable on both. Tests: create
  the calculation → raise a blocking flag → **each of** direct-POST generate with the pre-existing
  `fee_calculation_id`, renew of an expired proposal, and release of a draft → all rejected.
- **Strategic-budget guard:** proposals require an approved Strategic Budget **or** the explicit override
  ([ProposalController.php:65-77](app/Http/Controllers/Advisor/ProposalController.php:52)). Standalone
  integration quotes (client without Standard Advisory) use the **existing override path** — category from
  the current allowlist (`advisor_judgement` fits; extending the `Rule::in` list with `integration_scope` is
  a one-line option) with **auto-drafted notes referencing the scope id**; the advisor confirms. No new guard,
  no silent bypass.
- **One payment path (the rails are disjoint today):** signature triggers proposal invoice scheduling
  ([SignoffFlow.php:86-88](app/Services/Proposals/SignoffFlow.php:86)) while `ServiceActivation` runs its own
  package-selection/payment lifecycle ([ServiceActivationManager.php:305+](app/Services/ServiceActivations/ServiceActivationManager.php:305))
  — wiring both naively double-charges or dead-locks activation. Rule: **the proposal is the contract and
  the only invoicer — for the BUILD stage (v1.6)**; the scoping stage is paid separately through its
  workspace-package activation payment (§3), which is precisely what funds the credit. And "payment event" is **not one integration point today (v1.4)**: successes are
  recorded independently by the collector ([PaymentProcessor::recordSuccessfulAttempt](app/Services/Payments/PaymentProcessor.php:180))
  **and** the webhook reconciler ([PaymentWebhookReconciler.php:129](app/Services/Payments/PaymentWebhookReconciler.php:129))
  — a webhook-recorded production payment would otherwise bypass activation. So: one **idempotent
  post-success application service** (e.g. `ApplyProposalPaymentOutcome`) called by **both** paths;
  `service_activations.proposal_id` nullable **with a unique index**; explicit activation transitions
  (`awaiting_proposal_payment → active`, audited). **The activation path is bespoke, not the portal-accept
  flow (v1.5):** today acceptance is a client portal action and the workspace factory routes **every non-DD
  service into the entrepreneur workspace** ([ServiceActivationManager.php:556-565](app/Services/ServiceActivations/ServiceActivationManager.php:556),
  [ServiceActivationController.php:153](app/Http/Controllers/Portal/ServiceActivationController.php:153)) —
  a naive `SERVICE_INTEGRATION` transition would mint an entrepreneur profile. So:
  **`activateFromProposalPayment(activation)`** — no portal accept step (client consent = the **signed
  proposal**, its signoff evidence snapshotted onto the activation); `ensureWorkspace` gains explicit
  integration branches — **scoping** activation's workspace *is* the scoping tool (advisor client
  workspace), **build** activation is a no-op navigation with an advisor-side delivery view (goals/
  milestones) and an optional portal status banner; **neither ever creates an entrepreneur profile** (tested).
  Tested: exactly one invoice schedule per accepted integration proposal, and firing **both** success paths
  for the same payment produces **one** transition.
- → activation (**`SERVICE_INTEGRATION`** added to the DD/entrepreneur-only whitelist,
  [ServiceActivationManager.php:451-460](app/Services/ServiceActivations/ServiceActivationManager.php:451))
  → **auto-create Goal** — which needs **two minimal `GoalTracker` extensions (v1.3 — "unchanged" was
  wrong):** (i) `createGoal` accepts an existing `pv_target_calculation_id` (today it either computes a new
  PV from cash flows or stores null, [GoalTracker.php:42-65](app/Services/Goals/GoalTracker.php:40) — it
  cannot attach the scope's saved savings calc); (ii) a **measurement contract** — `recordMeasurement(goal,
  measuredValue|PvCalculation, source, measured_at)` persisting top-down re-measurements + variance vs
  target, because `pvRealisedTotal()` only sums completed milestones' original `pv_of_impact`
  ([:220-226](app/Services/Goals/GoalTracker.php:220)) and cannot receive a re-measured value. Milestones per
  connection (`recommendation_ref` = connection id) → delivery → proof-verified completion (that flow is
  unchanged) → **+90-day re-measure**: same task rows re-entered with current minutes → realised delta
  recorded via `recordMeasurement`, surfaced beside the milestone-based `pvRealisedTotal` (bottom-up proven
  vs top-down measured — both, labelled), feeding the outcome loop. *(The AI extraction that drafts system/task/connection rows from the advisor's description + uploaded documents is **WO-I6, core** — see §3A; registered in the prompt registry as advisor-side/non-examiner.)*

## 8. Work orders

| WO | Deliverable |
|---|---|
| **I1** | `integration_scopes` migration (+ client RLS, audit) ; `IntegrationScopeCalculator` **with the worked-example unit-test matrix mandated up front** (waste math incl. day/week/month factors, capture bounds, complexity bands at boundaries 6/7, 14/15, 26/27, partner-margin path, payback, zero/empty edges) — *the budget module shipped with 2 tests for a 666-line engine; this WO does not repeat that*; persist service with status/flags/audit; **v1.5: `PvEngine::calculate()` extended with optional `sourceAttributions` persisted to the existing `PvCalculation.source_attributions` column** (task-row claims for the savings calc). |
| **I2** | Scoping UI **shell** (advisor, client workspace): systems/tasks/connections repeatable rows with confidence tags, computed panel, flags, band + payback display; autosave with audit debounce. **v1.3: does NOT mount the document intake** — `ScopeDocumentIntake` (upload, per-document verification status, discrepancy/unverified banners, extracted-row review/confirm with provenance + claim quotes) is **built and mounted in I6**, which owns the component, migrations, pipeline, and endpoints. |
| **I3** | Quote-band matrix + capture %/margin defaults in admin reference data (audited edits); band resolver; `fee_override_outside_band` handling. |
| **I4** | Quote Pack page + PDF; **the quote-to-cash adapter (v1.2):** new `FeeMethod::Integration` case + `FeeCalculator` path producing a client-scoped `FeeCalculation` from the band-matrix resolution (attributed to the computed snapshot) so `ProposalController`'s required `fee_calculation_id` is satisfied; proposal pre-fill; **`ServiceActivation::SERVICE_INTEGRATION`** constant + `normaliseServiceType` + activation flow (today DD/entrepreneur-only, [:451-460](app/Services/ServiceActivations/ServiceActivationManager.php:451)); **Authoritative deliverable order (v1.9 — supersedes ALL earlier summaries):** (1) `billing_adjustments` + `billing_adjustment_applications` + **`payment_installments`** migrations (full §3/§4 schema incl. `processing_started_at`), **`payments.payment_installment_id` + `payments.idempotency_key` (v1.15)**, the **attempt-uniqueness split** (legacy `UNIQUE(schedule, attempt)` made partial `WHERE installment IS NULL`; new `UNIQUE(installment, attempt)` — v1.16), the **partial unique open-activation index**, and the **six** same-client write guards; (2) `fee_calculations.integration_scope_id` (immutable) + **`IntegrationScopeProposalGuard`** wired into generate/renew/release; (3) `FeeMethod::Integration` adapter (server-side refusal on blocking scope flags; ROI from the scope's `PvCalculation` only); (4) two service types + **`offerIntegrationScoping`** (dedicated manager action: `advisor_offer` source, `integration.scoping_offered` audit, client notification, package-selected atomically; portal creation stays DD/entrepreneur-only) + **`activateScopingFromPackagePayment`**; (4b) the **installment attempt state machine** (`due → processing → settled | failed(definitive decline → reopen due) | awaiting_gateway_confirmation(ambiguous → webhook/lookup resolves)`, conditional transitions in collector AND webhook reconciler, one active/succeeded attempt per installment, **same idempotency identity on ambiguous retries**, **zero-payment internal succeeded `Payment` + receipt**, **the pre-I/O write-ahead transaction + stale-`processing` sweep (v1.15)**, **`ConfirmAmbiguousPayments` recovery job + `manual_review` escalation**, **three-way typed gateway outcomes (`DefinitiveDecline`/`PendingConfirmation`/`AmbiguousOutcome`) with failover only on `DefinitiveDecline` and no receipt/activation before confirmation** (v1.12–13) + **`findCharge` lookup contract per gateway client**, and **installments as the sole clock** for installment-backed schedules — the collector selects due installments respecting `next_attempt_at`, not `next_run_at`); (5) **the `payment_installments` model (v1.7)** — single source for invoice generation, card charging (`PaymentProcessor` charges the due installment's `net_amount`), and outcomes; credit consumed via `billing_adjustment_applications` keyed to installments (unique pair), earliest-first, named Xero lines, `remaining_amount` derived from the ledger; `settled_zero` auto-settlement counting as a payment outcome; (6) **`ApplyProposalPaymentOutcome`** idempotent post-success service wired into **both** `PaymentProcessor` and `PaymentWebhookReconciler` (fired equally by `settled_zero`); (7) `service_activations.proposal_id` (unique) + **`activateFromProposalPayment`** with signed-proposal consent evidence + explicit integration workspace branches (never an entrepreneur profile) + **`activateScopingFromPackagePayment`** (v1.7 — offer transaction package-selects; payment activates scoping directly, consent = payment + offered-terms snapshot); (8) Quote Pack page + PDF + proposal pre-fill; (9) workspace-package catalog entries. **Proposal = sole invoicer for the build only**; scoping is package-paid. |
| **I5** | Goal/milestone auto-creation on activation via the **two `GoalTracker` extensions (v1.3)** — `createGoal(pv_target_calculation_id:)` attach-existing path + `recordMeasurement()` contract; +90-day re-measure flow storing top-down measurements + variance beside the milestone-based `pvRealisedTotal` (both labelled); outcome data recorded. |
| **I6** *(core; authoritative spec = §3A as amended v1.3/v1.4 — this row supersedes all earlier gating language)* | `QuoteSourceExtractor` — `SecureFileWriter` → `DocumentVerifier` with the **service claim** → **per-document positive-allowlist gate on the verification row for THIS claim's `context_hash`** (`verified`, or `advisory_flag` + `resolved_at`; `pending`/`verification_error` block; `accuracy_discrepancy` clears **only by re-verifying the same context to an allowlisted outcome** — `resolved_at` alone never allowlists it) → `AiClient` extraction over **document(s) + description snapshot** → drafts in the **batch model** (`quote_source_extractions` batch + `quote_source_extraction_documents` join persisting the exact **`document_verification_id`** consumed; migrations + client RLS + the cross-client **ownership guard** here) → confirm/reject endpoints (audited; three-valued origin travels with the row). **Own text pipeline:** chunking with stable locators (page/paragraph; sheet/cell) — the verifier's capped excerpt ([DocumentVerifier.php:149](app/Services/Ai/Verification/DocumentVerifier.php:149)) is **not** reused. Prompt registered (advisor-side); `FakeAiClient` tests. |
| **I7** *(universal seam, v1.1; anchored v1.4)* | Generalise: extract the two-method service contract (`rowSchema()`, `extractionClaim()`); introduce the **client-scoped `quote_intakes` aggregate** as the pre-proposal `scopeable` for surfaces with no persisted object yet; mount `ScopeDocumentIntake` on the existing **advisor** quote surfaces (proposal creation; service-package/fee quoting); cross-client **ownership-guard tests** on every adopting surface; document adoption for future services (valuation intake, DD exploration, budget review). Advisor-only everywhere; **no portal mounting, any service**. |

Sequence (v1.3 — intake dependency fixed): **I1 → I2 (shell) / I3 → I6 (owns + mounts the intake) → I4 → I5
→ I7.** One WO per branch/PR.

## 9. Testing (gates stay green)

Calculator worked examples (I1, mandated); RLS: advisor-on-client access, cross-client denial, no portal access in v1; flag lifecycle incl. re-raise-fresh semantics; fee override requires reason + audit; proposal/payment flow feature test; goal auto-creation carries the right `pv_target_calculation_id`; re-measure computes variance; PDF output escapes all client-entered text; **v1.1 document-intake gates:** infected upload rejected + audited; unverified document never reaches extraction; **v1.2:** `advisory_flag` **holds** extraction until acknowledged through the existing verification flow, then proceeds; `accuracy_discrepancy` blocks extraction **and** quote-pack generation until **re-verification clears it** (v1.4 semantics); a **portal/entrepreneur user posting to the upload or confirm endpoints is denied** (the advisor-only universal rule); unconfirmed `extracted_rows` never affect `computed` (calculator output identical before/after an unconfirmed draft lands); every confirmed row **retains** `source`/`source_reference`/`claim` after confirmation (provenance survives the move); `annual_hours_wasted` and `annual_cost_wasted` asserted independently (mixed hourly costs across tasks — no blended rate); **`FeeMethod::Integration` produces a client-scoped `FeeCalculation` that `ProposalController` accepts, and `SERVICE_INTEGRATION` passes `normaliseServiceType` + activates**; **v1.3 rail tests:** a `pending`/`verification_error` document never reaches extraction (positive-allowlist gate) while an **unrelated** flagged document elsewhere does **not** block this quote (per-document gating); the description snapshot persists on the batch and `description`-sourced rows keep that origin through confirmation; **a client with active unrelated `ImprovementOpportunity` records gets proposal ROI from the scope's `PvCalculation` only** (never the client-wide waterfall); a standalone client without an approved Strategic Budget generates the proposal **only** through the override path (category + notes recorded); **exactly one invoice schedule per accepted integration proposal** and the activation completes from the proposal's payment event (no second charge, no dead-lock); `createGoal` attaches the existing savings calc (no recompute, not null); `recordMeasurement` stores the re-measure + variance without mutating milestone `pv_of_impact`; **v1.4 identity/rail tests:** the gate reads the verification row for **this claim's `context_hash`** (a `verified` row for a *different* claim does **not** allowlist); `accuracy_discrepancy` with `resolved_at` set **still blocks** until re-verification of the same context returns an allowlisted outcome; the join row persists the exact `document_verification_id`; the `Integration` adapter **refuses via direct POST** while blocking scope flags stand; **both** payment-success paths (collector + webhook) fire for one payment → exactly **one** activation transition (idempotent), and `service_activations.proposal_id` uniqueness holds; an open scoping activation does **not** block the build activation (distinct types; scoping closes in the accept transaction); the scoping credit reduces the build invoice **exactly once**; a cross-client `scopeable` or joined document is **rejected at write time**; **v1.5:** create-calc → raise blocking flag → **direct-POST the pre-existing `fee_calculation_id` → rejected** (proposal-time re-check); `fee_calculations.integration_scope_id` immutability enforced; integration/scoping activation **never creates an entrepreneur profile** and lands on its explicit workspace path; the savings `PvCalculation` carries task-row `source_attributions`; the billing adjustment's `status` transitions once (`available → applied`) with per-invoice applications ledgered and the Xero payload carrying the named credit line on each affected invoice; **v1.6:** a credit **larger than the first instalment** spreads across earliest invoices, never below zero, Σ invoices = fee − credit; **renew and release of a proposal whose linked scope is blocked are rejected** (each path tested); the advisor-offer → portal-payment scoping path works while portal *creation* of the type is denied; all four financial links reject cross-client rows at write time; **v1.7:** **Stripe and Xero agree** — the card charge equals the credited installment's `net_amount`, never the schedule base; a credit zeroing the first instalment(s) produces `settled_zero` outcomes that **still activate** the build; the offer transaction lands `package_selected` and **payment alone activates the scoping workspace** (no portal accept), with consent evidence persisted; `(adjustment_id, payment_installment_id)` uniqueness holds and applications **survive invoice regeneration**; `remaining_amount` always equals amount − Σ ledger; `source_unverified` cannot be acknowledged and auto-clears on allowlisted re-verification; **v1.8:** every installment-backed `Payment` carries `payment_installment_id` and the zero-settlement's internal succeeded `Payment` yields a real receipt; concurrent duplicate offers hit the **partial unique index** and fail cleanly (race test); the fifth tenant link (application → installment → schedule → client) rejects cross-client writes; the offer records `source: advisor_offer`, audits `integration.scoping_offered`, and notifies the **client**, never spawning a request thread; **v1.9:** **two concurrent collectors on one due installment produce exactly one gateway charge** (conditional `due → processing` transition; the loser sees zero rows affected); the webhook reconciler settles only via the same conditional transition; the partial unique on non-terminal `payments(payment_installment_id)` holds; a cross-client `payments.payment_installment_id` link is rejected (sixth guard); **v1.10:** an ambiguous gateway outcome parks the installment in `awaiting_gateway_confirmation` — a second collector cannot re-attempt it, a later webhook success settles the **original** payment without a duplicate, and only a confirmed not-charged reopens `due`; a definitive decline reopens immediately; ambiguous retries reuse the same idempotency identity (asserted against the gateway fake); **v1.11:** a delayed webhook resolves a parked installment via `ConfirmAmbiguousPayments`; missing webhook + unreachable provider escalates to `manual_review` with an alert (never a silent permanent block, never an auto-reopen); an installment-backed schedule's collection order/timing derives **only** from installments (`next_run_at` untouched and unconsulted; zero-value installments are not skipped); the ambiguous-outcome `Payment` row is reused for same-key retries and a fresh row/key appears **only after** the original terminalizes; **v1.12:** primary capture + lost response → **no secondary-provider charge** (typed outcomes; ambiguous never fails over; the parked installment records the attempted provider); a declined-and-reopened installment is **not** selected before its `next_attempt_at` (backoff respected) and exhausted attempts pause the schedule per the existing policy; **v1.13:** a Stripe `processing` result produces **no receipt, no outcome application, no activation** until confirmed (`processing → succeeded` and `processing → payment_failed` both tested); `findCharge` resolves a lost-response charge via the idempotency-key/`payment_id`-metadata correlation; a `not_found` lookup result **does not reopen** the installment before the deadline; **v1.14:** the charge request is sent and the worker is killed before response handling → the stale-`processing` sweep parks the installment and the lookup settles/reopens it from the **pre-I/O persisted** provider + idempotency-key correlation (never stuck in `processing`, never double-charged); **v1.16:** two due installments on one schedule charge concurrently with **no unique-constraint collision** and independent per-installment attempt sequences, while a legacy schedule still rejects a duplicate schedule-level attempt number; **v1.17:** timeout → lookup confirms not-charged → the fresh `Payment` takes ordinal **2** via the atomic allocator (no unique collision), while transport retries of the parked payment allocate no ordinal; extraction tested with `FakeAiClient`; `pint --test`, Larastan (if adopted), `tsc`, ESLint, full PHPUnit.

## 10. ⛔ Owner-confirm before I3 seeds (commercial values — not inventable)

Suggested defaults, from the NZ market work earlier (all ex GST): **Low-code:** S $3,500–5,500 · M $6,500–9,500 · L $12,000–18,000 · XL scope-first. **In-house custom:** S $6,500–9,500 · M $12,000–19,500 · L $22,000–38,000 · XL $45,000+. **Partner:** cost + 25% margin + $1,800 scoping/PM, floor at the in-house band minimum. **Support retainer** (recommended add-on, per integration/month): $120–$350 — integrations break when vendors change APIs; recurring revenue + honest expectation-setting. Capture % default 80. Scoping fee $950–$1,500 credited on acceptance. **These seed as admin-editable reference data marked owner-approved; the mechanics ship regardless.**

## 11. Out of scope (v1)

Building the client integrations themselves (that's the delivered service, not this platform); a connector library/marketplace; client self-serve scoping **and client/portal document-assisted input — the §3A intake is advisor-initiated only, universally**; coupling to Standard Advisory (pre-fill is optional, not required); automatic fee escalation from savings (D2); auto-confirming extracted rows (the advisor confirms every row, no exceptions).

## 12. CLAUDE.md block (add on build)

> **Integration Efficiency service.** Scoping lives in `integration_scopes` (client-scoped RLS); the fee comes
> **only** from the admin band matrix (complexity × delivery mode) — never derived from savings. Savings PV is
> a stored `PvCalculation` with attributions — and the integration fee/proposal ROI reads **that calc only**,
> never the client-wide improvement waterfall. Accepted quotes auto-create a Goal (`pv_target` = savings PV,
> attached via `createGoal`'s attach-existing path) with per-connection milestones; completion is
> proof-verified via `GoalTracker` (proof flow unchanged; re-measures go through `recordMeasurement`, never
> by mutating milestone `pv_of_impact`). **The proposal is the only invoicer for the build stage** (scoping
> is package-paid, and that payment funds the credit) — integration activations
> complete payment from the proposal's payment event. Extraction gates **per document** on a positive
> allowlist (`verified`, or acknowledged `advisory_flag`); `pending`/`verification_error` block. Quote-band values,
> capture %, and margins are owner-set reference data — never hardcoded. Flags are flag-and-acknowledge; fee
> overrides outside the band require a recorded reason.
> **Document-assisted quote input (universal):** advisor-initiated **only**, on every service — never mounted
> on portal surfaces. Order is inviolable: virus scan → document verification → extraction; an
> `accuracy_discrepancy` pauses extraction *and* quote generation until **re-verification of the same claim
> context clears it** (`resolved_at` never allowlists a discrepancy). AI-extracted rows are drafts
> with per-row provenance + claim quotes; **nothing enters a calculator until the advisor confirms it**, and
> calculators treat confirmed rows of every origin (`manual`/`document`/`description`) identically in
> arithmetic. The scoping-fee credit is a `billing_adjustments` record applied exactly once; integration
> activations use `activateFromProposalPayment` and **never create an entrepreneur profile**.
