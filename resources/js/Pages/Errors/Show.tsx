import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';

export default function Show({
    status,
    title,
    message,
}: {
    status: number;
    title: string;
    message: string;
}) {
    return (
        <div className="flex min-h-svh items-center justify-center px-4 py-10">
            <div className="w-full max-w-md text-center">
                <p className="text-muted-foreground text-sm font-medium">{status}</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">{title}</h1>
                <p className="text-muted-foreground mt-3 text-sm">{message}</p>
                <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Go back
                    </Button>
                    <Button render={<Link href="/" />}>Back home</Button>
                </div>
            </div>
        </div>
    );
}

Show.layout = (page: ReactNode) => <GuestLayout title="Something went wrong">{page}</GuestLayout>;
