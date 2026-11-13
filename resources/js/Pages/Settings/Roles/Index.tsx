import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import ResourceTable from '@/components/shared/ResourceTable';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/lib/page-kit';
import type { SharedProps } from '@/types/inertia';

type RoleRow = { id: number | string; name: string; permissions_count: number; users_count: number; is_system_managed: boolean; edit_url: string; destroy_url: string };

export default function RolesIndex({ roles, createUrl }: { roles: RoleRow[]; createUrl: string }) {
    const demo = usePage<SharedProps>().props.auth.user?.isDemo;
    return <>
        <PageHeader title="Roles" description={demo ? 'Demo — view only.' : 'Manage permission groups.'} actions={!demo && <Button render={<Link href={createUrl} />}>Create role</Button>} />
        <ResourceTable rows={roles} columns={[
            { label: 'Role', render: (role) => role.name },
            { label: 'Permissions', render: (role) => role.permissions_count },
            { label: 'Users', render: (role) => role.users_count },
            { label: 'Managed', render: (role) => role.is_system_managed ? 'System' : 'Custom' },
        ]} editUrl={(role) => demo || role.is_system_managed ? null : role.edit_url} destroyUrl={(role) => demo || role.is_system_managed ? null : role.destroy_url} />
    </>;
}

RolesIndex.layout = (page: ReactNode) => <AppLayout title="Roles">{page}</AppLayout>;
