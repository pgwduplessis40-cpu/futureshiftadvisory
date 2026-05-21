# WO-12 notifications and communication preferences

WO-12 introduces the central notification routing primitive. The notification bell UI is still WO-24; this work creates the delivery rules, durable ledger, and digest jobs.

## Preference model

Each user has one `communication_preferences` row:

- `channel`: `email_only`, `in_platform_only`, or `both`
- `frequency`: `immediate`, `daily`, or `weekly`
- `timezone`: stored now for future user-local digest scheduling

Missing preferences default to `both` + `immediate`.

## Channel decisions

Application notifications that should honour these preferences extend `App\Notifications\ChannelAwareNotification`. Its `via()` method delegates to `ChannelResolver`.

Every routed notification gets a durable row through the custom `fsa_database` notification channel. That row records:

- `urgency`
- `channel_decision`
- the notification payload in `data`

The database row is the Phase 1 audit ledger and the future WO-24 in-platform source. Email delivery is added only when the decision says to send immediately.

Urgent notifications bypass preference and frequency settings. They always route to `fsa_database` and `mail`.

## Digests

For non-urgent notifications where the user wants email but selected `daily` or `weekly`, the resolver writes a database row with `channel_decision.email_deferred = true` and does not send immediate mail.

Scheduled jobs:

- `DispatchDailyDigest`
- `DispatchWeeklyDigest`

Both call `DigestDispatcher`, which groups pending rows by user, sends a single `NotificationDigestMail`, then marks each row's `channel_decision.digest_sent_at`. Rows are marked only after the mail send call completes, so a failed send remains pending for the next run.

## Current integrations

`TermsDeclinedUrgentNotification` now extends `ChannelAwareNotification`, so terms-decline alerts use the central resolver and still bypass preferences as urgent.
