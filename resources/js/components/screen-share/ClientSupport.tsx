import { useEffect, useRef, useState } from 'react';
import { MonitorUp, PhoneOff } from 'lucide-react';
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
    closeScreenShareEcho,
    normalizeScreenShareDescription,
    registerScreenShareConnection,
    screenShareEcho,
    screenSharePost,
} from '@/lib/screen-share';

type Props = {
    config: {
        portal_context_token: string;
        connection_url: string;
        prompt_url: string;
        connection_heartbeat_url: string;
        response_url: string;
        browser_permission_url: string;
        ice_servers_url: string;
        active_url: string;
        signal_url: string;
        pending_signals_url: string;
        heartbeat_url: string;
        end_url: string;
        heartbeat_seconds: number;
        warning_at_minutes: number;
    } | null;
};

type Credentials = {
    connection_id: string;
    connection_secret: string;
    channel: string;
};

type Prompt = {
    session_id: string;
    nonce: string;
    advisor_name: string;
    context: { label: string };
};

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

type PendingPromptResponse = {
    prompt: Prompt | null;
};

export function ClientSupport({ config }: Props) {
    const [credentials, setCredentials] = useState<Credentials | null>(null);
    const [prompt, setPrompt] = useState<Prompt | null>(null);
    const [sharing, setSharing] = useState(false);
    const [sessionId, setSessionId] = useState<string | null>(null);
    const [overSharing, setOverSharing] = useState(false);
    const [advisorName, setAdvisorName] = useState<string | null>(null);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [isMobile, setIsMobile] = useState(false);
    const [shareError, setShareError] = useState<string | null>(null);
    const peer = useRef<RTCPeerConnection | null>(null);
    const stream = useRef<MediaStream | null>(null);
    const callTone = useRef<{ context: AudioContext; interval: number } | null>(null);
    const sessionIdRef = useRef<string | null>(null);
    const pendingCandidates = useRef<RTCIceCandidateInit[]>([]);
    const outboundCandidates = useRef<RTCIceCandidateInit[]>([]);
    const offerSignaled = useRef(false);
    const lastPolledSignalId = useRef(0);
    const receivedSignalIds = useRef(new Set<number>());

    useEffect(() => {
        if (!config) {
            return;
        }

        let active = true;
        let promptPoll: number | null = null;
        let connectionHeartbeat: number | null = null;
        void registerScreenShareConnection(config.connection_url, {
            portal_context_token: config.portal_context_token,
        }).then((next) => {
            if (!active) {
                return;
            }

            setCredentials(next);
            const pollForPrompt = (): void => {
                void screenSharePost<PendingPromptResponse>(
                    replaceConnection(config.prompt_url, next.connection_id),
                    participant(next),
                ).then((response) => {
                    if (active && response.prompt) {
                        setPrompt(response.prompt);
                    }
                }).catch(() => undefined);
            };
            pollForPrompt();
            promptPoll = window.setInterval(pollForPrompt, 3_000);
            connectionHeartbeat = window.setInterval(() => {
                void screenSharePost(
                    replaceConnection(config.connection_heartbeat_url, next.connection_id),
                    participant(next),
                ).catch(() => undefined);
            }, config.heartbeat_seconds * 1000);

            try {
                const channel = screenShareEcho(next).private(next.channel);
                channel.listen('.screen-share.prompt', (event: Prompt) => setPrompt(event));
                channel.listen('.screen-share.signal', (event: Signal) => {
                    void handleIncomingSignal(event);
                });
                channel.listen('.screen-share.session-updated', (event: { session_id: string; status: string }) => {
                    if (event.session_id === sessionIdRef.current && event.status === 'ended') {
                        stop();
                    }

                    if (event.session_id === sessionIdRef.current && event.status !== 'requested') {
                        setPrompt(null);
                    }
                });
            } catch {
                // The authenticated polling fallback continues when realtime is unavailable.
            }
        }).catch(() => undefined);

        return () => {
            active = false;
            if (promptPoll !== null) {
                window.clearInterval(promptPoll);
            }
            if (connectionHeartbeat !== null) {
                window.clearInterval(connectionHeartbeat);
            }
            stop();
            closeScreenShareEcho();
        };
    }, [config]);

    useEffect(() => {
        if (!config || !credentials || !sessionId) {
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
                    ...participant(credentials),
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
                    });
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
    }, [config, credentials, sessionId]);

    useEffect(() => {
        setIsMobile(/Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(navigator.userAgent));
    }, []);

    useEffect(() => {
        if (!prompt) {
            return;
        }

        let active = true;
        let context: AudioContext | null = null;
        try {
            context = new AudioContext();
            const audioContext = context;
            const playRing = (): void => {
                const start = audioContext.currentTime;
                playTone(audioContext, 740, start);
                playTone(audioContext, 880, start + 0.32);
            };

            void audioContext.resume().then(() => {
                if (!active) {
                    void audioContext.close();
                    return;
                }

                playRing();
                callTone.current = {
                    context: audioContext,
                    interval: window.setInterval(playRing, 2_600),
                };
            }).catch(() => audioContext.close());
        } catch {
            // The approval dialog remains visible when the browser blocks audio.
        }

        return () => {
            active = false;
            if (callTone.current !== null) {
                window.clearInterval(callTone.current.interval);
                void callTone.current.context.close();
                callTone.current = null;
            } else if (context !== null) {
                void context.close();
            }
        };
    }, [prompt?.session_id]);

    useEffect(() => {
        if (!config || !credentials || !sharing || !sessionId) {
            return;
        }

        const interval = window.setInterval(() => {
            void screenSharePost(replaceSession(config.heartbeat_url, sessionId), participant(credentials))
                .catch(() => undefined);
        }, config.heartbeat_seconds * 1000);

        return () => window.clearInterval(interval);
    }, [config, credentials, sessionId, sharing]);

    useEffect(() => {
        if (!sharing) {
            setElapsedSeconds(0);
            return;
        }

        const startedAt = Date.now();
        const interval = window.setInterval(() => {
            setElapsedSeconds(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);

        return () => window.clearInterval(interval);
    }, [sharing]);

    async function approve(): Promise<void> {
        if (!config || !credentials || !prompt || isMobile) {
            return;
        }

        const currentPrompt = prompt;
        const nextSessionId = currentPrompt.session_id;
        let capture: Promise<MediaStream>;

        try {
            // This must run synchronously from the button click. Awaiting the
            // approval request first can make Chromium discard user activation.
            capture = navigator.mediaDevices.getDisplayMedia({
                video: { frameRate: { ideal: 20, max: 30 } },
                audio: false,
                preferCurrentTab: true,
            } as DisplayMediaStreamOptions);
        } catch (caught) {
            setShareError(messageFor(caught));

            return;
        }

        setShareError(null);
        let approved = false;
        let browserPermissionGranted = false;

        try {
            await screenSharePost(replaceSession(config.response_url, nextSessionId), {
                action: 'approve',
                ...participant(credentials),
                nonce: currentPrompt.nonce,
            });
            approved = true;
            setSession(nextSessionId);
            setAdvisorName(currentPrompt.advisor_name);

            const captured = await capture;
            stream.current = captured;
            const track = captured.getVideoTracks()[0];
            const displaySurface = track?.getSettings().displaySurface;
            setOverSharing(displaySurface === 'monitor' || displaySurface === 'window');

            await screenSharePost(replaceSession(config.browser_permission_url, nextSessionId), {
                ...participant(credentials),
                granted: true,
                display_surface: displaySurface,
            });
            browserPermissionGranted = true;

            const ice = await screenSharePost<RTCIceServer[]>(
                replaceSession(config.ice_servers_url, nextSessionId),
                participant(credentials),
            );
            const connection = new RTCPeerConnection({ iceServers: ice });
            peer.current = connection;
            offerSignaled.current = false;
            outboundCandidates.current = [];
            captured.getTracks().forEach((mediaTrack) => connection.addTrack(mediaTrack, captured));
            connection.onicecandidate = ({ candidate }) => {
                if (candidate) {
                    const payload = candidate.toJSON();
                    if (!offerSignaled.current) {
                        outboundCandidates.current.push(payload);

                        return;
                    }

                    void signal(nextSessionId, 'candidate', payload).catch(() => undefined);
                }
            };
            connection.onconnectionstatechange = () => {
                if (connection.connectionState === 'connected') {
                    void screenSharePost(replaceSession(config.active_url, nextSessionId), participant(credentials));
                    setSharing(true);
                }

                if (connection.connectionState === 'failed') {
                    void end(nextSessionId, 'connection_lost');
                }
            };
            track?.addEventListener('ended', () => void end(nextSessionId, 'client_navigated_away'));

            const offer = await connection.createOffer();
            await connection.setLocalDescription(offer);
            const localDescription = connection.localDescription;
            if (!localDescription) {
                throw new Error('The browser did not prepare a screen-share offer.');
            }

            await signal(nextSessionId, 'offer', normalizeScreenShareDescription(localDescription));
            offerSignaled.current = true;
            for (const candidate of outboundCandidates.current.splice(0)) {
                void signal(nextSessionId, 'candidate', candidate).catch(() => undefined);
            }
            setPrompt(null);
        } catch (caught) {
            if (approved && !browserPermissionGranted) {
                await screenSharePost(replaceSession(config.browser_permission_url, nextSessionId), {
                    ...participant(credentials),
                    granted: false,
                }).catch(() => undefined);
            } else if (approved) {
                await screenSharePost(replaceSession(config.end_url, nextSessionId), {
                    ...participant(credentials),
                    reason: 'connection_lost',
                }).catch(() => undefined);
            } else {
                void capture.then((captured) => captured.getTracks().forEach((mediaTrack) => mediaTrack.stop()))
                    .catch(() => undefined);
            }
            setShareError(messageFor(caught));
            stop();
        }
    }

    async function decline(): Promise<void> {
        if (!config || !credentials || !prompt) {
            return;
        }

        await screenSharePost(replaceSession(config.response_url, prompt.session_id), {
            action: 'decline',
            ...participant(credentials),
            nonce: prompt.nonce,
        });
        setPrompt(null);
    }

    async function signal(nextSessionId: string, type: string, payload: object): Promise<void> {
        if (!config || !credentials) {
            return;
        }

        await screenSharePost(replaceSession(config.signal_url, nextSessionId), {
            ...participant(credentials),
            type,
            payload,
        });
    }

    async function handleIncomingSignal(event: Signal): Promise<void> {
        if (event.session_id !== sessionIdRef.current || !peer.current) {
            return;
        }

        if (event.id !== undefined) {
            if (receivedSignalIds.current.has(event.id)) {
                return;
            }
        }

        if (event.type === 'answer') {
            await peer.current.setRemoteDescription(
                normalizeScreenShareDescription(event.payload as RTCSessionDescriptionInit),
            );
            for (const candidate of pendingCandidates.current.splice(0)) {
                await addIceCandidate(peer.current, candidate);
            }
        }

        if (event.type === 'candidate') {
            const candidate = event.payload as RTCIceCandidateInit;
            if (peer.current.remoteDescription) {
                await addIceCandidate(peer.current, candidate);
            } else {
                pendingCandidates.current.push(candidate);
            }
        }

        if (event.id !== undefined) {
            receivedSignalIds.current.add(event.id);
        }
    }

    async function end(nextSessionId: string, reason: string): Promise<void> {
        if (config && credentials && nextSessionId) {
            await screenSharePost(replaceSession(config.end_url, nextSessionId), {
                ...participant(credentials),
                reason,
            }).catch(() => undefined);
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
        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;
        pendingCandidates.current = [];
        outboundCandidates.current = [];
        offerSignaled.current = false;
        setPrompt(null);
        setSession(null);
        setSharing(false);
        setOverSharing(false);
        setAdvisorName(null);
    }

    return (
        <>
            <Dialog open={prompt !== null || shareError !== null}>
                <DialogContent
                    onEscapeKeyDown={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                >
                    {shareError ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>Screen support could not start</DialogTitle>
                                <DialogDescription>{shareError}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button onClick={() => setShareError(null)}>Close</Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <DialogHeader>
                                <DialogTitle>Screen support request</DialogTitle>
                                <DialogDescription>
                                    {isMobile
                                        ? 'Screen support is available from a desktop browser. This request cannot start on this device.'
                                        : prompt?.advisor_name + ' would like to view ' + prompt?.context.label + '. Your browser will ask you what to share next; choose This Tab where it is available.'}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => void decline()}>Decline</Button>
                                {!isMobile ? (
                                    <Button onClick={() => void approve()}>
                                        <MonitorUp className="size-4" />
                                        Continue
                                    </Button>
                                ) : null}
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
            {sharing && sessionId ? (
                <div className="fixed inset-x-0 top-0 z-50 flex items-center justify-between gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-black shadow">
                    <span>
                        {overSharing
                            ? 'You are sharing a window or your entire screen. Switch to this tab where possible.'
                            : 'Screen sharing with ' + (advisorName ?? 'your advisor') + ' is active (' + formatDuration(elapsedSeconds) + '). Your advisor can view only.'}
                        {!overSharing && elapsedSeconds >= (config?.warning_at_minutes ?? 0) * 60
                            ? ' Your session is approaching its time limit.'
                            : ''}
                    </span>
                    <Button variant="outline" size="sm" onClick={() => void end(sessionId, 'completed_client_ended')}>
                        <PhoneOff className="size-4" />
                        End
                    </Button>
                </div>
            ) : null}
        </>
    );
}

function participant(credentials: Credentials): Record<string, string> {
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

function playTone(context: AudioContext, frequency: number, start: number): void {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.frequency.value = frequency;
    oscillator.type = 'sine';
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(0.08, start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.25);
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start(start);
    oscillator.stop(start + 0.26);
}

function messageFor(caught: unknown): string {
    if (caught instanceof Error && caught.message === 'Screen support relay is unavailable.') {
        return 'Screen support is temporarily unavailable. Please ask your advisor to try again shortly.';
    }

    if (caught instanceof Error && caught.name === 'NotAllowedError') {
        return 'Screen sharing was not started. Choose a screen or tab, then select Share.';
    }

    return caught instanceof Error
        ? caught.message
        : 'Screen sharing could not start. Please try again.';
}

function formatDuration(seconds: number): string {
    return String(Math.floor(seconds / 60)) + ':' + String(seconds % 60).padStart(2, '0');
}
