type Chunk = { user_id: string; seq: number; audio: string };

type PostJson = (url: string, body?: Record<string, unknown>, timeoutMs?: number) => Promise<{ chunks?: Chunk[] }>;

export type AudioRelay = {
    stop: () => void;
    setMuted: (muted: boolean) => void;
    preferDirect: (userId: string, direct: boolean) => void;
};

const TARGET_RATE = 16000;
const FRAME_SAMPLES = 3200;

type ProcessorContext = AudioContext & {
    createScriptProcessor: (bufferSize: number, inputs: number, outputs: number) => ScriptProcessorNode;
};

function bytesToBase64(bytes: Uint8Array): string {
    let binary = '';
    for (let index = 0; index < bytes.length; index += 1) {
        binary += String.fromCharCode(bytes[index] ?? 0);
    }

    return btoa(binary);
}

function base64ToBytes(value: string): Uint8Array {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}

function encodeFrame(samples: number[]): string {
    const pcm = new Int16Array(samples.length);
    for (let index = 0; index < samples.length; index += 1) {
        const sample = Math.max(-1, Math.min(1, samples[index] ?? 0));
        pcm[index] = sample < 0 ? sample * 0x8000 : sample * 0x7fff;
    }

    return bytesToBase64(new Uint8Array(pcm.buffer));
}

/**
 * Portal-relayed voice. Works when browsers cannot open a direct WebRTC path
 * through office NAT. Audio is short PCM frames posted to the meeting.
 */
export function startAudioRelay(options: {
    stream: MediaStream;
    pushUrl: string;
    pullUrl: string;
    post: PostJson;
    onHeard: (userId: string) => void;
    alive: () => boolean;
}): AudioRelay {
    const capture = new AudioContext() as ProcessorContext;
    const playback = new AudioContext();
    void capture.resume();
    void playback.resume();

    const source = capture.createMediaStreamSource(options.stream);
    const processor = capture.createScriptProcessor(4096, 1, 1);
    const sink = capture.createGain();
    sink.gain.value = 0;
    source.connect(processor);
    processor.connect(sink);
    sink.connect(capture.destination);

    let muted = false;
    const direct = new Set<string>();
    const pending: number[] = [];
    const queue: string[] = [];
    let sending = false;
    let seq = 0;
    const cursors: Record<string, number> = {};
    const nextAt = new Map<string, number>();
    let stopped = false;

    const pump = async () => {
        if (sending) {
            return;
        }
        sending = true;
        while (!stopped && options.alive() && queue.length > 0) {
            if (queue.length > 3) {
                queue.splice(0, queue.length - 1);
            }
            const audio = queue.shift();
            if (!audio) {
                break;
            }
            seq += 1;
            try {
                await options.post(options.pushUrl, { seq, audio }, 5000);
            } catch {
                // Drop this frame and keep the call live.
            }
        }
        sending = false;
    };

    processor.onaudioprocess = (event) => {
        if (stopped || muted || !options.alive()) {
            pending.length = 0;
            return;
        }

        const input = event.inputBuffer.getChannelData(0);
        const ratio = capture.sampleRate / TARGET_RATE;
        for (let index = 0; index < input.length; index += ratio) {
            pending.push(input[Math.floor(index)] ?? 0);
        }

        while (pending.length >= FRAME_SAMPLES) {
            queue.push(encodeFrame(pending.splice(0, FRAME_SAMPLES)));
        }
        void pump();
    };

    const play = (userId: string, audio: string) => {
        if (direct.has(userId) || playback.state === 'closed') {
            return;
        }

        const scheduled = nextAt.get(userId) ?? 0;
        if (scheduled > playback.currentTime + 1.2) {
            return;
        }

        const bytes = base64ToBytes(audio);
        const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
        const length = Math.floor(bytes.byteLength / 2);
        if (length < 1) {
            return;
        }

        const buffer = playback.createBuffer(1, length, TARGET_RATE);
        const channel = buffer.getChannelData(0);
        for (let index = 0; index < length; index += 1) {
            channel[index] = view.getInt16(index * 2, true) / 0x8000;
        }

        const node = playback.createBufferSource();
        node.buffer = buffer;
        node.connect(playback.destination);
        const start = Math.max(playback.currentTime + 0.03, scheduled < playback.currentTime ? playback.currentTime + 0.03 : scheduled);
        node.start(start);
        nextAt.set(userId, start + buffer.duration);
    };

    const pull = async () => {
        if (stopped || !options.alive()) {
            return;
        }
        const data = await options.post(options.pullUrl, { after: cursors }, 4000);
        for (const chunk of data.chunks ?? []) {
            const previous = cursors[chunk.user_id] ?? 0;
            if (chunk.seq <= previous) {
                continue;
            }
            cursors[chunk.user_id] = chunk.seq;
            play(chunk.user_id, chunk.audio);
            options.onHeard(chunk.user_id);
        }
    };

    const timer = window.setInterval(() => {
        void pull().catch(() => undefined);
    }, 250);
    void pull().catch(() => undefined);

    const resume = () => {
        void capture.resume();
        void playback.resume();
    };
    window.addEventListener('pointerdown', resume);

    return {
        stop: () => {
            stopped = true;
            window.clearInterval(timer);
            window.removeEventListener('pointerdown', resume);
            processor.onaudioprocess = null;
            processor.disconnect();
            source.disconnect();
            sink.disconnect();
            void capture.close();
            void playback.close();
        },
        setMuted: (next) => {
            muted = next;
            if (next) {
                pending.length = 0;
                queue.length = 0;
            }
        },
        preferDirect: (userId, useDirect) => {
            if (useDirect) {
                direct.add(userId);
                nextAt.delete(userId);
                return;
            }
            direct.delete(userId);
        },
    };
}
