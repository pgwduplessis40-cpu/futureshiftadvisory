export type BrowserMetadata = {
    family: string;
    platform: string;
    viewport: string;
};

export type ClientErrorTelemetryEvent = {
    release_sha: string;
    route: string;
    feature: string;
    browser: BrowserMetadata;
    error_fingerprint: string;
    sanitized_stack: string;
};

type TelemetryOptions = {
    releaseSha: string;
    endpoint?: string;
    browserMetadata?: () => BrowserMetadata;
    route?: () => string;
    deliver?: (event: ClientErrorTelemetryEvent) => void | Promise<void>;
};

type ClientErrorTelemetry = {
    report: (error: unknown, feature: string) => void;
};

const emittedErrors = new WeakSet<object>();
let telemetry: ClientErrorTelemetry | null = null;
const knownErrorNames = new Set([
    'Error',
    'EvalError',
    'RangeError',
    'ReferenceError',
    'SyntaxError',
    'TypeError',
    'URIError',
    'AggregateError',
]);

export function configureClientErrorTelemetry(options: TelemetryOptions): void {
    telemetry = createClientErrorTelemetry(options);
}

export function reportClientError(error: unknown, feature: string): void {
    telemetry?.report(error, feature);
}

export function registerGlobalClientErrorTelemetry(): void {
    window.addEventListener('error', (event) => {
        reportClientError(event.error ?? event.message, 'window.error');
    });
    window.addEventListener('unhandledrejection', (event) => {
        reportClientError(event.reason, 'window.unhandled_rejection');
    });
}

export function createClientErrorTelemetry(
    options: TelemetryOptions,
): ClientErrorTelemetry {
    const endpoint = options.endpoint ?? '/api/telemetry/client-errors';
    const browserMetadata = options.browserMetadata ?? defaultBrowserMetadata;
    const route = options.route ?? (() => window.location.pathname);
    const deliver =
        options.deliver ?? ((event) => deliverEvent(endpoint, event));

    return {
        report(error, feature) {
            if (typeof error === 'object' && error !== null) {
                if (emittedErrors.has(error)) {
                    return;
                }

                emittedErrors.add(error);
            }

            const event = makeEvent(
                error,
                feature,
                options.releaseSha,
                browserMetadata(),
                route(),
            );

            if (!isSafeContract(event)) {
                return;
            }

            void deliver(event);
        },
    };
}

function makeEvent(
    value: unknown,
    feature: string,
    releaseSha: string,
    browser: BrowserMetadata,
    route: string,
): ClientErrorTelemetryEvent {
    const error =
        value instanceof Error ? value : new Error('Unknown client error');
    const sanitizedStack = sanitizeStack(error);
    const safeErrorName = sanitisedErrorName(error);
    const safeFeature = feature.replace(/[^a-z0-9._-]/gi, '_').slice(0, 80);

    return {
        release_sha: releaseSha.replace(/[^a-z0-9._-]/gi, '').slice(0, 64),
        route: route.slice(0, 200),
        feature: safeFeature || 'unknown',
        browser,
        error_fingerprint: fingerprint(
            `${safeErrorName}\n${safeFeature}\n${fingerprintSource(error)}`,
        ),
        sanitized_stack: sanitizedStack,
    };
}

function sanitizeStack(error: Error): string {
    const frames = (error.stack ?? '')
        .split('\n')
        .filter((line) => /^\s*at\s+/.test(line))
        .slice(0, 12)
        .map(() => 'at [frame-redacted]');

    return [`${sanitisedErrorName(error)}: [message-redacted]`, ...frames]
        .join('\n')
        .slice(0, 2000);
}

function sanitisedErrorName(error: Error): string {
    return knownErrorNames.has(error.name) ? error.name : 'Error';
}

function fingerprintSource(error: Error): string {
    return (error.stack ?? '')
        .split('\n')
        .filter((line) => /^\s*at\s+/.test(line))
        .slice(0, 12)
        .map((line) =>
            line.replace(/\?.*$/, '').replace(/:\d+:\d+/g, ':line:column'),
        )
        .join('\n');
}

function defaultBrowserMetadata(): BrowserMetadata {
    const userAgent = navigator.userAgent;

    return {
        family: userAgent.includes('Firefox')
            ? 'firefox'
            : userAgent.includes('Edg/')
              ? 'edge'
              : userAgent.includes('Chrome/')
                ? 'chrome'
                : userAgent.includes('Safari/')
                  ? 'safari'
                  : 'other',
        platform: navigator.platform
            .replace(/[^a-z0-9._ -]/gi, '')
            .slice(0, 40),
        viewport: `${window.innerWidth}x${window.innerHeight}`,
    };
}

function isSafeContract(event: ClientErrorTelemetryEvent): boolean {
    const allowedKeys = [
        'release_sha',
        'route',
        'feature',
        'browser',
        'error_fingerprint',
        'sanitized_stack',
    ];
    const serialised = JSON.stringify(event);

    return (
        Object.keys(event).every((key) => allowedKeys.includes(key)) &&
        !/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i.test(serialised) &&
        !/(bearer\s+|authorization:|password=|token=|secret=)/i.test(
            serialised,
        ) &&
        !/\$\d|\b(?:NZD|USD|AUD)\s?\d/i.test(serialised) &&
        /^(?:Error|EvalError|RangeError|ReferenceError|SyntaxError|TypeError|URIError|AggregateError): \[message-redacted\](?:\nat \[frame-redacted\]){0,12}$/.test(
            event.sanitized_stack,
        )
    );
}

function fingerprint(value: string): string {
    let hash = 2166136261;

    for (let index = 0; index < value.length; index += 1) {
        hash ^= value.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    return (hash >>> 0).toString(16).padStart(8, '0');
}

function deliverEvent(
    endpoint: string,
    event: ClientErrorTelemetryEvent,
): void {
    void fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(event),
        keepalive: true,
    }).catch(() => undefined);
}
