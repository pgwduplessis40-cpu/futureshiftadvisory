import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import type { ServiceHealth } from '../pages/admin/integration-health/HealthChecks';
import { HealthChecks } from '../pages/admin/integration-health/HealthChecks';

const sample: ServiceHealth = {
    id: 'ai',
    service: 'anthropic',
    label: 'Anthropic AI requests',
    health: 'amber',
    display_health: 'amber',
    success_rate: 1,
    p95_latency_ms: 35000,
    window_start: '2026-10-22T01:00:00Z',
    window_end: '2026-10-22T01:05:00Z',
    lag_seconds: 0,
    fresh: true,
    diagnostics: {
        availability: 'green',
        response_time: 'amber',
        green_success_rate: 0.99,
        amber_success_rate: 0.95,
        green_latency_ms: 30000,
        amber_latency_ms: 60000,
        latency_red_min_samples: 5,
        event_count: 1,
        success_count: 1,
        retry_count: 0,
        failure_count: 0,
        fallback_count: 0,
        cached_count: 0,
        latest_issue: null,
    },
};

function render(service: ServiceHealth, credentialsUrl: string | null = null) {
    return renderToStaticMarkup(
        <HealthChecks
            services={[service]}
            refreshing={false}
            onRefresh={() => undefined}
            credentialsUrl={credentialsUrl}
        />,
    );
}

test('a slow successful AI response explains its threshold, limited evidence and next action', () => {
    const html = render(sample);
    const text = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ');
    assert.match(text, /Availability: Successful replies recorded/);
    assert.match(text, /P95: 35s. Green target up to 30s; amber up to 60s/);
    assert.match(text, /Reason: response time exceeded the target/);
    assert.match(text, /Next action:.*review prompt size, output length/);
    assert.match(text, /Small sample/);
    assert.match(text, /refreshing does not run an assessment/);
});

test('billing errors display the reason and permitted credential action separately from AI', () => {
    const billing: ServiceHealth = {
        ...sample,
        service: 'anthropic_admin',
        label: 'Anthropic billing sync',
        health: 'red',
        display_health: 'red',
        success_rate: 0,
        diagnostics: {
            ...sample.diagnostics!,
            availability: 'red',
            latest_issue: {
                code: 'authentication',
                reason: 'The provider rejected the credentials.',
                action: 'Check the Admin API key.',
                http_status: 401,
                request_id: 'req_support',
                occurred_at: sample.window_end,
            },
        },
    };
    const html = render(billing, '/admin/integration-credentials');
    assert.match(html, /Anthropic billing sync/);
    assert.match(html, /The provider rejected the credentials/);
    assert.match(html, /Check the Admin API key/);
    assert.match(html, /HTTP 401/);
    assert.match(html, /req_support/);
    assert.match(html, /Recheck billing sync/);
    assert.match(html, /href="\/admin\/integration-credentials"/);
    assert.doesNotMatch(
        render(billing),
        /href="\/admin\/integration-credentials"/,
    );
});

test('stale and legacy observations explicitly withhold a current health claim', () => {
    const stale = render({ ...sample, display_health: 'stale', fresh: false });
    assert.match(stale, /Stale · Current health unknown/);
    assert.match(stale, /monitoring scheduler/);
    const legacy = render({
        ...sample,
        display_health: 'unknown',
        diagnostics: null,
    });
    assert.match(legacy, /Unknown · Earlier sample/);
    assert.match(legacy, /Event count unavailable/);
    assert.match(
        legacy,
        /Earlier Anthropic samples combined AI requests and billing checks/,
    );
    assert.match(legacy, /Next action/);
});
