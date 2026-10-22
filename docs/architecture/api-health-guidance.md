# API Health diagnostics

API Health separates availability from response time and shows a reason and next action beside each observation. The five-minute rollup stores its thresholds and evidence in a nullable diagnostics snapshot. Changes to configuration do not rewrite earlier explanations.

Anthropic AI completions and Admin API billing sync have separate rollups. Aggregation recognises older cost-report events by endpoint without modifying their original logs. Refresh checks billing and aggregates recent events; it does not execute a paid AI assessment or clear an AI failure notice. A successful AI response continues to resolve the corresponding existing notice.

## Response-time policy

Generic integrations retain their existing success-rate and latency limits. Anthropic measures the full response, with configurable operational starting values:

| Variable | Default |
| --- | --- |
| ANTHROPIC_HEALTH_GREEN_P95_MS | 30000 |
| ANTHROPIC_HEALTH_AMBER_P95_MS | 60000 |
| ANTHROPIC_HEALTH_LATENCY_RED_MIN_SAMPLES | 5 |

These defaults are not a measured service-level guarantee. Tune them against actual assessment workloads and acceptable user waiting times. A successful 25.805-second AI response is within the initial target. A latency-only red requires at least five events; smaller samples still display a warning. Availability failures remain visible regardless of sample size.

Counts refer to recorded integration events, including retries, cached results and fallbacks, rather than unique user requests. P95 uses events with measured latency. The panel shows low-volume limitations explicitly.

## Interpretation and recovery

Samples older than five minutes are stale and excluded from current green/amber/red totals. A lack of activity is not proof of an outage. Older samples without diagnostic snapshots are shown as unknown with their historical health retained. New activity and aggregation produce diagnostic snapshots; there is no fabricated backfill.

Issue guidance distinguishes missing or rejected credentials, permissions, billing restrictions, rate limits, overload, timeout, network errors, circuit-breaker pauses and unknown failures. The new diagnostic panel receives fixed guidance and an allow-listed request ID, never raw response bodies, prompts or exception messages. Credential links retain existing permission checks.

Validation covers AI/billing separation, legacy cost-event classification, immutable threshold evidence, stale totals, notice recovery, safe guidance, access control and rendered warning text.
