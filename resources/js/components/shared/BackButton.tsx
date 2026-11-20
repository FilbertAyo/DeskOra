import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import type { ComponentProps } from 'react';

import AppLink from '@/components/shared/AppLink';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type BackButtonProps = {
    href: string;
    /** Use Inertia AppLink (prefetch). Default true. Set false for plain Link if needed. */
    prefetch?: boolean;
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
};

/** Uniform outline back control: ArrowLeft + "Back". */
export default function BackButton({ href, prefetch = true, size = 'default', className }: BackButtonProps) {
    const LinkComponent = prefetch ? AppLink : Link;

    return (
        <Button variant="outline" size={size} className={cn(className)} render={<LinkComponent href={href} />}>
            <ArrowLeftIcon data-icon="inline-start" className="size-4" />
            Back
        </Button>
    );
}
