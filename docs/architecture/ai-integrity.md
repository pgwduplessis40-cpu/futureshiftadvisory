# AI integrity foundation

WO-04 establishes the single AI exit path and the integrity checks every AI output must pass before application code can use it.

## Contract

All AI call sites resolve `App\Services\Ai\Contracts\AiClient`. The app never constructs Anthropic requests directly outside `App\Services\Ai\Claude\AnthropicClaudeClient`.

`AiClient` accepts a `PromptEnvelope` and returns an `AiResponse` with:

- `text`
- `attributions[]` as `{ claim, source_reference }`
- `uncertainty` as `high`, `medium`, `low`, or `none`
- `bias_signals[]`
- `model`, `prompt_version`, `prompt_hash`, `tokens_in`, `tokens_out`

## Runtime flow

1. `AiServiceProvider` binds `AiClient` to `IntegrityCheckedAiClient`.
2. `FallbackAiClient` uses Anthropic when configured and falls back to `FakeAiClient` when the key is absent or the live call is unavailable.
3. `SourceAttribution` rejects any response with text but no source attribution.
4. `BiasDetector` logs every inspected output and creates a governed `learning_updates` candidate when Phase 1 heuristics flag wording for review.
5. `AdvisorAiNotice` records degraded-mode notices in cache and audit; a future notification-specific pass can route them through the WO-12 resolver.

## Degraded mode

When `ANTHROPIC_API_KEY` is empty, every AI method returns:

`AI unavailable — analysis deferred`

The response carries `uncertainty=high`, model `fake-ai-client`, a deterministic prompt hash, and the source reference `system:degraded-mode`.

## Learning governance

WO-04 creates `learning_updates` and `learning_update_implementations` as scaffolding only. No self-modifying behaviour exists in Phase 1. Detected bias is stored as a candidate with status `detected`; later phases add approval UI and implementation/rollback workflows.
