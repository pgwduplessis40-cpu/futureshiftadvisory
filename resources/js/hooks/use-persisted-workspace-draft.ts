import { useCallback, useEffect, useRef, useState } from 'react';

export type PersistedDraftState = 'idle' | 'saving' | 'saved' | 'error';

export type PersistedWorkspaceDraft = {
    state: PersistedDraftState;
    hasRecovery: true;
    retry: () => void;
    discardRecovery: () => void;
};

type Options<T extends object> = {
    url: string | null;
    data: T;
    hydrate: (payload: Partial<T>) => void;
    enabled?: boolean;
    delay?: number;
};

export function usePersistedWorkspaceDraft<T extends object>({
    url,
    data,
    hydrate,
    enabled = true,
    delay = 750,
}: Options<T>): PersistedWorkspaceDraft {
    const signature = JSON.stringify(data);
    const initialSignature = useRef(signature);
    const savedSignature = useRef(signature);
    const dataRef = useRef(data);
    const signatureRef = useRef(signature);
    const saving = useRef(false);
    const stateRef = useRef<PersistedDraftState>('idle');
    const persistRef = useRef<
        (payload: T, payloadSignature: string, keepalive?: boolean) => void
    >(() => undefined);
    const [loadedUrl, setLoadedUrl] = useState<string | null>(null);
    const [state, setState] = useState<PersistedDraftState>('idle');
    const ready = loadedUrl === url;

    useEffect(() => {
        dataRef.current = data;
        signatureRef.current = signature;
    }, [data, signature]);

    const setDraftState = useCallback((next: PersistedDraftState) => {
        stateRef.current = next;
        setState(next);
    }, []);

    const persist = useCallback(
        async (payload: T, payloadSignature: string, keepalive = false) => {
            if (!url || saving.current) {
                return;
            }

            saving.current = true;
            setDraftState('saving');

            try {
                const body = JSON.stringify({ payload });
                const response = await fetch(url, {
                    method: 'POST',
                    // Browsers reject keepalive requests that exceed their
                    // small request budget. A normal debounced save has
                    // already run before navigation; this is only a last
                    // chance flush for small drafts.
                    keepalive: keepalive && body.length < 60_000,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body,
                });

                if (!response.ok) {
                    throw new Error('Draft could not be saved.');
                }

                savedSignature.current = payloadSignature;

                if (signatureRef.current === payloadSignature) {
                    clearRecoveryDraft(url);
                }

                setDraftState('saved');
            } catch {
                setDraftState('error');
            } finally {
                saving.current = false;

                if (
                    !keepalive &&
                    signatureRef.current !== savedSignature.current
                ) {
                    persistRef.current(dataRef.current, signatureRef.current);
                }
            }
        },
        [setDraftState, url],
    );

    useEffect(() => {
        persistRef.current = (payload, payloadSignature, keepalive = false) => {
            void persist(payload, payloadSignature, keepalive);
        };
    }, [persist]);

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        let cancelled = false;
        initialSignature.current = signatureRef.current;
        savedSignature.current = signatureRef.current;
        const recoveryDraft = readRecoveryDraft<T>(url);

        void fetch(url, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Draft could not be loaded.');
                }

                return (await response.json()) as {
                    payload?: Partial<T>;
                    saved_at?: string | null;
                };
            })
            .then((draft) => {
                if (cancelled) {
                    return;
                }

                const payload = draft.payload ?? {};
                const savedAt = draft.saved_at ?? null;
                const serverDraft = { ...dataRef.current, ...payload };
                const recoveryIsNewer =
                    recoveryDraft !== null &&
                    isRecoveryNewer(recoveryDraft.saved_at, savedAt);
                const next = recoveryIsNewer
                    ? { ...dataRef.current, ...recoveryDraft.payload }
                    : serverDraft;
                savedSignature.current = JSON.stringify(
                    recoveryIsNewer ? serverDraft : next,
                );

                if (
                    signatureRef.current === initialSignature.current &&
                    Object.keys(next).length > 0
                ) {
                    hydrate(next);
                }

                setDraftState(
                    recoveryIsNewer ? 'idle' : savedAt ? 'saved' : 'idle',
                );
            })
            .catch(() => {
                if (!cancelled) {
                    setDraftState('error');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadedUrl(url);
                }
            });

        return () => {
            cancelled = true;
        };
        // This only hydrates the initial server form once per mounted workspace.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, setDraftState, url]);

    useEffect(() => {
        if (
            !url ||
            !enabled ||
            !ready ||
            signature === savedSignature.current
        ) {
            return;
        }

        const timer = window.setTimeout(() => {
            void persist(data, signature);
        }, delay);

        return () => window.clearTimeout(timer);
    }, [data, delay, enabled, persist, ready, signature, url]);

    useEffect(() => {
        if (
            !url ||
            !enabled ||
            signature === initialSignature.current ||
            signature === savedSignature.current
        ) {
            return;
        }

        writeRecoveryDraft(url, data);
    }, [data, enabled, signature, url]);

    useEffect(() => {
        if (!url || !enabled || !ready) {
            return;
        }

        const persistLatest = () => {
            const latestSignature = signatureRef.current;

            if (latestSignature !== savedSignature.current) {
                void persist(dataRef.current, latestSignature, true);
            }
        };
        const persistWhenHidden = () => {
            if (document.visibilityState === 'hidden') {
                persistLatest();
            }
        };

        document.addEventListener('visibilitychange', persistWhenHidden);
        window.addEventListener('beforeunload', persistLatest);

        return () => {
            document.removeEventListener('visibilitychange', persistWhenHidden);
            window.removeEventListener('beforeunload', persistLatest);
        };
    }, [enabled, persist, ready, url]);

    const retry = useCallback(() => {
        if (!url || !enabled) {
            return;
        }

        persistRef.current(dataRef.current, signatureRef.current);
    }, [enabled, url]);

    const discardRecovery = useCallback(() => {
        if (url) {
            clearRecoveryDraft(url);
        }
    }, [url]);

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        const retryWhenOnline = () => {
            if (
                stateRef.current === 'error' ||
                signatureRef.current !== savedSignature.current
            ) {
                retry();
            }
        };

        window.addEventListener('online', retryWhenOnline);

        return () => window.removeEventListener('online', retryWhenOnline);
    }, [enabled, retry, url]);

    return { state, hasRecovery: true, retry, discardRecovery };
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

type RecoveryDraft<T extends object> = {
    payload: T;
    saved_at: string;
};

function readRecoveryDraft<T extends object>(
    url: string,
): RecoveryDraft<T> | null {
    try {
        const raw = window.sessionStorage.getItem(recoveryKey(url));

        if (!raw) {
            return null;
        }

        const recovery = JSON.parse(raw) as Partial<RecoveryDraft<T>>;

        return recovery.payload && typeof recovery.saved_at === 'string'
            ? { payload: recovery.payload, saved_at: recovery.saved_at }
            : null;
    } catch {
        return null;
    }
}

function writeRecoveryDraft<T extends object>(url: string, payload: T): void {
    try {
        window.sessionStorage.setItem(
            recoveryKey(url),
            JSON.stringify({ payload, saved_at: new Date().toISOString() }),
        );
    } catch {
        // Saving to the server remains the primary recovery path. Some
        // browsers can block session storage, so never let this fallback
        // interrupt the visible server save status.
    }
}

function clearRecoveryDraft(url: string): void {
    try {
        window.sessionStorage.removeItem(recoveryKey(url));
    } catch {
        // A blocked storage API must not affect a confirmed server save.
    }
}

function recoveryKey(url: string): string {
    return `fsa:workspace-draft-recovery:v1:${url}`;
}

function isRecoveryNewer(
    recoverySavedAt: string,
    serverSavedAt: string | null,
): boolean {
    if (serverSavedAt === null) {
        return true;
    }

    const recoveryTimestamp = Date.parse(recoverySavedAt);
    const serverTimestamp = Date.parse(serverSavedAt);

    if (Number.isNaN(recoveryTimestamp) || Number.isNaN(serverTimestamp)) {
        return recoverySavedAt > serverSavedAt;
    }

    return recoveryTimestamp > serverTimestamp;
}
