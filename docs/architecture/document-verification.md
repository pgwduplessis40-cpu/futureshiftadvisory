# Document upload and verification

WO-18 connects secure uploads, questionnaire evidence, and AI verification outcomes.

## Upload path

Client portal uploads post to `/portal/documents`. The controller only writes through `SecureFileWriter`, so uploaded bytes are scanned before encrypted persistence on `secure_local`.

Clean documents dispatch `VerifyDocumentJob`. Scanner errors still follow the WO-06 quarantine path and are not verified or shown to clients.

## Verification records

`document_verifications` stores one row per document claim context:

- `document_id` and `client_id` keep the row scoped to the encrypted upload and client.
- `questionnaire_question_id`, `questionnaire_answer_id`, and `questionnaire_response_id` link verification back to WO-17 answers when available.
- `context_hash` deduplicates repeated job runs for the same document, question, and claim text.
- `outcome` is one of `pending`, `verified`, `advisory_flag`, `accuracy_discrepancy`, or `verification_error`.
- `resolved_at`, `resolved_by_user_id`, and `resolution_note` provide the Phase 1 advisor resolution hook.

The table has a client-scoped RLS policy. Queue jobs apply the `system` request context before loading documents so async verification can run outside an HTTP request.

## AI boundary

`DocumentVerifier` builds the `document.verify` prompt through `PromptRegistry` and calls `AiClient::verifyDocument`. The prompt includes the claim, question prompt, document metadata, SHA-256, and a text excerpt when the secure file can be safely treated as UTF-8 text.

The fake AI client returns deterministic verification outcomes for tests:

- normal claims return `verified`
- advisory language returns `advisory_flag`
- discrepancy language returns `accuracy_discrepancy`

Live AI responses must provide the same outcome in `metadata.verification_outcome`.

## User surfaces

The portal dashboard lists uploaded document tiles with the current verification state and client-facing explanation.

The advisor dashboard receives a `DocumentVerificationFlagPanel` prop that lists unresolved `advisory_flag` and `accuracy_discrepancy` rows. Accuracy discrepancies also create urgent notifications through the WO-12 channel-aware notification pipeline.

## Analysis gate

Phase 2 analysis code must call `DocumentVerificationGate::ensureClear($client)` before rendering client-facing output. The gate blocks whenever unresolved advisory or discrepancy rows exist for the client.
