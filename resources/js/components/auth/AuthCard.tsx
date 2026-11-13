import { Link, usePage } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import type { ReactNode } from 'react';

import AuthBackgroundShape from '@/assets/svg/auth-background-shape';
import ModeToggle from '@/components/layout/ModeToggle';
import Logo from '@/components/shared/Logo';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { route } from '@/lib/route';
import type { SharedProps } from '@/types/inertia';

export default function AuthCard({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    const { branding } = usePage<SharedProps>().props;

    return (
        <div className="relative flex h-auto min-h-screen items-center justify-center overflow-x-hidden px-4 py-10 sm:px-6 lg:px-8">
            <div className="absolute">
                <AuthBackgroundShape />
            </div>
            <div className="absolute top-4 right-4 z-10">
                <ModeToggle />
            </div>
            <Card className="z-1 w-full max-w-sm gap-5 py-6">
                <CardHeader className="gap-5 px-5">
                    <Link href={route('login')}>
                        <Logo className="gap-3" wordmark />
                    </Link>
                    <div>
                        <CardTitle className="mb-1.5 text-2xl font-semibold">{title}</CardTitle>
                        {description && <CardDescription className="text-sm">{description}</CardDescription>}
                    </div>
                </CardHeader>
                <CardContent className="px-5">
                    {children}
                    <div className="text-muted-foreground mt-5 flex items-center justify-center gap-2 text-xs">
                        <span className="bg-primary/10 text-primary flex size-6 items-center justify-center rounded-full">
                            <LockKeyhole className="size-3.5" />
                        </span>
                        <span>Encrypted session · staff access only</span>
                    </div>
                </CardContent>
            </Card>
            <span className="sr-only">{branding.productName}</span>
        </div>
    );
}
