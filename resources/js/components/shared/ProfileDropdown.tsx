import { router, usePage } from '@inertiajs/react';
import { LogOutIcon, SettingsIcon, UserIcon } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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

export default function ProfileDropdown() {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={<Button variant="ghost" size="icon" className="relative rounded-full hover:bg-transparent" />}
            >
                <Avatar>
                    <AvatarImage src={user.avatar} alt={user.name} />
                    <AvatarFallback>{user.initials}</AvatarFallback>
                </Avatar>
                <span className="ring-card absolute right-0 bottom-0 block size-2 rounded-full bg-green-600 ring-2" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex items-center gap-4 px-2 py-2.5 font-normal">
                        <div className="relative">
                            <Avatar className="size-10">
                                <AvatarImage src={user.avatar} alt={user.name} />
                                <AvatarFallback>{user.initials}</AvatarFallback>
                            </Avatar>
                            <span className="ring-card absolute right-0 bottom-0 block size-2 rounded-full bg-green-600 ring-2" />
                        </div>
                        <div className="flex min-w-0 flex-1 flex-col items-start">
                            <span className="text-foreground w-full truncate text-base font-semibold">{user.name}</span>
                            <span className="text-muted-foreground w-full truncate text-sm">{user.email}</span>
                        </div>
                    </DropdownMenuLabel>
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                <DropdownMenuGroup>
                    <DropdownMenuItem render={<a href={route('profile.edit')} />}>
                        <UserIcon />
                        <span>My profile</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem render={<a href={`${route('profile.edit')}?tab=settings`} />}>
                        <SettingsIcon />
                        <span>Settings</span>
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                <DropdownMenuGroup>
                    <DropdownMenuItem variant="destructive" onClick={() => router.post(route('logout'))}>
                        <LogOutIcon />
                        <span>Sign out</span>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
