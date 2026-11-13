import { useEffect } from 'react';

import { route } from '@/lib/route';

export default function FeatureTracker() {
    useEffect(() => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (!token) {
            return;
        }

        const endpoint = route('analytics.events.store');
        const recent = new Map<string, number>();

        const onClick = (event: MouseEvent) => {
            const target = event.target instanceof Element ? event.target.closest('[data-feature]') : null;
            const featureKey = target?.getAttribute('data-feature');

            if (!featureKey) {
                return;
            }

            const now = Date.now();

            if (now - (recent.get(featureKey) ?? 0) < 1500) {
                return;
            }

            recent.set(featureKey, now);

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ feature_key: featureKey, properties: null }),
                keepalive: true,
            }).catch(() => undefined);
        };

        document.addEventListener('click', onClick, true);

        return () => document.removeEventListener('click', onClick, true);
    }, []);

    return null;
}
