import { config, createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import { notify } from '@/lib/notify';

config.extend({
    prefetch: {
        cacheFor: '60s',
        hoverDelay: 75,
    },
});

const pages = import.meta.glob('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} — Adilisha Portal` : 'Adilisha Portal — DeskOra'),
    resolve: async (name) => {
        const importer = pages[`./Pages/${name}.tsx`];

        if (!importer && name.startsWith('Inventory/')) {
            const { inventoryPageConfig, makeInventoryPage } = await import('@/Pages/Inventory/InventoryPage');

            return makeInventoryPage(inventoryPageConfig(name));
        }

        if (!importer) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return importer();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: false,
});

router.on('httpException', (event) => {
    const status = event.detail?.response?.status;

    if (status === 419) {
        notify('error', 'Your session expired. Refresh the page and try again.');
        event.preventDefault();
        window.location.reload();
    }
});
