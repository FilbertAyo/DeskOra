import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function UsersShow({
    user,
    editUrl,
    indexUrl,
}: {
    user: {
        name: string;
        email: string;
        title?: string | null;
        phone?: string | null;
        status: string;
        department?: { name: string } | null;
        subDepartments: Array<{ name: string }>;
        roles: string[];
        permissions: string[];
    };
    editUrl: string;
    indexUrl: string;
}) {
    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
            <PageHeader
                title={user.name}
                actions={
                    <>
                        <Button variant="outline" render={<Link href={indexUrl} />}>
                            Back
                        </Button>
                        <Button render={<Link href={editUrl} />}>Edit</Button>
                    </>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <div>Email: {user.email}</div>
                    <div>Title: {user.title || '—'}</div>
                    <div>Phone: {user.phone || '—'}</div>
                    <div>Status: {user.status}</div>
                    <div>Department: {user.department?.name || '—'}</div>
                    <div>Sub-departments: {user.subDepartments.map((item) => item.name).join(', ') || '—'}</div>
                    <div>Roles: {user.roles.join(', ') || '—'}</div>
                    <div>Permissions: {user.permissions.join(', ') || '—'}</div>
                </CardContent>
            </Card>
        </div>
    );
}

UsersShow.layout = (page: ReactNode) => <AppLayout title="User">{page}</AppLayout>;
