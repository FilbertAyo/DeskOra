import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import { inventoryPageConfig, makeInventoryPage } from '@/Pages/Inventory/InventoryPage';

createInertiaApp({
    title: (title) => (title ? `${title} — Adilisha Portal` : 'Adilisha Portal — DeskOra'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`] as ResolvedComponent | undefined;

        if (!page && name.startsWith('Inventory/')) {
            return makeInventoryPage(inventoryPageConfig(name));
        }

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0077b6',
    },
});
