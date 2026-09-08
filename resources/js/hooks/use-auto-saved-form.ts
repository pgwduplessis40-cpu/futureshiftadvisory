import { useCallback, useEffect, useRef, useState } from 'react';

export type AutosavedFormState = 'idle' | 'saving' | 'saved' | 'error';

export type AutosavedForm = {
    state: AutosavedFormState;
    retry: () => void;
};

type Options<T extends object> = {
    url: string | null;
    data: T;
    enabled?: boolean;
    delay?: number;
};

/**
 * Persists a safe, non-finalising form value as the client works.
 *
 * Call this only for fields whose server endpoint is safe to update without a
 * submission, acceptance, payment, or other irreversible user decision.
 */
export function useAutoSavedForm<T extends object>({
    url,
    data,
    enabled = true,
    delay = 750,
}: Options<T>): AutosavedForm {
    const signature = JSON.stringify(data);
    const savedSignature = useRef(signature);
    const dataRef = useRef(data);
    const signatureRef = useRef(signature);
    const saving = useRef(false);
    const saveRef = useRef<(keepalive?: boolean) => void>(() => undefined);
    const [state, setState] = useState<AutosavedFormState>('idle');

    useEffect(() => {
        dataRef.current = data;
        signatureRef.current = signature;
    }, [data, signature]);

    const save = useCallback(
        async (keepalive = false) => {
            if (
                !url ||
                !enabled ||
                saving.current ||
                signatureRef.current === savedSignature.current
            ) {
                return;
            }

            saving.current = true;
            const payload = dataRef.current;
            const payloadSignature = signatureRef.current;
            let persisted = false;
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
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Form could not be saved.');
                }

                savedSignature.current = payloadSignature;
                persisted = true;
                setState('saved');
            } catch {
                setState('error');
            } finally {
                saving.current = false;

                if (
                    persisted &&
                    !keepalive &&
                    signatureRef.current !== savedSignature.current
                ) {
                    saveRef.current();
                }
            }
        },
        [enabled, url],
    );

    useEffect(() => {
        saveRef.current = (keepalive = false) => {
            void save(keepalive);
        };
    }, [save]);

    const retry = useCallback(() => {
        void save();
    }, [save]);

    useEffect(() => {
        if (!url || !enabled || signature === savedSignature.current) {
            return;
        }

        const timer = window.setTimeout(() => void save(), delay);

        return () => window.clearTimeout(timer);
    }, [delay, enabled, save, signature, url]);

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        const saveWhenHidden = () => {
            if (document.visibilityState === 'hidden') {
                void save(true);
            }
        };
        const saveBeforeUnload = () => void save(true);

        document.addEventListener('visibilitychange', saveWhenHidden);
        window.addEventListener('beforeunload', saveBeforeUnload);

        return () => {
            document.removeEventListener('visibilitychange', saveWhenHidden);
            window.removeEventListener('beforeunload', saveBeforeUnload);
        };
    }, [enabled, save, url]);

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        const retryWhenOnline = () => {
            void save();
        };

        window.addEventListener('online', retryWhenOnline);

        return () => window.removeEventListener('online', retryWhenOnline);
    }, [enabled, save, url]);

    return { state, retry };
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
