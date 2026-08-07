<div id="pwaInstallBanner" class="position-fixed bottom-0 start-50 translate-middle-x mb-3 d-none" style="z-index: 1080; max-width: 420px; width: calc(100% - 1.5rem);">
    <div class="card shadow border-0">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="flex-grow-1">
                <strong class="d-block">Install DeskOra</strong>
                <span class="small text-muted">Add to your home screen for faster work logs.</span>
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="pwaInstallBtn">Install</button>
            <button type="button" class="btn btn-sm btn-light" id="pwaInstallDismiss" aria-label="Dismiss">&times;</button>
        </div>
    </div>
</div>

<script>
(() => {
    const DISMISS_KEY = 'deskora_pwa_install_dismissed';
    const LEGACY_DISMISS_KEY = 'workhub_pwa_install_dismissed';
    const OFFLINE_KEY = 'deskora_offline_work_logs';
    const LEGACY_OFFLINE_KEY = 'workhub_offline_work_logs';
    const banner = document.getElementById('pwaInstallBanner');
    const installBtn = document.getElementById('pwaInstallBtn');
    const dismissBtn = document.getElementById('pwaInstallDismiss');
    let deferredPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
        });
    }

    // Migrate legacy offline drafts once.
    (function migrateLegacyOfflineDrafts() {
        try {
            const legacy = localStorage.getItem(LEGACY_OFFLINE_KEY);
            if (!legacy) return;
            const existing = JSON.parse(localStorage.getItem(OFFLINE_KEY) || '[]');
            const legacyItems = JSON.parse(legacy || '[]');
            if (Array.isArray(legacyItems) && legacyItems.length) {
                localStorage.setItem(OFFLINE_KEY, JSON.stringify([...existing, ...legacyItems]));
            }
            localStorage.removeItem(LEGACY_OFFLINE_KEY);
        } catch (e) {}
    })();

    if (localStorage.getItem(LEGACY_DISMISS_KEY) === '1' && localStorage.getItem(DISMISS_KEY) !== '1') {
        localStorage.setItem(DISMISS_KEY, '1');
        localStorage.removeItem(LEGACY_DISMISS_KEY);
    }

    window.DeskOraOfflineLogs = {
        key: OFFLINE_KEY,
        saveDraft(payload) {
            const items = JSON.parse(localStorage.getItem(this.key) || '[]');
            items.push({ ...payload, saved_at: Date.now() });
            localStorage.setItem(this.key, JSON.stringify(items));
        },
        all() {
            return JSON.parse(localStorage.getItem(this.key) || '[]');
        },
        clear() {
            localStorage.removeItem(this.key);
        },
        async flush() {
            if (!navigator.onLine) return;
            const items = this.all();
            if (!items.length) return;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const remaining = [];
            for (const item of items) {
                try {
                    const form = new FormData();
                    Object.entries(item).forEach(([key, value]) => {
                        if (value !== undefined && value !== null && key !== 'saved_at') {
                            form.append(key, value);
                        }
                    });
                    const response = await fetch('{{ route('weekly-work-logs.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: form,
                    });
                    if (!response.ok && response.status !== 302) {
                        remaining.push(item);
                    }
                } catch (e) {
                    remaining.push(item);
                }
            }
            localStorage.setItem(this.key, JSON.stringify(remaining));
        }
    };

    window.addEventListener('online', () => {
        window.DeskOraOfflineLogs.flush();
    });
    window.DeskOraOfflineLogs.flush();

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        if (localStorage.getItem(DISMISS_KEY) === '1' || !banner) return;
        banner.classList.remove('d-none');
    });

    installBtn?.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        banner?.classList.add('d-none');
    });

    dismissBtn?.addEventListener('click', () => {
        localStorage.setItem(DISMISS_KEY, '1');
        banner?.classList.add('d-none');
    });
})();
</script>
