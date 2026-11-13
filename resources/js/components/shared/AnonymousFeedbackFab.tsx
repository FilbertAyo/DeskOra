import { Link, usePage } from '@inertiajs/react';
import { InboxIcon } from 'lucide-react';

import { route } from '@/lib/route';
import type { SharedProps } from '@/types/inertia';

export default function AnonymousFeedbackFab() {
    const { overlays } = usePage<SharedProps>().props;
    const fab = overlays.anonymousFeedback;

    if (!fab || fab.count <= 0 || fab.hideOnCurrentPage) {
        return null;
    }

    const label = `${fab.count} new anonymous feedback${fab.count === 1 ? '' : 's'}`;

    return (
        <Link
            href={route('anonymous-feedback.index', { status: 'new' })}
            className="anonymous-feedback-fab fixed bottom-4 left-4 z-[1045] flex size-13 items-center justify-center rounded-full bg-red-600 text-white shadow-[0_4px_14px_rgba(220,53,69,0.45)] transition hover:scale-105 hover:text-white"
            aria-label={label}
            title="New anonymous feedback"
        >
            <InboxIcon className="size-5" />
            <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-red-600 bg-white px-1 text-[0.7rem] font-bold text-red-600">
                {fab.count > 99 ? '99+' : fab.count}
            </span>
        </Link>
    );
}
