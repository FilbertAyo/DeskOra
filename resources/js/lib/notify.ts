import { toast } from 'sonner';

export type NoticeKind = 'success' | 'error' | 'info' | 'warning';

export type Notice = {
    id: number;
    kind: NoticeKind;
    message: string;
};

let lastNotice: { message: string; at: number } | null = null;

/** App-wide notices via Sonner (template-style, bottom-right). */
export function notify(kind: NoticeKind, message: string): void {
    const now = Date.now();
    if (lastNotice && lastNotice.message === message && now - lastNotice.at < 800) {
        return;
    }

    lastNotice = { message, at: now };

    switch (kind) {
        case 'success':
            toast.success(message);
            return;
        case 'error':
            toast.error(message);
            return;
        case 'warning':
            toast.warning(message);
            return;
        case 'info':
            toast.info(message);
            return;
        default: {
            const _exhaustive: never = kind;
            void _exhaustive;
            toast(message);
        }
    }
}

/** @deprecated Kept for older callers; Sonner does not need subscriptions. */
export function subscribeNotices(_listener: (notice: Notice) => void): () => void {
    return () => undefined;
}
