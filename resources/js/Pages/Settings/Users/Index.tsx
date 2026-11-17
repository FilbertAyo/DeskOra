import { Link } from '@inertiajs/react';
import { EyeIcon, MailIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PaginationLinks, PageHeader } from '@/lib/page-kit';

type UserRow = {
    id: string | number;
    name: string;
    email: string;
    phone?: string | null;
    title?: string | null;
    status: string;
    avatar: string;
    department?: { id?: string | number; name: string } | null;
    stats: {
        documents: number;
        roles: number;
        units: number;
    };
    showUrl: string;
    editUrl: string;
};

type PaginatedUsers = {
    data: UserRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatCount(value: number): string {
    if (value >= 1000) {
        return `${(value / 1000).toFixed(value >= 10000 ? 0 : 2).replace(/\.00$/, '')}k`;
    }
    return String(value);
}

function UserConnectionCard({ user }: { user: UserRow }) {
    const subtitle = user.title?.trim() || user.department?.name || 'Staff';

    return (
        <article className="bg-card relative flex h-full flex-col rounded-xl border p-5 shadow-xs">
            <div className="absolute top-3 right-3">
                <RowActions
                    items={[
                        { label: 'View', href: user.showUrl, icon: <EyeIcon /> },
                        { label: 'Edit', href: user.editUrl, icon: <PencilIcon /> },
                    ]}
                />
            </div>

            <div className="flex flex-1 flex-col items-center text-center">
                <Avatar className="size-16">
                    <AvatarImage src={user.avatar} alt={user.name} />
                    <AvatarFallback className="text-base">{initials(user.name) || 'U'}</AvatarFallback>
                </Avatar>

                <h3 className="mt-3 line-clamp-1 text-base font-semibold tracking-tight">{user.name}</h3>
                <p className="text-muted-foreground mt-0.5 line-clamp-1 text-sm">{subtitle}</p>

                <div className="mt-3 flex min-h-7 flex-wrap items-center justify-center gap-1.5">
                    <Badge variant={statusBadgeVariant(user.status)} className="capitalize">
                        {user.status}
                    </Badge>
                </div>

                <div className="mt-5 grid w-full grid-cols-3">
                    <div className="px-2">
                        <p className="text-sm font-semibold tabular-nums">{formatCount(user.stats.documents)}</p>
                        <p className="text-muted-foreground text-[11px]">Docs</p>
                    </div>
                    <div className="border-border px-2 border-x">
                        <p className="text-sm font-semibold tabular-nums">{formatCount(user.stats.roles)}</p>
                        <p className="text-muted-foreground text-[11px]">Roles</p>
                    </div>
                    <div className="px-2">
                        <p className="text-sm font-semibold tabular-nums">{formatCount(user.stats.units)}</p>
                        <p className="text-muted-foreground text-[11px]">Units</p>
                    </div>
                </div>
            </div>

            <div className="mt-4 flex justify-center">
                <Button
                    variant="outline"
                    size="icon-sm"
                    render={<a href={`mailto:${user.email}`} aria-label={`Email ${user.name}`} />}
                >
                    <MailIcon className="size-4" />
                </Button>
            </div>
        </article>
    );
}

export default function UsersIndex({ users, urls }: { users: PaginatedUsers; urls: { create: string } }) {
    return (
        <>
            <PageHeader
                title="Users"
                description="Manage staff accounts, roles, and access."
                actions={
                    <Button render={<Link href={urls.create} />}>Add user</Button>
                }
            />

            {users.data.length === 0 ? (
                <div className="bg-card text-muted-foreground rounded-xl border px-6 py-16 text-center text-sm">
                    No users found.
                </div>
            ) : (
                <div className="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(220px,1fr))]">
                    {users.data.map((user) => (
                        <UserConnectionCard key={user.id} user={user} />
                    ))}
                </div>
            )}

            {users.links.length > 3 && <PaginationLinks className="mt-2" links={users.links} />}
        </>
    );
}

UsersIndex.layout = (page: ReactNode) => <AppLayout title="Users">{page}</AppLayout>;
