# Client management

WO-14 introduces the advisor-facing client creation flow and the storage contract that later questionnaire, portal, document, lifecycle, and offboarding work will build on.

## Core tables

- `clients` stores the engagement type, NZBN-derived registry data, initial data quality, source badges, creator, and future primary contact.
- `client_team` links users to client scopes for RLS and advisor access.
- `conflict_declarations` records the mandatory declaration captured before a client can be saved.

The initial `clients.data_quality` value is always `insufficient`. WO-19 owns the later scoring rules once questionnaire responses and document verification exist.

## Engagement type

`App\Enums\EngagementType` is the canonical list:

- `standard_advisory`
- `due_diligence`
- `post_acquisition_advisory`
- `entrepreneur_module`

`Client::engagementTypeIsLocked()` returns true when `engagement_type_locked_at` is set or when a `questionnaire_responses` row exists for the client. WO-17 will create that table and use this seam to prevent engagement switching after questionnaire work begins.

## Registry population

`PopulateFromNzbn` calls the WO-13 NZBN and Companies Office clients and includes the IRD client status. IRD is regulatory-deferred pending the proposed Data Consumer category, so it returns a client-supplied/not-IRD-verified status rather than live Inland Revenue verification.

- raw service payloads
- a normalized summary for the create form
- per-service `source_badges`
- a `degraded` flag when any source came from fallback

The advisor create controller re-runs the lookup at save time so stored client data and source badges are server-derived.

## Conflict gate

WO-14 stores the create-time conflict declaration directly in `conflict_declarations`. WO-21 will expand this into a reusable `ConflictDeclarer` service for broker/coach referrals and DD-specific re-declarations.

## RLS scope

WO-14 extends the Postgres request context with `fsa.user_id`. `EnforceClientScope` first sets role and user id, then resolves `client_team` rows for that user, then applies the final client id list. This lets RLS protect client-scoped tables while still allowing the current user to discover their own memberships.
