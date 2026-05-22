# Funnel analytics

WO-61 adds the Phase 2 funnel analytics ledger and governed UX-improvement
candidate layer.

## Event Ledger

`funnel_events` records entry, completion, and abandonment for multi-step flows:

- `onboarding`
- `questionnaire`
- `proposal`

Each event can be linked to a client and user. Advisor dashboard queries are
scoped to the advisor's visible client IDs; super-admins see the full portfolio.

## Capture Points

- The portal onboarding controller records step entry on view and completion on
  successful save.
- Questionnaire submission records completion of the questionnaire submit step.
- Proposal generation and release record proposal-flow completion.

`FunnelTracker` also exposes `abandonStaleEntries`, used by the scheduled layer
to mark old open entries as abandoned.

## Governed Suggestions

`analytics:funnel-learning` runs the monthly governed-learning layer. It marks
stale entries abandoned, computes the highest drop-off step, and queues a
`learning_updates` row in `detected` status. The candidate is advisory only:
there is no automatic UX change and no Phase 2 approval UI.

Layer ID: `15`.
