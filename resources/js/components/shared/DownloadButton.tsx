import { DownloadIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type DownloadButtonProps = {
    href: string;
    children?: ReactNode;
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
    /** Open in new tab when true */
    external?: boolean;
};

/** Uniform outline download CTA: DownloadIcon + label. */
export default function DownloadButton({
    href,
    children = 'Download PDF',
    size = 'sm',
    className,
    external = false,
}: DownloadButtonProps) {
    return (
        <Button
            variant="outline"
            size={size}
            className={cn(className)}
            render={
                <a href={href} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})} />
            }
        >
            <DownloadIcon />
            {children}
        </Button>
    );
}
