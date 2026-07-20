# PLAN — Client Screen Support (Native WebRTC Co-Browsing)

**Source spec:** `FSA_Client_Screen_Support_WebRTC_Scope_v1.0.docx` (v1.0, July 2026) — Option B, native
WebRTC, view-only, two-layer consent, desktop-only, no recording.
**Plan version:** 1.7 — implementation-ready test-environment revision; review-fixed seven times (v1.1 participant
identity, live presence, TURN wording, terminal states; v1.2 picker deadline, authorization
privilege-escalation, timing mechanism, RLS system context; v1.3 multi-tab fan-out, advisor-team attachment
regression, queue-latency honesty, config completeness; v1.4 cross-actor RLS context at approval,
first-response-wins consent, per-connection context, accurate audit claim, S1 de-duplicated; v1.5 pre-bind
consent authorization with prompt nonces, presence keyed by client+user, shared attachment resolver,
allowlisted route-key context; **v1.6 explicit server-established portal context, signed per-connection page
context, winning-response context recorded for approve and decline, resolver terminology aligned; v1.7 Reverb
implementation, session-bound TURN credentials, realtime recovery worker, audited timing controls, test
configuration, operational runbook, and focused automated coverage**).
*(Build target: Codex, into the test env, then push to live.)*

**Implementation status (v1.7):** The application source is implemented for test: Reverb transport and
connection registry, RLS-protected session state machine, consent and signaling endpoints, ephemeral coturn
REST credentials, view-only desktop WebRTC UI, server-side expiry/recovery, audit events, timing controls,
and the no-control/no-recording guard. The local test suite covers consent races, authorization denial,
expiry, stale-connection recovery, and TURN credential secrecy. Test-host provisioning remains an operations
step because coturn certificates, test secrets, reverse-proxy configuration, and supervised processes must be
created on that host; follow docs/operations/screen-share-test-environment.md before manual browser testing.

**Universal action update (v1.8):** Screen support is a compact top-right action on every advisor-side
client record, replacing the wide client-profile tile. Standard advisory, due-diligence, and NPO records share
the existing Client flow. Entrepreneur profiles use the same consent, signalling, expiry, TURN, and audit
lifecycle through a profile-bound scope: only the assigned advisor can request support, and only the linked
entrepreneur account can approve it. Profiles without an activated entrepreneur account retain the visible
action in its unavailable state. Screen content remains unrecorded; the audit trail stores request, consent,
browser-permission, start, end, expiry, and connection-loss metadata.

**One-line intent:** An advisor can request to view a specific client user's FSA browser tab; that user approves
in-app and again in their browser's own picker; a view-only WebRTC mirror runs for a bounded, banner-visible,
fully audit-logged session — with **screen content never reaching the Laravel application server or any FSA
storage**. (Media flows peer-to-peer where possible; where a direct connection fails it transits FSA's
self-hosted TURN relay — encrypted in transit, never recorded or persisted. See §2/§7.)

> **Read §0 before anything else.** The spec's central infrastructure assumption is incorrect, and it changes
> the shape and cost of this build.

---

## 0. ⛔ CRITICAL PREMISE CORRECTION — there is no existing WebSocket layer

The spec states (§1.1, §2.1) that *"the platform already has a WebSocket layer (used for real-time dashboard
updates and notifications)"* and that signaling is therefore *"a new message type on an existing channel, not
a new server."*

**Verified false.** In this repository today:

| Claim | Reality (verified) |
|---|---|
| Existing WebSocket layer | **None.** No Reverb, Pusher, Ably, Soketi in `composer.json`; no `laravel-echo`/`pusher-js` in `package.json`. |
| Broadcasting configured | **No.** No `config/broadcasting.php`, no `routes/channels.php`, **`BROADCAST_CONNECTION=log`** (a no-op driver). Zero `ShouldBroadcast`/`Broadcast::` usages in `app/`. |
| Real-time dashboard/notifications | **Polling, not sockets** — `NotificationBell` uses `setInterval` + `router.reload` ([NotificationBell.tsx:28-29](resources/js/components/notifications/NotificationBell.tsx:28)). |

**Consequence:** signaling infrastructure is **new core infrastructure**, not a message type on an existing
channel. The spec's "no new infrastructure category" rationale for Option B does not hold as written. The
feature is still buildable and the architecture is still sound — but the **transport must be chosen and
provisioned as part of this build** (§11 D1), and the cost/effort estimate must include it alongside the TURN
server the spec already (correctly) treats as mandatory.

This does **not** invalidate the spec's other conclusions: WebRTC media never reaching the app server or FSA
storage (§2), two-layer
consent, view-only, desktop-only, and no-recording all stand, and the consent pattern genuinely does match the
platform's existing request→approve→log architecture.

---

## 1. Verified platform anchors (what the build actually hangs on)

| Anchor | Status | Use |
|---|---|---|
| **Sessions table** (`SESSION_DRIVER=database`) | ⚠️ **not sufficient for presence** | `SESSION_LIFETIME=120` with `expire_on_close=false` ([config/session.php:35-37](config/session.php:35)) — a **closed tab stays "recently active" for two hours**. Using it for "client is online" would let an advisor send a request nothing can receive. Presence must be **live** (§1.1 below). Still useful as a cheap *pre*-filter ("has a session at all") and for the security columns. |
| **`client_team`** — `(client_id, user_id, role)`, unique per pair ([clients migration:40-51](database/migrations/2026_05_21_190000_create_clients_tables.php:40)) | ✅ exists | A client is an **organisation with many users**. The session must target a specific `client_user_id`, and that user must be a current team member (§3). |
| `AuditWriter` | ✅ | Every request/response/session transition audited (spec §8). |
| Client-scoped **RLS** pattern (documents/goals/analysis) | ✅ | `screen_share_sessions` follows it exactly. |
| `ProjectSettings` (definition-driven: `definitionsByKey`/`set`, [ProjectSettings.php:469-566](app/Services/Settings/ProjectSettings.php:469)) | ✅ | Admin-configurable max duration/timeouts must be **registered definitions**, not ad-hoc rows. |
| Notifications (`FsaDatabaseChannel`, channel preferences) | ✅ | "Client did not respond", session-ended notices. |
| `User::accessibleClientIds()` ([User.php:228-273](app/Models/User.php:228)) + MFA middleware | ✅ | The existing expression of advisor↔client attachment: **direct `client_team` rows *and* advisor teams** (`advisor_teams.lead_advisor_user_id` / `advisor_team_members` role=LEAD → `client_team.advisor_team_id`). S1 extracts `AdvisorClientAttachment` as the shared resolver for `ScreenShareAuthorizer`, with parity against this method (§5.7). **`Gate::authorize('view', $client)` proves nothing about assignment** — the policy ignores its `$client` argument (§5.7). MFA already mandatory. |
| `ResilientHttp` | ✅ | Any TURN/ICE **control-plane** HTTP (never media). |
| Queue (`QUEUE_CONNECTION=database`) + scheduler | ✅ | Stale-session sweep, expiry enforcement. |
| **Broadcasting / WebSocket** | ❌ **absent** | §0 — must be built (D1). |
| **Live presence / connection registry** | ❌ absent | §1.1 — defined by S0 with the transport. |
| **TURN server** | ❌ absent | Must be provisioned (spec §7; §8 below). |

### 1.1 Live presence (S0 must define this — the DB sessions table cannot)

"Client is currently logged in" (spec §3 step 1) must mean **a live browser connection able to receive the
prompt right now**, not "has an unexpired DB session". S0 delivers one of:

- **Transport-native presence (Reverb/Pusher — preferred):** a presence channel per client user; the request
  targets an **active connection id**. Presence is authoritative and free with the transport.
- **Heartbeat registry (only if D1 = polling):** `client_presence_connections` `{id, user_id, client_id,
  connection_id, last_seen_at}` refreshed every ~15s by the open tab, TTL ~45s, swept by schedule. Same
  semantics, more moving parts.

Either way the API is one method — **`presence()->activeConnectionsFor(Client $client, User $clientUser): array`**
(v1.5) — so the rest of the plan is transport-agnostic. **Keyed by client *and* user, never user alone:** one
person can belong to several client organisations (`client_team` is unique per `(client_id, user_id)`, so
multiple rows per user are normal), and prompting their tabs for the *wrong* portal would expose one client's
support request in another client's context. Connections carry `client_id` (presence channel
`presence-client.{clientId}.user.{userId}`) and are filtered by it. Test: the same user signed into two client
portals is prompted **only** in the tabs for the requested client. If zero live connections for that
client+user pair: **the request is not created at all** (spec §5: no queued/async requests) and the advisor
sees "Client is not currently online."

**1.1a Explicit portal context precedes presence (v1.6).** The current
[`ClientPortalResolver::resolveFor()`](app/Services/Portal/ClientPortalResolver.php:41) selects the latest
accessible client; it has no route, session, or other
explicit selection of the client portal currently open. Therefore it cannot support a claim that the same user
is simultaneously present in two client portals. Before screen support is enabled, S0/S1 must add a
`ClientPortalContext`:

1. Every screen-support-capable portal page has an explicit, server-authorized client selection (a
   client-scoped route or equivalent stable portal context). The resolver validates the current user's
   membership for that selected client; it does **not** use the current `latest()` fallback for these pages.
   Separate tabs may hold separate validated client contexts for the same user.
2. When the server renders a portal page, it derives the allowlisted route key from the matched server route and
   mints a short-lived, single-use signed `portal_context_token` containing
   `{user_id, client_id, route_key, issued_at, expires_at, nonce}`. The browser presents that token only to
   transport authentication; the server verifies and consumes it, then binds
   `{connection_id, user_id, client_id, route_key, bound_at}` in the presence registry.
3. A connection's portal context is immutable. Navigation that changes the client or route establishes a new
   signed context and transport registration, atomically retiring the old registration. A connection with an
   expired, missing, or invalid context is not present and cannot receive a prompt.
4. The client never submits a `client_id` or route key with a presence, consent, or signaling message. The
   server reads the bound context instead. The audit wording is deliberately precise: it records the
   **server-rendered route context bound to the connection**, not an unprovable claim about pixels still visible
   when the user clicks a response.

This makes `activeConnectionsFor(Client, User)` a lookup over server-bound client contexts, not browser
assertions. Tests: the same user in two explicitly selected client portals is prompted only for the requested
client; a token for client A cannot register/rebind a connection to client B; and a consent payload containing a
different-but-allowlisted route key is rejected or ignored without altering the stored context.

**Multi-tab: fan out, then bind to the approving connection (v1.3).** Clients routinely have several FSA tabs
open, so `activeConnectionsFor()` returning many is the *normal* case, not an edge case:

1. **Fan out** the consent prompt to **every** live connection for that client+user — the client sees it
   wherever they are looking, rather than in whichever tab the server happened to pick. **The prompted set is
   recorded state**: each entry is `{connection_id, nonce_hash, context_key, prompted_at, expires_at}`
   persisted to `prompted_connections`, and each tab receives its own **single-use nonce**. This is what makes
   the consent response authorizable *before* any connection is bound (§5.2a). `context_key` is copied
   from that connection's server-bound `ClientPortalContext` (§1.1a), never from the prompt or consent
   payload.
2. **First *response* wins — approve or decline (v1.4).** The outcome is decided by whichever response commits
   first, **regardless of type**, via a conditional transition out of `requested`:
   - **approve →** `approved_pending_browser`; that connection becomes `client_connection_id` (bound at
     approval, not request); the **winning connection's server-bound context is persisted** as
     `consent_context`.
   - **decline →** `ended` + `end_reason: declined`, immediately; the same winning
     connection's `consent_context` is persisted before the request is over.
   Late responses from other tabs affect **zero rows** and are answered with a dismissal — so an
   approve-then-decline (or decline-then-approve) race has exactly one winner and no split state.
3. **Dismiss everywhere else** — other tabs immediately replace the prompt with "Handled in another tab" (or
   "Request declined"). No orphaned modals.
4. `getDisplayMedia()` therefore runs in the tab the client actually chose, which is also the tab whose
   content is shared — picker, stream, and banner all live together.

Signaling remains bound to that one connection (§5.2) — so a *different* tab signaling is correctly rejected;
it is no longer a legitimate participant. Tests: two tabs → both prompted → approve in one → other dismisses →
signaling from the non-approving tab rejected → session completes; **approve/decline races in both orders →
exactly one outcome**; the persisted `consent_context` matches the connection whose response won, whether it
approved or declined.

---

## 2. Architecture (as it must actually be built)

```
Advisor browser                    FSA server (control plane only)                 Client browser
     │                                        │                                          │
     ├── request session ───────────────────► │ authz + presence + concurrency check     │
     │                                        ├── deliver request ──────────────────────►│ (transport, D1)
     │                                        │◄──── approve/decline (FSA consent) ──────┤
     │                                        │                                          │
     │◄───── SDP/ICE relay (signaling) ──────►│◄────── SDP/ICE relay (signaling) ───────►│ getDisplayMedia()
     │                                        │        (metadata ONLY, never media)      │  → browser picker
     │                                        │                                          │
     │◄══════════ WebRTC media: DTLS-SRTP, direct P2P or via FSA-hosted TURN ═══════════►│
     │              (screen video NEVER passes through the app server)                   │
```

- **Signaling** carries only: session id, participant ids, approval state, SDP offer/answer, ICE candidates.
  Every signaling message is **authorized server-side against the session record** — a participant may only
  signal into a session they belong to, in a state where that message type is legal.
- **Media never reaches the Laravel application server or any FSA storage.** It flows peer-to-peer where
  possible; where a direct connection fails it transits FSA's self-hosted `coturn` relay (encrypted in
  transit, never recorded or persisted). That is still FSA-controlled infrastructure, so the spec's "no
  third-party data pathway" position holds — but the accurate claim is *"never reaches the app server or
  storage"*, **not** *"never touches FSA servers"*.

---

## 3. Data model — `screen_share_sessions` (client-scoped)

Implements spec §8.1 exactly, plus the fields the state machine and recovery need.

| Column | Notes |
|---|---|
| `id` uuid PK | |
| `client_id` FK | the client **organisation** — scopes RLS |
| **`client_user_id`** FK (users) | **the specific person** whose screen is requested (v1.1 — a client has many users via `client_team`). Write-time guard: must be a **current `client_team` member of `client_id`**; membership re-checked at approval, not just at request (someone removed from the team mid-flow cannot approve). |
| `advisor_id` FK (users) | must be **assigned to `client_id`** at request time (write-time check + test) |
| **`client_connection_id`** / **`advisor_connection_id`** | the transport connection each participant is bound to (§1.1). **Only these two connections may signal into this session** (§5.2) — this is what makes signaling authorization enforceable rather than nominal. `advisor_connection_id` is set at request; **`client_connection_id` is set at *approval* — the fan-out winner** (§1.1, v1.3), and is null while `requested`. A reconnect within the grace window rebinds via an audited transition. |
| `status` | **four states only** (v1.1) — `requested`\|`approved_pending_browser`\|`active`\|`ended`. **`ended` is the single terminal state; the outcome lives in `end_reason`.** (Earlier drafts also listed `declined`/`timed_out`/`failed` as statuses — that duplicated `end_reason`, and would have split indexes, history filters, and tests down two representations.) |
| `requested_at` | |
| `client_response` | `approved`\|`declined`\|`timed_out` (nullable until response) |
| `client_response_at` | |
| `browser_permission_granted` bool | **distinct from FSA approval** (spec §8.1) — client may approve in-app then cancel the picker |
| `session_started_at` | peer connection established |
| `session_ended_at` | |
| `end_reason` | the **outcome** of every terminal session (v1.1 — carries what the removed statuses used to): `completed_client_ended`\|`completed_advisor_ended`\|`max_duration_reached`\|`connection_lost`\|`client_navigated_away`\|`declined`\|`request_timed_out`\|`request_undeliverable`\|`browser_permission_denied`\|**`browser_permission_timed_out`** (v1.2)\|`failed`. History filters and reporting key on this. |
| **`picker_deadline_at`** (v1.2) | server-enforced deadline for the browser-picker step — see §4.2. Without it `approved_pending_browser` is an unbounded non-terminal state holding **both** unique indexes. |
| `connection_type` | `direct_p2p`\|`turn_relayed` — diagnostics only, **not exposed to either party** (spec §8.1) |
| `duration_seconds` | computed on end |
| `display_surface` | `browser`\|`window`\|`monitor` — from `track.getSettings()` (§5.4); privacy signal, not content |
| `last_heartbeat_at` | drives the stale-session sweep (§4.4) |
| `expires_at` | server-authoritative auto-expiry (§4.3) |
| `consent_context` | jsonb — the `{route_key, bound_at}` from the **server-bound connection that supplied the winning consent response**, recorded for **both approve and decline** (§1.1, §1.1a, §5.9). It is the server-rendered route context at connection registration, never a claim about browser pixels at click time. **Never a raw URL, page title, or client free-text; no screen content, ever.** |
| **`authorization_basis`** (v1.4) | jsonb — which rule granted the advisor access (`direct_client_team` / `advisor_team` + id, `evaluated_at`), recorded at request time for audit and revocation re-checking (§5.7). |
| **`prompted_connections`** (v1.5) | jsonb — the fan-out set: `[{connection_id, nonce_hash, context_key, prompted_at, expires_at}]`, where `context_key` comes from the server-bound `ClientPortalContext`. **Nonces are stored hashed, are single-use, and expire with the 60s request deadline.** This is the authorization basis for consent responses *before* `client_connection_id` exists (§5.2a). |
| timestamps | |

**Indexes / invariants**
- **Partial unique indexes** — with a single terminal state the predicate is simply `WHERE status <> 'ended'`:
  - `UNIQUE (client_user_id) WHERE status <> 'ended'` — a person cannot share their screen to two advisors at once.
  - `UNIQUE (advisor_id) WHERE status <> 'ended'` — an advisor views one screen at a time (spec §5).
  These enforce concurrency **in the database**, not by check-then-act (a check-then-create race would let two
  advisors both open a session on one person). Pre-checks remain for friendly errors; the indexes are the
  guarantee, and unique-violations surface as clean validation errors.
  **Deliberate reading of spec §5 (v1.1):** the spec's "one active session per client" is scoped to the
  **client user**, not the client organisation — otherwise one team member receiving support would block every
  other team member of the same business from getting help. Flagged as **D5** if you intended org-wide.
- **RLS (spec §8.3):** advisor sees their own sessions; the **participating client user** sees their own; admin
  sees all. Whether a client's **primary contact** can see other team members' sessions is **D6** (default:
  no — screen-share history is personal).

---

## 4. Session state machine (server is the authority)

### 4.1 States & legal transitions

**Four statuses; one terminal state (`ended`); the outcome is always in `end_reason`.**

```
requested ──approve──► approved_pending_browser ──picker completed──► active ──────► ended
    │                          │                                         │             ▲
    │                          └── picker cancelled ────────────────────────────────► │  end_reason:
    ├── decline ──────────────────────────────────────────────────────────────────►  │   declined
    └── 60s no response ──────────────────────────────────────────────────────────►  │   request_timed_out
                                                                                      │   browser_permission_denied
   from active:  client End / advisor End / 30-min cap / track.onended / 15s no re-establish
                                                                                      │   completed_client_ended
                                                                                      │   completed_advisor_ended
                                                                                      │   max_duration_reached
                                                                                      │   client_navigated_away
                                                                                      │   connection_lost
```

- **All transitions are idempotent and conditional** (`UPDATE … WHERE id=? AND status=?`): both parties
  clicking *End* simultaneously produces **one** terminal transition, not two (test).
- `ended` is terminal — no resurrection. Continuing past the limit requires a **fresh request** (spec §5).

### 4.2 Timeouts are server-enforced — **every non-terminal state is bounded** (v1.2)

Each non-terminal state must have its own deadline, because **all three hold both partial unique indexes** —
an unbounded one silently locks a client user and an advisor out of the feature indefinitely:

| State | Bound | On expiry |
|---|---|---|
| `requested` | 60s (`request_timeout_seconds`) | `ended` + `request_timed_out` |
| **`approved_pending_browser`** | **90s (`picker_timeout_seconds`, v1.2)** — the client approved in-app then left the browser picker open | `ended` + **`browser_permission_timed_out`**, releasing both indexes |
| `active` | 30 min cap + heartbeat/reconnect grace (§4.4) | `max_duration_reached` / `connection_lost` |

All are **server-enforced** (deadline column + sweep/delayed job), never only a client timer — a tampered or
stalled client must not extend anything. Client-side timers are display only.

### 4.3 Configurable limits
`max_duration_minutes` (30), `warning_at_minutes` (25), `request_timeout_seconds` (60),
**`picker_timeout_seconds` (90)**, `reconnect_grace_seconds` (15), `heartbeat_interval_seconds` (10) —
**all six** registered as `ProjectSettings` definitions (audited edits), none hardcoded, none ad-hoc rows.
This list is the authority; every deadline in §4.2/§4.4 must read from it.

### 4.4 Crash/stale recovery (**not in the spec — required**)

Because the concurrency invariant blocks new sessions while one is non-terminal, a browser crash or server
restart could otherwise leave a client user **permanently unable to receive support**. Two mechanisms with
**distinct jobs** (v1.2 — one cannot do both):

1. **Disconnect-triggered delayed job — the fast path.** On the transport's disconnect signal for a bound
   connection, dispatch `EndSessionIfNotReconnected` with `->delay(reconnect_grace_seconds)`. On run it
   conditionally ends the session **only if** still disconnected and still `active` (idempotent conditional
   transition), `end_reason = connection_lost`; a reconnect within the window rebinds the connection id and
   the job no-ops. **The scheduler cannot do this** — the finest cadence available is `everyMinute()`
   ([routes/console.php:20](routes/console.php:20)).
   **Queue reality (v1.3):** `QUEUE_CONNECTION=database` with a single shared `default` queue
   ([config/queue.php:16,42](config/queue.php:16)) — `->delay(15)` only makes the job *eligible* at 15s; behind
   a bulk-comms or AI backlog it can run much later. So the job **must** go on a dedicated
   **`realtime`** queue with its **own worker** (`--queue=realtime`, low `--sleep`), isolated from bulk work;
   S6 provisions and monitors it. Test under a seeded backlog on `default` to prove isolation.
   **Honest SLA framing:** 15s is a **target**, not a hard real-time guarantee — a database queue cannot
   promise one. Crucially, **safety does not depend on it**: media is peer-to-peer, so when the client's
   connection drops **the advisor's view dies immediately at the WebRTC/ICE layer**, regardless of when the
   session row updates. The timer governs *bookkeeping and lock release*, not the privacy boundary. Alert if
   the observed p95 exceeds the grace window.
2. **Scheduled sweep — the backstop, not the bound.** `everyMinute()`, ends any session whose
   `last_heartbeat_at`/deadline is well past its grace, covering the cases where **no disconnect event ever
   fires** (server restart, transport crash, killed worker) and expiring `requested`/`approved_pending_browser`
   deadlines (§4.2). Active sessions heartbeat ~10s to feed it.

**⚠ Both run outside HTTP middleware, and `screen_share_sessions` is RLS-protected — so they must execute
inside `RequestContext::withSystemContext(fn () => …)`** ([RequestContext.php:132](app/Support/RequestContext.php:132)).
Without it the sweep sees **zero rows**, silently ends nothing, and leaves the concurrency locks in place
forever — the exact failure this section exists to prevent. **S1 requirement + integration test.**

Tests: kill the client tab → session ends within the 15s grace (time-travelled, asserting the *bound*, not
eventual cleanup) → a new request succeeds; a sweep run in system context ends an orphaned session, and a test
proves the sweep would find nothing without that context.

---

## 5. Security hardening (beyond the spec — these are the review-catchable items)

**5.1 TURN credentials must be ephemeral.** Never ship a static TURN shared secret to browsers. Use coturn's
REST/time-limited credential scheme: the server mints a short-TTL (e.g. 10-minute) HMAC username/password
**per session**, returned only to the two authorized participants. A leaked long-lived credential would make
FSA's TURN server an open relay.

**5.2 Signaling authorization on every message — bound to the connection, not just the user.** Authorize each
signaling frame against the session record: it must arrive on the session's recorded
`client_connection_id`/`advisor_connection_id`, from the matching user, in a state where that message type is
legal. A user must not be able to inject SDP/ICE into another session, into their **own** session from a
**second tab**, or into any session after `ended`. (Without the stored connection ids from §3 this rule is
unenforceable — which is why they are part of the model.)

**5.2a Consent-response authorization — the pre-bind rule (v1.5).** §5.2 cannot govern the approve/decline
response itself: while the session is `requested`, `client_connection_id` is **null** by design (it is bound
*by* approval). That window needs its own rule, or any authenticated user could POST an approval. A consent
response is accepted only when **all** hold:

1. the actor **is** `client_user_id` — not merely another `client_team` member of the same client;
2. the responding connection is **in `prompted_connections`** — an unprompted or newly-opened tab cannot respond;
3. it presents the **matching single-use nonce** for *that* connection (compared against `nonce_hash`);
4. the nonce is **unexpired** (bounded by the 60s request deadline) and **unconsumed**;
5. the session is still `requested` (the first-response-wins conditional transition, §1.1);
6. the response supplies **no client id, route key, or page context**. In the same transaction, the server copies
   the already-bound connection context into `consent_context`, regardless of whether the winner approves or
   declines.

The nonce is consumed in the same transaction as the transition, so a replay after the winner commits affects
zero rows. **Negative tests:** a different `client_team` user responds → 403; a tab opened *after* fan-out
responds → 403; an expired nonce → 403; a replayed/consumed nonce → rejected; a response containing a
different-but-valid route key cannot alter the stored context; a response after another tab already won →
no-op + dismissal.

**5.3 Audio is explicitly disabled.** Call `getDisplayMedia({ video: {...}, audio: false })`. Tab-share can
otherwise capture tab audio — out of scope, unconsented, and not mentioned in the spec. Assert in tests.

**5.4 Over-share detection (`displaySurface`).** The spec accepts (§6, §10) that FSA cannot force tab-only.
It *can* detect it: `track.getSettings().displaySurface` returns `browser` | `window` | `monitor`. If the
client shares more than the FSA tab, **immediately** escalate the client banner ("You're sharing your **entire
screen** — switch to just this tab") and show the advisor a matching warning. Persist to `display_surface`.
This converts an accepted limitation into an actively-managed one — a genuine privacy improvement over the spec.

**5.5 No input channel exists — architecturally.** No RTCDataChannel is opened, no input-forwarding code is
written, not even behind a flag (spec §9.2 / CLAUDE block). Add a **CI grep guard** failing on
`createDataChannel`, `robotjs`, or synthetic-input helpers in this feature's namespace, so "just experimentally"
cannot land.

**5.6 No recording surface exists.** No `MediaRecorder`, no `canvas.captureStream`/`drawImage` of the remote
track, no frame-upload endpoint. Same CI guard approach. Screen content is never logged, screenshotted, or
persisted (spec §8.2).

**5.7 Authorization — `Gate::authorize('view', $client)` is NOT sufficient (v1.2, privilege-escalation fix).**
[`ClientPolicy::view()`](app/Policies/ClientPolicy.php:20) is `allows($user, Permission::CLIENTS_VIEW)` — it
**ignores its `$client` argument entirely**, so it proves neither *advisor-ness* nor *assignment to this
client*. And `client_team` holds **both** advisor-side and client-side users (the platform's own RLS keys on
`client_team.role = 'lead_advisor'`), so "is a `client_team` member" does not mean "is an advisor". Relying on
either alone would let **a client user request a screen share of a colleague**. A dedicated
`ScreenShareAuthorizer` must assert **all** of:

1. **Requester is advisor-side by user type** — `User::TYPE_ADVISOR` or `TYPE_JUNIOR_ADVISOR`
   ([User.php:49-57](app/Models/User.php:49)). *(`super_admin` is deliberately excluded from **initiating**;
   admins retain full §8.3 **visibility**.)*
2. **Requester is attached to this client — through `AdvisorClientAttachment::resolve()` (v1.6).**
   Attachment has **two legitimate paths**: a direct `client_team` row **and** advisor-team assignment
   (`advisor_teams.lead_advisor_user_id`, or `advisor_team_members` with role `LEAD`, resolved through
   `client_team.advisor_team_id` — [User.php:243-270](app/Models/User.php:243)). A "direct `client_team` row
   only" rule would deny legitimate advisors assigned through a team.
   `AdvisorClientAttachment::resolve(User $advisor, Client $client): ?Attachment` returns
   `{basis: 'direct_client_team'|'advisor_team', advisor_team_id?}` (null = not attached). S1 extracts it from
   the platform's existing attachment semantics and makes it the **single production resolver** for
   `ScreenShareAuthorizer`; it supplies `authorization_basis` without a second hand-rolled query.
   `accessibleClientIds()` remains the platform API that yields a flat list, while a parity test asserts that
   `resolve()` is non-null exactly when the id appears in that list. Changes to attachment semantics update the
   shared resolver and its parity test together.
3. **Target is client-side by user type** — `TYPE_CLIENT_PRIMARY` or `TYPE_CLIENT_TEAM` — **and** a current
   direct `client_team` member of the same `client_id` (client users are attached directly, per
   [User.php:232-237](app/Models/User.php:228)).
4. **`advisor_id !== client_user_id`** (no self-sessions).
5. Re-assert (1)–(4) **at approval**, not only at request — **inside `withSystemContext()`** (see the hazard
   below), never under the approving client's scope.

**⚠ Cross-actor authorization must run in system context (v1.4 — recurring RLS hazard).** The approval request
is made **by the client user**, so `EnforceClientScope` has set the Postgres session to *that client*. Under
that scope `advisor_teams` is invisible — its policy is
`lead_advisor_user_id = fsa_current_user_id()` ([advisor_teams migration:90-98](database/migrations/2026_05_25_120000_create_advisor_teams_table.php:90))
— so `AdvisorClientAttachment::resolve($advisor, $client)` evaluated there can see only the direct
`client_team` path and **advisor-team-only attachment silently fails**, wrongly rejecting a legitimate
approval. Rule: **any
authorization question about a *different* actor is evaluated in `RequestContext::withSystemContext()`.** This
is the same failure class as the background sweep (§4.4) — one actor's RLS scope cannot answer another actor's
authorization question.

**Authorization snapshot (belt and braces).** At request time persist `authorization_basis` jsonb —
`{path: 'direct_client_team'|'advisor_team', advisor_team_id?, evaluated_at}` — so (a) the audit trail records
*why* access was granted, and (b) the approval-time check is a cheap **revocation** check against a recorded
basis rather than a blind re-derivation. Test: an advisor attached **only** via an advisor team can complete a
full request→approve cycle (this is the case that breaks without the system-context fix).

**Consistency rule:** attachment semantics live in `AdvisorClientAttachment`; the authorizer calls that resolver
directly, and the parity test keeps `accessibleClientIds()` aligned with it. There is one production
attachment query for this feature, not a second approximation inside the authorizer.

**Negative tests (all must 403):** a client user attempting to request support for a colleague; an advisor not
attached to the client; an advisor attached to a *different* client; a self-request; a target who is
advisor-side; a target removed from `client_team` between request and approval.

**Secure context + audit.** `getDisplayMedia` requires HTTPS/secure context — verify in the test env. MFA is
already mandatory platform-wide; no additional step-up for view-only. Every request/response/transition is
audited via `AuditWriter`. **Accurate statement of what that redacts (v1.4):** `AuditWriter` redacts the
`before`/`after` **payloads** through `Redactor`, while `ip` and `user_agent` are stored **raw as first-class
audit columns** ([AuditWriter.php:77-80](app/Services/Audit/AuditWriter.php:68)). That is deliberate and
appropriate for a security-sensitive trail on an immutable, access-controlled table — but this plan must not
claim redaction covers them. The platform's "no PII in raw logs" rule governs **application log lines**, not
`audit_events`. Keep session payloads free of PII; never log page content or URLs containing client data.

**5.9 Page context is server-derived and connection-bound (v1.6).** The browser does **not** report a
route/component key. On page render the server maps the matched route to an allowlisted key (for example,
`portal.dd.questionnaire`) and signs it into the one-time `portal_context_token` (§1.1a). If no
allowlisted mapping exists, the server binds the generic key `portal.generic` and uses generic consent copy
("the page you're currently on"). Transport authentication consumes the token and becomes the only writer of a
connection's context; consent and signaling payloads contain no page-context fields.

**Raw URLs, query strings, page titles, and any client-entered text are never accepted, persisted in
`prompted_connections` or `consent_context`, or logged** — which is also what keeps §5.7's logging rule true
(a URL can itself carry client data). Tests: an unrecognised server route gets `portal.generic`; a tab
submitting a raw URL, arbitrary string, or another valid route key cannot change its bound context; and no URL
or page content reaches `prompted_connections`, `consent_context`, or `audit_events`.

**5.8 Presence check is racy by nature.** Between the online-check and delivery the client user may close the
tab; the request must then end cleanly as **`ended` + `end_reason = request_undeliverable`** (v1.2 — not a
`timed_out` *status*; that status no longer exists, §3) with a clear advisor message — never hang, never leave
a non-terminal row holding the indexes.

---

## 6. Consent & UX (spec §3–§4 — implement as written)

- **Two layers, both required.** FSA in-app approval **does not** start sharing; it authorizes proceeding to
  the browser's own picker. Never attempt to bypass, pre-fill, or automate the browser layer.
- **Modal copy** verbatim from spec §4.1, including the pre-warning that the browser will ask again — this
  sentence is what prevents the "why two prompts?" confusion.
- **Browser-adaptive guidance** (spec §4.2/§6.3): Chromium ("Choose 'This Tab'"), Firefox/Safari (adjusted).
  Use `preferCurrentTab: true` on Chromium 107+ to pre-select the current tab.
- **Persistent client banner** (spec §4.3): fixed top, non-dismissible, advisor name/photo + live timer +
  one-click End, alert-amber (**must use the Meridian token, not a hardcoded hex** — see the app's token
  standard). Escalates on over-share (§5.4).
- **Advisor viewer panel** (spec §4.4): in-page panel within the client profile, "View Only — you cannot click
  or type", timer, one-click End.
- **Mobile** (spec §6.2): detect and show the fallback message; do not attempt capture.

---

## 7. Infrastructure (new — must be provisioned, not assumed)

1. **Signaling transport** (D1) — see §11.
2. **STUN** — public (e.g. Google) acceptable; self-hosted optional.
3. **TURN — `coturn`, self-hosted, mandatory** (spec §7): TLS (`turns:`), 443/5349 reachable, ephemeral-credential
   secret in `.env` (never committed), **no logging of relayed payloads**, capacity/bandwidth sized for
   concurrent sessions, monitored via the existing integration-health surface.
4. **Ops runbook**: cert renewal, credential-secret rotation, and what "TURN down" degrades to (direct-P2P-only
   → some clients simply cannot connect; surface honestly rather than hanging).

---

## 8. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **S0** | **Transport decision + portal context + presence spike** ⛔ blocking | Resolve D1 (§10). Stand up the chosen transport in the test env and deliver the **`ClientPortalContext` contract (§1.1a)**: explicit server-authorized client selection, server-rendered signed context token, and a connection registry bound to that context. Prove an authorized round trip between named advisor and client-user connections, client A/B portal isolation for one user, and rejection of a mismatched context token. **No other WO starts until S0 lands.** |
| **S1** | Data model + state machine | `screen_share_sessions` migration (**`client_user_id`, connection ids, `consent_context`**, RLS, **partial unique indexes on `client_user_id` and `advisor_id` where `status <> 'ended'`**, audit), state machine service with conditional/idempotent transitions over the **four statuses**, **`ScreenShareAuthorizer` — build it to §5.7 exactly: it calls `AdvisorClientAttachment::resolve()` for attachment, runs cross-actor checks in `withSystemContext()`, and records `authorization_basis`**; preserve parity with `accessibleClientIds()`, `ProjectSettings` definitions (**all six of §4.3**), live-presence + fan-out contract from S0 (**`activeConnectionsFor(Client, User)`**), **deadlines on all three non-terminal states (§4.2)**, **first-response-wins consent transitions with the §5.2a nonce rule (`prompted_connections` + winning `consent_context` for approve or decline)**, **server-derived page-context enforcement (§1.1a, §5.9)**, **`EndSessionIfNotReconnected` on the `realtime` queue + `everyMinute` backstop sweep, both wrapped in `RequestContext::withSystemContext()`** (§4.4). No screen-share UI. Unit tests on every transition + concurrency + deadlines + sweep + approve/decline races + the full §9 negative-authorization matrix. |
| **S2** | Signaling + authorization | Session-scoped signaling endpoints/channel with per-message authorization (§5.2), ephemeral TURN credential minting (§5.1), ICE config delivery, **multi-tab fan-out + bind-on-approval** (§1.1). Media never reaches the app server or storage — asserted by the absence of any media endpoint. |
| **S3** | Client side | `getDisplayMedia({audio:false})` + `preferCurrentTab`, consent modal with browser-adaptive copy, non-dismissible banner + timer + End, `track.onended` → immediate end, `displaySurface` over-share escalation (§5.4), mobile fallback. |
| **S4** | Advisor side | Request action (authz + presence + concurrency pre-check), viewer panel with view-only labelling, timer, End; "client not online" / "did not respond" states. |
| **S5** | Audit, history & guards | Full §8.1 field logging on every transition; session history for advisor / client portal / admin (spec §8.3); **CI guards** for no-data-channel and no-recording (§5.5–5.6); notification wiring. |
| **S6** | Infrastructure + runbook | coturn provisioning, TLS, secrets, health monitoring, ops runbook (§7); **dedicated `realtime` queue worker** (`--queue=realtime`, low sleep, supervised, isolated from `default`) with p95-latency alerting (§4.4, v1.3). Can run in parallel with S1–S5 but must complete before live. |

Sequence: **S0 → S1 → S2 → S3/S4 → S5**, S6 in parallel. One WO per branch/PR.

## 9. Testing

State machine: every legal transition, every illegal one rejected, **simultaneous double-end yields one
terminal transition**, `ended` immutable, and **every terminal path sets the right `end_reason`** (single
terminal state — assert no code writes `declined`/`timed_out`/`failed` as a *status*). Concurrency: two
advisors requesting the **same client user** concurrently → partial unique index rejects one cleanly; a second
request while one is non-terminal → rejected; **two different users of the same client organisation can each
hold a session simultaneously** (the D5 reading — assert it explicitly). Participants: request targets a
specific `client_user_id`; a user who is **not** a `client_team` member → rejected; a member removed from the
team **between request and approval** cannot approve. Recovery (v1.2): killed tab → the **delayed job** ends the session **within the 15s grace** (time-travelled —
assert the *bound*, not eventual cleanup) → new request succeeds; an orphaned session with no disconnect event
is cleaned by the `everyMinute` backstop; **the sweep run without `withSystemContext` finds zero rows** (prove
the RLS trap exists, then that the fix closes it). Timeouts — **every non-terminal state is bounded**: 60s
no-response → `request_timed_out`; **approval-then-abandoned-picker → `browser_permission_timed_out` at 90s,
releasing both unique indexes** (v1.2); 30-min cap enforced **server-side** even with a tampered client clock;
warning at 25. Consent: FSA approval alone never starts media; picker cancelled → `ended` +
`browser_permission_denied` (never `active`). Authorization (**full negative matrix, §5.7**): client user
requesting a colleague → 403; advisor not attached to the client → 403; advisor attached to a different client
→ 403; self-request → 403; advisor-side target → 403; target removed from `client_team` between request and
approval → approval rejected; **an advisor attached only via an advisor team (no direct `client_team` row)
→ ALLOWED** (v1.3 — the regression this round's fix prevents); signaling from a **different connection of the
same user** (second tab) → rejected; signaling into another session → rejected; signaling after `ended` →
rejected. Multi-tab (v1.3/v1.4): two live tabs → **both** prompted → approval in one binds
`client_connection_id` and **dismisses the other** → simultaneous approvals yield exactly one winner →
**approve/decline races in both orders yield exactly one outcome** (late responses affect zero rows) →
signaling from the non-approving tab rejected → the persisted `consent_context` comes from the connection
whose response won, including a winning decline. Queue isolation (v1.3): with `default` backlogged, `EndSessionIfNotReconnected` still runs on `realtime`
within the grace target. **RLS-context authorization (v1.4):** an advisor attached **only** via an advisor team
completes a full request→approve cycle — and a test asserts the approval check **fails** if evaluated under the
client's scope instead of `withSystemContext()`, proving the hazard is real and closed. **Consent-response
authorization (v1.5, §5.2a):** a different `client_team` user responding → 403; a tab opened after fan-out →
403; expired nonce → 403; replayed nonce → zero rows; response after another tab won → no-op + dismissal.
**Portal/presence scoping (v1.6):** the same user in **two explicitly selected, server-authorized client
portals** is prompted only in the requested client's tabs; the current `latest()` resolver fallback is not
used for either tab. A context token for client A cannot register or rebind a connection to client B, and a
stale/unregistered context is offline. **Attachment parity (v1.6):** `AdvisorClientAttachment::resolve()` returns non-null exactly for
the ids `accessibleClientIds()` yields, on both the direct and advisor-team paths. **Context provenance
(v1.6, §5.9):** an unrecognised server-rendered route yields generic copy; a tab submitting a raw URL,
arbitrary string, or a different valid route key cannot change the bound context; and no URL or page content
reaches `prompted_connections`, `consent_context`, or `audit_events`. Presence: **a client
whose only DB session is 120-minute-stale but has no live connection is treated as offline** (request not
created); a client user who disconnects between check and delivery → `ended` + `request_undeliverable`. Privacy: `audio:false` asserted; `displaySurface='monitor'` triggers escalation and is persisted;
**no endpoint accepts media/frames** (asserted); CI guards fail on data-channel/recording APIs. RLS:
cross-client session access denied for advisor, client user, and API paths. Plus `pint --test`, Larastan (if
adopted), `tsc`, ESLint, full PHPUnit.

## 10. Owner decisions

**D1 ⛔ Signaling transport (blocks S0 — the one real decision this plan needs).**
- **(a) Laravel Reverb (recommended)** — first-party, self-hosted, no per-seat vendor cost, matches the
  spec's "no third-party data pathway" intent, and gives the platform a genuine real-time layer it currently
  lacks (which would also let the polling `NotificationBell` improve later). Cost: a new long-running process
  to run and monitor.
- **(b) Pusher/Ably (managed)** — fastest to working; but a third-party sees *signaling metadata* (not video),
  which sits awkwardly with §1.1's rationale, and adds recurring cost.
- **(c) Polling-based signaling** — no new infrastructure, but 1–3s handshake latency and heavy request
  volume; workable for a low-frequency feature, ugly for ICE. Only if (a) is rejected on ops grounds.

**D2 TURN hosting** — self-hosted `coturn` (spec's recommendation, keeps the data-path claim intact) vs a paid
TURN service. Confirm you'll provision the box + TLS cert.

**D3 Who may request** — default: **any advisor attached to the client by the platform's own rules**
(`AdvisorClientAttachment::resolve()` — direct `client_team` *or* advisor-team lead assignment, with
parity to `accessibleClientIds()`), advisor + junior advisor
user types, `super_admin` excluded from initiating. Narrow it to lead advisors only if you prefer; it's one
predicate in `ScreenShareAuthorizer`.

**D4 Session-history visibility to the client** — spec §8.3 says the client sees their own history in the
portal. Confirm (it's the transparency-consistent choice, and I recommend keeping it).

**D5 Concurrency scope (v1.1)** — the plan reads spec §5's "one active session per client" as **per client
user** (default), so one team member's session doesn't block the rest of the business. Say the word if you
meant one per client **organisation**; it's a one-line index change.

**D6 Cross-team history visibility (v1.1)** — can a client's **primary contact** see other team members'
screen-share history? Default **no** (personal to the participant); admin and the advisor still see it.

## 11. Out of scope (spec §9.2 — do not build incrementally)

Remote control / synthetic input (**materially different security posture — separate spec + security review**);
session recording (separate consent + retention policy); mobile browsers; multi-party sessions; reverse-direction
sharing (advisor's screen → client — a *different*, simpler feature; do not conflate).

## 12. CLAUDE.md block (add on build — spec §11, corrected)

> **## Client Screen Support (Co-Browsing) Rules**
> **ARCHITECTURE** — Native WebRTC; never a third-party co-browsing SaaS embed. Signaling relays **only**
> connection setup (SDP/ICE) and **never** video. **Signaling runs on the transport stood up in S0 — the
> platform had no WebSocket layer before this feature (`BROADCAST_CONNECTION=log`, polling only); do not
> describe it as pre-existing.** STUN for NAT discovery; **self-hosted TURN (`coturn`) is required, not
> optional**; TURN is FSA-controlled, so the "no third-party data pathway" position holds. TURN credentials
> are **ephemeral, per-session, short-TTL** — never a static shared secret in the client.
> **CONSENT — TWO LAYERS, BOTH REQUIRED** — Layer 1 FSA in-app approve/decline (logged); Layer 2 the browser's
> own `getDisplayMedia` picker, which **cannot be skipped, pre-filled, or automated**. Layer 1 alone never
> starts sharing.
> **PARTICIPANTS & AUTHORIZATION** — a client is an **organisation with many users** (`client_team`): every
> session targets a specific **`client_user_id`** and binds both parties' **connection ids**; only those two
> connections may signal. **`Gate::authorize('view', $client)` is NOT an authorization check here** — the
> policy ignores its `$client` argument, and `client_team` holds advisor-side *and* client-side users. Use
> `ScreenShareAuthorizer`: requester `user_type` advisor-side **and** attached through
> **`AdvisorClientAttachment::resolve()`** (direct `client_team` or advisor-team assignment; parity-tested
> with `accessibleClientIds()`, never hand-roll a narrower rule); target `user_type` client-side **and** a current direct member;
> never self-session; **re-asserted at approval**. **Cross-actor authorization always runs in
> `RequestContext::withSystemContext()`** — the approval arrives under the *client's* RLS scope, where
> `advisor_teams` is invisible, so an advisor-team-attached advisor would be wrongly rejected. Presence means
> a **live connection**, never a row in the 120-minute DB `sessions` table, and is keyed by **client + user**.
> Every screen-support portal page has an **explicit server-authorized client context**: its render produces a
> short-lived, one-time signed token with the server-derived client and allowlisted route key; transport auth
> consumes it and binds that context to the connection. The browser never submits a client id or route key in
> presence, consent, or signaling messages. Prompts **fan out to all that client+user's bound tabs** as a
> recorded set with **per-connection single-use nonces**; **first response wins (approve *or* decline)**;
> consent responses are authorized by the **§5.2a pre-bind rule** (actor is the target user, connection was
> prompted, nonce valid and unconsumed) — §5.2's connection binding cannot cover them, since no client
> connection exists until approval. The server persists the winning connection's **`consent_context`** for
> **both approve and decline**: a server-rendered route context at registration, never a raw URL, client
> free-text, or a claim about pixels visible at click time.
> **SESSION RULES** — 60s request timeout (expired, never queued); 30-min default max (admin-configurable via
> `ProjectSettings`), warning at 25, **no silent extension**; **server-side enforcement of all timeouts**;
> **four statuses** (`requested`/`approved_pending_browser`/`active`/`ended`) with **`ended` the only terminal
> state and the outcome in `end_reason`**; one non-terminal session per **client user** and per advisor,
> enforced by **partial unique index** (`WHERE status <> 'ended'`), not check-then-act; **every non-terminal
> state has a server-enforced deadline** — including the browser-picker step (90s), since all of them hold the
> unique indexes; client offline ⇒ request not sent; `track.onended` ⇒ immediate end; no re-establish within
> 15s ⇒ `connection_lost` — delivered by a **disconnect-triggered delayed job on a dedicated `realtime`
> queue/worker** (the `everyMinute` scheduler cannot meet it, and the shared `default` queue can be
> backlogged; the sweep is only the backstop). 15s is a **target**, not a hard guarantee — safety does not
> depend on it, because peer-to-peer media stops the instant the connection drops. **All background
> sweeps/jobs run inside `RequestContext::withSystemContext()`** — the table is RLS-protected and would
> otherwise see nothing and leave concurrency locks behind. **All six timing values come from
> `ProjectSettings`; none are hardcoded.**
> **PRIVACY/UI** — `getDisplayMedia` is called with **`audio: false`**; `displaySurface` is inspected and
> over-sharing escalates the client banner; the client banner is **mandatory, non-dismissible** (advisor
> name/photo, live timer, one-click End, Meridian alert-amber token); advisor viewer is labelled **view-only**;
> browser-adaptive picker guidance (Chromium/Firefox/Safari); mobile shows the fallback message.
> **ARCHITECTURALLY ABSENT** — **no** RTCDataChannel, **no** input forwarding, **no** `MediaRecorder`/frame
> capture, **no** media endpoint — enforced by CI guards. Screen content is never logged, screenshotted, or
> stored. Remote control, recording, multi-party, mobile, and reverse-direction sharing require **separate
> specs and security review**.
> **AUDIT** — every request/response/transition logged with the §8.1 field set, plus `authorization_basis`;
> visible to the advisor (own), the participating client user (own), and admin (all). `AuditWriter` redacts
> `before`/`after` payloads only — `ip`/`user_agent` are stored raw as security-audit columns by design; the
> "no PII in raw logs" rule governs **application logs**, not `audit_events`. Never log page content or URLs
> containing client data.

---

*Companion to the platform's consent-first architecture (broker/coach referrals, funder access, document
sharing) — this feature deliberately reuses that request→approve→log pattern rather than inventing another.*
