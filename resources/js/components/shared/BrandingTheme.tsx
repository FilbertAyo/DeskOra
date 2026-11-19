import { Head, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import type { SharedProps } from '@/types/inertia';

/** Applies system branding (primary color + favicon) across the authenticated shell. */
export default function BrandingTheme() {
    const { branding } = usePage<SharedProps>().props;

    useEffect(() => {
        const root = document.documentElement;
        const color = branding.primaryColor?.trim();

        if (!color || !/^#[0-9A-Fa-f]{6}$/.test(color)) {
            return;
        }

        root.style.setProperty('--primary', color);
        root.style.setProperty('--ring', color);
        root.style.setProperty('--sidebar-primary', color);
        root.style.setProperty('--sidebar-ring', color);

        return () => {
            root.style.removeProperty('--primary');
            root.style.removeProperty('--ring');
            root.style.removeProperty('--sidebar-primary');
            root.style.removeProperty('--sidebar-ring');
        };
    }, [branding.primaryColor]);

    return (
        <Head>
            <link rel="icon" href={branding.logoIcon} type="image/png" />
            <link rel="apple-touch-icon" href={branding.logoIcon} />
        </Head>
    );
}
