import { useEffect, useRef, useState } from 'react';
import { Maximize2, Monitor, PhoneOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    closeScreenShareEcho,
    normalizeScreenShareDescription,
    registerScreenShareConnection,
    screenShareEcho,
    screenSharePost,
} from '@/lib/screen-share';

type Props = {
    config: {
        connection_url: string;
        connection_heartbeat_url: string;
        request_url: string;
        ice_servers_url: string;
        active_url: string;
        signal_url: string;
        pending_signals_url: string;
        heartbeat_url: string;
        end_url: string;
        heartbeat_seconds: number;
        participants: Array<{ id: string; name: string }>;
    };
    onConnectionChange: (connected: boolean) => void;
    onSessionEnded: () => void;
};

type Credentials = { connection_id: string; connection_secret: string; channel: string };

type Signal = {
    id?: number;
    session_id: string;
    type: string;
    payload: RTCSessionDescriptionInit | RTCIceCandidateInit;
};

type PendingSignalsResponse = {
    signals: Array<{
        id: number;
        type: string;
        payload: RTCSessionDescriptionInit | RTCIceCandidateInit;
    }>;
};

type NegotiationStage =
    | 'load-relay-settings'
    | 'prepare-viewer'
    | 'apply-offer'
    | 'create-answer'
    | 'apply-answer'
    | 'send-answer';

export function AdvisorSupport({ config, onConnectionChange, onSessionEnded }: Props) {
    const [credentials, setCredentials] = useState<Credentials | null>(null);
    const [participant, setParticipant] = useState(config.participants[0]?.id ?? '');
    const [sessionId, setSessionId] = useState<string | null>(null);
    const [connected, setConnected] = useState(false);
    const [overSharing, setOverSharing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const video = useRef<HTMLVideoElement | null>(null);
    const peer = useRef<RTCPeerConnection | null>(null);
    const sessionIdRef = useRef<string | null>(null);
    const pendingCandidates = useRef<RTCIceCandidateInit[]>([]);
    const outboundCandidates = useRef<RTCIceCandidateInit[]>([]);
    const answerSignaled = useRef(false);
    const lastPolledSignalId = useRef(0);
    const receivedSignalIds = useRef(new Set<number>());
    const processingSignalIds = useRef(new Set<number>());
    const signalProcessingQueue = useRef<Promise<void>>(Promise.resolve());

    useEffect(() => {
        onConnectionChange(connected);
    }, [connected, onConnectionChange]);

    useEffect(() => {
        let active = true;
        void registerScreenShareConnection(config.connection_url, {}).then((next) => {
            if (!active) {
                return;
            }

            setCredentials(next);
            const channel = screenShareEcho(next).private(next.channel);
            channel.listen('.screen-share.signal', (event: Signal) => {
                void handleIncomingSignal(event, next);
            });
            channel.listen('.screen-share.session-updated', (event: {
                session_id: string;
                status: string;
                display_surface: string | null;
            }) => {
                if (event.session_id !== sessionIdRef.current) {
                    return;
                }

                setOverSharing(event.display_surface === 'monitor' || event.display_surface === 'window');
                if (event.status === 'ended') {
                    stop();
                    onSessionEnded();
                }
            });
        }).catch(() => setError('Unable to connect screen support.'));

        return () => {
            active = false;
            stop();
            closeScreenShareEcho();
        };
    }, [config.connection_url]);

    useEffect(() => {
        if (!credentials || !sessionId) {
            return;
        }

        let active = true;
        let polling = false;
        const poll = (): void => {
            if (polling) {
                return;
            }

            polling = true;
            void screenSharePost<PendingSignalsResponse>(
                replaceSession(config.pending_signals_url, sessionId),
                {
                    ...participantPayload(credentials),
                    // Until an offer has created the peer connection, re-read this
                    // short-lived session from the start so a stale cursor cannot hide it.
                    after_id: peer.current ? lastPolledSignalId.current : 0,
                },
            ).then(async ({ signals }) => {
                for (const signal of signals) {
                    if (!active) {
                        return;
                    }

                    const processed = await handleIncomingSignal({
                        id: signal.id,
                        session_id: sessionId,
                        type: signal.type,
                        payload: signal.payload,
                    }, credentials);
                    if (processed) {
                        lastPolledSignalId.current = Math.max(lastPolledSignalId.current, signal.id);
                    }
                }
            }).catch(() => undefined).finally(() => {
                polling = false;
            });
        };

        poll();
        const interval = window.setInterval(poll, 1_000);

        return () => {
            active = false;
            window.clearInterval(interval);
        };
    }, [config.pending_signals_url, credentials, sessionId]);

    useEffect(() => {
        if (!credentials) {
            return;
        }

        const heartbeat = (): void => {
            void screenSharePost(
                replaceConnection(config.connection_heartbeat_url, credentials.connection_id),
                { connection_secret: credentials.connection_secret },
            ).catch(() => setError('Screen support connection was lost. Please request it again.'));
        };

        heartbeat();
        const interval = window.setInterval(heartbeat, config.heartbeat_seconds * 1000);

        return () => window.clearInterval(interval);
    }, [config.connection_heartbeat_url, config.heartbeat_seconds, credentials]);

    useEffect(() => {
        if (!credentials || !sessionId || !connected) {
            return;
        }

        const heartbeat = (): void => {
            void screenSharePost(
                replaceSession(config.heartbeat_url, sessionId),
                participantPayload(credentials),
            ).catch(() => undefined);
        };

        heartbeat();
        const interval = window.setInterval(() => {
            heartbeat();
        }, config.heartbeat_seconds * 1000);

        return () => window.clearInterval(interval);
    }, [config.heartbeat_seconds, config.heartbeat_url, connected, credentials, sessionId]);

    useEffect(() => {
        if (!connected) {
            setElapsedSeconds(0);
            return;
        }

        const startedAt = Date.now();
        const interval = window.setInterval(() => {
            setElapsedSeconds(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);

        return () => window.clearInterval(interval);
    }, [connected]);

    async function request(): Promise<void> {
        if (!credentials || !participant) {
            return;
        }

        setError(null);
        try {
            const result = await screenSharePost<{ id: string }>(config.request_url, {
                client_user_id: participant,
                advisor_connection_id: credentials.connection_id,
                advisor_connection_secret: credentials.connection_secret,
            });
            setSession(result.id);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Unable to request screen support.');
        }
    }

    async function handleSignal(event: Signal, nextCredentials: Credentials): Promise<void> {
        const currentSessionId = sessionIdRef.current;
        if (!currentSessionId) {
            return;
        }

        if (event.type === 'candidate' && !peer.current) {
            pendingCandidates.current.push(event.payload as RTCIceCandidateInit);

            return;
        }

        let stage: NegotiationStage = 'load-relay-settings';

        try {
            let connection = peer.current;
            if (!connection) {
                const ice = await screenSharePost<RTCIceServer[]>(
                    replaceSession(config.ice_servers_url, currentSessionId),
                    participantPayload(nextCredentials),
                );
                stage = 'prepare-viewer';
                connection = new RTCPeerConnection({ iceServers: ice });
                peer.current = connection;
                const viewer = connection;
                answerSignaled.current = false;
                outboundCandidates.current = [];
                connection.ontrack = (trackEvent) => {
                    if (video.current) {
                        video.current.srcObject = trackEvent.streams[0] ?? null;
                    }
                };
                connection.onicecandidate = ({ candidate }) => {
                    if (!candidate) {
                        return;
                    }

                    const payload = candidate.toJSON();
                    if (!answerSignaled.current) {
                        outboundCandidates.current.push(payload);

                        return;
                    }

                    void signal(currentSessionId, nextCredentials, 'candidate', payload).catch(() => undefined);
                };
                connection.onconnectionstatechange = () => {
                    if (viewer.connectionState === 'connected') {
                        void screenSharePost(
                            replaceSession(config.active_url, currentSessionId),
                            participantPayload(nextCredentials),
                        );
                        setConnected(true);
                        setError(null);
                    }
                    if (viewer.connectionState === 'failed') {
                        void end();
                    }
                };
            }

            if (event.type === 'offer') {
                stage = 'apply-offer';
                await connection.setRemoteDescription(
                    normalizeScreenShareDescription(event.payload as RTCSessionDescriptionInit),
                );
                stage = 'create-answer';
                const answer = await connection.createAnswer();
                stage = 'apply-answer';
                await connection.setLocalDescription(answer);
                const localDescription = connection.localDescription;
                if (!localDescription) {
                    throw new Error('The browser did not prepare a screen-share answer.');
                }

                stage = 'send-answer';
                await signal(
                    currentSessionId,
                    nextCredentials,
                    'answer',
                    normalizeScreenShareDescription(localDescription),
                );
                answerSignaled.current = true;
                for (const candidate of outboundCandidates.current.splice(0)) {
                    void signal(currentSessionId, nextCredentials, 'candidate', candidate).catch(() => undefined);
                }
                for (const candidate of pendingCandidates.current.splice(0)) {
                    await addIceCandidate(connection, candidate);
                }
            } else if (event.type === 'candidate') {
                const candidate = event.payload as RTCIceCandidateInit;
                if (connection.remoteDescription) {
                    await addIceCandidate(connection, candidate);
                } else {
                    pendingCandidates.current.push(candidate);
                }
            }
        } catch (caught) {
            throw new ScreenShareNegotiationError(stage, caught);
        }
    }

    async function handleIncomingSignal(event: Signal, nextCredentials: Credentials): Promise<boolean> {
        if (event.session_id !== sessionIdRef.current) {
            return false;
        }

        if (event.id !== undefined) {
            if (receivedSignalIds.current.has(event.id)) {
                return true;
            }

            if (processingSignalIds.current.has(event.id)) {
                return false;
            }

            processingSignalIds.current.add(event.id);
        }

        const operation = signalProcessingQueue.current.then(async (): Promise<boolean> => {
            if (event.session_id !== sessionIdRef.current) {
                if (event.id !== undefined) {
                    processingSignalIds.current.delete(event.id);
                }

                return false;
            }

            try {
                await handleSignal(event, nextCredentials);
                if (event.id !== undefined) {
                    receivedSignalIds.current.add(event.id);
                }
                if (event.type === 'offer') {
                    setError(null);
                }

                return true;
            } catch (caught) {
                if (event.type === 'offer') {
                    resetPeerForRetry();
                }
                setError(messageForNegotiationFailure(caught));

                return false;
            } finally {
                if (event.id !== undefined) {
                    processingSignalIds.current.delete(event.id);
                }
            }
        });
        signalProcessingQueue.current = operation.then(() => undefined, () => undefined);

        return operation;
    }

    async function signal(
        currentSessionId: string,
        nextCredentials: Credentials,
        type: string,
        payload: object,
    ): Promise<void> {
        await screenSharePost(replaceSession(config.signal_url, currentSessionId), {
            ...participantPayload(nextCredentials),
            type,
            payload,
        });
    }

    async function end(): Promise<void> {
        if (credentials && sessionIdRef.current) {
            await screenSharePost(
                replaceSession(config.end_url, sessionIdRef.current),
                { ...participantPayload(credentials), reason: 'completed_advisor_ended' },
            ).catch(() => undefined);
        }
        stop();
        onSessionEnded();
    }

    function setSession(nextSessionId: string | null): void {
        if (sessionIdRef.current !== nextSessionId) {
            lastPolledSignalId.current = 0;
            receivedSignalIds.current.clear();
            processingSignalIds.current.clear();
        }
        sessionIdRef.current = nextSessionId;
        setSessionId(nextSessionId);
    }

    function resetPeerForRetry(): void {
        const connection = peer.current;
        peer.current = null;
        if (connection) {
            connection.onconnectionstatechange = null;
            connection.onicecandidate = null;
            connection.ontrack = null;
            connection.close();
        }
        pendingCandidates.current = [];
        outboundCandidates.current = [];
        answerSignaled.current = false;
        lastPolledSignalId.current = 0;
        receivedSignalIds.current.clear();
        if (video.current) {
            video.current.srcObject = null;
        }
        setConnected(false);
        setOverSharing(false);
    }

    function stop(): void {
        const connection = peer.current;
        peer.current = null;
        if (connection) {
            connection.onconnectionstatechange = null;
            connection.onicecandidate = null;
            connection.ontrack = null;
            connection.close();
        }
        pendingCandidates.current = [];
        outboundCandidates.current = [];
        answerSignaled.current = false;
        processingSignalIds.current.clear();
        if (video.current) {
            video.current.srcObject = null;
        }
        setConnected(false);
        setOverSharing(false);
        setSession(null);
    }

    async function enterFullscreen(): Promise<void> {
        if (!video.current) {
            return;
        }

        try {
            await video.current.requestFullscreen();
        } catch {
            setError('The browser could not open the shared screen in full-screen mode.');
        }
    }

    if (config.participants.length === 0) {
        return null;
    }

    return (
        <section className="flex h-full min-h-0 flex-col border-0 bg-card p-4 shadow-none">
            <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 pr-10">
                <div>
                    <h2 className="text-sm font-semibold">Screen support</h2>
                    <p className="text-sm text-muted-foreground">
                        {connected
                            ? 'View only. Live for ' + formatDuration(elapsedSeconds) + '.'
                            : 'View only. The client chooses the shared screen in their browser.'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <select className="h-9 rounded-md border bg-background px-3 text-sm" value={participant} onChange={(event) => setParticipant(event.target.value)} disabled={sessionId !== null}>
                        {config.participants.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                    </select>
                    {sessionId ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="size-9 p-0"
                            title="View shared screen full screen"
                            aria-label="View shared screen full screen"
                            disabled={!connected}
                            onClick={() => void enterFullscreen()}
                        >
                            <Maximize2 className="size-4" aria-hidden="true" />
                        </Button>
                    ) : null}
                    {sessionId
                        ? <Button variant="destructive" size="sm" onClick={() => void end()}><PhoneOff className="size-4" />End</Button>
                        : <Button size="sm" onClick={() => void request()} disabled={!credentials}><Monitor className="size-4" />Request view</Button>}
                </div>
            </div>
            {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
            {sessionId ? (
                <div className={connected ? 'mt-4 flex min-h-0 flex-1 flex-col' : 'mt-4 shrink-0'}>
                    <video
                        ref={video}
                        autoPlay
                        playsInline
                        className={
                            connected
                                ? 'min-h-0 w-full flex-1 bg-black object-contain'
                                : 'aspect-video w-full bg-black object-contain'
                        }
                        title="Double-click to view full screen"
                        onDoubleClick={() => void enterFullscreen()}
                    />
                    <p className="mt-2 shrink-0 text-sm text-muted-foreground">
                        {connected
                            ? overSharing
                                ? 'The client is sharing a window or their entire screen.'
                                : 'The client is sharing their browser.'
                            : 'Waiting for the client to approve and choose a screen.'}
                    </p>
                </div>
            ) : null}
        </section>
    );
}

function participantPayload(credentials: Credentials): Record<string, string> {
    return {
        connection_id: credentials.connection_id,
        connection_secret: credentials.connection_secret,
    };
}

function replaceSession(url: string, sessionId: string): string {
    return url.replace('__session__', sessionId);
}

function replaceConnection(url: string, connectionId: string): string {
    return url.replace('__connection__', connectionId);
}

async function addIceCandidate(
    connection: RTCPeerConnection,
    candidate: RTCIceCandidateInit,
): Promise<void> {
    try {
        await connection.addIceCandidate(candidate);
    } catch {
        // Candidates are alternatives; one unsupported route must not block negotiation.
    }
}

class ScreenShareNegotiationError extends Error {
    constructor(
        readonly stage: NegotiationStage,
        original: unknown,
    ) {
        super(original instanceof Error ? original.message : 'The browser could not continue.');
        this.name = 'ScreenShareNegotiationError';
    }
}

function messageForNegotiationFailure(caught: unknown): string {
    if (!(caught instanceof ScreenShareNegotiationError)) {
        return 'Screen support could not establish a connection. Keep the client sharing and try again.';
    }

    const labels: Record<NegotiationStage, string> = {
        'load-relay-settings': 'loading the secure connection settings',
        'prepare-viewer': 'preparing the browser viewer',
        'apply-offer': 'reading the client screen-share offer',
        'create-answer': 'preparing the advisor response',
        'apply-answer': 'activating the advisor response',
        'send-answer': 'sending the advisor response',
    };
    const detail = caught.message ? ' ' + caught.message : '';

    return 'Screen support stopped while ' + labels[caught.stage] + '.' + detail + ' It will retry while the client keeps sharing.';
}

function formatDuration(seconds: number): string {
    return String(Math.floor(seconds / 60)) + ':' + String(seconds % 60).padStart(2, '0');
}
