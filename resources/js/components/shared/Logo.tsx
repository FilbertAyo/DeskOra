import { usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types/inertia';

const Logo = ({ className, wordmark = false }: { className?: string; wordmark?: boolean }) => {
    const { branding } = usePage<SharedProps>().props;

    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white p-1.5 shadow-sm ring-1 ring-black/10">
                <img src={branding.logoIcon} alt="" className="size-7 object-contain" />
            </span>
            <span className="text-lg font-semibold">{wordmark ? branding.appName : branding.productName}</span>
        </div>
    );
};

export default Logo;
