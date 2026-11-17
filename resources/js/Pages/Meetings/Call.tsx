import { router } from '@inertiajs/react';
import {
    ExpandIcon,
    MicIcon,
    MicOffIcon,
    Minimize2Icon,
    PanelRightIcon,
    PhoneOffIcon,
    PlusIcon,
    Trash2Icon,
    UsersIcon,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageShell } from '@/lib/page-kit';
import { cn } from '@/lib/utils';

type PeerInfo = { id: string; name: string };

type ActionItem = {
    id?: string;
    title: string;
    assignee_id?: string | null;
    assignee_name?: string | null;
    due_date?: string | null;
    status: string;
};

type Props = {
    meeting: {
        id: string;
        title: string;
        agenda?: string | null;
        location?: string | null;
        status: string;
        minutes_notes?: string | null;
        is_instant?: boolean;
        when_label: string;
        organizer_name?: string | null;
        participants: Array<{ id: string; name?: string | null; response: string }>;
        rsvp: { accepted: number; declined: number; pending: number; total: number };
        action_items: ActionItem[];
    };
    me: { id: string; name: string };
    canEditMinutes: boolean;
    canManageRecords: boolean;
    staffUsers: Array<{ id: string; name: string }>;
    urls: {
        show: string;
        saveMinutes: string;
        saveActions: string;
        heartbeat: string;
        peers: string;
        signal: string;
        signals: string;
        leave: string;
    };
};

type SignalMessage = {
    id: string;
    from_user_id: string;
    type: 'offer' | 'answer' | 'ice';
    payload: Record<string, unknown>;
};

const ICE_SERVERS: RTCIceServer[] = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
];

function csrfHeaders(): HeadersInit {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
    };
}

async function postJson(url: string, body?: Record<string, unknown>, timeoutMs = 8000) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        return response.json();
    } finally {
        window.clearTimeout(timer);
    }
}

async function getJson(url: string, timeoutMs = 8000) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        return response.json();
    } finally {
        window.clearTimeout(timer);
    }
}

function fireLeave(url: string) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    void fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: '{}',
        keepalive: true,
    });
}

export default function MeetingCall({ meeting, me, canEditMinutes, canManageRecords, staffUsers, urls }: Props) {
    const [muted, setMuted] = useState(false);
    const [micError, setMicError] = useState<string | null>(null);
    const [peers, setPeers] = useState<PeerInfo[]>([]);
    const [connectedIds, setConnectedIds] = useState<string[]>([]);
    const [micLevel, setMicLevel] = useState(0);
    const [status, setStatus] = useState('Connecting microphone…');
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [workspace, setWorkspace] = useState<'call' | 'full'>('call');
    const [minutes, setMinutes] = useState(meeting.minutes_notes ?? '');
    const [actionItems, setActionItems] = useState<ActionItem[]>(
        meeting.action_items.map((item) => ({
            ...item,
            assignee_id: item.assignee_id ?? '',
            due_date: item.due_date ?? '',
            status: item.status || 'open',
        })),
    );
    const [savingMinutes, setSavingMinutes] = useState(false);
    const [savingActions, setSavingActions] = useState(false);
    const [leaving, setLeaving] = useState(false);

    const localStreamRef = useRef<MediaStream | null>(null);
    const peersRef = useRef<Map<string, RTCPeerConnection>>(new Map());
    const peerNamesRef = useRef<Map<string, string>>(new Map());
    const remoteAudioRef = useRef<Map<string, HTMLAudioElement>>(new Map());
    const offeredPeersRef = useRef<Set<string>>(new Set());
    const makingOfferRef = useRef<Set<string>>(new Set());
    const aliveRef = useRef(true);
    const leftRef = useRef(false);

    useEffect(() => {
        aliveRef.current = true;
        leftRef.current = false;

        const cleanupPeer = (peerId: string) => {
            const pc = peersRef.current.get(peerId);
            if (pc) {
                pc.onicecandidate = null;
                pc.ontrack = null;
                pc.onconnectionstatechange = null;
                pc.close();
                peersRef.current.delete(peerId);
            }
            offeredPeersRef.current.delete(peerId);
            makingOfferRef.current.delete(peerId);
            const audio = remoteAudioRef.current.get(peerId);
            if (audio) {
                audio.pause();
                audio.srcObject = null;
                remoteAudioRef.current.delete(peerId);
            }
            setConnectedIds((current) => current.filter((id) => id !== peerId));
        };

        const sendSignal = async (toUserId: string, type: string, payload: Record<string, unknown>) => {
            await postJson(urls.signal, {
                to_user_id: toUserId,
                type,
                payload,
            });
        };

        const ensurePeerConnection = (peerId: string, peerName: string) => {
            const existing = peersRef.current.get(peerId);
            if (existing) {
                return existing;
            }

            const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
            peersRef.current.set(peerId, pc);

            const localStream = localStreamRef.current;
            localStream?.getAudioTracks().forEach((track) => {
                pc.addTrack(track, localStream);
            });

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    void sendSignal(peerId, 'ice', { candidate: event.candidate.toJSON() }).catch(() => undefined);
                }
            };

            pc.onconnectionstatechange = () => {
                if (pc.connectionState === 'connected') {
                    setConnectedIds((current) => (current.includes(peerId) ? current : [...current, peerId]));
                    setStatus('In call');
                }
                if (pc.connectionState === 'failed') {
                    cleanupPeer(peerId);
                    return;
                }
                if (pc.connectionState === 'closed' || pc.connectionState === 'disconnected') {
                    setConnectedIds((current) => current.filter((id) => id !== peerId));
                }
            };

            pc.ontrack = (event) => {
                let audio = remoteAudioRef.current.get(peerId);
                if (!audio) {
                    audio = new Audio();
                    audio.autoplay = true;
                    remoteAudioRef.current.set(peerId, audio);
                }
                audio.srcObject = event.streams[0] ?? null;
                void audio.play().catch(() => undefined);
                setConnectedIds((current) => (current.includes(peerId) ? current : [...current, peerId]));
                setStatus(`Connected with ${peerName}`);
            };

            return pc;
        };

        const createOfferFor = async (peerId: string, peerName: string) => {
            if (offeredPeersRef.current.has(peerId) || makingOfferRef.current.has(peerId)) {
                return;
            }

            makingOfferRef.current.add(peerId);
            try {
                const pc = ensurePeerConnection(peerId, peerName);
                if (pc.signalingState !== 'stable') {
                    return;
                }
                const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: false });
                await pc.setLocalDescription(offer);
                await sendSignal(peerId, 'offer', { sdp: pc.localDescription });
                offeredPeersRef.current.add(peerId);
            } catch {
                // Peer may have left while negotiating.
            } finally {
                makingOfferRef.current.delete(peerId);
            }
        };

        const handleSignal = async (signal: SignalMessage) => {
            const fromId = signal.from_user_id;
            const peerName = peerNamesRef.current.get(fromId) ?? 'Participant';
            const pc = ensurePeerConnection(fromId, peerName);

            try {
                if (signal.type === 'offer') {
                    if (pc.signalingState !== 'stable' && pc.signalingState !== 'have-local-offer') {
                        return;
                    }
                    await pc.setRemoteDescription(signal.payload.sdp as RTCSessionDescriptionInit);
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    await sendSignal(fromId, 'answer', { sdp: pc.localDescription });
                    return;
                }

                if (signal.type === 'answer') {
                    if (!pc.currentRemoteDescription) {
                        await pc.setRemoteDescription(signal.payload.sdp as RTCSessionDescriptionInit);
                    }
                    return;
                }

                if (signal.type === 'ice' && signal.payload.candidate) {
                    await pc.addIceCandidate(signal.payload.candidate as RTCIceCandidateInit);
                }
            } catch {
                // Ignore stale negotiation races.
            }
        };

        const syncPeers = async () => {
            const data = await getJson(urls.peers);
            const nextPeers = (data.peers ?? []) as PeerInfo[];
            setPeers(nextPeers);
            peerNamesRef.current = new Map(nextPeers.map((peer) => [peer.id, peer.name]));

            const remotePeers = nextPeers.filter((peer) => peer.id !== me.id);
            const remoteIds = new Set(remotePeers.map((peer) => peer.id));

            for (const peerId of Array.from(peersRef.current.keys())) {
                if (!remoteIds.has(peerId)) {
                    cleanupPeer(peerId);
                }
            }

            for (const peer of remotePeers) {
                if (me.id < peer.id) {
                    await createOfferFor(peer.id, peer.name);
                } else {
                    ensurePeerConnection(peer.id, peer.name);
                }
            }

            if (remotePeers.length === 0) {
                setStatus('Waiting for participants to join…');
            }
        };

        const start = async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                    },
                    video: false,
                });
                localStreamRef.current = stream;

                const audioContext = new AudioContext();
                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                source.connect(analyser);
                const data = new Uint8Array(analyser.frequencyBinCount);
                let lastLevel = -1;
                let raf = 0;
                const tick = () => {
                    if (!aliveRef.current) {
                        void audioContext.close();
                        return;
                    }
                    analyser.getByteFrequencyData(data);
                    const average = data.reduce((sum, value) => sum + value, 0) / data.length;
                    const nextLevel = Math.min(100, Math.round((average / 80) * 100));
                    if (Math.abs(nextLevel - lastLevel) >= 4) {
                        lastLevel = nextLevel;
                        setMicLevel(nextLevel);
                    }
                    raf = requestAnimationFrame(tick);
                };
                raf = requestAnimationFrame(tick);

                setStatus('Waiting for participants to join…');
                await postJson(urls.heartbeat, undefined, 5000);
                await syncPeers();

                return () => {
                    cancelAnimationFrame(raf);
                    void audioContext.close();
                };
            } catch {
                setMicError('Microphone access is required for the voice call. Allow the mic and refresh.');
                setStatus('Microphone blocked');
            }
        };

        let stopMicVisual: (() => void) | undefined;
        void start().then((cleanup) => {
            if (typeof cleanup === 'function') {
                stopMicVisual = cleanup;
            }
        });

        const heartbeatTimer = window.setInterval(() => {
            void postJson(urls.heartbeat, undefined, 4000).catch(() => undefined);
        }, 8000);

        const peersTimer = window.setInterval(() => {
            void syncPeers().catch(() => undefined);
        }, 4000);

        const signalsTimer = window.setInterval(() => {
            void getJson(urls.signals, 4000)
                .then(async (data) => {
                    for (const signal of (data.signals ?? []) as SignalMessage[]) {
                        await handleSignal(signal);
                    }
                })
                .catch(() => undefined);
        }, 1500);

        const onPageHide = () => {
            if (!leftRef.current) {
                fireLeave(urls.leave);
            }
        };
        window.addEventListener('pagehide', onPageHide);

        return () => {
            aliveRef.current = false;
            window.clearInterval(heartbeatTimer);
            window.clearInterval(peersTimer);
            window.clearInterval(signalsTimer);
            window.removeEventListener('pagehide', onPageHide);
            stopMicVisual?.();
            if (!leftRef.current) {
                fireLeave(urls.leave);
            }
            localStreamRef.current?.getTracks().forEach((track) => track.stop());
            localStreamRef.current = null;
            for (const peerId of Array.from(peersRef.current.keys())) {
                cleanupPeer(peerId);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- room lifecycle binds once per mount
    }, [me.id, urls.heartbeat, urls.leave, urls.peers, urls.signal, urls.signals]);

    const toggleMute = () => {
        const next = !muted;
        localStreamRef.current?.getAudioTracks().forEach((track) => {
            track.enabled = !next;
        });
        setMuted(next);
    };

    const leaveCall = () => {
        if (leaving) {
            return;
        }
        setLeaving(true);
        leftRef.current = true;
        fireLeave(urls.leave);
        localStreamRef.current?.getTracks().forEach((track) => track.stop());
        notify('success', 'You left the voice call.');
        router.visit(urls.show, { replace: true });
    };

    const saveMinutes = async () => {
        setSavingMinutes(true);
        try {
            await postJson(urls.saveMinutes, { minutes_notes: minutes }, 10000);
            notify('success', 'Minutes saved.');
        } catch {
            notify('error', 'Could not save minutes. Try again.');
        } finally {
            setSavingMinutes(false);
        }
    };

    const saveActions = async () => {
        setSavingActions(true);
        try {
            const data = await postJson(
                urls.saveActions,
                {
                    action_items: actionItems.map((item) => ({
                        title: item.title,
                        assignee_id: item.assignee_id || null,
                        due_date: item.due_date || null,
                        status: item.status || 'open',
                    })),
                },
                10000,
            );
            setActionItems(data.action_items ?? actionItems);
            notify('success', 'Action plan saved.');
        } catch {
            notify('error', 'Could not save action plan. Try again.');
        } finally {
            setSavingActions(false);
        }
    };

    const others = peers.filter((peer) => peer.id !== me.id);
    const openFullWorkspace = () => {
        setDetailsOpen(false);
        setWorkspace('full');
    };

    const callControls = (
        <div className="flex flex-wrap items-center justify-center gap-2">
            <Button type="button" variant={muted ? 'default' : 'outline'} onClick={toggleMute}>
                {muted ? <MicOffIcon className="size-4" /> : <MicIcon className="size-4" />}
                {muted ? 'Unmute' : 'Mute'}
            </Button>
            <Button type="button" variant="destructive" onClick={leaveCall} disabled={leaving} loading={leaving}>
                <PhoneOffIcon className="size-4" />
                Leave call
            </Button>
            {workspace === 'call' ? (
                <>
                    <Button type="button" variant="outline" onClick={() => setDetailsOpen(true)}>
                        <PanelRightIcon className="size-4" />
                        Details
                    </Button>
                    <Button type="button" variant="outline" onClick={openFullWorkspace}>
                        <ExpandIcon className="size-4" />
                        Full workspace
                    </Button>
                </>
            ) : (
                <Button type="button" variant="outline" onClick={() => setWorkspace('call')}>
                    <Minimize2Icon className="size-4" />
                    Back to call
                </Button>
            )}
        </div>
    );

    return (
        <PageShell className={cn('mx-auto', workspace === 'full' ? 'max-w-5xl pb-28' : 'max-w-3xl')}>
            {workspace === 'call' ? (
                <Card className="overflow-hidden shadow-none">
                    <div className="from-primary/15 via-background to-background border-b bg-gradient-to-b px-6 py-8 text-center">
                        <Badge variant="success" className="mb-3">
                            Voice call
                        </Badge>
                        <h1 className="text-xl font-semibold tracking-tight">{meeting.title}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{status}</p>
                        <p className="text-muted-foreground mt-1 text-xs">{meeting.when_label}</p>
                    </div>

                    <CardContent className="space-y-6 pt-6">
                        {micError ? (
                            <div className="border-destructive/30 bg-destructive/5 text-destructive rounded-lg border px-4 py-3 text-sm">
                                {micError}
                            </div>
                        ) : null}

                        <div className="flex flex-col items-center gap-3">
                            <div className="relative flex size-28 items-center justify-center rounded-full border">
                                <div
                                    className="bg-primary/20 absolute inset-0 rounded-full transition-transform"
                                    style={{ transform: `scale(${1 + micLevel / 200})` }}
                                />
                                <div className="bg-primary/10 relative flex size-20 items-center justify-center rounded-full">
                                    {muted ? <MicOffIcon className="text-muted-foreground size-8" /> : <MicIcon className="text-primary size-8" />}
                                </div>
                            </div>
                            <div className="text-center">
                                <p className="font-medium">{me.name} (you)</p>
                                <p className="text-muted-foreground text-xs">
                                    {muted ? 'Muted' : 'Mic live — others will hear you when they join'}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium uppercase tracking-wide">
                                <UsersIcon className="size-3.5" />
                                In this call ({peers.length})
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {peers.map((peer) => {
                                    const isMe = peer.id === me.id;
                                    const connected = isMe || connectedIds.includes(peer.id);
                                    return (
                                        <div
                                            key={peer.id}
                                            className={cn(
                                                'flex items-center justify-between rounded-lg border px-3 py-2 text-sm',
                                                connected ? 'border-emerald-500/30 bg-emerald-500/5' : 'bg-muted/20',
                                            )}
                                        >
                                            <span className="truncate font-medium">
                                                {peer.name}
                                                {isMe ? ' (you)' : ''}
                                            </span>
                                            <Badge variant={connected ? 'success' : 'warning'}>
                                                {connected ? 'Connected' : 'Waiting'}
                                            </Badge>
                                        </div>
                                    );
                                })}
                                {others.length === 0 ? (
                                    <p className="text-muted-foreground sm:col-span-2 text-sm">
                                        Waiting for invited staff to join this voice call.
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        {callControls}
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <Badge variant="success" className="mb-2">
                                Call continues
                            </Badge>
                            <h1 className="text-xl font-semibold tracking-tight">{meeting.title}</h1>
                            <p className="text-muted-foreground text-sm">{status} · {meeting.when_label}</p>
                        </div>
                        {callControls}
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(260px,0.8fr)]">
                        <div className="space-y-4">
                            <Card className="shadow-none">
                                <CardHeader className="border-b bg-muted/20">
                                    <CardTitle className="text-base">Minutes</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 pt-6">
                                    <Textarea
                                        rows={12}
                                        value={minutes}
                                        onChange={(event) => setMinutes(event.target.value)}
                                        disabled={!canEditMinutes}
                                        placeholder="Write meeting minutes while the call continues…"
                                    />
                                    {canEditMinutes ? (
                                        <Button type="button" onClick={() => void saveMinutes()} disabled={savingMinutes} loading={savingMinutes}>
                                            Save minutes
                                        </Button>
                                    ) : null}
                                </CardContent>
                            </Card>

                            <Card className="shadow-none">
                                <CardHeader className="border-b bg-muted/20">
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle className="text-base">Action plan</CardTitle>
                                        {canManageRecords ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setActionItems((current) => [
                                                        ...current,
                                                        { title: '', assignee_id: '', due_date: '', status: 'open' },
                                                    ])
                                                }
                                            >
                                                <PlusIcon className="size-4" />
                                                Add item
                                            </Button>
                                        ) : null}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3 pt-6">
                                    {!canManageRecords ? (
                                        actionItems.length === 0 ? (
                                            <p className="text-muted-foreground text-sm">No action items yet.</p>
                                        ) : (
                                            <ul className="space-y-2">
                                                {actionItems.map((item) => (
                                                    <li key={item.id ?? item.title} className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                                                        <span>{item.title}</span>
                                                        <Badge variant={statusBadgeVariant(item.status)}>{item.status}</Badge>
                                                    </li>
                                                ))}
                                            </ul>
                                        )
                                    ) : (
                                        <>
                                            {actionItems.length === 0 ? (
                                                <p className="text-muted-foreground text-sm">Add follow-up tasks from this meeting.</p>
                                            ) : (
                                                actionItems.map((item, index) => (
                                                    <div key={index} className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_140px_120px_90px_auto]">
                                                        <Input
                                                            placeholder="Action item"
                                                            value={item.title}
                                                            onChange={(event) =>
                                                                setActionItems((current) =>
                                                                    current.map((row, rowIndex) =>
                                                                        rowIndex === index ? { ...row, title: event.target.value } : row,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <select
                                                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                                            value={item.assignee_id ?? ''}
                                                            onChange={(event) =>
                                                                setActionItems((current) =>
                                                                    current.map((row, rowIndex) =>
                                                                        rowIndex === index ? { ...row, assignee_id: event.target.value } : row,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <option value="">Assignee</option>
                                                            {staffUsers.map((user) => (
                                                                <option key={user.id} value={user.id}>
                                                                    {user.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        <Input
                                                            type="date"
                                                            value={item.due_date ?? ''}
                                                            onChange={(event) =>
                                                                setActionItems((current) =>
                                                                    current.map((row, rowIndex) =>
                                                                        rowIndex === index ? { ...row, due_date: event.target.value } : row,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <select
                                                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                                            value={item.status}
                                                            onChange={(event) =>
                                                                setActionItems((current) =>
                                                                    current.map((row, rowIndex) =>
                                                                        rowIndex === index ? { ...row, status: event.target.value } : row,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <option value="open">Open</option>
                                                            <option value="done">Done</option>
                                                        </select>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => setActionItems((current) => current.filter((_, rowIndex) => rowIndex !== index))}
                                                        >
                                                            <Trash2Icon className="size-4" />
                                                        </Button>
                                                    </div>
                                                ))
                                            )}
                                            <Button type="button" onClick={() => void saveActions()} disabled={savingActions} loading={savingActions}>
                                                Save action plan
                                            </Button>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <Card className="h-fit shadow-none">
                            <CardHeader className="border-b bg-muted/20">
                                <CardTitle className="text-base">Meeting details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6 text-sm">
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase tracking-wide">Organizer</p>
                                    <p>{meeting.organizer_name ?? '—'}</p>
                                </div>
                                {meeting.location ? (
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase tracking-wide">Venue</p>
                                        <p>{meeting.location}</p>
                                    </div>
                                ) : null}
                                {meeting.agenda ? (
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase tracking-wide">Agenda</p>
                                        <p className="whitespace-pre-wrap leading-relaxed">{meeting.agenda}</p>
                                    </div>
                                ) : null}
                                <div>
                                    <p className="text-muted-foreground mb-2 text-xs uppercase tracking-wide">
                                        Invitees ({meeting.rsvp.accepted} accepted · {meeting.rsvp.pending} pending)
                                    </p>
                                    <div className="space-y-1.5">
                                        {meeting.participants.map((participant) => (
                                            <div key={participant.id} className="flex items-center justify-between gap-2 rounded-md border px-2 py-1.5">
                                                <span className="truncate">{participant.name}</span>
                                                <Badge
                                                    variant={
                                                        participant.response === 'accepted'
                                                            ? 'success'
                                                            : participant.response === 'declined'
                                                              ? 'destructive'
                                                              : 'warning'
                                                    }
                                                >
                                                    {participant.response}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}

            {workspace === 'full' ? (
                <div className="border-border/70 bg-background/95 fixed inset-x-0 bottom-0 z-40 border-t px-4 py-3 backdrop-blur">
                    <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{meeting.title}</p>
                            <p className="text-muted-foreground text-xs">
                                {status} · {muted ? 'Muted' : 'Mic live'} · {peers.length} in call
                            </p>
                        </div>
                        {callControls}
                    </div>
                </div>
            ) : null}

            <Sheet open={detailsOpen} onOpenChange={setDetailsOpen}>
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-b px-4 py-4">
                        <SheetTitle>Meeting details</SheetTitle>
                        <SheetDescription>Quick notes while you stay on the call.</SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">When</p>
                            <p className="text-sm">{meeting.when_label}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Organizer</p>
                            <p className="text-sm">{meeting.organizer_name ?? '—'}</p>
                        </div>
                        {meeting.location ? (
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs uppercase tracking-wide">Venue</p>
                                <p className="text-sm">{meeting.location}</p>
                            </div>
                        ) : null}
                        {meeting.agenda ? (
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs uppercase tracking-wide">Agenda</p>
                                <p className="whitespace-pre-wrap text-sm leading-relaxed">{meeting.agenda}</p>
                            </div>
                        ) : null}

                        <div className="space-y-2">
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">
                                Invitees ({meeting.rsvp.accepted} accepted · {meeting.rsvp.pending} pending)
                            </p>
                            <div className="space-y-1.5">
                                {meeting.participants.map((participant) => (
                                    <div key={participant.id} className="flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5 text-sm">
                                        <span className="truncate">{participant.name}</span>
                                        <Badge
                                            variant={
                                                participant.response === 'accepted'
                                                    ? 'success'
                                                    : participant.response === 'declined'
                                                      ? 'destructive'
                                                      : 'warning'
                                            }
                                        >
                                            {participant.response}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2 border-t pt-4">
                            <p className="text-sm font-medium">Minutes</p>
                            <Textarea
                                rows={6}
                                value={minutes}
                                onChange={(event) => setMinutes(event.target.value)}
                                disabled={!canEditMinutes}
                                placeholder="Quick notes…"
                            />
                            <div className="flex flex-wrap gap-2">
                                {canEditMinutes ? (
                                    <Button type="button" onClick={() => void saveMinutes()} disabled={savingMinutes} loading={savingMinutes}>
                                        Save minutes
                                    </Button>
                                ) : null}
                                <Button type="button" variant="outline" onClick={openFullWorkspace}>
                                    <ExpandIcon className="size-4" />
                                    Open full workspace
                                </Button>
                            </div>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </PageShell>
    );
}

MeetingCall.layout = (page: ReactNode) => <AppLayout title="Voice call">{page}</AppLayout>;
