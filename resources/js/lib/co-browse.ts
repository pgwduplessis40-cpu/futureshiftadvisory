import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export type CoBrowseCredentials = {
    connection_id: string;
    connection_secret: string;
    channel: string;
    expires_at: string;
};

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let echo: Echo<'reverb'> | null = null;

export async function registerCoBrowseConnection(
    url: string,
    body: Record<string, string>,
): Promise<CoBrowseCredentials> {
    return coBrowsePost<CoBrowseCredentials>(url, body);
}

export function coBrowseEcho(credentials: CoBrowseCredentials): Echo<'reverb'> {
    echo?.disconnect();
    window.Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Co-Browse-Connection-Secret': credentials.connection_secret,
            },
        },
    });

    return echo;
}

export function closeCoBrowseEcho(): void {
    echo?.disconnect();
    echo = null;
}

export async function coBrowsePost<T>(url: string, body: Record<string, unknown>): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as { message?: string } | null;
        throw new Error((payload?.message ?? 'Guided assistance request failed.') + ' (HTTP ' + response.status + ')');
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export function replaceCoBrowsePath(url: string, placeholder: '__connection__' | '__session__', value: string): string {
    return url.replace(placeholder, value);
}

export function coBrowseParticipant(credentials: CoBrowseCredentials): Record<string, string> {
    return {
        connection_id: credentials.connection_id,
        connection_secret: credentials.connection_secret,
    };
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
