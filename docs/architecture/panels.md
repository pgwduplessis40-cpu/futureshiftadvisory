# Panel portal foundation

WO-70 creates the shared broker/coach panel foundation. Broker-specific FSP
checks and coach-specific specialisations land in WO-71 and WO-72.

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

`ReferralLifecycle` provides the shared lifecycle:

- `draft`
- `sent`
- `accepted`
- `in_progress`
- `completed`
- `withdrawn`

Transitions are forward-only and audited. `referral_messages` provide a small
per-referral message ledger visible to the advisor/client scope and the owning
panel member.

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
