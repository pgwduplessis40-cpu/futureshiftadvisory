import assert from 'node:assert/strict';
import test from 'node:test';
import {
    configureClientErrorTelemetry,
    createClientErrorTelemetry,
    registerGlobalClientErrorTelemetry,
    reportClientError,
} from './client-error-telemetry';

test('an error emits exactly one PII-safe telemetry event', () => {
    const events: unknown[] = [];
    const error = new Error(
        'Customer jane@example.test supplied token=secret and $1200',
    );
    error.name = 'Northwind_Client_Error';
    error.stack =
        'Error: Customer jane@example.test token=secret\n    at render (http://app.test/Plan.tsx?token=secret:4:9)';

    const telemetry = createClientErrorTelemetry({
        releaseSha: 'abc1234',
        browserMetadata: () => ({
            family: 'chrome',
            platform: 'Win32',
            viewport: '1440x900',
        }),
        route: () => '/portal/entrepreneur',
        deliver: (event) => {
            events.push(event);
        },
    });

    telemetry.report(error, 'entrepreneur.plan');
    telemetry.report(error, 'entrepreneur.plan');

    assert.equal(events.length, 1);
    assert.deepEqual(Object.keys(events[0] as object).sort(), [
        'browser',
        'error_fingerprint',
        'feature',
        'release_sha',
        'route',
        'sanitized_stack',
    ]);

    const serialised = JSON.stringify(events[0]);
    assert.doesNotMatch(
        serialised,
        /jane@example\.test|token=secret|\$1200|Northwind/,
    );
    assert.match(
        (events[0] as { sanitized_stack: string }).sanitized_stack,
        /^Error: \[message-redacted\]\nat \[frame-redacted\]$/,
    );
});

test('telemetry registration is inert during server-side rendering', () => {
    assert.equal(typeof window, 'undefined');

    assert.doesNotThrow(() => {
        configureClientErrorTelemetry({ releaseSha: 'ssr-release' });
        registerGlobalClientErrorTelemetry();
        reportClientError(
            new Error('SSR should not emit browser telemetry'),
            'ssr',
        );
    });
});
