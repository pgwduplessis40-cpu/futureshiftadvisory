export type ScreenShareConnectionScope = {
    connection_url: string;
    portal_context_token: string;
} | null;

/**
 * A portal context token authorises one connection registration. It is renewed
 * on every Inertia response, so it must not be used as the lifetime key for an
 * established screen-share connection.
 */
export function screenShareConnectionScopeKey(
    config: ScreenShareConnectionScope,
): string | null {
    return config?.connection_url ?? null;
}
