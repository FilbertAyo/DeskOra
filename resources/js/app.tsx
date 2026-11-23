import './bootstrap';

import { config, createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import { bindHttpProgress } from '@/lib/http-progress';
import { notify } from '@/lib/notify';

let historyPop = false;
let signedIn = false;

window.addEventListener(
    'popstate',
    () => {
        historyPop = true;
    },
    true,
);

function isAuthComponent(component: string): boolean {
    return component.startsWith('Auth/');
}

function isAuthPath(pathname: string): boolean {
    return (
        pathname === '/login' ||
        pathname.startsWith('/login/') ||
        pathname === '/register' ||
        pathname === '/forgot-password' ||
        pathname.startsWith('/reset-password') ||
        pathname === '/verify-email' ||
        pathname.startsWith('/verify-email/') ||
        pathname === '/confirm-password'
    );
}

window.addEventListener('pageshow', (event) => {
    if (!event.persisted || !isAuthPath(window.location.pathname)) {
        return;
    }

    window.location.reload();
});

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
        const initialUser = (
            props.initialPage.props as { auth?: { user?: unknown } }
        ).auth?.user;

        if (initialUser) {
            signedIn = true;
        }

        createRoot(el).render(<App {...props} />);
    },
    // GitHub-style slim top bar (Inertia wraps NProgress).
    progress: {
        delay: 120,
        color: '#29d',
        includeCSS: true,
        showSpinner: false,
    },
});

// Also animate the same bar for Axios / fetch API calls (not Inertia visits).
bindHttpProgress(window.axios);

router.on('navigate', (event) => {
    const page = event.detail.page as {
        component?: string;
        props?: { auth?: { user?: unknown } };
    };
    const component = page.component ?? '';
    const user = page.props?.auth?.user ?? null;

    if (user) {
        signedIn = true;
    } else if (!historyPop) {
        signedIn = false;
    }

    if (historyPop && isAuthComponent(component) && signedIn) {
        historyPop = false;
        window.history.forward();

        return;
    }

    historyPop = false;
});

router.on('httpException', (event) => {
    const status = event.detail?.response?.status;

    if (status === 419) {
        notify('error', 'Your session expired. Refresh the page and try again.');
        event.preventDefault();
        window.location.reload();
    }
});

// A weekly-report submit that the server already saved can still come back as
// the old Blade error page. Leave that page and open the report instead of
// showing the error inside a popup.
router.on('invalid', (event) => {
    const status = event.detail.response?.status ?? 0;
    const responseUrl = event.detail.response?.url ?? '';
    const here = window.location.pathname;
    const onReview =
        /\/documents\/[^/]+\/weekly-report\/(?:review|action-tasks)(?:\/|$)/.test(responseUrl) ||
        /\/documents\/[^/]+\/weekly-report\/(?:review|action-tasks)(?:\/|$)/.test(here);

    if (!onReview || ![403, 404, 500, 503].includes(status)) {
        return;
    }

    event.preventDefault();
    const reportUrl = here.replace(/\/weekly-report\/(?:review|action-tasks)\/?$/, '');
    window.location.assign(reportUrl === here ? here : reportUrl);
});
