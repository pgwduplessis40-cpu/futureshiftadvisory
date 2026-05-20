# Integration resilience pattern

WO-05 establishes the wrapper every external integration uses.

## Required flow

All live integrations call `App\Services\Integration\Resilience\ResilientHttp`; application code does not call third-party hosts directly.

`ResilientHttp` provides:

- retry attempts through `RetryPolicy`
- service-level circuit breaker through `CircuitBreaker`
- per-attempt rows in `integration_calls`
- cached fallback through Laravel cache
- graceful degraded fallback when no cache is available

## Tables

`integration_calls` records every attempt and fallback decision:

- `success`
- `retry`
- `failure`
- `cached`
- `fallback`

Rows share a `correlation_id` across retry attempts for the same logical call.

`integration_health_samples` stores the scheduled Green/Amber/Red rollup used by WO-30.

## Health thresholds

Thresholds live in `config/integrations.php`:

- Green: success rate at least `0.99` and p95 latency at most `1000ms`.
- Amber: success rate at least `0.95` and p95 latency at most `3000ms`.
- Red: anything worse.

The values are config-driven so production can tune them without changing the aggregation command.

## Circuit breaker

Default breaker settings:

- five consecutive failed logical calls
- within sixty seconds
- opens for five minutes

When open, the next call does not hit the network. It returns a cached response when `cacheKey` is supplied and present; otherwise it returns a degraded fallback response.
