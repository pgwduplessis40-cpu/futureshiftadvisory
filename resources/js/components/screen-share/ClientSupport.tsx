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
    registerScreenShareConnection,
    screenShareEcho,
    screenSharePost,
} from '@/lib/screen-share';

type Props = {
    config: {
        portal_context_token: string;
        connection_url: string;
        response_url: string;
        browser_permission_url: string;
        ice_servers_url: string;
        active_url: string;
        signal_url: string;
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
    session_id: string;
    type: string;
    payload: RTCSessionDescriptionInit | RTCIceCandidateInit;
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
    const peer = useRef<RTCPeerConnection | null>(null);
    const stream = useRef<MediaStream | null>(null);
    const sessionIdRef = useRef<string | null>(null);
    const pendingCandidates = useRef<RTCIceCandidateInit[]>([]);

    useEffect(() => {
        if (!config) {
            return;
        }

        let active = true;
        void registerScreenShareConnection(config.connection_url, {
            portal_context_token: config.portal_context_token,
        }).then((next) => {
            if (!active) {
                return;
            }

            setCredentials(next);
            const channel = screenShareEcho(next).private(next.channel);
            channel.listen('.screen-share.prompt', (event: Prompt) => setPrompt(event));
            channel.listen('.screen-share.signal', (event: Signal) => {
                if (event.session_id !== sessionIdRef.current || !peer.current) {
                    return;
                }

                if (event.type === 'answer') {
                    void peer.current.setRemoteDescription(event.payload as RTCSessionDescriptionInit)
                        .then(async () => {
                            for (const candidate of pendingCandidates.current.splice(0)) {
                                await peer.current?.addIceCandidate(candidate);
                            }
                        });
                }

                if (event.type === 'candidate') {
                    const candidate = event.payload as RTCIceCandidateInit;
                    if (peer.current.remoteDescription) {
                        void peer.current.addIceCandidate(candidate);
                    } else {
                        pendingCandidates.current.push(candidate);
                    }
                }
            });
            channel.listen('.screen-share.session-updated', (event: { session_id: string; status: string }) => {
                if (event.session_id === sessionIdRef.current && event.status === 'ended') {
                    stop();
                }

                if (event.session_id === sessionIdRef.current && event.status !== 'requested') {
                    setPrompt(null);
                }
            });
        }).catch(() => undefined);

        return () => {
            active = false;
            stop();
            closeScreenShareEcho();
        };
    }, [config]);

    useEffect(() => {
        setIsMobile(/Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(navigator.userAgent));
    }, []);

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
        await screenSharePost(replaceSession(config.response_url, nextSessionId), {
            action: 'approve',
            ...participant(credentials),
            nonce: currentPrompt.nonce,
        });
        setSession(nextSessionId);
        setAdvisorName(currentPrompt.advisor_name);

        try {
            const captured = await navigator.mediaDevices.getDisplayMedia({
                video: { frameRate: { ideal: 20, max: 30 } },
                audio: false,
                preferCurrentTab: true,
            } as DisplayMediaStreamOptions);
            stream.current = captured;
            const track = captured.getVideoTracks()[0];
            const displaySurface = track?.getSettings().displaySurface;
            setOverSharing(displaySurface === 'monitor' || displaySurface === 'window');

            await screenSharePost(replaceSession(config.browser_permission_url, nextSessionId), {
                ...participant(credentials),
                granted: true,
                display_surface: displaySurface,
            });

            const ice = await screenSharePost<RTCIceServer[]>(
                replaceSession(config.ice_servers_url, nextSessionId),
                participant(credentials),
            );
            const connection = new RTCPeerConnection({ iceServers: ice });
            peer.current = connection;
            captured.getTracks().forEach((mediaTrack) => connection.addTrack(mediaTrack, captured));
            connection.onicecandidate = ({ candidate }) => {
                if (candidate) {
                    void signal(nextSessionId, 'candidate', candidate.toJSON());
                }
            };
            connection.onconnectionstatechange = () => {
                if (connection.connectionState === 'connected') {
                    void screenSharePost(replaceSession(config.active_url, nextSessionId), participant(credentials));
                    setSharing(true);
                }

                if (['failed', 'closed'].includes(connection.connectionState)) {
                    void end(nextSessionId, 'connection_lost');
                }
            };
            track?.addEventListener('ended', () => void end(nextSessionId, 'client_navigated_away'));

            const offer = await connection.createOffer();
            await connection.setLocalDescription(offer);
            await signal(nextSessionId, 'offer', offer);
            setPrompt(null);
        } catch {
            await screenSharePost(replaceSession(config.browser_permission_url, nextSessionId), {
                ...participant(credentials),
                granted: false,
            }).catch(() => undefined);
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
        sessionIdRef.current = nextSessionId;
        setSessionId(nextSessionId);
    }

    function stop(): void {
        peer.current?.close();
        peer.current = null;
        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;
        pendingCandidates.current = [];
        setPrompt(null);
        setSession(null);
        setSharing(false);
        setOverSharing(false);
        setAdvisorName(null);
    }

    return (
        <>
            <Dialog open={prompt !== null}>
                <DialogContent
                    onEscapeKeyDown={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                >
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

function formatDuration(seconds: number): string {
    return String(Math.floor(seconds / 60)) + ':' + String(seconds % 60).padStart(2, '0');
}
