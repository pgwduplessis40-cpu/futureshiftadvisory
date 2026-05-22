# Advisor Dashboard Phase 2 Panels

WO-63 completes the Phase 2 advisor dashboard surface by combining existing
live signals with the proposal and questionnaire-learning layers.

## Panels

- Proposal status: counts proposals by Phase 2 reachable status and lists
  released proposals expiring within 14 days. Data is scoped through the same
  visible-client resolver as the rest of the advisor dashboard.
- Economic indicators: continues to surface the live/fallback NZ indicator feed
  and governed change alerts from WO-36.
- Red flags: continues to surface open AI red flags from WO-34.
- Practice health: uses the WO-62 active-client PV and revenue portfolio payload.
- Questionnaire optimisation: displays detected governed candidates from the
  questionnaire optimisation learning layer.

Phase 3-only panels remain placeholders: payments, broker referrals, coach
referrals, and the full learning queue UI.

## Questionnaire Optimisation Layer

`QuestionnaireOptimisationLayer` is layer `16`. The quarterly scheduled command
`questionnaires:optimisation-learning` scans submitted questionnaire responses
for questions whose blank or omitted response rate crosses the configured
threshold.

The layer writes `learning_updates` in `detected` status with
`automatic_application = false`. It also records a `learning_layer_runs` row and
an immutable audit event. No questionnaire version, prompt, or condition is
changed automatically in Phase 2.
