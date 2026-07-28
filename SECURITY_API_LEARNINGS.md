# Security & API Learnings

Findings extracted from review of the Future Shift Advisory codebase (Laravel 13 + Inertia/React + PostgreSQL 16 with RLS), written as reusable rules for future builds. Every item below is drawn from actual code in this repo, not generic guidance.

Both defects and patterns worth repeating are included — knowing which controls actually saved the system is as useful as knowing which failed.

---

## 1. Authentication & Authorization

1. **Policy methods accepted the subject and then ignored it.** `ClientPolicy::view/update/delete` are declared `(User $user, mixed $client = null)` but only ever check `$this->allows($user, Permission::CLIENTS_VIEW)` ([ClientPolicy.php:20-38](app/Policies/ClientPolicy.php:20)). Any user holding the permission passes the check for *every* client, so `$this->authorize('view', $client)` reads as a scoped check at every call site while being a global one. **Rule:** If a policy method takes a subject, it must reference the subject; a policy that ignores its argument should not accept one.

2. **Row-level security, not the policy layer, was the control that actually held.** Cross-client access was prevented by Postgres RLS with `FORCE ROW LEVEL SECURITY` and `fsa_current_client_ids()`, which meant the policy gap above degraded to defence-in-depth rather than a breach. **Rule:** Put tenant isolation in the database where every query path inherits it, and treat application-layer authorization as the second line, never the only one.

3. **The access accessor returned IDs only, so callers re-derived the reasoning.** `User::accessibleClientIds()` resolves two distinct attachment paths — direct `client_team` rows and advisor-team leadership — but returns a flat ID list ([User.php:216-273](app/Models/User.php:216)). Any feature needing *why* access was granted (for audit, or for a basis field) had to re-implement both paths, and the copies drift. **Rule:** Where authorization has multiple grant paths, expose a resolver returning the grant *and its basis*, and test the resolver against the boolean accessor so they cannot diverge.

4. **RLS visibility is asymmetric by role, which silently breaks cross-actor reads.** The `advisor_teams` policy is keyed on `lead_advisor_user_id = fsa_current_user_id()`, so under a *client's* session context zero advisor-team rows are visible — an advisor-lookup executed while a client is the actor returns empty rather than erroring. **Rule:** Any code that reads another actor's records — approval flows, background sweeps, webhook handlers — must run inside an explicit system context; assume every RLS-scoped read returns empty until you have proven whose context it executes in.

5. **Login throttling was keyed on email + IP, not IP alone.** `RateLimiter::for('login')` composes the transliterated username with the request IP at 5/minute ([FortifyServiceProvider.php:119-126](app/Providers/FortifyServiceProvider.php:119)). This blocks credential stuffing against one account without letting one abusive IP lock out an entire office behind a shared NAT. **Rule:** Key auth rate limits on the credential *and* the source, never the source alone.

---

## 2. Secrets & Credential Management

6. **`.env.example` shipped empty placeholders, never sample values.** Every secret key (`APP_KEY`, `ANTHROPIC_API_KEY`, `XERO_CLIENT_SECRET`, `STATS_NZ_API_KEY`) is present as a bare name with no value. A template carrying a plausible-looking fake secret invites someone to commit a real one in the same shape. **Rule:** Keep secret names in the env template and values empty, so a populated value in a diff is always a mistake.

7. **The client-bundle boundary was drawn by naming convention alone.** `VITE_REVERB_APP_KEY` is exposed to the browser bundle while `REVERB_APP_SECRET` is not — correct, but the two live adjacent in the same file and differ by one word. Any `VITE_`-prefixed variable is compiled into shipped JavaScript. **Rule:** Never let a secret's env name differ from a public value's by a single token; audit the `VITE_`/`NEXT_PUBLIC_` namespace as a publication surface, not configuration.

8. **Third-party credentials went through an encryption envelope rather than raw config reads.** Integration secrets are resolved via `IntegrationCredentials` and encrypted at rest with `KeyEnvelope`, and are masked in logs and audit payloads. **Rule:** Route third-party credentials through a single accessor that can enforce encryption, masking, and rotation — direct `config()` reads scattered across services make those guarantees unenforceable.

---

## 3. Data Protection

9. **The redaction helper was applied to logs and audit, but not to the AI provider path.** `Redactor` is wired into `AuditWriter`, `ResilientHttp`, and `HealthRecorder`, yet nothing in `app/Services/Ai/` references it — client business data reaches Anthropic exactly as composed. That may be intended (the model needs context to analyse), but it means the boundary is "whatever the prompt builder happened to include" rather than an enforced filter. **Rule:** Decide explicitly what a third-party model may receive and enforce it at the client boundary; a redaction helper that covers logs but not outbound AI calls protects the artefact nobody was worried about.

10. **Audit records redacted the payload but stored IP and user agent raw.** `AuditWriter::record()` passes `before`/`after` through `Redactor` while writing `$this->request->ip()` and `userAgent()` unmodified ([AuditWriter.php:77-80](app/Services/Audit/AuditWriter.php:77)). Those are personal data in most privacy regimes even when the payload is clean. **Rule:** Treat request metadata as part of the record subject to the same retention and redaction policy as the payload — redacting the body while logging the identifier is not de-identification.

11. **AI content isolation was enforced by a CI test, not by convention.** Entrepreneur prompts are classified in `EntrepreneurPromptRegistry`, and `AiContentIsolationTest` fails the build if scoring-rubric content reaches a coaching prompt or vice versa. **Rule:** When two data classes must never mix in a generated payload, encode the separation as a registry plus a test that fails on violation — a documented rule with no executable guard erodes at the first deadline.

12. **Uploads failed closed when the scanner was unavailable.** The `FileScanner` binding resolves to `ClamAvScanner` when live, `NoopScanner` *only* if explicitly allowed, and otherwise `UnavailableScanner` ([AppServiceProvider.php:67-79](app/Providers/AppServiceProvider.php:67)) — an unconfigured environment refuses uploads rather than accepting them unscanned. **Rule:** Make the default binding for a security control the one that refuses service; "no scanner configured" must never resolve to "no scanning required".

---

## 4. Input Validation & Injection Risk

13. **193 of 195 models set `$guarded = []`, leaving mass assignment open by default.** Nothing exploits it today because no controller passes `$request->all()` into `create`/`update`/`fill` — the protection is developer discipline across ~200 files, not a mechanism. Notably `User` is the exception, using an explicit `#[Fillable([...])]` list. **Rule:** Make the model the enforcement point with explicit fillable lists on anything holding roles, status, ownership, or money; relying on every future controller to hand-pick fields is a policy, not a control.

14. **Full-text search used bound parameters inside raw SQL.** The knowledge search passes the term as a binding — `whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$search])` ([KnowledgeController.php:232-233](app/Http/Controllers/Advisor/KnowledgeController.php:232)) — rather than interpolating it. **Rule:** Raw SQL is acceptable for expressions the query builder cannot express; string interpolation of user input into that SQL is not, and the two are easy to conflate in review.

15. **One raw aggregate interpolated a column name from a method argument.** `sumByClient()` builds `DB::raw("sum({$column})")` ([PracticeHealthReport.php:242](app/Services/Reports/PracticeHealthReport.php:242)). Both current call sites pass hardcoded literals, so it is safe today — but the signature accepts any string, so safety depends on every future caller. **Rule:** When an identifier must be interpolated into SQL, validate it against an allowlist inside the function rather than trusting the call site.

16. **Rich text was sanitised at the source, not at the render sink.** Admin-authored welcome content is cleaned by `WelcomeMessageSanitizer` server-side before storage, then rendered through `dangerouslySetInnerHTML` in the portal dashboards. **Rule:** Sanitise HTML on write, store only the sanitised form, and keep the list of `dangerouslySetInnerHTML` sites small enough to enumerate — auditing sinks is tractable; auditing every path that reaches them is not.

---

## 5. API Design

17. **Pagination was effectively absent: one `paginate()` call across all controllers against 135 unbounded `->get()` calls.** Collections that grow with client count, audit volume, or analysis history are returned whole. This degrades quietly — fine in test data, a memory and latency problem at real volume, and a denial-of-service amplifier on any endpoint an attacker can call repeatedly. **Rule:** Default list endpoints to paginated responses and make unbounded fetches the exception that requires justification.

18. **The payment status enum had no intermediate state, which blocked an entire payment rail.** `Payment` modelled `pending | succeeded | failed | retrying` — adequate for synchronous card capture, but with nowhere to express "submitted, awaiting settlement". Adding bank direct debit required new states, new indexes, and changes to every consumer of `succeeded`. **Rule:** When modelling an outcome, ask whether a *third* party can leave it undetermined for hours or days; a two-outcome enum silently assumes synchronous resolution.

19. **The gateway abstraction was a concrete class with two hardcoded implementations.** `Gateway` is a `final class` taking `StripeClient` and `WindcaveClient` as constructor dependencies, and `secondaryGateway()` is a binary flip between them. Adding a third provider meant either widening a method whose meaning eight call sites depended on, or building a parallel path. **Rule:** If a second provider was added by injecting it alongside the first, the next one needs an interface and a registry — the binary-flip failover is the signal you have run out of room.

20. **Typed route generation kept the frontend contract in sync.** Wayfinder generates `resources/js/actions/**` from controllers, so a renamed route or changed signature surfaces as a TypeScript error rather than a runtime 404. **Rule:** Generate the client-side API surface from the server definition; hand-maintained endpoint constants drift silently and fail in the browser.

21. **API versioning was in place from the start (`advisor/v1`, `mobile/v1`) but webhooks were unversioned.** Third-party callers cannot be asked to migrate on your schedule, which is precisely why their payload shape needs a version boundary too. **Rule:** Version inbound webhook routes as deliberately as outbound APIs — the caller you cannot coordinate with is the one you most need room to change around.

---

## 6. Webhook & Integration Security

22. **Replay protection was a database invariant, not application logic.** `payment_webhook_events` carries `unique(gateway, event_id)` plus a `payload_hash`, so a replayed delivery fails on insert rather than depending on a correct check-then-act. **Rule:** Enforce webhook idempotency with a unique constraint on `(provider, event_id)`; a duplicate that races two workers must be impossible, not merely unlikely.

23. **Event-ID extraction was hardcoded to one provider's payload shape.** `PaymentWebhookReconciler::eventId()` reads `$payload['id']`, which is Stripe's field. A provider using a different name (Ezypay uses `requestId`) yields a null ID — and a null ID silently disables the dedupe in finding 22, because there is nothing to collide on. **Rule:** Put payload parsing behind a per-provider adapter, and reject any event whose ID cannot be extracted rather than storing it with a generated one.

24. **Signature verification assumed every provider signs the same way.** `PaymentWebhookVerifier` enforces an HMAC plus a timestamp tolerance window per gateway — a sound pattern that does not generalise, because some providers sign the raw body with no timestamp at all. Reusing the interface without the timestamp leaves replay protection resting entirely on finding 22. **Rule:** Treat signature scheme, hash algorithm, and replay defence as per-provider facts to be verified in their docs, and record which compensating control covers a provider that omits one.

25. **The reconciler re-checked amount and currency against its own record before applying an event.** A verified signature proves origin, not correctness — this second check catches a mismatched or misrouted event. **Rule:** Validate that an inbound event agrees with your own state before acting on it; authentication of the sender is not validation of the content.

26. **Webhook endpoints were unauthenticated *and* unthrottled.** `webhooks/payments/stripe`, `webhooks/payments/windcave`, `webhooks/prospects`, and `dd/guest-uploads/{token}` carry no `throttle` middleware while the internal `advisor/v1` and `mobile/v1` APIs do ([api.php:15-43](routes/api.php:15)). Signature verification rejects forgeries but still runs on every request, so an attacker can force unbounded HMAC computation and database lookups. **Rule:** Rate-limit public webhook routes by source; signature verification is the correctness control, not the availability control.

27. **The mandatory retry layer was itself a double-charge risk on payment calls.** Every external call routes through `ResilientHttp` + `RetryPolicy` + `CircuitBreaker` — correct for reads, dangerous for a POST that moves money, because a timed-out request may have succeeded. **Rule:** A blanket retry policy must not apply to non-idempotent operations; require a provider idempotency key or set `maxAttempts: 1` and resolve ambiguous outcomes by querying the provider rather than retrying.

28. **Reconciliation ran inside an explicit system context.** Webhook handlers wrap their work in `withSystemContext()` because an inbound event carries no authenticated user, and without it RLS hides the very rows being reconciled. **Rule:** Give unauthenticated-but-trusted execution paths an explicit system context, and make it a named wrapper so it appears in review rather than being implied by absence.

---

## 7. Dependency & Environment Risk

29. **Integration activation defaulted to off across every third party.** `FEATURE_NZBN_LIVE`, `FEATURE_XERO_LIVE`, `FEATURE_VIRUS_SCAN_LIVE` and peers all default `false`, so a misconfigured environment cannot accidentally transact against a live third party. **Rule:** Gate every external integration behind an explicit per-provider live flag defaulting to off; environment parity failures should degrade to inert, not to production side effects.

30. **The env template is dev-shaped (`APP_ENV=local`, `APP_DEBUG=true`), which is correct but load-bearing.** Anyone provisioning by copying `.env.example` starts with debug enabled, and `APP_DEBUG=true` in production exposes stack traces, environment contents, and query detail on every error page. **Rule:** Keep the template dev-shaped for local ergonomics, but add a boot-time assertion that refuses to serve when `APP_ENV=production` and `APP_DEBUG=true` — the check costs nothing and closes the most common single-variable production leak.

---

## Cross-cutting patterns worth carrying forward

- **Push invariants into the database.** The controls that held under review were the ones the database enforced: RLS, unique constraints for idempotency, append-only triggers, partial unique indexes for in-flight state. The ones that slipped were the ones depending on every call site behaving.
- **A guard defined is not a guard installed.** In Postgres, adding a branch to a shared trigger function does nothing until a `CREATE TRIGGER` names the table. Anything that reads as enforcement in review but executes nowhere at runtime is worse than a known gap.
- **Establish associations before the asynchronous step, never reconstruct them after.** Three separate defects in this codebase shared one shape: a record needing an identifier that only exists after the operation it authorises. If a webhook must find "the right row", write the correlation key before the outbound call.
- **Check-then-act across a boundary is a race.** Reading a status, then acting on it in a separate statement, is unsound whenever another process can write in between. Use conditional updates (`UPDATE … WHERE id = ? AND status = ?`) and treat zero affected rows as "someone else won".
- **Ask what a control keys on, not whether it exists.** Several findings here (5, 7, 23, 26) are cases where the mechanism was present and correct but scoped to the wrong key, field, or namespace.
