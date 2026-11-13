import { Link, router, usePage } from '@inertiajs/react';
import { BellIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { route } from '@/lib/route';
import type { SharedProps } from '@/types/inertia';

export default function NotificationMenu() {
    const { notifications } = usePage<SharedProps>().props;
    const unread = notifications.unread;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" size="icon" className="relative" />}>
                <BellIcon />
                {unread > 0 && (
                    <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium">
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
                <span className="sr-only">Notifications</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex items-center justify-between">
                        <span>Notifications</span>
                        {unread > 0 && (
                            <Button
                                type="button"
                                variant="link"
                                className="text-primary h-auto px-0 text-xs font-normal"
                                data-action="read-all"
                                onClick={() => router.post(route('notifications.read-all'))}
                            >
                                Mark all read
                            </Button>
                        )}
                    </DropdownMenuLabel>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <p className="text-muted-foreground px-3 py-6 text-center text-sm">No new notifications.</p>
                ) : (
                    notifications.items.map((item) => (
                        <DropdownMenuItem key={item.id} className="items-start" render={<a href={item.href} />}>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="truncate font-medium">{item.title}</span>
                                <span className="text-muted-foreground line-clamp-2 text-xs">{item.message}</span>
                                <span className="text-muted-foreground text-xs">{item.time}</span>
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem render={<Link href={route('notifications.inbox')} />}>View all notifications</DropdownMenuItem>
                {notifications.canManage && (
                    <DropdownMenuItem render={<Link href={route('notification.index')} />}>Notification settings</DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
