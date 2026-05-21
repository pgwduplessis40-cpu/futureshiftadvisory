# Analysis spine

WO-31 introduces the shared Phase 2 analysis pipeline. Every analysis module is an adapter into this spine instead of owning its own AI, data-quality, document-verification, or attribution rules.

## Storage contract

`analysis_runs` is the run ledger. It records the client, module, status, framework lenses produced, data-quality snapshot, prompt/version/hash, AI model, token counts, timing, and initiating user.

`analysis_findings` stores governed output from a run. Every row carries its analytical lens, severity, prose, claim/source attributions, document-support state, uncertainty, data-quality disclaimer, bias signals, and optional future PV link.

`analysis_feedback` stores advisor confirmations, corrections, ratings, and extra context against individual findings. WO-31 creates the ledger and RLS policy; WO-32 adds the advisor-facing capture flow and learning-update emission.

## Module contract

Analysis modules implement `App\Services\Analysis\Contracts\AnalysisModule` and provide:

- the module enum value
- the registered prompt id
- prompt input built from the client and current data-quality score
- source references available to the prompt
- mapping from the AI response to `AnalysisFindingData` rows

Modules do not call `AiClient` directly. They return structured inputs and mapped findings; `AnalysisRunner` owns the cross-cutting rules.

## Runtime flow

1. Score the client with `DataQualityScorer`.
2. Create an `analysis_runs` row in `running` state with the data-quality snapshot.
3. If data quality is insufficient, still check document verification first so unresolved document flags surface as `blocked_documents`; otherwise return `blocked_data_quality`.
4. Block on `DocumentVerificationGate` for unresolved `advisory_flag` or `accuracy_discrepancy` rows.
5. Build a prompt envelope through `PromptRegistry`.
6. Call `AiClient::analyse`.
7. Re-validate source attribution before any finding is persisted.
8. Map module findings and persist only findings with complete claim/source attributions.
9. Complete the run with model, prompt, token, and framework-lens metadata.

## Integrity outcomes

Missing attribution on the AI response fails the run and records `analysis.integrity_violation`.

Missing attribution on a mapped finding drops only that finding, records `analysis.finding_dropped_missing_attribution`, and keeps unattributed text out of user-facing storage.

Medium and low data quality add a disclaimer to each persisted finding. Insufficient data blocks the run before AI is called.

Outstanding document flags block the run before AI is called. This preserves the WO-18 rule that both advisory flags and accuracy discrepancies pause analysis output until resolved.

## RLS

`analysis_runs` and `analysis_findings` are scoped by `client_id`.

`analysis_feedback` is scoped through its parent finding. A user can only see feedback for findings visible under their current client scope.

Tests exercise the run, finding, and feedback policies under a non-bypass Postgres role when the local connection user would otherwise bypass RLS.
