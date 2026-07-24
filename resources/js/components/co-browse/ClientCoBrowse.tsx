import { useCallback, useEffect, useRef, useState } from 'react';
import { Hand, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    closeCoBrowseEcho,
    coBrowseEcho,
    coBrowseParticipant,
    coBrowsePost,
    registerCoBrowseConnection,
    replaceCoBrowsePath,
    type CoBrowseCredentials,
} from '@/lib/co-browse';

export type ClientCoBrowseConfig = {
    portal_context_token: string;
    connection_url: string;
    prompt_url: string;
    connection_heartbeat_url: string;
    response_url: string;
    pending_actions_url: string;
    status_url: string;
    heartbeat_url: string;
    end_url: string;
    heartbeat_seconds: number;
};

type Prompt = {
    session_id: string;
    nonce: string;
    advisor_name: string;
    expires_at: string;
    context: { key: string; label: string };
};

type Session = {
    id: string;
    status: string;
    end_reason: string | null;
};

type GuidanceAction = {
    id: number;
    session_id?: string;
    type: 'pointer' | 'clear_pointer' | 'highlight' | 'clear_highlight';
    payload: { x?: number; y?: number; target?: string };
};

type Props = {
    config: ClientCoBrowseConfig | null;
};

export function ClientCoBrowse({ config }: Props) {
    const [credentials, setCredentials] = useState<CoBrowseCredentials | null>(null);
    const [prompt, setPrompt] = useState<Prompt | null>(null);
    const [session, setSession] = useState<Session | null>(null);
    const [pointer, setPointer] = useState<{ x: number; y: number } | null>(null);
    const [highlight, setHighlight] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const sessionId = useRef<string | null>(null);
    const lastActionId = useRef(0);
    const appliedActions = useRef(new Set<number>());

    const applyAction = useCallback((action: GuidanceAction): void => {
        if (action.id && appliedActions.current.has(action.id)) {
            return;
        }

        if (action.id) {
            appliedActions.current.add(action.id);
            lastActionId.current = Math.max(lastActionId.current, action.id);
        }

        if (action.type === 'pointer' && typeof action.payload.x === 'number' && typeof action.payload.y === 'number') {
            setPointer({ x: action.payload.x, y: action.payload.y });
        }
        if (action.type === 'clear_pointer') {
            setPointer(null);
        }
        if (action.type === 'highlight' && typeof action.payload.target === 'string') {
            setHighlight(action.payload.target);
        }
        if (action.type === 'clear_highlight') {
            setHighlight(null);
        }
    }, []);

    const clearGuidance = useCallback((): void => {
        setPointer(null);
        setHighlight(null);
        lastActionId.current = 0;
        appliedActions.current.clear();
    }, []);

    useEffect(() => {
        if (!config) {
            return;
        }

        let mounted = true;
        let promptPoll: number | null = null;
        let connectionHeartbeat: number | null = null;
        let refreshPresenceOnVisibilityChange: (() => void) | null = null;

        void registerCoBrowseConnection(config.connection_url, {
            portal_context_token: config.portal_context_token,
        }).then((next) => {
            if (!mounted) {
                return;
            }

            setCredentials(next);
            const pollPrompt = (): void => {
                void coBrowsePost<{ prompt: Prompt | null }>(
                    replaceCoBrowsePath(config.prompt_url, '__connection__', next.connection_id),
                    { connection_secret: next.connection_secret },
                ).then((response) => {
                    if (mounted && response.prompt) {
                        setPrompt(response.prompt);
                    }
                }).catch(() => undefined);
            };
            pollPrompt();
            promptPoll = window.setInterval(pollPrompt, 2_000);
            const heartbeat = (): void => {
                void coBrowsePost(
                    replaceCoBrowsePath(config.connection_heartbeat_url, '__connection__', next.connection_id),
                    { connection_secret: next.connection_secret },
                ).catch(() => undefined);
            };
            heartbeat();
            connectionHeartbeat = window.setInterval(heartbeat, config.heartbeat_seconds * 1000);
            refreshPresenceOnVisibilityChange = (): void => heartbeat();
            document.addEventListener('visibilitychange', refreshPresenceOnVisibilityChange);

            try {
                const channel = coBrowseEcho(next).private(next.channel);
                channel.listen('.co-browse.prompt', (event: Prompt) => setPrompt(event));
                channel.listen('.co-browse.action', (event: GuidanceAction) => {
                    if (event.session_id === sessionId.current) {
                        applyAction(event);
                    }
                });
                channel.listen('.co-browse.session-updated', (event: { session_id: string; status: string }) => {
                    if (event.session_id === sessionId.current) {
                        if (event.status === 'ended') {
                            setSession(null);
                            sessionId.current = null;
                            clearGuidance();
                        }
                    }
                });
            } catch {
                // Authenticated polling below remains available if realtime is unavailable.
            }
        }).catch((caught: unknown) => {
            if (mounted) {
                setError(messageFor(caught));
            }
        });

        return () => {
            mounted = false;
            if (promptPoll !== null) {
                window.clearInterval(promptPoll);
            }
            if (connectionHeartbeat !== null) {
                window.clearInterval(connectionHeartbeat);
            }
            if (refreshPresenceOnVisibilityChange !== null) {
                document.removeEventListener('visibilitychange', refreshPresenceOnVisibilityChange);
            }
            closeCoBrowseEcho();
        };
    }, [applyAction, clearGuidance, config]);

    useEffect(() => {
        if (!config || !credentials || !session) {
            return;
        }

        let mounted = true;
        let polling = false;
        const poll = (): void => {
            if (polling) {
                return;
            }

            polling = true;
            void coBrowsePost<{ actions: GuidanceAction[] }>(
                replaceCoBrowsePath(config.pending_actions_url, '__session__', session.id),
                { ...coBrowseParticipant(credentials), after_id: lastActionId.current },
            ).then(({ actions }) => {
                if (mounted) {
                    actions.forEach(applyAction);
                }
            }).catch(() => undefined).finally(() => {
                polling = false;
            });
        };

        poll();
        const interval = window.setInterval(poll, 1_000);
        const heartbeat = window.setInterval(() => {
            void coBrowsePost(
                replaceCoBrowsePath(config.heartbeat_url, '__session__', session.id),
                coBrowseParticipant(credentials),
            ).catch(() => undefined);
        }, config.heartbeat_seconds * 1000);

        return () => {
            mounted = false;
            window.clearInterval(interval);
            window.clearInterval(heartbeat);
        };
    }, [applyAction, config, credentials, session]);

    async function approve(): Promise<void> {
        if (!config || !credentials || !prompt) {
            return;
        }

        const activePrompt = prompt;
        try {
            const next = await coBrowsePost<Session>(
                replaceCoBrowsePath(config.response_url, '__session__', activePrompt.session_id),
                {
                    action: 'approve',
                    ...coBrowseParticipant(credentials),
                    nonce: activePrompt.nonce,
                },
            );
            sessionId.current = next.id;
            setSession(next);
            setPrompt(null);
            setError(null);
        } catch (caught) {
            setError(messageFor(caught));
        }
    }

    async function decline(): Promise<void> {
        if (!config || !credentials || !prompt) {
            return;
        }

        try {
            await coBrowsePost(
                replaceCoBrowsePath(config.response_url, '__session__', prompt.session_id),
                {
                    action: 'decline',
                    ...coBrowseParticipant(credentials),
                    nonce: prompt.nonce,
                },
            );
            setPrompt(null);
        } catch (caught) {
            setError(messageFor(caught));
        }
    }

    async function stop(): Promise<void> {
        if (!config || !credentials || !session) {
            return;
        }

        try {
            await coBrowsePost(
                replaceCoBrowsePath(config.end_url, '__session__', session.id),
                { ...coBrowseParticipant(credentials), reason: 'client_revoked' },
            );
        } catch {
            // The local stop state still prevents the visual assistance from persisting.
        }
        setSession(null);
        sessionId.current = null;
        clearGuidance();
    }

    return (
        <>
            <Dialog open={prompt !== null || error !== null}>
                <DialogContent
                    onEscapeKeyDown={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                >
                    {error ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>Guided assistance is unavailable</DialogTitle>
                                <DialogDescription>{error}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button onClick={() => setError(null)}>Close</Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <DialogHeader>
                                <DialogTitle>Allow guided assistance?</DialogTitle>
                                <DialogDescription>
                                    {prompt?.advisor_name} can point and highlight items only inside {prompt?.context.label}. They cannot control your mouse or keyboard, open other sites, or see your screen.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => void decline()}>Decline</Button>
                                <Button onClick={() => void approve()}>
                                    <Hand className="size-4" />
                                    Allow assistance
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
            {session?.status === 'active' ? (
                <div className="fixed inset-x-0 top-0 z-50 flex items-center justify-between gap-3 bg-amber-400 px-4 py-2 text-sm font-medium text-black shadow">
                    <span>Your advisor can point and highlight items on this Future Shift Advisory page. You can stop this at any time.</span>
                    <Button size="sm" variant="outline" onClick={() => void stop()}>
                        <X className="size-4" />
                        Stop assistance
                    </Button>
                </div>
            ) : null}
            <GuidanceOverlay pointer={pointer} highlight={highlight} />
        </>
    );
}

function GuidanceOverlay({ pointer, highlight }: { pointer: { x: number; y: number } | null; highlight: string | null }) {
    const [rect, setRect] = useState<DOMRect | null>(null);

    useEffect(() => {
        const update = (): void => {
            if (!highlight) {
                setRect(null);
                return;
            }

            const target = document.querySelector<HTMLElement>(`[data-co-browse-target="${highlight}"]`);
            setRect(target?.getBoundingClientRect() ?? null);
        };

        update();
        window.addEventListener('resize', update);
        window.addEventListener('scroll', update, true);

        return () => {
            window.removeEventListener('resize', update);
            window.removeEventListener('scroll', update, true);
        };
    }, [highlight]);

    return (
        <div className="pointer-events-none fixed inset-0 z-40" aria-hidden="true">
            {pointer ? (
                <span
                    className="absolute size-5 rounded-full border-2 border-amber-400 bg-amber-200/70 shadow"
                    style={{ left: `calc(${pointer.x * 100}% - 10px)`, top: `calc(${pointer.y * 100}% - 10px)` }}
                />
            ) : null}
            {rect ? (
                <span
                    className="absolute rounded border-2 border-amber-400 bg-amber-200/20 shadow-[0_0_0_4px_rgba(251,191,36,0.25)]"
                    style={{ left: rect.left, top: rect.top, width: rect.width, height: rect.height }}
                />
            ) : null}
        </div>
    );
}

function messageFor(caught: unknown): string {
    return caught instanceof Error ? caught.message : 'Guided assistance could not be started. Please try again.';
}
