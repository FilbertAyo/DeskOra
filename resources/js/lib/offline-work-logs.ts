const STORAGE_KEY = 'deskora_offline_work_logs';

type Draft = Record<string, string | number>;

function readDrafts(): Draft[] {
    try {
        const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]') as unknown;

        return Array.isArray(parsed) ? (parsed as Draft[]) : [];
    } catch {
        return [];
    }
}

export function saveOfflineWorkLog(payload: Record<string, string>): void {
    const items = readDrafts();
    items.push({ ...payload, saved_at: Date.now() });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

export async function flushOfflineWorkLogs(storeUrl: string): Promise<void> {
    if (!navigator.onLine) {
        return;
    }

    const items = readDrafts();

    if (items.length === 0) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const remaining: Draft[] = [];

    for (const item of items) {
        try {
            const form = new FormData();

            Object.entries(item).forEach(([key, value]) => {
                if (value !== undefined && value !== null && key !== 'saved_at') {
                    form.append(key, String(value));
                }
            });

            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: form,
                credentials: 'same-origin',
            });

            if (!response.ok && response.status !== 302) {
                remaining.push(item);
            }
        } catch {
            remaining.push(item);
        }
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(remaining));
}
