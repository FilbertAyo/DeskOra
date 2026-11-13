import { router, usePage } from '@inertiajs/react';
import { BellOffIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { route } from '@/lib/route';
import type { SharedProps } from '@/types/inertia';

type InboxItem = {
    id: string;
    title: string;
    message: string;
    isUnread: boolean;
    time: string | null;
    relative: string | null;
    href: string;
};

type Pagination = {
    current: number;
    last: number;
    prev: string | null;
    next: string | null;
};

export default function Inbox({
    unreadCount,
    items,
    pagination,
}: {
    unreadCount: number;
    items: InboxItem[];
    pagination: Pagination;
}) {
    const { notifications } = usePage<SharedProps>().props;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>Your notifications</CardTitle>
                    <CardDescription>
                        {unreadCount > 0 ? `${unreadCount} unread` : 'You are caught up.'}
                    </CardDescription>
                </div>
                {unreadCount > 0 && (
                    <Button variant="outline" type="button" data-action="read-all" onClick={() => router.post(route('notifications.read-all'))}>
                        Mark all as read
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {items.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center gap-3 px-6 py-16 text-center">
                        <BellOffIcon className="size-10" />
                        <p>You have no notifications yet.</p>
                    </div>
                ) : (
                    <ul className="divide-border divide-y">
                        {items.map((item) => (
                            <li key={item.id} className={item.isUnread ? 'bg-primary/5' : undefined}>
                                <div className="flex items-start justify-between gap-4 px-6 py-4">
                                    <div className="min-w-0">
                                        <div className="mb-1 flex items-center gap-2">
                                            <a href={item.href} className="font-medium hover:underline">
                                                {item.title}
                                            </a>
                                            {item.isUnread && <Badge>New</Badge>}
                                        </div>
                                        <p className="text-muted-foreground text-sm">{item.message}</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {item.time}
                                            {item.relative ? ` (${item.relative})` : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col gap-2">
                                        <Button variant="outline" size="sm" render={<a href={item.href} />}>
                                            Open
                                        </Button>
                                        {item.isUnread && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                data-action="mark-read"
                                                onClick={() => router.post(route('notifications.read', { notification: item.id }))}
                                            >
                                                Mark read
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                {pagination.last > 1 && (
                    <div className="flex items-center justify-between px-6 pt-4">
                        <p className="text-muted-foreground text-sm">
                            Page {pagination.current} of {pagination.last}
                        </p>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" disabled={!pagination.prev} render={pagination.prev ? <a href={pagination.prev} /> : undefined}>
                                Previous
                            </Button>
                            <Button variant="outline" size="sm" disabled={!pagination.next} render={pagination.next ? <a href={pagination.next} /> : undefined}>
                                Next
                            </Button>
                        </div>
                    </div>
                )}
                {notifications.canManage && (
                    <div className="px-6 pt-4 text-right">
                        <a href={route('notification.index')} className="text-muted-foreground text-sm hover:underline">
                            Notification settings
                        </a>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

Inbox.layout = (page: ReactNode) => <AppLayout title="Notifications">{page}</AppLayout>;
