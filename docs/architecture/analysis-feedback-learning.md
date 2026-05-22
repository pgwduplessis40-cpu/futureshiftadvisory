# Analysis feedback learning loop

WO-32 activates advisor feedback capture for Phase 2 analysis findings and connects systematic correction patterns to the governed learning queue.

## Feedback capture

Every persisted `analysis_findings` row can receive advisor feedback through the advisor route:

`POST /advisor/analysis-findings/{analysisFinding}/feedback`

The route is internal-advisor scoped, authorizes the parent client, and records one of four decisions:

- `confirm`
- `correct`
- `rate`
- `add_context`

`FeedbackRecorder` is the single write path. It persists `analysis_feedback` and writes `analysis_feedback.recorded` to the immutable audit trail with the finding, run, client, module, lens, decision, and whether a correction or note was supplied.

## Advisor surface

The advisor client detail page now includes recent analysis findings. Each finding card shows its module, lens, severity, attribution badges, document-support status, uncertainty, data-quality disclaimer, and latest feedback. The same card provides confirm, rating, correction, and context controls.

Future analysis modules only need to persist findings through the WO-31 spine; the feedback controls appear from the shared finding list.

## Learning layer

The feedback learning layer is implemented by `FeedbackLearningLayer` and exposed as:

`php artisan analysis:feedback-learning`

The scheduled cadence runs daily at 03:00. The command scans a rolling 30-day window and groups correction feedback by analysis module.

When corrections for a module meet the threshold, the layer creates one `learning_updates` row in `detected` status. The candidate proposes human review of the module prompt or finding mapping. It is never auto-applied, and no `learning_update_implementations` row is created by this layer.

The layer is idempotent while a detected feedback candidate already exists for the module, so repeated scheduled runs do not spam duplicates.

## Run ledger

Each command execution writes `learning_layer_runs` with:

- layer id
- run timestamp
- candidate count
- window start/end/days
- threshold
- status

The row is observability only; proposed behaviour changes remain in `learning_updates`.
