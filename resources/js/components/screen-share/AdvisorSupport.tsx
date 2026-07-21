import { useEffect, useRef, useState } from 'react';
import { Monitor, PhoneOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    closeScreenShareEcho,
    registerScreenShareConnection,
    screenShareEcho,
    screenSharePost,
} from '@/lib/screen-share';

type Props = {
    config: {
        connection_url: string;
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

export function AdvisorSupport({ config }: Props) {
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
    const lastPolledSignalId = useRef(0);
    const receivedSignalIds = useRef(new Set<number>());

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
                    after_id: lastPolledSignalId.current,
                },
            ).then(async ({ signals }) => {
                for (const signal of signals) {
                    if (!active) {
                        return;
                    }

                    await handleIncomingSignal({
                        id: signal.id,
                        session_id: sessionId,
                        type: signal.type,
                        payload: signal.payload,
                    }, credentials);
                    lastPolledSignalId.current = Math.max(lastPolledSignalId.current, signal.id);
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
        if (!credentials || !sessionId) {
            return;
        }

        const heartbeat = (): void => {
            void screenSharePost(
                replaceSession(config.heartbeat_url, sessionId),
                participantPayload(credentials),
            ).catch(() => setError('Screen support connection was lost. Please request it again.'));
        };

        heartbeat();
        const interval = window.setInterval(() => {
            heartbeat();
        }, config.heartbeat_seconds * 1000);

        return () => window.clearInterval(interval);
    }, [config.heartbeat_seconds, config.heartbeat_url, credentials, sessionId]);

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

        if (!peer.current) {
            const ice = await screenSharePost<RTCIceServer[]>(
                replaceSession(config.ice_servers_url, currentSessionId),
                participantPayload(nextCredentials),
            );
            peer.current = new RTCPeerConnection({ iceServers: ice });
            peer.current.ontrack = (trackEvent) => {
                if (video.current) {
                    video.current.srcObject = trackEvent.streams[0];
                }
            };
            peer.current.onicecandidate = ({ candidate }) => {
                if (candidate) {
                    void signal(currentSessionId, nextCredentials, 'candidate', candidate.toJSON());
                }
            };
            peer.current.onconnectionstatechange = () => {
                if (peer.current?.connectionState === 'connected') {
                    void screenSharePost(
                        replaceSession(config.active_url, currentSessionId),
                        participantPayload(nextCredentials),
                    );
                    setConnected(true);
                }
                if (['failed', 'closed'].includes(peer.current?.connectionState ?? '')) {
                    void end();
                }
            };
        }

        if (event.type === 'offer') {
            await peer.current.setRemoteDescription(event.payload as RTCSessionDescriptionInit);
            for (const candidate of pendingCandidates.current.splice(0)) {
                await peer.current.addIceCandidate(candidate);
            }
            const answer = await peer.current.createAnswer();
            await peer.current.setLocalDescription(answer);
            await signal(currentSessionId, nextCredentials, 'answer', answer);
        } else if (event.type === 'candidate') {
            const candidate = event.payload as RTCIceCandidateInit;
            if (peer.current.remoteDescription) {
                await peer.current.addIceCandidate(candidate);
            } else {
                pendingCandidates.current.push(candidate);
            }
        }
    }

    async function handleIncomingSignal(event: Signal, nextCredentials: Credentials): Promise<void> {
        if (event.session_id !== sessionIdRef.current) {
            return;
        }

        if (event.id !== undefined) {
            if (receivedSignalIds.current.has(event.id)) {
                return;
            }

            receivedSignalIds.current.add(event.id);
        }

        await handleSignal(event, nextCredentials);
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
    }

    function setSession(nextSessionId: string | null): void {
        if (sessionIdRef.current !== nextSessionId) {
            lastPolledSignalId.current = 0;
            receivedSignalIds.current.clear();
        }
        sessionIdRef.current = nextSessionId;
        setSessionId(nextSessionId);
    }

    function stop(): void {
        peer.current?.close();
        peer.current = null;
        pendingCandidates.current = [];
        if (video.current) {
            video.current.srcObject = null;
        }
        setConnected(false);
        setOverSharing(false);
        setSession(null);
    }

    if (config.participants.length === 0) {
        return null;
    }

    return (
        <section className="border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
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
                    {sessionId
                        ? <Button variant="destructive" size="sm" onClick={() => void end()}><PhoneOff className="size-4" />End</Button>
                        : <Button size="sm" onClick={() => void request()} disabled={!credentials}><Monitor className="size-4" />Request view</Button>}
                </div>
            </div>
            {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
            {sessionId ? (
                <div className="mt-4">
                    <video ref={video} autoPlay playsInline className="aspect-video w-full bg-black object-contain" />
                    <p className="mt-2 text-sm text-muted-foreground">
                        {overSharing ? 'The client is sharing a window or their entire screen.' : 'Waiting for the client to approve and choose a screen.'}
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

function formatDuration(seconds: number): string {
    return String(Math.floor(seconds / 60)) + ':' + String(seconds % 60).padStart(2, '0');
}
