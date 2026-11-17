import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import { notify } from '@/lib/notify';
import type { SharedProps } from '@/types/inertia';

type ErrorBag = Record<string, string | string[] | undefined>;

function firstError(errors: ErrorBag | undefined): string | null {
    if (!errors) {
        return null;
    }

    for (const value of Object.values(errors)) {
        if (typeof value === 'string' && value !== '') {
            return value;
        }

        if (Array.isArray(value)) {
            const message = value.find((item) => item !== '');
            if (message) {
                return message;
            }
        }
    }

    return null;
}

/** Bridges Inertia flash/errors into Sonner toasts (bottom-right). */
export default function FlashToaster() {
    const { flash, errors } = usePage<SharedProps & { errors?: ErrorBag }>().props;
    const errorKey = JSON.stringify(errors ?? {});

    useEffect(() => {
        const message = firstError(errors);
        if (message) {
            notify('error', message);
        }
    }, [errorKey, errors]);

    useEffect(() => {
        const showValidation = (event: { detail?: { errors?: ErrorBag } }) => {
            const message = firstError(event.detail?.errors);
            if (message) {
                notify('error', message);
            }
        };

        const stopError = router.on('error', showValidation);
        const stopNetwork = router.on('networkError', () => {
            notify('error', 'The action could not be completed. Please try again.');
        });
        const stopHttp = router.on('httpException', () => {
            notify('error', 'Something went wrong. Please try again.');
        });

        return () => {
            stopError();
            stopNetwork();
            stopHttp();
        };
    }, []);

    useEffect(() => {
        if (flash?.success) {
            notify('success', flash.success);
        }
        if (flash?.status) {
            notify('success', flash.status);
        }
        if (flash?.info) {
            notify('info', flash.info);
        }
        if (flash?.warning) {
            notify('warning', flash.warning);
        }
        if (flash?.error) {
            notify('error', flash.error);
        }
    }, [flash]);

    return null;
}
