export type NoticeKind = 'success' | 'error' | 'info' | 'warning';

export type Notice = {
    id: number;
    kind: NoticeKind;
    message: string;
};

const listeners = new Set<(notice: Notice) => void>();
let nextId = 1;
let lastNotice: { message: string; at: number } | null = null;

export function notify(kind: NoticeKind, message: string): void {
    const now = Date.now();
    if (lastNotice && lastNotice.message === message && now - lastNotice.at < 800) {
        return;
    }

    lastNotice = { message, at: now };
    const notice = { id: nextId, kind, message };
    nextId += 1;
    listeners.forEach((listener) => listener(notice));
}

export function subscribeNotices(listener: (notice: Notice) => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}
