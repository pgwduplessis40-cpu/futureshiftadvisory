import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type Credentials = {
    connection_id: string;
    connection_secret: string;
    channel: string;
    expires_at: string;
};

type CredentialsResponse = Credentials;

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let echo: Echo<'reverb'> | null = null;

export async function registerScreenShareConnection(url: string, body: Record<string, string>): Promise<Credentials> {
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
        throw new Error('Unable to connect screen support.');
    }

    return (await response.json()) as CredentialsResponse;
}

export function screenShareEcho(credentials: Credentials): Echo<'reverb'> {
    if (echo !== null) {
        echo.disconnect();
    }

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
                'X-Screen-Share-Connection-Secret': credentials.connection_secret,
            },
        },
    });

    return echo;
}

export function closeScreenShareEcho(): void {
    echo?.disconnect();
    echo = null;
}

export async function screenSharePost<T>(url: string, body: Record<string, unknown>): Promise<T> {
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
        const message = payload?.message ?? 'Screen support request failed.';
        throw new Error(message + ' (HTTP ' + response.status + ')');
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
