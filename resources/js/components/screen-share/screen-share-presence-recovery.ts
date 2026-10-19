import type { PresenceStatus } from './ClientSupportPresenceStatus';

export const PRESENCE_FAILURES_BEFORE_REFRESH = 2;

export function nextScreenSharePresenceFailure(
    failures: number,
    active: boolean,
    setPresenceStatus: (status: PresenceStatus) => void,
    refreshPresence: () => void,
): number {
    if (!active) {
        return failures;
    }

    const nextFailures = failures + 1;
    setPresenceStatus('reconnecting');

    if (nextFailures >= PRESENCE_FAILURES_BEFORE_REFRESH) {
        refreshPresence();
    }

    return nextFailures;
}
