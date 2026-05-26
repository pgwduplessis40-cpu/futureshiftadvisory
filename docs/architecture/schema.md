# Schema notes

This file records schema additions by work order. It is intentionally concise; migrations remain the source of truth.

## WO-04 - AI integrity foundation

### `learning_updates`

Governed queue for proposed AI behaviour changes. Phase 1 only writes detected candidates; no automatic implementation is allowed.

Key columns:

- `id` UUID primary key
- `layer_id` small integer identifying the future learning layer
- `source` JSONB with detector/prompt/source metadata
- `summary` human-readable candidate summary
- `proposed_change` JSONB
- `impact_scope` JSONB
- `clients_affected`
- `magnitude`
- `confidence`
- `evidence` JSONB
- `effective_date`
- `status` (`detected`, `staged`, `approved`, `rejected`, `deferred`, `implemented`, `rolled_back`)
- `decided_by_user_id`, `decided_at`
- `rollback_id`

### `learning_update_implementations`

Implementation and review ledger for an approved learning update. Created now so Phase 3 can add approval UI without changing the storage contract.

Key columns:

- `id` UUID primary key
- `learning_update_id`
- `implemented_at`
- `review_due`
- `review_outcome`
- `rolled_back_at`

## WO-93 - Learning update approval queue

### `learning_update_decisions`

Immutable decision ledger for the governed learning-update queue. Each admin action records the update, decision, optional effective date/reason, actor, and timestamp. Approval updates the candidate with a seven-day pre-implementation notice timestamp and a 30-day post-effective review date; it does not create an implementation row.

Key columns:

- `id` UUID primary key
- `learning_update_id`
- `decision` (`approve`, `approve_modified_date`, `defer`, `reject`)
- `effective_date`
- `reason`
- `decided_by_user_id`
- `decided_at`

Additional `learning_updates` columns:

- `pre_implementation_notice_at`
- `review_due_at`

## WO-94 - Learning rollback audit

### `learning_rollbacks`

Rollback ledger for implemented learning updates. One rollback row is allowed per implementation so retries are idempotent. The rollback service restores the prior target state from the implementation snapshot, marks the implementation and update as rolled back, and writes an immutable audit event.

Key columns:

- `id` UUID primary key
- `learning_update_id`
- `learning_update_implementation_id`
- `reason`
- `rolled_back_by_user_id`
- `rolled_back_at`
- `restored_state` JSONB

Additional `learning_update_implementations` columns:

- `target_type`, `target_id`
- `before_state` JSONB
- `after_state` JSONB

## WO-95 - Learning cadence and monitoring

No new tables are required. WO-95 formalises the 32-layer cadence registry over the existing `learning_layer_runs` and `learning_updates` governance tables.

Operational notes:

- `LayerCadenceRegistry` defines 32 registered learning layers with hourly, daily, weekly, or monthly cadence.
- `learning:cadence` records due layer runs hourly through the scheduler.
- Cadence-only rows write `window.governed_candidates_only = true` and `window.automatic_application = false`.
- The admin learning queue payload now includes monitoring summary, layer latest-run state, queue counts, and recent run history.

## WO-102 - Active learning engine

### `learning_layer_state`

Per-layer activation and cadence state for the 32 governed learning layers. Active runs create `learning_updates` candidates only and record `learning_layer_runs`; they never write implementation rows or mutate prompts, scoring weights, rating descriptors, or framework values.

Key columns:

- `layer_id` small integer primary key matching `LayerCadenceRegistry`
- `name`
- `cadence`
- `active`
- `min_sample`
- `last_run_at`
- `next_due_at`
- `config` JSONB

## WO-104 - DD outcome and valuation learning

### `dd_outcome_records`

Client-scoped DD outcome capture used to compare eventual acquisition outcomes with earlier DD valuations. The DD learning layer reads these rows and repeated DD findings to create governed candidates only.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `recorded_price`
- `actual_outcome` JSONB
- `recorded_at`

## WO-105 - Cohort privacy and plan-quality benchmarks

No new tables are required. WO-105 centralises minimum-cohort suppression in
`CohortGuard` and writes aggregate-only plan-quality benchmark candidates to
`learning_updates` with `layer_id = 20`.

## WO-106 - Conversion outcome learning

### `conversion_outcomes`

Client/profile-scoped realised-outcome records that connect entrepreneur plan
quality to later business-success signals. The learning layer reads only
aggregate cohorts through `CohortGuard` and writes candidate-only
`learning_updates`.

Key columns:

- `id` UUID primary key
- `entrepreneur_profile_id`
- `client_id`
- `plan_assessment_id`
- `outcome_signal` JSONB
- `observed_at`

## WO-107 - Rating predictive-validity tests

### `rating_validity_tests`

Semiannual aggregate test results comparing rating-framework plan scores with
realised conversion outcomes. Rows are aggregate-only and feed governed
`learning_updates` candidates with `layer_id = 18`.

Key columns:

- `id` UUID primary key
- `rating_framework_id`
- `period`
- `correlation` JSONB
- `tested_at`

## WO-108 - Cross-client competitive intelligence

### `industry_intelligence_signals`

Aggregate-only industry pattern signals generated from cross-client analysis
findings. Signals are suppressed below `privacy.min_cohort` through
`CohortGuard`; advisor alerts are deduped by `signal_key`.

Key columns:

- `id` UUID primary key
- `industry_code`
- `signal_type`
- `signal_key`
- `aggregate` JSONB
- `cohort_size`
- `generated_at`
- `suppressed`
- `alerted_at`

## WO-109 - Shared intelligence layer

### `shared_intelligence_patterns`

Aggregate-only patterns bridged between advisory and entrepreneur domains.
Rows are created only from already-governed aggregate sources and only at or
above `privacy.min_cohort`.

Key columns:

- `id` UUID primary key
- `source_domain`
- `target_domain`
- `signal_key`
- `pattern` JSONB
- `cohort_size`
- `generated_at`

## WO-110 - Anonymous benchmarking community

### `benchmark_aggregates`

Domain-separated anonymous benchmark distributions. Rows store percentile-band
aggregates only and can record privacy-counsel sign-off.

Key columns:

- `id` UUID primary key
- `domain`
- `industry_code`
- `metric`
- `distribution` JSONB
- `cohort_size`
- `quarter`
- `suppressed`
- `generated_at`
- `privacy_counsel_signed_off_at`
- `privacy_counsel_user_id`

### `peer_network_members`

Opt-in community membership shared by benchmark and peer features. Membership
requires an active consent for its `membership_type` and is pseudonymous.

Key columns:

- `id` UUID primary key
- `user_id`
- `community`
- `membership_type`
- `pseudonym`
- `joined_at`
- `consent_id`
- `status`
- `suspended_at`
- `revoked_at`

## WO-111 - Anonymous peer network

### `peer_posts`

Pseudonymous community posts. Posts are hidden until a moderation row approves
them.

Key columns:

- `id` UUID primary key
- `peer_network_member_id`
- `community`
- `body`
- `posted_at`
- `visible_at`
- `reported_by_user_id`
- `reported_at`
- `report_reason`

### `peer_post_moderation`

One moderation decision per post.

Key columns:

- `id` UUID primary key
- `peer_post_id`
- `status` (`pending`, `approved`, `rejected`)
- `moderated_by_user_id`
- `reason`
- `moderated_at`

## WO-112 - Advisor API layer

### `advisor_api_clients`

Approved external integration clients for the advisor API. Tokens are stored as
hashes only; route middleware applies advisor client scope, rate limiting, and
call auditing.

Key columns:

- `id` UUID primary key
- `name`
- `advisor_user_id`
- `token_hash`
- `scopes` JSONB
- `status`
- `rate_limit_per_minute`
- `approved_by_user_id`
- `approved_at`
- `last_used_at`

## WO-113 - NZ business tool integrations

### `nz_tool_connections`

Encrypted OAuth connection records for Employment Hero, Cin7, and Tradify.
One active connection is allowed per client/provider, and the latest snapshot
payload preserves provider source metadata for advisory evidence.

Key columns:

- `id` UUID primary key
- `client_id`
- `provider`
- `external_tenant_id`
- `status`
- `token_envelope`
- `token_envelope_meta` JSONB
- `scopes` JSONB
- `last_sync_payload` JSONB
- `connected_by_user_id`
- `connected_at`
- `revoked_by_user_id`
- `revoked_at`
- `last_synced_at`

## WO-114 - Voice assistant companion

### `voice_assistant_sessions`

Client-scoped shortcut launch and completion records for the advisor voice
assistant. Payloads are static allowlisted intents, and completed sessions link
to the resulting `call_logs` row.

Key columns:

- `id` UUID primary key
- `client_id`
- `advisor_user_id`
- `call_log_id`
- `status` (`started`, `completed`, `cancelled`)
- `shortcut_intent`
- `shortcut_payload` JSONB
- `transcript`
- `started_at`
- `completed_at`

WO-114 also adds `whisper_transcription` to the consent type registry. Live
Whisper transcription requires both `FEATURE_WHISPER_LIVE=true` and active
client opt-in consent.

## WO-115 - Mobile API foundation

### `device_registrations`

First-party mobile device tokens. Plaintext tokens are returned once by
`MobileTokenIssuer`; only SHA-256 hashes are stored. Active devices require MFA
and current terms acceptance at registration and on each API request.

Key columns:

- `id` UUID primary key
- `user_id`
- `device_id`
- `platform`
- `device_name`
- `app_version`
- `token_hash`
- `status` (`active`, `revoked`)
- `capabilities` JSONB
- `mfa_confirmed_at`
- `terms_confirmed_at`
- `last_used_at`
- `expires_at`
- `revoked_at`
- `revoked_by_user_id`

## WO-116 - Industry WACC automation

### `industry_wacc_data`

Global industry-level weighted-average-cost-of-capital reference data consumed
by `DiscountRateResolver` when `DiscountMethod::IndustryWacc` is requested
without an explicit rate override.

Key columns:

- `id` UUID primary key
- `industry_code`
- `industry_label`
- `wacc_rate`
- `cost_of_equity`
- `cost_of_debt`
- `equity_weight`
- `debt_weight`
- `source`
- `source_badge`
- `degraded`
- `correlation_id`
- `quarter`
- `fetched_at`
- `superseded_at`
- `record_hash`
- `payload` JSONB

## WO-96 - Terms version-manager polish

No new tables are required. WO-96 adds material-clause counts to admin terms payloads so the version history, editor, preview, and publish screens expose whole-document classification and per-clause classification state from the existing `terms_versions` and `terms_clauses` tables.

## WO-97 - Testimonial capture

### `testimonials`

Consent-aware testimonial capture. NPS scores of 8 or higher create a requested testimonial row; marketing-library visibility requires explicit FSA marketing consent and either named or anonymous display mode.

Key columns:

- `id` UUID primary key
- `client_id`
- `source_type`, `source_score`
- `quote`
- `marketing_consent`
- `display_mode` (`named`, `anonymous`)
- `display_name`
- `status` (`requested`, `consented`, `declined`)
- `requested_by_user_id`, `submitted_by_user_id`
- `requested_at`, `consented_at`, `declined_at`

## WO-98 - Voice notes and call logs

### `voice_notes`

Secure voice-note processing ledger. Uploaded audio is first stored as a scanned `documents` row on the secure disk, then transcribed through the Whisper client and summarised through the shared AI client.

Key columns:

- `id` UUID primary key
- `client_id`
- `document_id`
- `uploaded_by_user_id`
- `original_filename`, `mime_type`, `duration_seconds`
- `transcription_text`, `transcription_metadata`
- `summary_text`, `summary_payload`
- `status` (`uploaded`, `transcribed`, `summarized`)
- `transcribed_at`, `summarized_at`

### `call_logs`

Structured meeting and phone-call notes, optionally generated from a voice note. Action items are stored as JSON and linked to concrete `milestone_actions` when a milestone id is present.

Key columns:

- `id` UUID primary key
- `client_id`
- `voice_note_id`
- `advisor_user_id`
- `title`
- `channel` (`voice_note`, `phone_call`)
- `occurred_at`
- `transcript`
- `summary`
- `action_items` JSONB

Additional `milestone_actions` columns:

- `call_log_id`

## WO-99 - Bulk communications

### `bulk_communications`

Advisor-created communication batches for branded client updates. A batch stores
the selected template, subject/body, audience rule, schedule, sender, status,
and aggregate delivery/open metrics.

Key columns:

- `id` UUID primary key
- `title`, `template_key`
- `subject`, `body`
- `audience_type` (`selected_clients`, `all_clients`)
- `selected_client_ids` JSONB
- `status` (`scheduled`, `sent`)
- `scheduled_at`, `sent_at`
- `created_by_user_id`
- `metrics` JSONB

Rows are RLS-scoped to system/super-admin users or the creating advisor.

### `bulk_communication_recipients`

Per-client recipient ledger for delivery and open tracking. Recipients resolve
from the client's primary contact and client-team users, then delivery honours
the existing `communication_preferences.channel` value: email-only gets mail,
in-platform-only gets a portal message, and both gets both.

Key columns:

- `id` UUID primary key
- `bulk_communication_id`
- `client_id`
- `user_id`
- `message_id` nullable in-platform message link
- `channel`
- `preference_channel`, `preference_frequency`
- `status` (`pending`, `sent`, `skipped`, `failed`)
- `open_token`
- `sent_at`, `opened_at`
- `delivery_metadata` JSONB

Recipient rows use the standard client-scoped RLS policy. The public open-pixel
route applies system context before resolving a token, so anonymous access never
receives direct table visibility.

## WO-100 - Document expiry reminders

### `document_expiry_reminders`

Idempotent reminder ledger for clean client documents with `documents.expires_at`
inside the configured lookahead window. The scheduler writes one row per
document, user, and reminder type before sending the channel-aware notification,
so repeated runs do not send duplicate reminders.

Key columns:

- `id` UUID primary key
- `document_id`
- `client_id`
- `user_id`
- `reminder_type` (`expires_soon`)
- `expires_at_snapshot`
- `triggered_at`
- `metadata` JSONB

Rows use the standard client-scoped RLS policy. `documents:expiry-reminders`
runs daily in system context and routes `DocumentExpiryReminderNotification`
through `ChannelResolver`, preserving each user's mail/platform frequency
preference.

## WO-101 - Portal offline PWA

No database tables are added. WO-101 uses encrypted browser IndexedDB storage
for the temporary offline queue and replays queued questionnaire/document-upload
requests against the existing authenticated portal endpoints when connectivity
returns.

## WO-122 - Portal offline sync hardening

### `portal_offline_sync_records`

Server-side idempotency ledger for replayed portal offline queue records. Rows
store the queued client id, operation, idempotency key, request fingerprint, and
the normalized JSON success payload returned to the browser.

Key columns:

- `id` UUID primary key
- `user_id`
- `client_id`
- `operation`
- `idempotency_key`
- `request_fingerprint`
- `response_payload` JSONB
- `status_code`

The unique key is `(user_id, client_id, operation, idempotency_key)`. RLS permits
the owning user within their accessible client scope, plus `super_admin` and
`system` contexts.

## WO-123 - Advisor knowledge capture drafts

### `knowledge_entry_drafts`

Advisor-owned review queue for AI-assisted knowledge-base capture. Offboarding
completion and the manual client action write pending drafts here; accepted
drafts create a live `knowledge_entries` row and store its id. Pending and
discarded drafts never appear in the live knowledge-entry index.

Key columns:

- `id` UUID primary key
- `author_user_id`
- `client_id` nullable
- `source_type`, `source_id`, `source_reference`
- `category`, `title`, `body`
- `tags` JSONB
- `source_attribution` JSONB
- `state` (`pending`, `accepted`, `discarded`)
- `accepted_entry_id` nullable link to `knowledge_entries`

The unique source key is `(author_user_id, source_type, source_id)`, making
capture idempotent per advisor/source. RLS mirrors `knowledge_entries`: advisors
see only their own drafts, while `super_admin` and `system` contexts can see all
rows. This is an advisor-draft governance model, not a `learning_updates`
candidate or cadence-registry layer.

## WO-05 - Integration resilience layer

### `integration_calls`

Per-attempt ledger for external integration calls. Every retry, success, failure, cached response, and fallback response is recorded with a shared correlation id for the logical call.

Key columns:

- `id` UUID primary key
- `service`
- `endpoint`
- `request_id`
- `status` (`success`, `retry`, `failure`, `cached`, `fallback`)
- `latency_ms`
- `attempt`
- `error_payload` JSONB
- `correlation_id`
- `occurred_at`

### `integration_health_samples`

Five-minute rollup table for WO-30 dashboard surfaces.

Key columns:

- `id` UUID primary key
- `service`
- `window_start`, `window_end`
- `success_rate`
- `p95_latency_ms`
- `health` (`green`, `amber`, `red`)

## WO-06 - Secure file storage + virus scanning

### `documents`

Ledger for sensitive uploads. File bytes are stored on the `secure_local` encrypted disk; this table stores metadata, scan state, and future linkage to clients, entrepreneur profiles, verification rows, and upload owners.

Key columns:

- `id` UUID primary key
- `client_id`
- `entrepreneur_profile_id`
- `category` (`financial_statement`, `contract`, `insurance_certificate`, `hr_record`, `compliance_doc`, `plan_attachment`, `dd_artifact`, `other`)
- `original_filename`
- `stored_path`
- `byte_size`
- `mime_type`
- `sha256`
- `uploaded_by_user_id`
- `scanner_result` (`pending`, `clean`, `infected`, `error`)
- `scanner_payload` JSONB
- `expires_at`

Scanner errors are persisted as quarantined documents with `scanner_result=error`; client-visible queries must use the model's `visibleToClients` scope.

## WO-07 - User roles, permissions, RBAC

### `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

Spatie `laravel-permission` tables back the Phase 1 RBAC matrix. `roles.name` values match the nine `users.user_type` values; permission names are defined in `app/Enums/Permission.php` and seeded by `PermissionSeeder`/`RoleSeeder`.

Key rules:

- The executable role matrix is `RoleSeeder::rolePermissions()`.
- `User::fsaRole()` resolves the first assigned Spatie role for RLS request context and falls back to `primary_role` only when no Spatie role has been assigned.
- `dd_guest` is a token type only for future DD upload links; it is not a Spatie role and must not be stored as `users.user_type`.

## WO-08 - Invite-only registration + MFA enforcement

### `users` additions

Identity and MFA metadata used by invite-only auth, MFA enforcement, RBAC fallback role resolution, and WO-09 session timeout policy.

Key columns:

- `user_type` (`super_admin`, `advisor`, `junior_advisor`, `entrepreneur_mentor`, `client_primary`, `client_team`, `entrepreneur`, `broker`, `coach`)
- `primary_role`
- `mfa_enabled_at`
- `mfa_method`
- `last_password_set_at`
- `session_timeout_minutes`
- `suspended_at`
- `suspended_reason`

### `invite_tokens`

One-shot account invitation records. Only the SHA-256 token hash is stored.

Key columns:

- `id` UUID primary key
- `email`
- `target_role`
- `target_user_type`
- `token_hash`
- `expires_at`
- `accepted_at`
- `issued_by_user_id`
- `accepted_by_user_id`

### `mfa_factors`

MFA factor ledger synced from Fortify's TOTP state.

Key columns:

- `id` UUID primary key
- `user_id`
- `type` (`totp`)
- `label`
- `secret_envelope`
- `recovery_codes_envelope`
- `confirmed_at`
- `last_used_at`

## WO-09 - Session management + step-up MFA

### `sessions` additions

The Laravel database session table carries lightweight security observability for the WO-09 middleware.

Key columns:

- `risk_score` latest calculated request risk score for the session
- `step_up_at` timestamp when the session was last forced into step-up MFA

The enforcement source of truth remains the server-side session store: `EnforceSessionSecurity` keeps last activity and device-signal markers in session data so the same logic works with the array session driver in tests.

## WO-10 - Terms model + version control

### `terms_versions`

Version header for the platform terms contract. Published versions are immutable and remain readable forever.

Key columns:

- `id` UUID primary key
- `version`
- `title`
- `material`
- `published_at`
- `published_by_user_id`
- `notice_period_days`
- `reviewer_reference`
- `pdf_path`
- `created_by_user_id`

### `terms_clauses`

The 14-clause body for each terms version. Clauses are copied forward into drafts so historical text remains intact.

Key columns:

- `id` UUID primary key
- `terms_version_id`
- `clause_number`
- `title`
- `body`
- `material`

### `terms_acceptances`

Acceptance ledger. WO-10 uses it to expire active acceptances when a material version is published; WO-11 writes accepted/declined gate outcomes and signed-PDF evidence.

Key columns:

- `id` UUID primary key
- `user_id`
- `terms_version_id`
- `accepted_at`
- `declined_at`
- `expires_at`
- `reacceptance_notice_queued_at`
- `signed_pdf_path`
- `signed_pdf_sha256_envelope`
- `signed_pdf_envelope_meta`
- `signed_pdf_byte_size`
- `ip`
- `user_agent`

## WO-12 - Notifications and communication preferences

### `communication_preferences`

Per-user delivery preference used by `ChannelResolver`.

Key columns:

- `id` UUID primary key
- `user_id`
- `channel` (`email_only`, `in_platform_only`, `both`)
- `frequency` (`immediate`, `daily`, `weekly`)
- `timezone`

### `notifications`

Laravel-compatible notification ledger extended with Phase 1 routing evidence. It is the durable source for future in-platform notification UI and digest dispatch.

Key columns:

- `id` UUID primary key
- `type`
- `notifiable_type`
- `notifiable_id`
- `data`
- `urgency`
- `channel_decision`
- `read_at`

## WO-14 - Add New Client

### `clients`

Client record and Phase 1 registry snapshot.

Key columns:

- `id` UUID primary key
- `engagement_type` (`standard_advisory`, `due_diligence`, `post_acquisition_advisory`, `entrepreneur_module`)
- `nzbn`
- `legal_name`
- `trading_name`
- `entity_type`
- `address` JSONB
- `gst_registered`
- `directors` JSONB
- `filing_status`
- `data_quality` (`insufficient` initially; WO-19 owns scoring)
- `registry_sources` JSONB source badges from NZBN, Companies Office, and IRD
- `created_by_user_id`
- `primary_contact_user_id`
- `engagement_type_locked_at`

### `client_team`

User-to-client access ledger used by RLS scope resolution.

Key columns:

- `id` UUID primary key
- `client_id`
- `user_id`
- `granted_modules` JSONB
- `role`

### `conflict_declarations`

Mandatory conflict declaration captured before a client can be saved. WO-21 will expand this primitive for referrals and DD-specific declarations.

Key columns:

- `id` UUID primary key
- `client_id`
- `advisor_id`
- `declaration` JSONB
- `declared_at`

## WO-15 - Add New Entrepreneur

### `entrepreneur_profiles`

Phase 1 entrepreneur profile and invite handoff record. The full entrepreneur module remains Phase 3; Phase 1 only stores enough to issue invites and route accepted users to the placeholder portal.

Key columns:

- `id` UUID primary key
- `user_id` accepted entrepreneur account, nullable until invite acceptance
- `assigned_advisor_id`
- `invite_token_id`
- `name`
- `email`
- `stage` (`invited` or `onboarding` reachable in Phase 1; full enum is forward-compatible with Phase 3)
- `concept_summary`

## WO-16 - Client portal shell + onboarding wizard

### `clients` addition

The portal wizard persists Phase 1 onboarding progress directly on the client row.

Key column:

- `onboarding_wizard_state` JSONB containing `current_step`, `completed_steps`, per-step payloads under `steps`, `submitted_at`, and `updated_at`

## WO-17 - Questionnaire engine

### `questionnaires`

Version header for each questionnaire set.

Key columns:

- `id` UUID primary key
- `set` (`standard_advisory`, `dd_specific`, `post_acquisition_gap`, `entrepreneur_readiness`, `entrepreneur_idea_validation`)
- `version`
- `title`
- `published_at`
- `created_by_user_id`
- `published_by_user_id`

### `questionnaire_sections`

Ordered section headings and help text for a questionnaire version.

Key columns:

- `id` UUID primary key
- `questionnaire_id`
- `order`
- `title`
- `help_text`

### `questionnaire_questions`

Ordered question definitions. `conditional_logic` stores simple `when` plus `equals` or `in` rules with a `show` target question id.

Key columns:

- `id` UUID primary key
- `questionnaire_section_id`
- `order`
- `type` (`text`, `long-text`, `number`, `currency`, `date`, `single-select`, `multi-select`, `file-attach`, `likert`)
- `prompt`
- `help_text`
- `options` JSONB
- `conditional_logic` JSONB
- `required`

### `questionnaire_responses`

One client response per questionnaire version. Client-scoped RLS applies.

Key columns:

- `id` UUID primary key
- `client_id`
- `questionnaire_id`
- `submitted_at`
- `submitted_by_user_id`

### `questionnaire_answers`

Answer values and document links for each visible submitted question. Answer rows are scoped through their parent response.

Key columns:

- `id` UUID primary key
- `response_id`
- `question_id`
- `value` JSONB
- `attached_document_ids` JSONB

## WO-29 - Website integration prospect capture

### `prospect_leads` additions

The public website integration writes signed intake payloads into the existing prospect lead table. Advisors triage rows from the authenticated prospect inbox; an `invited` outcome issues a normal WO-08 invite token and stores the linkage without creating platform access automatically.

Key columns:

- `status` (`new`, `invited`, `parked`, `declined`)
- `assigned_advisor_user_id`
- `dedupe_key`
- `payload_hash`
- `intake_payload` JSONB
- `triage_outcome` (`invited`, `parked`, `declined`)
- `triage_notes`
- `triaged_at`
- `triaged_by_user_id`
- `invite_token_id`

## WO-30 - API health dashboard

### `integration_health_alerts`

Idempotency ledger for stuck-red integration alerts. The alerting command creates one row for each contiguous red incident once the red run exceeds 30 minutes, then notifies super-admins without spamming repeated scheduler runs.

Key columns:

- `id` UUID primary key
- `service`
- `stuck_started_at`
- `last_red_window_end`
- `notified_at`
- `notification_id`

## WO-31 - Analysis spine

### `analysis_runs`

Run ledger for every Phase 2 analysis module. The row is created before AI execution so blocked and failed runs remain auditable.

Key columns:

- `id` UUID primary key
- `client_id`
- `module` (`financial`, `website_audit`, `competitor`, `swot`, `hr`, `operational`, `systems`, `compliance`, `regulatory_impact`, `insurance_risk`, `knowledge_assessment`, `scenario`, `succession`)
- `status` (`queued`, `running`, `blocked_documents`, `blocked_data_quality`, `completed`, `failed`)
- `framework_lenses` JSONB containing produced lenses
- `data_quality_snapshot` JSONB from `DataQualityScorer`
- `ai_model`, `prompt_version`, `prompt_hash`
- `tokens_in`, `tokens_out`
- `started_at`, `completed_at`
- `created_by_user_id`

Client-scoped RLS applies.

### `analysis_findings`

Governed findings produced by an analysis run.

Key columns:

- `id` UUID primary key
- `analysis_run_id`
- `client_id`
- `lens` (`descriptive`, `diagnostic`, `predictive`, `prescriptive`)
- `severity` (`info`, `low`, `medium`, `high`, `critical`)
- `title`, `body`
- `attributions` JSONB with claim/source-reference pairs
- `document_support` (`verified`, `advisory_flag`, `accuracy_discrepancy`, `none`)
- `uncertainty` (`high`, `medium`, `low`, `none`)
- `data_quality_disclaimer`
- `bias_signals` JSONB
- `pv_link_id`

Client-scoped RLS applies.

### `analysis_feedback`

Advisor feedback ledger for governed analysis findings. WO-31 creates storage and policy only; WO-32 adds capture surfaces and learning-update emission.

Key columns:

- `id` UUID primary key
- `analysis_finding_id`
- `advisor_user_id`
- `decision` (`confirm`, `correct`, `rate`, `add_context`)
- `rating`
- `corrected_body`
- `note`
- `created_at`

RLS scopes rows through the parent `analysis_findings.client_id`.

## WO-32 - Analysis feedback learning loop

### `learning_layer_runs`

Observability ledger for scheduled Phase 2 learning-layer executions. The candidate rows remain in `learning_updates`.

Key columns:

- `id` UUID primary key
- `layer_id`
- `ran_at`
- `candidates_created`
- `window` JSONB with `window_start`, `window_end`, `window_days`, and `threshold`
- `status` (`completed`, `failed`)

### `analysis_feedback`

WO-32 activates the WO-31 feedback table through `FeedbackRecorder` and advisor routes. Rows continue to use the WO-31 columns and RLS policy; systematic correction patterns feed `learning_updates` with `status=detected` only.

## WO-33 - Bias detection layer

WO-33 reuses the WO-31/WO-32 schema rather than adding tables.

### `analysis_findings`

`AnalysisRunner` now guarantees `bias_signals` is populated from the shared `BiasDetector` for each completed analysis output, even when a direct test AI client bypasses the production integrity wrapper.

### `learning_layer_runs`

The bias monitor records layer id `3` executions with `window` JSONB containing `window_start`, `window_end`, `window_days`, `min_findings`, and `skew_threshold`.

### `learning_updates`

Systematic bias signals create governed candidates only:

- `layer_id` = `3`
- `source.type` = `bias_monitor`
- `source.signal_key` idempotently identifies module + cohort + metric
- `proposed_change.action` = `review_module_bias_or_calibration`
- `proposed_change.automatic_application` = `false`
- `status` = `detected`

No WO-33 path writes `learning_update_implementations` or changes existing findings.

## WO-34 - AI red flag alerts

### `red_flags`

Advisor workflow ledger for critical analysis findings and future monitor-derived signals.

Key columns:

- `id` UUID primary key
- `client_id`
- `analysis_finding_id` nullable and unique for finding-derived flags
- `source_type`, `source_key` nullable idempotency pair for monitor-derived flags
- `category` (`financial`, `compliance`, `key_person`, `insurance`, `viability`, `regulatory`)
- `severity`
- `headline`, `detail`
- `surfaced_at`
- `acknowledged_at`, `acknowledged_by_user_id`
- `resolved_at`

Client-scoped RLS applies. WO-34 creates rows only for `critical` findings and never mutates the source finding.

## WO-35 - Client knowledge assessment

### `knowledge_assessments`

Advisor-recorded client knowledge scores and the derived prompt-calibration payload used by subsequent Phase 2 analysis runs.

Key columns:

- `id` UUID primary key
- `client_id`
- `financial_literacy` 1-5 score
- `strategic_awareness` 1-5 score
- `leadership` 1-5 score
- `calibration` JSONB with language depth, financial detail, strategic framing, leadership context, review note, and raw scores
- `assessed_at`
- `assessed_by_user_id`

Client-scoped RLS applies.

### `coaching_signals`

WO-35 reuses the WO-20 scaffold for leadership capability gaps. A low leadership score writes a raw `leadership_capability_gap` observation with `raw_observation_only=true` and `auto_referral=false` evidence.

No Phase 2 path consumes the row for coach detection, calibration, thresholding, referral generation, or notification.

## WO-36 - NZ economic indicators feed

### `economic_indicators`

Global reference-data ledger for the latest and historical economic indicators used by later Phase 2 analysis/PV work.

Key columns:

- `id` UUID primary key
- `indicator` (`ocr`, `cpi_annual`, `gdp_quarterly`, `unemployment_rate`, `minimum_wage`, `living_wage`)
- `label`
- `value`
- `unit`
- `period_date`
- `source` (`rbnz`, `stats_nz`, `mbie`)
- `source_badge` (`stub`, `live`, `cached`, `stub_live_fallback`)
- `degraded`
- `correlation_id`
- `fetched_at`
- `payload` JSONB

Rows are unique by `indicator`, `period_date`, and `source`.

### `exchange_rates`

Global reference-data ledger for NZD exchange rates from RBNZ.

Key columns:

- `id` UUID primary key
- `base_currency`
- `quote_currency`
- `rate`
- `rate_date`
- `source`
- `source_badge`
- `degraded`
- `correlation_id`
- `fetched_at`
- `payload` JSONB

Rows are unique by `base_currency`, `quote_currency`, `rate_date`, and `source`.

### `learning_layer_runs` / `learning_updates`

WO-36 records refresh runs with layer id `12`. OCR value changes create governed `learning_updates` candidates with `source.type=economic_indicator_auto_update` and `proposed_change.automatic_application=false`.

No WO-36 path applies PV discount-rate changes automatically.

## WO-37 - Accounting API integration

### `accounting_connections`

Client-scoped OAuth connection ledger for Xero, MYOB, and QuickBooks.

Key columns:

- `id` UUID primary key
- `client_id`
- `provider` (`xero`, `myob`, `quickbooks`)
- `external_tenant_id`
- `status` (`connected`, `revoked`)
- `token_envelope` encrypted JSON token payload via `KeyEnvelope`
- `token_envelope_meta` JSONB with envelope version, algorithm, and key id
- `scopes` JSONB
- `connected_by_user_id`, `connected_at`
- `revoked_by_user_id`, `revoked_at`
- `last_snapshot_at`

Client-scoped RLS applies. Connecting a provider revokes any prior active connection for the same client/provider before creating the new connected row.

### `financial_snapshots`

Append-only financial statement snapshots pulled from an accounting connection.

Key columns:

- `id` UUID primary key
- `client_id`
- `accounting_connection_id`
- `provider`
- `period_start`, `period_end`
- `source`
- `source_badge` (`stub`, `live`, `cached`, `stub_live_fallback`)
- `degraded`
- `correlation_id`
- `profit_and_loss` JSONB
- `balance_sheet` JSONB
- `cash_flow` JSONB
- `metrics` JSONB
- `pulled_at`

Client-scoped RLS applies. PostgreSQL rejects direct update/delete attempts through the `financial_snapshots_append_only` trigger so historical snapshots remain immutable after creation.

## WO-38 - Continuous financial health monitoring

### `financial_alerts`

Client-scoped early-warning alerts raised by comparing consecutive accounting snapshots for the same connection.

Key columns:

- `id` UUID primary key
- `client_id`
- `accounting_connection_id`
- `previous_snapshot_id`
- `current_snapshot_id`
- `alert_key` unique idempotency key for client/provider/connection/snapshot/metric
- `category` (`profitability`, `cash_flow`, `liquidity`)
- `severity` (`warning`, `critical`)
- `metric`
- `headline`, `detail`
- `previous_value`, `current_value`
- `change_amount`, `change_percent`
- `citation` JSONB with exact previous/current snapshot metric references
- `surfaced_at`
- `notified_at`

Client-scoped RLS applies. Alert citations point back to immutable `financial_snapshots` source rows; WO-38 does not mutate historical snapshots.

## WO-39 - Valuation multiple data feed

### `valuation_multiples`

Append-style NZ market reference data for future business valuation calculations.

Key columns:

- `id` UUID primary key
- `industry_code`
- `industry_label`
- `metric` (`ebitda`, `sde`)
- `multiple_low`, `multiple_mid`, `multiple_high`
- `source` (`mbie`, `nz_business_brokers`)
- `source_badge`
- `degraded`
- `correlation_id`
- `quarter`
- `fetched_at`
- `superseded_at`
- `record_hash` unique idempotency key for source/quarter/range values
- `payload` JSONB

Rows are global reference data rather than client-scoped rows. Refreshes mark prior active rows for the same industry, metric, and source with `superseded_at` before inserting the new active range.

### `learning_layer_runs` / `learning_updates`

WO-39 records refresh runs with layer id `13`. New active rows create governed `learning_updates` candidates with `source.type=valuation_multiple_refresh`, `proposed_change.action=review_valuation_multiple_assumptions`, and `automatic_application=false`.

No WO-39 path performs valuation calculations or applies multiple changes to client outputs.

## WO-40 - PV engine + discount-rate methods

### `pv_calculations`

Client-scoped ledger for present-value calculations. Later PV types write domain rows that point back to this table.

Key columns:

- `id` UUID primary key
- `client_id`
- `type` (`business_valuation`, `improvement_opportunity`, `risk_cost`, `entrepreneur_concept_projection`)
- `discount_method` (`ocr_linked`, `industry_wacc`, `advisor_configured`, `client_inputted`)
- `discount_rate`
- `discount_rate_rationale`
- `inputs` JSONB
- `result` JSONB
- `as_at`
- `created_by_user_id`
- `source_attributions` JSONB

Client-scoped RLS applies. Discount rates are stored as decimals, not percentages. Every calculation must carry source attributions for the selected discount-rate method.

## WO-41 - Business valuation

### `business_valuations`

Client-scoped business valuation output for PV Type 1.

Key columns:

- `id` UUID primary key
- `client_id`
- `pv_calculation_id`
- `sde_value` JSONB
- `ebitda_value` JSONB
- `dcf_value` JSONB
- `reconciled_low`, `reconciled_mid`, `reconciled_high`
- `adjustments` JSONB
- `data_quality_disclaimer`
- `source_attributions` JSONB
- `as_at`

Client-scoped RLS applies. The row stores side-by-side SDE multiple, EBITDA multiple, and DCF values; the linked `pv_calculation_id` points to the DCF calculation in the shared PV ledger.

## WO-42 - Improvement opportunity + risk cost PV

### `improvement_opportunities`

Client-scoped PV Type 2 rows for measurable improvement opportunities.

Key columns:

- `id` UUID primary key
- `client_id`
- `analysis_finding_id`
- `pv_calculation_id`
- `title`
- `annual_benefit`
- `duration_years`
- `pv_of_impact`
- `rank`
- `source_attributions` JSONB

### `risk_costs`

Client-scoped PV Type 3 rows for measurable risk costs.

Key columns:

- `id` UUID primary key
- `client_id`
- `analysis_finding_id`
- `pv_calculation_id`
- `title`
- `financial_impact`
- `probability`
- `duration_years`
- `statutory_penalty_range` JSONB
- `applied_impact`
- `annual_expected_cost`
- `pv_of_cost`
- `rank`
- `source_attributions` JSONB

Client-scoped RLS applies to both tables. Rankings are stored after sorting by descending PV impact/cost.

## WO-43 - PV integration + waterfall chart

WO-43 adds no tables or columns. It reads the latest `business_valuations` row,
summed `improvement_opportunities`, and summed `risk_costs` for visible clients,
then emits dashboard/report-ready waterfall steps from those persisted PV tables.

## WO-44 - Financial analysis module

WO-44 adds no tables or columns. It writes governed financial findings to the
existing `analysis_runs` and `analysis_findings` tables and, when a connected
accounting snapshot exists, feeds the existing `improvement_opportunities` and
`pv_calculations` tables through WO-42/WO-40 services.

## WO-45 - Website audit module

WO-45 adds no tables or columns. Website audit runs write to the existing
`analysis_runs` and `analysis_findings` tables, using questionnaire answers and
client profile rows as cited evidence.

## WO-46 - Competitor analysis module

WO-46 adds no tables or columns. Competitor analysis runs write to the existing
`analysis_runs` and `analysis_findings` tables, using questionnaire answers as
cited competitor evidence. The six-competitor cap is enforced in the module
input mapper rather than through schema.

## WO-47 - SWOT/TOWS/MAPS module

WO-47 adds no tables or columns. Matrix runs write to the existing
`analysis_runs` and `analysis_findings` tables. Prescriptive strategic findings
may set `analysis_findings.pv_link_id` to an existing `improvement_opportunities`
or `risk_costs` row.

## WO-48 - HR and people analysis

WO-48 adds no tables or columns. HR analysis writes to the existing
`analysis_runs` and `analysis_findings` tables, cites questionnaire answers,
verified HR `documents`, and wage `economic_indicators`, and uses the shared
document-verification gate.

## WO-49 - Operational analysis + systems review

WO-49 adds no tables or columns. Operational and systems review runs write to the
existing `analysis_runs` and `analysis_findings` tables, using questionnaire
answers as cited evidence.

## WO-50 - NZ compliance checker + legislative currency

WO-50 adds no tables or columns. Compliance runs write to the existing
`analysis_runs` and `analysis_findings` tables, citing questionnaire answers,
verified documents, and statute references. Legislative-currency monitoring
reuses `learning_layer_runs` and `learning_updates` with layer id `14`; no
automatic implementation rows are written.

## WO-51 - Regulatory change impact assessment

WO-51 adds no tables or columns. Regulatory impact assessment writes to existing
`analysis_runs` and `analysis_findings`, and links financial exposure through
existing `risk_costs` / `pv_calculations` rows by setting
`analysis_findings.pv_link_id`.

## WO-52 - Insurance risk flags

WO-52 adds no tables or columns. Insurance risk flags are governed
`analysis_findings` under module `insurance_risk`, citing questionnaire answers
and verified `insurance_certificate` documents for future broker-referral use.

## WO-53 - Scenario planning

### `scenarios`

Client-scoped named scenario rows for side-by-side best/expected/worst/custom
planning.

Key columns:

- `id` UUID primary key
- `client_id`
- `analysis_run_id`
- `name`
- `kind` (`best`, `expected`, `worst`, `custom`)
- `assumptions` JSONB
- `economic_overlay` JSONB
- `pv_calculation_id`
- `pv_impact`
- `position` (1-5 within the planning run)
- `is_client_visible`
- `created_by_user_id`

Client-scoped RLS applies. `ScenarioPlanner` enforces the five-scenario bound,
creates a `scenario` analysis run, snapshots latest NZ economic indicators, and
routes every scenario through the shared PV ledger.

## WO-54 - Succession planning

### `succession_plans`

Client-scoped succession-planning outputs.

Key columns:

- `id` UUID primary key
- `client_id`
- `analysis_run_id`
- `exit_readiness_score` (1-10)
- `options` JSONB
- `owner_dependency_plan` JSONB
- `target_exit_pv_calculation_id`
- `target_exit_pv`
- `owner_readiness_is_primary_constraint`
- `created_by_user_id`

Client-scoped RLS applies. `SuccessionPlanner` creates a `succession` analysis
run, writes assessed exit options and owner-dependency actions, calculates
target exit PV through the shared PV ledger, and writes a raw
`coaching_signals.owner_readiness_primary_constraint` row only when owner
readiness is the primary constraint. The coaching signal remains a Phase 2 raw
observation; it does not trigger coach referral logic.

## WO-55 - Fee calculator

### `fee_calculations`

Client-scoped ledger for Phase 2 fee suggestions.

Key columns:

- `id` UUID primary key
- `client_id`
- `method` (`hours_based`, `outcome_based`, `entrepreneur`)
- `inputs` JSONB
- `suggested_low`, `suggested_mid`, `suggested_high`
- `improvement_pv_total`
- `risk_cost_pv_total`
- `roi_ratio`
- `justification` JSONB
- `created_by_user_id`

Client-scoped RLS applies. Outcome-based calculations store direct references to
improvement PV, risk-cost PV, annual revenue, complexity, and ROI basis.
Entrepreneur calculations are a distinct lower-entry path and do not introduce
payment collection or signature workflow.

## WO-56 - Fee proposal generation

### `proposals`

Client-scoped proposal artifacts generated from a `fee_calculations` row.

Key columns:

- `id` UUID primary key
- `client_id`
- `fee_calculation_id`
- `status` (`draft`, `released`, `recalled`, `expired`, `renewed`, `awaiting_signature`, `signed`)
- `version`
- `scope` JSONB
- `services` JSONB
- `pv_summary` JSONB
- `roi_ratio`
- `acceptance_terms` JSONB
- `pdf_path`, `pdf_byte_size`
- `released_at`, `released_by_user_id`, `expires_at`
- `recalled_at`, `recalled_by_user_id`
- `expired_at`
- `awaiting_signature_at`
- `signed_at`, `signed_by_user_id`
- `signature_evidence_path`, `signature_evidence_sha256_envelope`, `signature_envelope_meta`, `signature_evidence_byte_size`
- `renewed_from_proposal_id`
- `created_by_user_id`

Client-scoped RLS applies. `ProposalBuilder` owns draft/release/recall/expiry
and renewal. WO-66 `SignoffFlow` is the only path allowed to move proposals
into `awaiting_signature` and `signed`.

### `consents`

Consent elections captured against a proposal for future referral workflows.

Key columns:

- `id` UUID primary key
- `client_id`
- `proposal_id`
- `type` (`insurance_referral`, `coach_referral`)
- `election` (`opt_in`, `opt_out`, `undecided`)
- `evidence` JSONB
- `captured_by_user_id`, `captured_at`

Rows are unique by proposal and consent type. WO-66 recaptures/revokes the
insurance and coach consent elections during sign-off, but still does not create
broker, insurance, or coach referrals automatically.

## WO-66 - Proposal sign-off and authority capture

### `proposal_signoff_steps`

Ordered client sign-off state ledger.

Key columns:

- `id` UUID primary key
- `proposal_id`
- `client_id`
- `step` (`review`, `insurance_consent`, `coach_consent`, `payment_method`, `authority`, `signature`, `confirmation`)
- `completed_by_user_id`
- `completed_at`
- `payload` JSONB with step-specific sanitized evidence

Rows are unique by `proposal_id + step`. Client-scoped RLS applies.

### `payment_authorities`

Tokenised payment authority records captured before a proposal can enter
`awaiting_signature`.

Key columns:

- `id` UUID primary key
- `client_id`
- `proposal_id`
- `type` (`card`, `direct_debit`)
- `gateway` (`stripe`, `windcave`)
- `gateway_customer_ref`
- `gateway_token_envelope` (`KeyEnvelope`; no raw card number or PAN)
- `status` (`active`, `failed`, `revoked`)
- `authorised_by_user_id`, `authorised_at`
- `revoked_at`

Client-scoped RLS applies. WO-66 only captures the authority; schedules begin in
WO-67, while charges, failover charging, and receipts are later WOs.

## WO-67 - Payment schedules

### `payment_schedules`

Client-scoped schedule rows that turn a signed proposal and active tokenised
authority into a future one-off or monthly retainer charge.

Key columns:

- `id` UUID primary key
- `client_id`
- `proposal_id`
- `payment_authority_id`
- `cadence` (`one_off`, `monthly_retainer`)
- `amount`
- `currency` (`NZD`)
- `next_run_at`
- `status` (`active`, `paused`, `revoked`, `completed`)
- `revoked_at`
- `created_by_user_id`

Client-scoped RLS applies. `ScheduleBuilder` only creates schedules from signed
proposals with active authorities. Revoking an authority through the builder
marks active/paused schedules as revoked and audits the cascade.

## WO-69 - Payment processing and receipts

### `payments`

Client-scoped payment attempt ledger for due schedules.

Key columns:

- `id` UUID primary key
- `client_id`
- `payment_schedule_id`
- `payment_authority_id`
- `amount`
- `currency` (`NZD`)
- `gateway`
- `gateway_ref`
- `status` (`pending`, `succeeded`, `failed`, `retrying`)
- `attempt`
- `failover_from`
- `failed_reason`
- `processed_at`

Rows are unique by `payment_schedule_id + attempt`. Client-scoped RLS applies.
Failed attempts never mutate proposal signature state.

### `receipts`

Receipt artifacts generated for successful payments.

Key columns:

- `id` UUID primary key
- `client_id`
- `payment_id`
- `receipt_path`
- `receipt_sha256_envelope`
- `receipt_envelope_meta`
- `receipt_byte_size`
- `generated_at`

`payment_id` is unique so each succeeded payment receives at most one receipt.
Receipt PDFs are stored on `secure_local`; hashes are wrapped with
`KeyEnvelope`. Client-scoped RLS applies.

## WO-70 - Panel portal foundation

### `panel_members`

Shared broker/coach panel member record.

Key columns:

- `id` UUID primary key
- `user_id`
- `invite_token_id`
- `panel_type` (`broker`, `coach`)
- `status` (`invited`, `application_pending`, `approved_pending_agreement`, `active`, `suspended`)
- `application` JSONB
- `approved_by_user_id`, `applied_at`, `approved_at`, `suspended_at`

Panel members are visible to admins/advisors and to the owning panel user
through RLS.

### `panel_agreements`

Generated and signed panel agreement records.

Key columns:

- `id` UUID primary key
- `panel_member_id`
- `status` (`pending_signature`, `signed`)
- `terms` JSONB including no-fee mutual-referral terms
- `pdf_path`, `pdf_sha256_envelope`, `pdf_envelope_meta`, `pdf_byte_size`
- `signed_by_user_id`, `generated_at`, `signed_at`

Signed agreement artifacts are stored on `secure_local`; hashes are wrapped with
`KeyEnvelope`.

### `referrals`, `referral_messages`, `reverse_referrals`

Shared referral lifecycle and panel messaging primitives.

`referrals` key columns:

- `client_id`
- `panel_member_id`
- `panel_type`
- `referral_type`
- `stage` (`draft`, `sent`, `accepted`, `in_progress`, `completed`, `withdrawn`)
- `payload`, `created_by_user_id`, `sent_at`, `closed_at`

`referral_messages` store per-referral messages with `client_id`,
`sender_user_id`, `body`, and `sent_at`.

`reverse_referrals` store panel-originated prospect/entrepreneur referrals and
link to either `prospect_lead_id` or `entrepreneur_profile_id`. They never create
an invite token or platform access automatically.

Client/panel-user RLS applies to referrals and messages; panel-user RLS applies
to reverse referrals.

## WO-71 - Insurance Broker portal

WO-71 extends the shared panel tables instead of creating a separate broker
schema.

`panel_members` additions:

- `fsp_number`
- `fsp_status` (`current`, `lapsed`, `unknown`)
- `fsp_last_checked_at`

Broker approval now requires an FSP lookup through `FspClient`; non-current
registrations are rejected before a panel agreement can be issued. Active broker
members are rechecked by `panels:broker-fsp-reverify`; a lapsed/unknown registry
result sets `panel_members.status = suspended`, writes
`panel.broker_fsp_lapsed`, and notifies advisors/super admins.

`panel_agreements.terms` now includes broker-specific clauses when
`panel_type = broker`, including the FSP number/status at approval,
FSP-current obligation, automatic suspension on lapse, client-consent
requirements, and broker responsibility for regulated insurance advice.

Broker referrals keep using `referrals` but use the broker-stage vocabulary:
`draft`, `referral_sent`, `broker_acknowledged`, `quote_requested`,
`cover_placed`, `declined`, `no_response`, and `withdrawn`. The terminal broker
stages set `closed_at`.

## WO-72 - Coach portal

WO-72 extends the shared panel/referral tables and adds a client-scoped
authorisation ledger for key-staff coaching referrals.

`panel_members` additions:

- `coach_specialisations` JSONB containing one or more of `life`,
  `business_executive`, `mental_health_wellbeing`, `financial_wellness`, and
  `career`
- `coach_profile` JSONB
- `professional_memberships` JSONB, displayed where held
- `coach_vetting` JSONB
- `coach_vetted_by_user_id`, `coach_vetted_at`

`coach_referral_authorisations`:

- `id` UUID primary key
- `client_id`
- `authorised_by_user_id`
- `staff_name`, `staff_email`
- `purpose`, `payload`
- `granted_at`, `revoked_at`

The authorisation table uses standard client-scoped RLS. Key-staff coach
referrals require an active authorisation row for the same client.

`referrals` additions:

- `entrepreneur_profile_id` nullable for entrepreneur coach referrals
- `coach_specialisation`
- `referred_subject_type` (`owner`, `key_staff`, `entrepreneur`)
- `coach_referral_authorisation_id`

`referrals.client_id` and `referral_messages.client_id` are nullable so an
entrepreneur coach referral can exist without a client row. Coach referral
stages are `draft`, `referral_sent`, `coach_accepted`, `coaching_underway`,
`concluded`, `declined`, and `withdrawn`; terminal coach stages set `closed_at`.

## WO-73 - Coaching referral signal detection

`coach_referral_suggestions` stores advisor-review suggestions derived from raw
`coaching_signals`. No suggestion creates a referral automatically.

Key columns:

- `id` UUID primary key
- `coaching_signal_id` unique link to the raw signal
- `client_id`
- `suggested_specialisation`
- `threshold_ref`
- `rationale`
- `evidence` JSONB, including `advisor_final_decision_required = true` and
  `auto_referral = false`
- `status` (`suggested`, `reviewed`, `dismissed`)
- `surfaced_at`, `reviewed_by_user_id`, `reviewed_at`

The table uses advisor/client-team RLS. WO-73 also reserves learning
`layer_id = 17` for coach-referral signal calibration candidates in
`learning_updates`; candidates are `detected` only and never auto-implemented.

## WO-74 - Referral conflict and consent wiring

WO-74 extends existing tables:

`consents` additions:

- `proposal_id` is nullable so a referral-specific consent can exist outside a
  proposal sign-off flow
- `revoked_by_user_id`
- `revoked_at`

`referrals` additions:

- `conflict_declaration_id`
- `consent_id`

Broker and coach send transitions require both linked rows. The conflict must be
fresh, client-matched, advisor-matched, and typed as `broker_referral` or
`coach_referral`. The consent must be an active opt-in row of
`insurance_referral` or `coach_referral` for the same client. Revoking a consent
withdraws linked non-terminal referrals.

## WO-75 - DD onboarding

### `dd_engagements`

Buyer-scoped due diligence engagement record. The acquisition target is stored
separately from buyer `clients` data.

Key columns:

- `id` UUID primary key
- `client_id` buyer client
- `target_name`
- `target_details` JSONB, including `data_scope = acquisition_target_only`
- `status` (`in_progress`, `acquisition_proceeding`, `abandoned`)
- `recommendation` nullable (`proceed`, `renegotiate`, `abandon`)
- `conflict_declaration_id`
- `created_by_user_id`
- `disclaimer_acknowledged_at`

Client-scoped RLS applies. DD onboarding requires a fresh
`due_diligence` conflict declaration and the published `dd_specific`
questionnaire. The standard advisory questionnaire is deferred until the
post-acquisition gap flow.

## WO-76 - DD data room and guest upload

### `dd_guest_links`

Token-only upload grants for third parties. The plain token is returned only at
issue time; the database stores a SHA-256 hash.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `workstream`
- `folder`
- `token_hash`
- `guest_email`
- `max_uploads`, `upload_count`
- `created_by_user_id`
- `revoked_by_user_id`
- `expires_at`, `revoked_at`, `last_used_at`

Client-scoped RLS applies for advisors and system jobs. Guest uploads resolve
the hash through the `DataRoom` service and never receive a read/list route.

### `dd_data_room_items`

Workstream-scoped DD artifact ledger. Each row points to a secure `documents`
row with `category = dd_artifact`.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `document_id`
- `workstream`
- `folder`
- `artifact_type = dd_artifact`
- `source` (`guest_upload`, `advisor_upload`)
- `dd_guest_link_id`
- `guest_name`, `guest_email`
- `metadata`

Client-scoped RLS applies. Guest uploads create rows only after the token is
active, the file scan succeeds, and `SecureFileWriter` has persisted the
document metadata.

## WO-77 - DD workstreams

### `dd_workstreams`

Per-workstream DD analysis ledger. Each row links an acquisition workstream to
its analysis-spine run and stores the scoped DD evidence/check summary used for
that run.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `workstream` (`financial`, `valuation`, `legal`, `tax`, `commercial_market`, `operational`, `hr_people`, `nz_regulatory`)
- `status` (`pending`, `running`, `completed`, `paused`, `failed`)
- `analysis_run_id`
- `data_room_item_ids`
- `verification_weight`
- `nz_checks`
- `paused_reason`
- `ran_by_user_id`
- `ran_at`

Client-scoped RLS applies. The DD runner pauses only the affected workstream
when unresolved accuracy discrepancies appear in that workstream's data-room
documents. Completed workstreams create `analysis_runs.module = dd_workstream`
findings with attribution and double-weighted verified document support.

## WO-78 - DD valuation and FX normalisation

### `dd_valuations`

DD-specific valuation wrapper over the Phase 2 `business_valuations` and
`pv_calculations` rows.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `business_valuation_id`
- `pv_calculation_id`
- `source_currency`
- `normalised_currency = NZD`
- `exchange_rate_id`
- `source_to_nzd_rate`
- `rate_timestamp`
- `normalised_values`
- `sensitivity`
- `buyer_position`
- `source_attributions`
- `as_at`

Client-scoped RLS applies. DD valuation forces target financial inputs so buyer
accounting snapshots cannot contaminate acquisition-target valuation. Non-NZD
valuations require the latest RBNZ `exchange_rates` row and store +/-10% FX
sensitivity around the source-to-NZD rate.

## WO-79 - DD business plan builder

### `business_plans`

Shared business-plan header for DD-built founding plans and future entrepreneur
plans.

Key columns:

- `id` UUID primary key
- `client_id` nullable buyer client for DD-owned plans
- `entrepreneur_profile_id` nullable owner for entrepreneur-owned plans
- `dd_engagement_id` nullable owner for DD-built plans
- `title`
- `source_type` (`due_diligence` or `entrepreneur`)
- `status` (`draft`, `ready`, `founding` for the DD path)
- `current_phase`
- `founding_advisory_payload`
- `created_by_user_id`
- `completed_at`

PostgreSQL enforces `business_plans_owner_xor`, so exactly one of
`entrepreneur_profile_id` or `dd_engagement_id` is set. DD-owned plans are
buyer-client scoped through `client_id`; entrepreneur-owned plans scope through
the assigned advisor or linked entrepreneur user.

### `plan_phases`

Ordered five-phase structure for each business plan.

Key columns:

- `id` UUID primary key
- `business_plan_id`
- `key` (`foundation`, `market`, `strategy`, `legal_operations`, `financial`)
- `title`
- `position`
- `depends_on`
- `status` (`pending`, `complete`)

Rows are unique by `business_plan_id + key` and scope through the parent plan.

### `plan_sections`

Persisted plan content sections, including DD auto-populated sections.

Key columns:

- `id` UUID primary key
- `business_plan_id`
- `plan_phase_id`
- `key`
- `title`
- `body`
- `source_type`
- `source_analysis_finding_id`
- `completeness_status` (`draft`, `complete`)
- `metadata`

Rows are unique by `business_plan_id + key` and scope through the parent plan.
DD sections can link directly back to `analysis_findings` so the founding plan
retains source attribution from the workstream spine.

## WO-80 - DD report generation

### `dd_risk_register`

Buyer-client-scoped risk register rebuilt when the DD report is generated.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `analysis_finding_id`
- `risk_cost_id`
- `risk_level` (`deal_killer`, `major`, `minor`, `informational`)
- `category` workstream key
- `title`, `body`
- `financial_impact`
- `probability`
- `pv_of_cost`
- `price_adjustment_nzd`
- `rank`
- `status`
- `source_attributions`

Rows are ranked by `pv_of_cost` from the shared risk-cost PV engine. The DD
report uses the ranked rows for the risk register, price-adjustment schedule,
recommendation path, and 100-day integration actions. Client-scoped RLS applies.

### `dd_integration_plans`

100-day acquisition integration action ledger generated from the DD risk
register.

Key columns:

- `id` UUID primary key
- `client_id`
- `dd_engagement_id`
- `dd_risk_register_id`
- `day`
- `phase`
- `action`
- `owner`
- `priority`
- `status`
- `metadata`

Rows are ordered by day and always include a day-100 review action. Client-scoped
RLS applies.

## WO-81 - Post-acquisition advisory pipeline

### `post_acquisition_migrations`

Handoff ledger for converting a proceeding DD acquisition into its own advisory
client.

Key columns:

- `id` UUID primary key
- `dd_engagement_id`
- `buyer_client_id`
- `advisory_client_id`
- `business_plan_id`
- `dd_report_id`
- `gap_questionnaire_response_id`
- `proposal_id`
- `migrated_document_ids`
- `dd_pv_baseline`
- `status`
- `metadata`
- `migrated_by_user_id`
- `migrated_at`

`dd_engagement_id` is unique so conversion is idempotent. RLS allows access
through either the buyer client or the new advisory client.

### `post_acquisition_gap` questionnaire

WO-81 seeds version 1 of the `post_acquisition_gap` questionnaire. Conversion
creates an unsubmitted `questionnaire_responses` row for the new advisory client,
prefills DD-known answers, and records remaining question IDs on
`post_acquisition_migrations.metadata.gap_questions_remaining`.

### `documents`

Migrated DD documents are copied into new advisory-client `documents` rows. The
new rows use a `Sourced from DD - ...` filename prefix and keep the source DD
document id, source stored path, DD engagement id, and workstream in
`scanner_payload`.

### `fee_calculations` / `proposals`

The auto-generated draft proposal uses an outcome-based `fee_calculations` row
with `inputs.source = due_diligence`, the DD report id, the latest DD valuation
midpoint as `dd_pv_baseline`, and the DD risk-register PV total. `ProposalBuilder`
then renders the normal proposal PDF for the new advisory client.

## WO-82 - Entrepreneur readiness assessment

### `entrepreneur_profiles`

WO-82 retrofits row-level security onto the Phase 1 entrepreneur profile table.
Visibility is granted to `super_admin`/`system`, the assigned advisor, or the
linked entrepreneur user through `fsa_current_user_id()`.

### `readiness_assessments`

Stores a readiness assessment for an entrepreneur profile.

Key columns:

- `id` UUID primary key
- `entrepreneur_profile_id`
- `responses`
- `score`
- `outcome` (`ready`, `develop_first`, `not_yet`)
- `personal_barriers`
- `assessed_by_user_id`
- `assessed_at`

RLS joins through `entrepreneur_profiles`, so an unassigned advisor and a
different entrepreneur user cannot read the row.

### `coaching_signals`

WO-82 adds nullable `entrepreneur_profile_id` support so Develop First readiness
outcomes with personal barriers can store a raw coaching observation without
creating a client-scoped signal. These rows retain `auto_referral = false`.

### `entrepreneur_readiness` questionnaire

The published version 1 questionnaire contains 16 readiness questions covering
concept, customer, demand, founder capacity, resilience, runway, feedback
readiness, support, and launch concerns.

## WO-83 - Entrepreneur idea validation

### `idea_validations`

Stores the validated concept capture before the plan builder opens.

Key columns:

- `id` UUID primary key
- `entrepreneur_profile_id`
- `problem`
- `target_customer`
- `solution`
- `value_proposition`
- `demand_signal`
- `revenue_model`
- `ai_evaluation`
- `viability_alerts`
- `evaluated_at`
- `evaluated_by_user_id`
- `advisor_gate_passed_at`
- `advisor_gate_passed_by_user_id`
- `advisor_gate_note`

`ai_evaluation` stores the AI model, prompt hash, uncertainty, attributions, and
aggregate past-plan pattern context. Viability alerts are informational and do
not unlock the plan builder. The advisor gate unlocks the builder only when an
advisor records a note. RLS joins through `entrepreneur_profiles`.

## WO-84 - Entrepreneur five-phase plan builder

### `business_plans`

WO-84 uses the existing owner-XOR plan table for entrepreneur-owned plans:

- `entrepreneur_profile_id` is set
- `dd_engagement_id` is null
- `source_type = entrepreneur`
- `status = building`

The existing entrepreneur-profile RLS path scopes entrepreneur-owned plans to the
assigned advisor, linked entrepreneur user, or super-admin/system context.

### `plan_phases`

The shared five-phase dependency graph is persisted per plan:

- `foundation` has no dependencies
- `market` depends on `foundation`
- `strategy` depends on `foundation` and `market`
- `legal_operations` depends on `foundation`
- `financial` depends on `foundation` and `strategy`

### `plan_sections`

WO-84 adds:

- `attached_document_ids`
- `predictive_score`

The entrepreneur plan adapter stores `metadata.dependency_warning` when a
founder drafts a later phase before its dependencies are complete.

## WO-85 - Entrepreneur guidance and NZ resources

### `nz_resources`

Admin-managed catalogue of New Zealand resources recommended by industry,
business type, and gap tag.

Key columns:

- `id` UUID primary key
- `industry`
- `business_type`
- `title`
- `url`
- `gap_tags`
- `metadata`
- `active`

### `plan_sections`

WO-85 uses the WO-84 `predictive_score` column and stores full guidance payloads
under `metadata.ai_guidance`. The predictive payload includes the numeric score,
band, gap tags, reasons, and `no_flattery = true`.

## WO-86 - Entrepreneur plan document verification

### `document_verifications`

WO-86 adds:

- `entrepreneur_profile_id`
- `plan_section_id`

This lets plan-section document verifications exist without a client row while
still using the shared verification ledger, outcomes, and blocking semantics.

### `documents` / `document_verifications` RLS

Both policies now include entrepreneur-profile visibility in addition to the
existing client scope. Super-admin/system can see all rows; assigned advisors
and linked entrepreneur users can see rows for their profile.

### `plan_sections`

`attached_document_ids` stores the verified section attachment ids. Outstanding
advisory flags or accuracy discrepancies on those documents block scoring until
resolved.

## WO-87a - Rating framework engine

### `rating_frameworks`

Global admin-managed entrepreneur assessment framework versions.

Key columns:

- `id` UUID primary key
- `version`
- `status` (`draft`, `published`)
- `industry_variant`
- `production_ready`
- `grade_bands`
- `supersedes_framework_id`
- `published_at`
- `published_by_user_id`
- `created_by_user_id`

### `rating_criteria`

Criteria attached to a framework version.

Key columns:

- `id` UUID primary key
- `rating_framework_id`
- `number`
- `name`
- `weight`
- `descriptors`
- `industry_variants`
- `is_placeholder`

WO-87a seeds the 11 spec criteria as placeholders and leaves the framework not
production-ready. Admin edits create new framework versions. Suggested
learning-driven changes are queued in `learning_updates` with
`automatic_application = false`.

## WO-87b - Founding rating values

WO-87b publishes version 2 of the default rating framework with owner-confirmed
weights and descriptors. Version 1 remains as the placeholder seed.

The production-ready framework has:

- all 11 criteria present
- weights totalling 100
- descriptors for Exceptional, Strong, Developing, and Needs Work
- `rating_criteria.is_placeholder = false`
- `rating_frameworks.production_ready = true`

## WO-88 - Plan assessments

### `plan_assessments`

Stores entrepreneur plan assessment rounds.

Key columns:

- `id` UUID primary key
- `business_plan_id`
- `round`
- `rating_framework_id`
- `ai_scores`
- `advisor_scores`
- `mentor_notes`
- `document_support`
- `overall_grade`
- `concept_pv_calculation_id`
- `finalised_at`
- `finalised_by_user_id`

RLS joins through the entrepreneur-owned `business_plans` row and its
`entrepreneur_profiles` owner. Advisor score adjustments feed governed
`learning_updates` candidates and never auto-apply scoring changes.

## WO-89 - Entrepreneur assessment reports

WO-89 extends the shared report and PV tables for entrepreneur-owned assessment
outputs.

### `reports` / `report_sections`

New nullable owner column:

- `entrepreneur_profile_id`

For entrepreneur reports, `client_id` may be null and
`entrepreneur_profile_id` scopes the row. PostgreSQL constraints require at
least one subject owner (`client_id` or `entrepreneur_profile_id`) to be
present. RLS allows system/super-admin access, client-team access for
client-scoped reports, and assigned-advisor / entrepreneur-user access for
entrepreneur-scoped reports.

`reports.type = entrepreneur_assessment` stores the assessment report metadata:

- `business_plan_id`
- `plan_assessment_id`
- `assessment_round`
- `rating_framework_id`
- `overall_grade`
- `weighted_score`
- `concept_pv_calculation_id`
- `concept_pv_present_value`

`report_sections` stores four ordered sections: criterion score notation,
criterion feedback, overall grade plus concept PV, and prioritised improvement
actions.

### `pv_calculations`

New nullable owner column:

- `entrepreneur_profile_id`

WO-89 adds `type = entrepreneur_concept_projection` for draft-stage concept PV
projections. These rows use advisor-configured conservative discount rates,
persist the projected cash-flow assumptions in `inputs`, store discounted rows
in `result`, and are linked from `plan_assessments.concept_pv_calculation_id`.

## WO-90 - Plan revisions

### `plan_revisions`

Stores unlimited entrepreneur plan resubmission rounds and the round-by-round
progress comparison.

Key columns:

- `id` UUID primary key
- `business_plan_id`
- `round`
- `submitted_at`
- `progress_comparison`
- `submitted_by_user_id`

`progress_comparison` contains previous/current rounds, previous/current
weighted scores, previous/current grade, overall delta, trajectory percent,
per-criterion deltas, biggest improvements, and remaining gaps.

RLS joins through `business_plans` and the owning `entrepreneur_profiles` row.
System and super-admin roles can read/write all rows; assigned advisors and the
linked entrepreneur user can read their own revision rows.

## WO-91 - Benchmarking, readiness, and living plan

### `advisory_readiness_signals`

Systematic signal when an entrepreneur nears advisory readiness.

Key columns:

- `id` UUID primary key
- `entrepreneur_profile_id`
- `business_plan_id`
- `plan_assessment_id`
- `score`
- `surfaced_at`
- `advisor_notified_at`

`entrepreneur_profile_id` is unique so a profile has one current readiness
signal. RLS joins to `entrepreneur_profiles` and allows system/super-admin,
assigned-advisor, and linked-entrepreneur visibility.

### `business_plans`

WO-91 adds living-plan cadence fields:

- `living_plan_next_update_at`
- `living_plan_last_prompted_at`
- `living_plan_last_assessed_at`
- `living_plan_divergence_flags`

Launched entrepreneur plans use these timestamps for quarterly update prompts
and re-assessment cadence. Divergence flags record whether the latest living
plan re-assessment regressed materially or still has criteria below 60.

## WO-57 - Report engine

### `reports`

Client-scoped report headers for generated Client, Advisor, and later report
types.

Key columns:

- `id` UUID primary key
- `client_id`
- `type` (`client`, `advisor`, `stakeholder`, `trajectory`, `due_diligence`, `entrepreneur_assessment`)
- `title`
- `pdf_path`, `pdf_byte_size`
- `pptx_path`, `pptx_byte_size` (WO-58 stakeholder export)
- `generated_by_user_id`, `generated_at`
- `metadata` JSONB with redaction and scaffold notes
- `review_status`, `reviewed_by_user_id`, `reviewed_at` (WO-59 trajectory review gate)

Client-scoped RLS applies. WO-57 composes only `client` and `advisor`; other
types are enum scaffolds for later work.

### `report_sections`

Ordered report body sections with the integrity notation required by spec
section 19.

Key columns:

- `id` UUID primary key
- `report_id`
- `client_id`
- `key`
- `title`
- `body`
- `position`
- `lens` nullable analysis lens
- `attributions` JSONB
- `document_support`
- `document_support_note`
- `data_quality_note`
- `metadata` JSONB

Every section carries source attribution, document-support notation, and a
data-quality note. Client reports exclude prescriptive findings and fee/proposal
sections; advisor reports include the full finding set, PV waterfall,
implementation plan, and fee proposal ROI.

## WO-58 - Stakeholder report + PowerPoint export

WO-58 reuses the `reports` and `report_sections` tables from WO-57.

Stakeholder reports set `reports.type = stakeholder`, store PDF and PPTX
artifacts on `secure_local`, and record `metadata.redactions` with
`fsa_methodology` and `fsa_ip`. The dedicated liability-disclaimer section is
stored in `report_sections` and is included in both PDF and PowerPoint exports.

## WO-59 - Business health trajectory report

WO-59 reuses `reports` and `report_sections`. Trajectory reports set
`reports.type = trajectory` and `review_status = pending_review` until an
advisor marks the report reviewed. `report_sections` stores start-to-current
metric trends, ordered PV milestones, and the generated trajectory narrative
with `metadata.advisor_review_required = true`.

## WO-60 - Industry briefings and pre-meeting briefs

### `meetings`

Minimal advisor-entered meeting records used by Phase 2 pre-meeting briefs.

Key columns:

- `id` UUID primary key
- `client_id`
- `title`
- `scheduled_at`
- `location`, `link`
- `attendees` JSONB
- `created_by_user_id`
- `external_ref` nullable placeholder for future calendar sync

Client-scoped RLS applies.

### `industry_briefings`

Monthly draft briefings held for advisor review before client notification.

Key columns:

- `id` UUID primary key
- `client_id`
- `period` month start date
- `body`
- `sources` JSONB with NZ source attributions
- `status` (`draft`, `sent`)
- `created_by_user_id`, `reviewed_by_user_id`, `reviewed_at`, `sent_at`

Unique on `client_id + period`; client-scoped RLS applies.

### `pre_meeting_briefs`

Generated one-page briefing records for meetings around 24 hours away.

Key columns:

- `id` UUID primary key
- `meeting_id` unique
- `client_id`
- `meeting_at`
- `body`
- `red_flag_ids` JSONB
- `generated_at`
- `reviewed_by_user_id`, `reviewed_at`, `sent_at`

The unique `meeting_id` enforces no duplicate brief generation for the same
meeting. Client-scoped RLS applies.

## WO-61 - Funnel analytics

### `funnel_events`

Entry/completion/abandonment ledger for multi-step app flows.

Key columns:

- `id` UUID primary key
- `flow` (`onboarding`, `questionnaire`, `proposal`, ...)
- `step`
- `client_id` nullable
- `user_id` nullable
- `entered_at`
- `completed_at`
- `abandoned`

Client-scoped RLS applies when `client_id` is present. User-only rows are
visible to the matching user; system and super-admin roles can see all rows.
The WO-61 governed learning layer writes UX-improvement candidates into
`learning_updates` with `layer_id = 15` and `status = detected`.

## WO-62 - Practice health report

### `practice_health_snapshots`

Monthly cached portfolio health reports for the whole practice or for one
advisor's active-client portfolio.

Key columns:

- `id` UUID primary key
- `scope` (`super_admin` or `advisor`)
- `advisor_user_id` nullable user id for advisor-scoped snapshots
- `client_ids` JSONB list of client IDs represented by the snapshot
- `metrics` JSONB payload from `PracticeHealthReport`
- `generated_at`

System and super-admin roles can see all rows. Advisor rows are visible only
when `advisor_user_id::text = fsa_current_user_id()`. The metrics payload
contains active-client count, current PV, improvement PV, risk-mitigation PV,
target PV, revenue under management, proposal/report/red-flag counts, and
funnel summary signals.

## WO-63 - Advisor dashboard Phase 2 panels

WO-63 does not add tables. It reuses:

- `proposals` for status counts and 14-day expiry alerts
- `economic_indicators`, `exchange_rates`, and `learning_updates` for economic
  tiles and change alerts
- `red_flags` for open critical alerts
- `practice_health_snapshots` and `PracticeHealthReport` payloads for the
  practice-health summary
- `learning_layer_runs` and `learning_updates` for questionnaire optimisation
  candidates

The questionnaire optimisation layer uses `layer_id = 16` and writes governed
candidate rows with `source.type = questionnaire_optimisation_layer` and
`status = detected`. It deliberately creates no
`learning_update_implementations` in Phase 2.

## WO-64 - Wellbeing monthly pulse and analytics

WO-64 reuses the Phase 1 `wellbeing_checkins` and `coaching_signals` tables.

Advisor dashboard analytics read `wellbeing_checkins` over the recent six-month
window and expose aggregate check-in counts, average business confidence,
average personal coping, low-coping counts, and current-period completion rate.
The payload contains no client notes.

The raw two-month coping observation is stored in `coaching_signals` with:

- `signal_type = low_personal_coping_streak`
- `status = detected`
- `severity = advisor_attention`
- `evidence.rule = two_consecutive_months_personal_coping_lte_2`
- `evidence.auto_referral = false`
- `evidence.phase_2_boundary = raw_internal_observation_only`

The detector suppresses duplicate rows for an ongoing low-coping streak. Phase 2
does not consume these signals for coach referral, calibration, or automation.

## WO-65 - Goals and milestones tracker

### `goals`

Client-scoped advisory goals with optional PV target linkage.

Key columns:

- `id` UUID primary key
- `client_id`
- `title`, `description`
- `pv_target_calculation_id` nullable link to `pv_calculations`
- `pv_target`
- `status` (`active`, `achieved`, `abandoned`)
- `created_by_user_id`

### `milestones`

Goal milestones linked to recommendations and PV of impact.

Key columns:

- `id` UUID primary key
- `goal_id`
- `client_id`
- `title`
- `recommendation_ref`
- `pv_of_impact_calculation_id` nullable link to `pv_calculations`
- `pv_of_impact`
- `due_date`
- `status` (`pending`, `in_progress`, `completed`, `blocked`)
- `completed_at`

### `milestone_actions`

Advisor action list for each milestone.

Key columns:

- `id` UUID primary key
- `milestone_id`
- `client_id`
- `title`
- `owner_user_id`
- `due_date`
- `priority`
- `status`

### `proof_of_completion`

Proof ledger tying a milestone completion attempt to an uploaded document and
the existing document-verification result.

Key columns:

- `id` UUID primary key
- `milestone_id`
- `client_id`
- `document_id`
- `document_verification_id`
- `status` (`pending`, `verified`, `flagged`)
- `reviewed_at`

All four tables use the standard client-scoped RLS policy. Completed milestones
are the source of the dashboard PV-realised total; verification outcomes that
block analysis keep the milestone in `blocked` status and exclude its PV from
realised totals.
