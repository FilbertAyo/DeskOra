type Chunk = { user_id: string; seq: number; audio: string };

type PostJson = (
    url: string,
    body?: Record<string, unknown>,
    timeoutMs?: number,
) => Promise<{ chunks?: Chunk[] }>;

export type AudioRelay = {
    stop: () => void;
    setMuted: (muted: boolean) => void;
    preferDirect: (userId: string, direct: boolean) => void;
};

const TARGET_RATE = 16000;
const FRAME_MS = 60;
const FRAME_SAMPLES = Math.round((TARGET_RATE * FRAME_MS) / 1000);
const JITTER_FRAMES = 3;
const MAX_QUEUE_FRAMES = 12;
const MAX_PLAYAHEAD_SEC = 0.9;

type ProcessorContext = AudioContext & {
    createScriptProcessor: (bufferSize: number, inputs: number, outputs: number) => ScriptProcessorNode;
};

function bytesToBase64(bytes: Uint8Array): string {
    let binary = '';
    const step = 0x8000;
    for (let index = 0; index < bytes.length; index += step) {
        binary += String.fromCharCode(...bytes.subarray(index, index + step));
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

function downsample(input: Float32Array, fromRate: number, toRate: number, carry: number[]): number[] {
    const ratio = fromRate / toRate;
    const output = carry;
    let position = 0;
    while (position < input.length) {
        const left = Math.floor(position);
        const right = Math.min(left + 1, input.length - 1);
        const mix = position - left;
        const sample = ((input[left] ?? 0) * (1 - mix)) + ((input[right] ?? 0) * mix);
        output.push(sample);
        position += ratio;
    }

    return output;
}

function encodeFrame(samples: number[]): string {
    const pcm = new Int16Array(samples.length);
    for (let index = 0; index < samples.length; index += 1) {
        const sample = Math.max(-1, Math.min(1, samples[index] ?? 0));
        pcm[index] = sample < 0 ? sample * 0x8000 : sample * 0x7fff;
    }

    return bytesToBase64(new Uint8Array(pcm.buffer));
}

function decodeFrame(audio: string, context: AudioContext): AudioBuffer | null {
    const bytes = base64ToBytes(audio);
    const length = Math.floor(bytes.byteLength / 2);
    if (length < 1) {
        return null;
    }

    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const buffer = context.createBuffer(1, length, TARGET_RATE);
    const channel = buffer.getChannelData(0);
    for (let index = 0; index < length; index += 1) {
        let sample = view.getInt16(index * 2, true) / 0x8000;
        // Soft edges reduce the click/scratch between packets.
        if (index < 32) {
            sample *= index / 32;
        } else if (index > length - 33) {
            sample *= (length - 1 - index) / 32;
        }
        channel[index] = sample;
    }

    return buffer;
}

/**
 * Portal-relayed voice with a small jitter buffer so office networks stay intelligible.
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
    const processor = capture.createScriptProcessor(2048, 1, 1);
    const sink = capture.createGain();
    sink.gain.value = 0;
    source.connect(processor);
    processor.connect(sink);
    sink.connect(capture.destination);

    let muted = false;
    const direct = new Set<string>();
    const pending: number[] = [];
    const outbound: Array<{ seq: number; audio: string }> = [];
    let sending = false;
    let pulling = false;
    let seq = 0;
    const cursors: Record<string, number> = {};
    const inbox = new Map<string, Array<{ seq: number; audio: string }>>();
    const nextAt = new Map<string, number>();
    const primed = new Set<string>();
    let stopped = false;

    const pump = async () => {
        if (sending || stopped || !options.alive()) {
            return;
        }
        sending = true;
        while (!stopped && options.alive() && outbound.length > 0) {
            if (outbound.length > MAX_QUEUE_FRAMES) {
                outbound.splice(0, outbound.length - Math.floor(MAX_QUEUE_FRAMES / 2));
            }
            const batch = outbound.splice(0, 4);
            try {
                await options.post(options.pushUrl, { chunks: batch }, 4000);
            } catch {
                // Keep going; missing packets are better than freezing the call.
            }
        }
        sending = false;
    };

    processor.onaudioprocess = (event) => {
        if (stopped || muted || !options.alive()) {
            pending.length = 0;
            return;
        }

        downsample(event.inputBuffer.getChannelData(0), capture.sampleRate, TARGET_RATE, pending);

        while (pending.length >= FRAME_SAMPLES) {
            seq += 1;
            outbound.push({
                seq,
                audio: encodeFrame(pending.splice(0, FRAME_SAMPLES)),
            });
        }
        void pump();
    };

    const schedule = (userId: string, audio: string) => {
        if (direct.has(userId) || playback.state === 'closed') {
            return;
        }

        const buffer = decodeFrame(audio, playback);
        if (!buffer) {
            return;
        }

        const scheduled = nextAt.get(userId) ?? 0;
        if (scheduled > playback.currentTime + MAX_PLAYAHEAD_SEC) {
            return;
        }

        const node = playback.createBufferSource();
        const gain = playback.createGain();
        gain.gain.value = 0.95;
        node.buffer = buffer;
        node.connect(gain);
        gain.connect(playback.destination);

        const start = Math.max(
            playback.currentTime + 0.02,
            scheduled < playback.currentTime - 0.02 ? playback.currentTime + 0.05 : scheduled,
        );
        node.start(start);
        nextAt.set(userId, start + buffer.duration);
    };

    const drainInbox = () => {
        for (const [userId, frames] of inbox.entries()) {
            if (direct.has(userId)) {
                frames.length = 0;
                continue;
            }
            if (!primed.has(userId) && frames.length < JITTER_FRAMES) {
                continue;
            }
            primed.add(userId);
            while (frames.length > 0) {
                const next = frames.shift();
                if (!next) {
                    break;
                }
                schedule(userId, next.audio);
                options.onHeard(userId);
                if ((nextAt.get(userId) ?? 0) > playback.currentTime + MAX_PLAYAHEAD_SEC) {
                    break;
                }
            }
        }
    };

    const pull = async () => {
        if (stopped || pulling || !options.alive()) {
            return;
        }
        pulling = true;
        try {
            const data = await options.post(options.pullUrl, { after: cursors }, 3500);
            for (const chunk of data.chunks ?? []) {
                const previous = cursors[chunk.user_id] ?? 0;
                if (chunk.seq <= previous) {
                    continue;
                }
                cursors[chunk.user_id] = chunk.seq;
                const frames = inbox.get(chunk.user_id) ?? [];
                frames.push({ seq: chunk.seq, audio: chunk.audio });
                if (frames.length > MAX_QUEUE_FRAMES) {
                    frames.splice(0, frames.length - JITTER_FRAMES);
                    nextAt.set(chunk.user_id, playback.currentTime + 0.08);
                }
                inbox.set(chunk.user_id, frames);
            }
            drainInbox();
        } catch {
            // Retry on next tick.
        } finally {
            pulling = false;
        }
    };

    const pullTimer = window.setInterval(() => {
        void pull();
    }, 160);
    const drainTimer = window.setInterval(drainInbox, 40);
    void pull();

    const resume = () => {
        void capture.resume();
        void playback.resume();
    };
    window.addEventListener('pointerdown', resume);

    return {
        stop: () => {
            stopped = true;
            window.clearInterval(pullTimer);
            window.clearInterval(drainTimer);
            window.removeEventListener('pointerdown', resume);
            processor.onaudioprocess = null;
            try {
                processor.disconnect();
                source.disconnect();
                sink.disconnect();
            } catch {
                // Already torn down.
            }
            void capture.close();
            void playback.close();
        },
        setMuted: (next) => {
            muted = next;
            if (next) {
                pending.length = 0;
                outbound.length = 0;
            }
        },
        preferDirect: (userId, useDirect) => {
            if (useDirect) {
                direct.add(userId);
                inbox.delete(userId);
                nextAt.delete(userId);
                primed.delete(userId);
                return;
            }
            direct.delete(userId);
        },
    };
}
