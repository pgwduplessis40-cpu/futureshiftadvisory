# Panel portal foundation

WO-70 creates the shared broker/coach panel foundation. WO-71 layers the
insurance-broker FSP validation and referral-stage specialisation onto that
foundation. WO-72 adds coach vetting, the five fixed specialisations, coach
stage handling, and key-staff authorisation.

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

Coach referrals use the coach lifecycle added in WO-72:

- `draft`
- `referral_sent`
- `coach_accepted`
- `coaching_underway`
- `concluded`
- `declined`
- `withdrawn`

`concluded`, `declined`, and `withdrawn` close the referral.

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

## Coach Vetting

Coach vetting is admin-managed; there is no mandatory register lookup. The fixed
specialisations are:

- `life`
- `business_executive`
- `mental_health_wellbeing`
- `financial_wellness`
- `career`

`CoachPanel::vet()` records selected specialisations, profile details,
professional memberships where held, and the admin vetting payload on
`panel_members`. Coach agreements include the wellbeing scope boundary:
coaching only, not clinical mental-health diagnosis, treatment, crisis support,
or regulated health advice.

Coach referrals may be for a business owner, key staff member, or entrepreneur.
Key-staff referrals require an active `coach_referral_authorisations` row for
the client. Entrepreneur referrals link directly to `entrepreneur_profiles` and
do not require a client row.

## Coach Signal Suggestions

WO-73 consumes raw `coaching_signals` rows and converts them into
`coach_referral_suggestions` for advisor review. Suggestions map to the five
fixed coach specialisations:

- low personal coping streak -> `mental_health_wellbeing`
- leadership capability gap -> `business_executive`
- owner-readiness primary constraint -> `life`
- financial stress -> `financial_wellness`
- career transition -> `career`

The advisor dashboard receives a scoped `coachSignals` payload. Suggestions
never create referrals automatically; the payload and persisted evidence both
record `auto_referral = false`.

`panels:coach-signal-calibration` runs the governed calibration layer
(`layer_id = 17`). It can queue `learning_updates` candidates for owner review,
but it does not apply behaviour changes and does not create
`learning_update_implementations`.

## Referral Send Gate

WO-74 moves conflict and consent checks into the shared send transition. Draft
broker/coach referrals can be prepared, but transitioning to `referral_sent`
requires:

- a fresh `conflict_declarations` row for the same client/advisor and referral
  type (`broker_referral` or `coach_referral`)
- an active opt-in `consents` row for the matching referral type

`ReferralConsentManager::prepareForSending()` links the conflict and consent to
the referral. `ReferralLifecycle::transition()` re-checks both at send time, so
stale conflicts or revoked/opt-out consents fail closed.

Revoking a referral consent marks the consent `opt_out`, records `revoked_at`,
and withdraws any linked non-terminal referrals with
`referral.withdrawn_consent_revoked`. This keeps client consent revocation
effective after a referral has already been sent.

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
