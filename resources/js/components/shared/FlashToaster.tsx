import { router, usePage } from '@inertiajs/react';
import { CircleAlertIcon, CircleCheckIcon, InfoIcon, TriangleAlertIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

import { notify, subscribeNotices, type Notice, type NoticeKind } from '@/lib/notify';
import type { SharedProps } from '@/types/inertia';

type ErrorBag = Record<string, string | string[] | undefined>;

const icons: Record<NoticeKind, typeof CircleAlertIcon> = {
    success: CircleCheckIcon,
    error: CircleAlertIcon,
    info: InfoIcon,
    warning: TriangleAlertIcon,
};

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

export default function FlashToaster() {
    const { flash, errors } = usePage<SharedProps & { errors?: ErrorBag }>().props;
    const [notices, setNotices] = useState<Notice[]>([]);

    useEffect(() => {
        return subscribeNotices((notice) => {
            setNotices((current) => [...current, notice].slice(-3));
            window.setTimeout(() => {
                setNotices((current) => current.filter((item) => item.id !== notice.id));
            }, 4500);
        });
    }, []);

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

    if (notices.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-6 z-[2000] flex flex-col items-center gap-2 px-4">
            {notices.map((notice) => {
                const Icon = icons[notice.kind];

                return (
                    <div
                        key={notice.id}
                        role="status"
                        className="pointer-events-auto flex max-w-md items-center gap-2 rounded-full border border-white/10 bg-neutral-900 px-3.5 py-2.5 text-sm text-white shadow-2xl"
                    >
                        <Icon className="size-4 shrink-0" />
                        <span>{notice.message}</span>
                    </div>
                );
            })}
        </div>
    );
}
