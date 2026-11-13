import { Head } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';

import Footer from '@/components/layout/Footer';
import Header from '@/components/layout/Header';
import AppSidebar from '@/components/layout/Sidebar';
import { ThemeProvider } from '@/components/ThemeProvider';
import AnonymousFeedbackFab from '@/components/shared/AnonymousFeedbackFab';
import FeatureTracker from '@/components/shared/FeatureTracker';
import FlashToaster from '@/components/shared/FlashToaster';
import ShellOverlays from '@/components/shared/ShellOverlays';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { flushOfflineWorkLogs } from '@/lib/offline-work-logs';
import { route } from '@/lib/route';

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    useEffect(() => {
        const storeUrl = route('weekly-work-logs.store');
        void flushOfflineWorkLogs(storeUrl);

        const onOnline = () => {
            void flushOfflineWorkLogs(storeUrl);
        };

        window.addEventListener('online', onOnline);

        return () => window.removeEventListener('online', onOnline);
    }, []);

    return (
        <ThemeProvider attribute="class" defaultTheme="system" enableSystem>
            <TooltipProvider>
                <SidebarProvider>
                    <Head title={title} />
                    <FeatureTracker />
                    <AppSidebar />
                    <SidebarInset className="flex min-w-0 flex-1 flex-col">
                        <Header />
                        <main className="relative mx-auto size-full max-w-360 flex-1 px-4 py-6 sm:px-6">
                            <div
                                aria-hidden
                                className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,color-mix(in_oklch,var(--primary)_8%,transparent),transparent_55%)]"
                            />
                            <ShellOverlays />
                            {children}
                        </main>
                        <Toaster />
                        <FlashToaster />
                        <Footer />
                    </SidebarInset>
                    <AnonymousFeedbackFab />
                </SidebarProvider>
            </TooltipProvider>
        </ThemeProvider>
    );
}
