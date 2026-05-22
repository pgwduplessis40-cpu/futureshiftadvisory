# Wellbeing Monthly Pulse and Analytics

WO-64 extends the Phase 1 wellbeing primitive into the Phase 2 analytics
surface while keeping the coaching boundary intact.

## Monthly Pulse

`wellbeing:send-prompts` remains the monthly scheduler command. It finds client
portal users who have not submitted a pulse for the current month and sends the
optional check-in prompt through the channel-aware notification path.

## Advisor Analytics

`WellbeingTrendAnalytics` aggregates advisor-visible wellbeing data without
exposing it to non-advisor users:

- total check-ins and distinct clients in the six-month window
- average business confidence and personal coping
- low personal-coping check-in count
- current-period completion rate across client portal users
- active raw low-coping observation rows

The advisor dashboard receives these metrics as `wellbeingAnalytics`; client
detail still shows the per-client trend only to the lead advisor or super-admin.

## Coaching Boundary

The fixed Phase 2 rule is a raw observation only: two consecutive monthly
`personal_coping <= 2` scores create one `coaching_signals` row with
`signal_type = low_personal_coping_streak` and
`evidence.auto_referral = false`.

The detector suppresses duplicates for a continuing low-coping streak. Phase 2
does not classify, calibrate, notify a coach, generate a referral, or consume
the row. Phase 3 owns coach signal calibration and referral workflows.
