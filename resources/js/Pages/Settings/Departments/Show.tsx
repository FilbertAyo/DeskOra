import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function DepartmentsShow({
    department,
    users,
    indexUrl,
}: {
    department: {
        name: string;
        status: string;
        users_count?: number;
        children_count?: number;
        parent?: { name: string } | null;
        head?: { name: string } | null;
        children: Array<{ id: string | number; name: string; users_count?: number }>;
    };
    users: Array<{ id: string | number; name: string; email: string; status: string }>;
    indexUrl: string;
}) {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title={department.name}
                description={`Status: ${department.status}`}
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Overview</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <div>Parent: {department.parent?.name || '—'}</div>
                    <div>Head: {department.head?.name || '—'}</div>
                    <div>Users: {department.users_count ?? users.length}</div>
                    <div>Sub-departments: {department.children_count ?? department.children.length}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Members</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {users.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No members.</p>
                    ) : (
                        users.map((user) => (
                            <div key={user.id} className="rounded-lg border px-3 py-2 text-sm">
                                {user.name} · {user.email} · {user.status}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

DepartmentsShow.layout = (page: ReactNode) => <AppLayout title="Department">{page}</AppLayout>;
