# Panel portal foundation

WO-70 creates the shared broker/coach panel foundation. WO-71 layers the
insurance-broker FSP validation and referral-stage specialisation onto that
foundation. Coach-specific specialisations land in WO-72.

## Onboarding Gate

`PanelOnboarding` owns the shared flow:

1. invite user into the broker or coach role
2. submit application into `panel_members`
3. advisor/super-admin approval creates a pending `panel_agreements` row
4. panel member signs the agreement
5. signed agreement activates portal access

`assertPortalAccess()` requires an active panel member and a signed agreement.
Agreement PDFs are rendered through `PdfRenderer`, stored on `secure_local`, and
hash-wrapped with `KeyEnvelope`.

Every generated agreement includes no-fee mutual-referral terms, confidentiality,
client-consent requirements, and an explicit note that reverse referrals do not
grant platform access.

## Referrals

`ReferralLifecycle` provides the shared coach/general lifecycle:

- `draft`
- `sent`
- `accepted`
- `in_progress`
- `completed`
- `withdrawn`

Transitions are forward-only and audited. `referral_messages` provide a small
per-referral message ledger visible to the advisor/client scope and the owning
panel member.

Broker referrals use the insurance lifecycle added in WO-71:

- `draft`
- `referral_sent`
- `broker_acknowledged`
- `quote_requested`
- `cover_placed`
- `declined`
- `no_response`
- `withdrawn`

Generic `accepted`/`in_progress` stages are not valid for broker referrals.
`cover_placed`, `declined`, `no_response`, and `withdrawn` close the referral.

## Broker FSP Gate

Broker applications must include an FSP number. `PanelOnboarding::approve()`
delegates broker approvals to `BrokerFspVerifier`, which looks up the FSP
record through `FspClient`. Live mode routes through `ResilientHttp`; local and
test environments use `database/fixtures/integration/fsp.json`.

Approval is blocked unless the FSP status is current. The generated panel
agreement stores broker-specific clauses requiring the FSP registration to stay
current, making lapse an automatic portal suspension event.

`panels:broker-fsp-reverify` runs daily and re-checks active brokers whose FSP
record is stale. A lapsed, inactive, cancelled, suspended, or unknown FSP status
suspends the panel member, audits `panel.broker_fsp_lapsed`, and sends urgent
advisor/super-admin notifications.

## Reverse Referrals

Active panel members can create reverse referrals into either:

- a `prospect_leads` row
- an `entrepreneur_profiles` row without a user account

No invite token is issued and no platform access is granted automatically.

## Access Scope

Panel RLS lets a broker or coach see their own panel member, agreement,
referral, message, and reverse-referral records. Advisors and super admins retain
oversight. Client-scoped referral rows remain visible to advisors through the
existing client RLS context.
