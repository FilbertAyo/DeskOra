import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ThemeProvider } from '@/components/ThemeProvider';
import FlashToaster from '@/components/shared/FlashToaster';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

export default function GuestLayout({ title, children }: { title: string; children: ReactNode }) {
    return (
        <ThemeProvider attribute="class" defaultTheme="system" enableSystem>
            <TooltipProvider>
                <Head title={title} />
                {children}
                <FlashToaster />
                <Toaster />
            </TooltipProvider>
        </ThemeProvider>
    );
}
