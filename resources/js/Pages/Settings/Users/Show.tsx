import { Link } from '@inertiajs/react';
import { EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Timeline,
    TimelineContent,
    TimelineDot,
    TimelineHeading,
    TimelineItem,
    TimelineLine,
} from '@/components/ui/timeline';
import { PageHeader } from '@/lib/page-kit';

type ActivityItem = {
    id: string | number;
    title: string;
    description?: string | null;
    time?: string | null;
};

type DocumentRow = {
    id: string | number;
    title: string;
    type?: string | null;
    updated?: string | null;
    href: string;
};

function Detail({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3 py-1.5 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className="min-w-0 text-right font-medium">{value}</span>
        </div>
    );
}

export default function UsersShow({
    user,
    activities = [],
    documents = [],
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
    activities?: ActivityItem[];
    documents?: DocumentRow[];
    editUrl: string;
    indexUrl: string;
}) {
    const initials = user.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
    const role = user.roles[0] ?? user.title ?? 'Staff';

    return (
        <div className="flex flex-col gap-4">
            <PageHeader
                title={user.name}
                description="Account, recent documents, and activity."
                actions={
                    <>
                        <Button variant="outline" size="sm" render={<Link href={indexUrl} />}>
                            Back
                        </Button>
                        <Button size="sm" render={<Link href={editUrl} />}>
                            Edit
                        </Button>
                    </>
                }
            />
            <div className="grid items-start gap-4 lg:grid-cols-[17rem_1fr]">
                <Card className="shadow-none">
                    <CardContent className="flex flex-col items-center gap-3 pt-5 text-center">
                        <div className="bg-muted flex size-16 items-center justify-center rounded-full text-sm font-semibold">
                            {initials || 'U'}
                        </div>
                        <div>
                            <div className="text-sm font-semibold">{user.name}</div>
                            <Badge variant="outline" className="mt-1">
                                {role}
                            </Badge>
                        </div>
                        <div className="grid w-full grid-cols-2 gap-2">
                            <div className="bg-muted/50 rounded-lg px-2 py-2">
                                <div className="text-base font-semibold">{user.permissions.length}</div>
                                <div className="text-muted-foreground text-[11px]">Permissions</div>
                            </div>
                            <div className="bg-muted/50 rounded-lg px-2 py-2">
                                <div className="text-base font-semibold">{documents.length}</div>
                                <div className="text-muted-foreground text-[11px]">Documents</div>
                            </div>
                        </div>
                        <div className="w-full border-t pt-2 text-left">
                            <p className="text-muted-foreground mb-1 text-[11px] font-medium tracking-wide uppercase">Details</p>
                            <Detail label="Email" value={user.email} />
                            <Detail
                                label="Status"
                                value={
                                    <Badge variant={statusBadgeVariant(user.status)} className="capitalize">
                                        {user.status}
                                    </Badge>
                                }
                            />
                            <Detail label="Role" value={user.roles.join(', ') || '—'} />
                            <Detail label="Title" value={user.title || '—'} />
                            <Detail label="Phone" value={user.phone || '—'} />
                            <Detail label="Department" value={user.department?.name || '—'} />
                            <Detail label="Sub-departments" value={user.subDepartments.map((item) => item.name).join(', ') || '—'} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="shadow-none">
                        <CardHeader className="border-b py-3">
                            <CardTitle className="text-sm">Documents</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Document</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Updated</TableHead>
                                        <TableHead className="w-12 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                                No documents yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        documents.map((document) => (
                                            <TableRow key={document.id}>
                                                <TableCell className="max-w-56 truncate font-medium">{document.title}</TableCell>
                                                <TableCell className="text-muted-foreground">{document.type || '—'}</TableCell>
                                                <TableCell className="text-muted-foreground">{document.updated || '—'}</TableCell>
                                                <TableCell className="text-right">
                                                    <RowActions items={[{ label: 'Open', href: document.href, icon: <EyeIcon /> }]} />
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader className="border-b py-3">
                            <CardTitle className="text-sm">Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4">
                            {activities.length === 0 ? (
                                <p className="text-muted-foreground text-xs">No recent activity.</p>
                            ) : (
                                <Timeline positions="left">
                                    {activities.map((item, index) => (
                                        <TimelineItem key={item.id} status="done">
                                            <TimelineHeading className="flex w-full items-center justify-between gap-3 text-xs">
                                                <span>{item.title}</span>
                                                {item.time && <span className="text-muted-foreground shrink-0 font-normal">{item.time}</span>}
                                            </TimelineHeading>
                                            <TimelineDot status="current" />
                                            {index < activities.length - 1 && <TimelineLine done />}
                                            <TimelineContent>
                                                {item.description && <p className="text-muted-foreground text-xs">{item.description}</p>}
                                            </TimelineContent>
                                        </TimelineItem>
                                    ))}
                                </Timeline>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

UsersShow.layout = (page: ReactNode) => <AppLayout title="User">{page}</AppLayout>;
