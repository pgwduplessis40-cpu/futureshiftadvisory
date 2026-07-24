import { useCallback, useEffect, useRef, useState } from 'react';
import type { PointerEvent, RefObject } from 'react';
import { createPortal } from 'react-dom';
import { Hand, Highlighter, MousePointer2, PhoneOff, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    closeCoBrowseEcho,
    coBrowseEcho,
    coBrowseParticipant,
    coBrowsePost,
    registerCoBrowseConnection,
    replaceCoBrowsePath,
    type CoBrowseCredentials,
} from '@/lib/co-browse';

export type AdvisorCoBrowseConfig = {
    connection_url: string;
    connection_heartbeat_url: string;
    request_url: string;
    status_url: string;
    heartbeat_url: string;
    end_url: string;
    action_url: string;
    heartbeat_seconds: number;
    participants: Array<{ id: string; name: string }>;
};

type Session = {
    id: string;
    status: string;
    end_reason: string | null;
    targets?: Record<string, string>;
};

type Props = {
    config: AdvisorCoBrowseConfig | null;
    participantId: string;
    screenShareLive: boolean;
    videoRef: RefObject<HTMLVideoElement | null>;
};

/**
 * This is deliberately embedded in Screen Support. Guidance never begins until
 * the client has approved both a screen view and this separate assistance prompt.
 */
export function AdvisorCoBrowseControls({ config, participantId, screenShareLive, videoRef }: Props) {
    const [credentials, setCredentials] = useState<CoBrowseCredentials | null>(null);
    const [session, setSession] = useState<Session | null>(null);
    const [error, setError] = useState<string | null>(null);
    const sessionRef = useRef<Session | null>(null);
    const credentialsRef = useRef<CoBrowseCredentials | null>(null);
    const lastPointerSentAt = useRef(0);

    const setCurrentSession = useCallback((next: Session | null): void => {
        sessionRef.current = next;
        setSession(next);
    }, []);

    useEffect(() => {
        credentialsRef.current = credentials;
    }, [credentials]);

    useEffect(() => {
        if (!config || !screenShareLive) {
            return;
        }

        let mounted = true;
        let connectionHeartbeat: number | null = null;

        void registerCoBrowseConnection(config.connection_url, {}).then((next) => {
            if (!mounted) {
                return;
            }

            credentialsRef.current = next;
            setCredentials(next);
            connectionHeartbeat = window.setInterval(() => {
                void coBrowsePost(
                    replaceCoBrowsePath(config.connection_heartbeat_url, '__connection__', next.connection_id),
                    { connection_secret: next.connection_secret },
                ).catch(() => undefined);
            }, config.heartbeat_seconds * 1000);

            try {
                const channel = coBrowseEcho(next).private(next.channel);
                channel.listen('.co-browse.session-updated', (event: { session_id: string; status: string; end_reason: string | null }) => {
                    const current = sessionRef.current;
                    if (!current || current.id !== event.session_id) {
                        return;
                    }

                    setCurrentSession(event.status === 'ended'
                        ? null
                        : { ...current, status: event.status, end_reason: event.end_reason });
                });
            } catch {
                // Status polling remains available when realtime delivery is unavailable.
            }
        }).catch((caught: unknown) => {
            if (mounted) {
                setError(messageFor(caught));
            }
        });

        return () => {
            mounted = false;
            if (connectionHeartbeat !== null) {
                window.clearInterval(connectionHeartbeat);
            }

            const current = sessionRef.current;
            const currentCredentials = credentialsRef.current;
            if (current && currentCredentials) {
                void coBrowsePost(
                    replaceCoBrowsePath(config.end_url, '__session__', current.id),
                    { ...coBrowseParticipant(currentCredentials), reason: 'completed_advisor_ended' },
                ).catch(() => undefined);
            }
            credentialsRef.current = null;
            setCurrentSession(null);
            setCredentials(null);
            closeCoBrowseEcho();
        };
    }, [config, screenShareLive, setCurrentSession]);

    useEffect(() => {
        if (!config || !credentials || !session || !screenShareLive) {
            return;
        }

        let mounted = true;
        const poll = (): void => {
            void coBrowsePost<Session>(
                replaceCoBrowsePath(config.status_url, '__session__', session.id),
                coBrowseParticipant(credentials),
            ).then((next) => {
                if (!mounted) {
                    return;
                }

                setCurrentSession(next.status === 'ended' ? null : next);
            }).catch(() => undefined);
        };

        poll();
        const statusInterval = window.setInterval(poll, 2_000);
        const heartbeatInterval = session.status === 'active'
            ? window.setInterval(() => {
                void coBrowsePost(
                    replaceCoBrowsePath(config.heartbeat_url, '__session__', session.id),
                    coBrowseParticipant(credentials),
                ).catch(() => undefined);
            }, config.heartbeat_seconds * 1000)
            : null;

        return () => {
            mounted = false;
            window.clearInterval(statusInterval);
            if (heartbeatInterval !== null) {
                window.clearInterval(heartbeatInterval);
            }
        };
    }, [config, credentials, screenShareLive, session, setCurrentSession]);

    async function request(): Promise<void> {
        if (!config || !credentials || !participantId || !screenShareLive) {
            return;
        }

        try {
            setError(null);
            const next = await coBrowsePost<Session>(config.request_url, {
                client_user_id: participantId,
                advisor_connection_id: credentials.connection_id,
                advisor_connection_secret: credentials.connection_secret,
            });
            setCurrentSession(next);
        } catch (caught) {
            setError(messageFor(caught));
        }
    }

    async function end(): Promise<void> {
        if (!config || !credentials || !session) {
            setCurrentSession(null);
            return;
        }

        await coBrowsePost(
            replaceCoBrowsePath(config.end_url, '__session__', session.id),
            { ...coBrowseParticipant(credentials), reason: 'completed_advisor_ended' },
        ).catch(() => undefined);
        setCurrentSession(null);
    }

    async function send(
        type: 'pointer' | 'clear_pointer' | 'highlight' | 'clear_highlight',
        payload: Record<string, unknown> = {},
    ): Promise<void> {
        if (!config || !credentials || !session || session.status !== 'active') {
            return;
        }

        try {
            await coBrowsePost(
                replaceCoBrowsePath(config.action_url, '__session__', session.id),
                { ...coBrowseParticipant(credentials), type, payload },
            );
            if (error !== null) {
                setError(null);
            }
        } catch (caught) {
            setError(messageFor(caught));
        }
    }

    function point(event: PointerEvent<HTMLDivElement>): void {
        // Stay below the server's five-points-per-second limit, including
        // the occasional pointer clear sent when the advisor leaves the view.
        if (Date.now() - lastPointerSentAt.current < 250) {
            return;
        }

        const video = videoRef.current;
        if (!video || video.videoWidth <= 0 || video.videoHeight <= 0) {
            return;
        }

        const bounds = video.getBoundingClientRect();
        const scale = Math.min(bounds.width / video.videoWidth, bounds.height / video.videoHeight);
        const contentWidth = video.videoWidth * scale;
        const contentHeight = video.videoHeight * scale;
        const contentLeft = bounds.left + (bounds.width - contentWidth) / 2;
        const contentTop = bounds.top + (bounds.height - contentHeight) / 2;
        const x = (event.clientX - contentLeft) / contentWidth;
        const y = (event.clientY - contentTop) / contentHeight;

        if (x < 0 || x > 1 || y < 0 || y > 1) {
            return;
        }

        lastPointerSentAt.current = Date.now();
        void send('pointer', { x, y });
    }

    if (!config || !screenShareLive) {
        return null;
    }

    const clientName = config.participants.find((participant) => participant.id === participantId)?.name ?? 'the client';
    const targets = Object.entries(session?.targets ?? {});
    const pointerOverlay = session?.status === 'active' && videoRef.current?.parentElement
        ? createPortal(
            <div
                className="absolute inset-0 z-10 cursor-crosshair touch-none"
                aria-label="Guided assistance pointer overlay"
                onPointerMove={point}
                onPointerLeave={() => void send('clear_pointer')}
            />,
            videoRef.current.parentElement,
        )
        : null;

    return (
        <>
            {pointerOverlay}
            <section className="mt-3 shrink-0 border-t pt-3" aria-label="Guided assistance">
                {error ? <p className="mb-2 text-sm text-destructive">{error}</p> : null}
                {!session ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Request separate approval to point and highlight within Future Shift Advisory. No mouse or keyboard control is available.
                        </p>
                        <Button type="button" size="sm" disabled={!credentials || !participantId} onClick={() => void request()}>
                            <Hand className="size-4" />
                            Request guidance approval
                        </Button>
                    </div>
                ) : session.status === 'requested' ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span>Waiting for {clientName} to approve guided assistance.</span>
                        <Button type="button" variant="destructive" size="sm" onClick={() => void end()}>
                            <X className="size-4" />
                            Cancel guidance
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span className="font-medium">Guided assistance is active. Move over the shared screen to point.</span>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={() => void send('clear_pointer')}>
                                    <MousePointer2 className="size-4" />
                                    Clear pointer
                                </Button>
                                <Button type="button" size="sm" variant="destructive" onClick={() => void end()}>
                                    <PhoneOff className="size-4" />
                                    End guidance
                                </Button>
                            </div>
                        </div>
                        {targets.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {targets.map(([target, label]) => (
                                    <Button key={target} type="button" size="sm" variant="outline" onClick={() => void send('highlight', { target })}>
                                        <Highlighter className="size-4" />
                                        Highlight {label}
                                    </Button>
                                ))}
                                <Button type="button" size="sm" variant="outline" onClick={() => void send('clear_highlight')}>
                                    Clear highlight
                                </Button>
                            </div>
                        ) : null}
                    </div>
                )}
            </section>
        </>
    );
}

function messageFor(caught: unknown): string {
    return caught instanceof Error ? caught.message : 'Guided assistance could not be started. Please try again.';
}
