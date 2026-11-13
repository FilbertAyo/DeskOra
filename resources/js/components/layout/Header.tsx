import { Fragment } from 'react';
import { usePage } from '@inertiajs/react';

import ModeToggle from '@/components/layout/ModeToggle';
import NotificationMenu from '@/components/shared/NotificationMenu';
import ProfileDropdown from '@/components/shared/ProfileDropdown';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { SharedProps } from '@/types/inertia';

export default function Header() {
    const page = usePage<SharedProps>();
    const { currentPeriod } = page.props;
    const segments = page.url.split('?')[0].split('/').filter(Boolean);

    return (
        <header className="bg-card/80 sticky top-0 z-50 border-b backdrop-blur-md supports-backdrop-filter:bg-card/70">
            <div className="mx-auto flex items-center justify-between gap-4 px-4 py-2.5 sm:px-6">
                <div className="flex min-w-0 items-center gap-4">
                    <SidebarTrigger className="[&_svg]:size-5!" />
                    <Separator orientation="vertical" className="hidden h-4! sm:block" />
                    <Breadcrumb className="hidden min-w-0 sm:block">
                        <BreadcrumbList>
                            {segments.length === 0 ? (
                                <BreadcrumbItem>
                                    <BreadcrumbPage>Dashboard</BreadcrumbPage>
                                </BreadcrumbItem>
                            ) : (
                                segments.map((segment, index) => {
                                    const isLast = index === segments.length - 1;
                                    const label = segment.replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

                                    return (
                                        <Fragment key={`${segment}-${index}`}>
                                            <BreadcrumbItem>
                                                {isLast ? <BreadcrumbPage>{label}</BreadcrumbPage> : <span>{label}</span>}
                                            </BreadcrumbItem>
                                            {!isLast && <BreadcrumbSeparator />}
                                        </Fragment>
                                    );
                                })
                            )}
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
                <div className="flex items-center gap-1.5">
                    {currentPeriod && (
                        <div className="text-muted-foreground mr-2 hidden items-center gap-2 text-sm lg:flex">
                            <span>
                                Week {currentPeriod.week_number} · {currentPeriod.date_range}
                            </span>
                            <Badge variant="secondary" className="capitalize">
                                {currentPeriod.status}
                            </Badge>
                        </div>
                    )}
                    <ModeToggle />
                    <NotificationMenu />
                    <ProfileDropdown />
                </div>
            </div>
        </header>
    );
}
