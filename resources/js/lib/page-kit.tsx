import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Shared layout helper for migrated Inertia pages. */
export function withAppLayout(title: string) {
    return (page: ReactNode) => <AppLayout title={title}>{page}</AppLayout>;
}

export function PageHeader({
    title,
    description,
    actions,
    className,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="min-w-0 space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight text-balance">{title}</h1>
                {description && <p className="text-muted-foreground max-w-2xl text-sm text-pretty">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function PageShell({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('flex flex-col gap-6', className)}>{children}</div>;
}

export function SurfaceCard({
    title,
    description,
    actions,
    children,
    className,
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('overflow-hidden shadow-none', className)}>
            {(title || description || actions) && (
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0 border-b bg-muted/20">
                    <div className="min-w-0 space-y-1">
                        {title && <CardTitle className="text-base">{title}</CardTitle>}
                        {description && <CardDescription>{description}</CardDescription>}
                    </div>
                    {actions}
                </CardHeader>
            )}
            {children && <CardContent className={title || description || actions ? 'pt-6' : undefined}>{children}</CardContent>}
        </Card>
    );
}

export function EmptyState({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <Card className="shadow-none">
            <CardHeader className="items-center text-center">
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription className="max-w-md">{description}</CardDescription>}
            </CardHeader>
            {action && <CardContent className="flex justify-center pb-8">{action}</CardContent>}
        </Card>
    );
}

export function PrimaryLinkButton({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Button render={<a href={href} />}>{children}</Button>
    );
}
