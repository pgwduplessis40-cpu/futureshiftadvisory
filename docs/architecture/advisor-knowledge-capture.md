# Advisor knowledge capture

WO-123 completes the AI-assisted half of the advisor Knowledge Base without
adding a global learning layer. Knowledge entries are personal to an advisor, so
capture uses an advisor-owned draft queue rather than the admin learning-update
approval flow.

## Trigger

`OffboardingService` is the concrete engagement-completion path in the current
app, so it calls `KnowledgeCaptureService::captureFromOffboarding()` after the
offboarding record, lifecycle transition, and client notification are complete.
The client detail page also exposes a manual action that reruns capture for the
latest completed offboarding record.

Capture is idempotent per `(author_user_id, source_type, source_id)`. Re-running
capture updates an existing pending draft and leaves accepted or discarded drafts
unchanged.

## Draft Governance

Drafts live in `knowledge_entry_drafts` with source provenance, AI attribution,
and a `pending`, `accepted`, or `discarded` state. They are scoped to the owning
advisor and are not queried by the live `knowledge_entries` index.

The advisor reviews a draft in the Knowledge UI, edits the normal entry fields,
then accepts or discards it. Accept creates a new live `knowledge_entries` row
owned by the advisor and marks the draft accepted with `accepted_entry_id`.
Discard marks the draft discarded and creates no live entry.

## Boundaries

- No `LayerCadenceRegistry` entry is added for this feature.
- No `learning_updates`, `ApprovalFlow`, or `LearningUpdateImplementation` rows
  are written for knowledge capture.
- Audit payloads record identifiers and provenance only; source notes and client
  names are not copied into draft-created audit events.
