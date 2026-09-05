import { useCallback, useEffect, useRef, useState } from 'react';

export type PersistedDraftState = 'idle' | 'saving' | 'saved' | 'error';

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
}: Options<T>): PersistedDraftState {
    const signature = JSON.stringify(data);
    const initialSignature = useRef(signature);
    const savedSignature = useRef(signature);
    const dataRef = useRef(data);
    const signatureRef = useRef(signature);
    const [loadedUrl, setLoadedUrl] = useState<string | null>(null);
    const [state, setState] = useState<PersistedDraftState>('idle');
    const ready = loadedUrl === url;

    useEffect(() => {
        dataRef.current = data;
        signatureRef.current = signature;
    }, [data, signature]);

    const persist = useCallback(
        async (payload: T, payloadSignature: string, keepalive = false) => {
            if (!url) {
                return;
            }

            setState('saving');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    keepalive,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ payload }),
                });

                if (!response.ok) {
                    throw new Error('Draft could not be saved.');
                }

                savedSignature.current = payloadSignature;
                setState('saved');
            } catch {
                setState('error');
            }
        },
        [url],
    );

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        let cancelled = false;
        initialSignature.current = signatureRef.current;
        savedSignature.current = signatureRef.current;

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
                const next = { ...dataRef.current, ...payload };
                savedSignature.current = JSON.stringify(next);

                if (
                    signatureRef.current === initialSignature.current &&
                    Object.keys(payload).length > 0
                ) {
                    hydrate(payload);
                }

                setState(draft.saved_at ? 'saved' : 'idle');
            })
            .catch(() => {
                if (!cancelled) {
                    setState('error');
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
    }, [enabled, url]);

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

    return state;
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
