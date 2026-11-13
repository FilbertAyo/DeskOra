import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import ResourceTable from '@/components/shared/ResourceTable';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

type UserRow = {
    id: string | number;
    name: string;
    email: string;
    phone?: string | null;
    status: string;
    department?: { name: string } | null;
    roles?: Array<{ name: string }>;
};

export default function UsersIndex({ users, urls }: { users: { data: UserRow[] }; urls: { create: string } }) {
    return <>
        <PageHeader title="Users" description="Manage staff accounts, roles, and access." actions={<Button render={<Link href={urls.create} />}>Add user</Button>} />
        <ResourceTable rows={users.data} columns={[
            { label: 'Name', render: (user) => user.name },
            { label: 'Email', render: (user) => user.email },
            { label: 'Department', render: (user) => user.department?.name ?? '—' },
            { label: 'Roles', render: (user) => user.roles?.map((role) => role.name).join(', ') || '—' },
            { label: 'Status', render: (user) => user.status },
        ]} editUrl={(user) => route('admin.edit', user.id)} />
    </>;
}

UsersIndex.layout = (page: ReactNode) => <AppLayout title="Users">{page}</AppLayout>;
