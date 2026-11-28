import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLink from '@/components/shared/AppLink';
import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Period = { id: string; label: string; date_range?: string };
type StaffUser = { id: string; name: string };
type StatusOption = { value: string; label: string };
type Row = {
    recipient_id: string;
    task_id: string;
    title: string;
    assigned_by: string;
    assignee: string;
    date_assigned: string | null;
    deadline: string | null;
    status: string;
    report_feedback: string | null;
    show_url: string;
    report_url: string | null;
};

type Props = {
    rows: Row[];
    filters: { period_id: string | null; status: string | null; assignee_user_id: string | null };
    periods: Period[];
    staffUsers: StaffUser[];
    statusOptions: StatusOption[];
    createUrl: string;
    indexUrl: string;
};

function statusLabel(status: string): string {
    switch (status) {
        case 'in_progress':
            return 'In Progress';
        case 'completed':
            return 'Completed';
        case 'overdue':
            return 'Overdue';
        case 'pending':
            return 'Pending';
        default:
            return status;
    }
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'overdue':
            return 'destructive';
        case 'in_progress':
            return 'secondary';
        default:
            return 'outline';
    }
}

export default function AssignedTasksIndex({
    rows,
    filters,
    periods,
    staffUsers,
    statusOptions,
    createUrl,
    indexUrl,
}: Props) {
    const apply = (key: string, value: string) => {
        router.get(
            indexUrl,
            {
                period_id: filters.period_id || undefined,
                status: filters.status || undefined,
                assignee_user_id: filters.assignee_user_id || undefined,
                [key]: value || undefined,
            },
            { preserveState: true },
        );
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-lg font-semibold">Management Assigned Tasks</h1>
                    <p className="text-muted-foreground text-sm">
                        Monitor tasks assigned by management and staff progress from weekly reports.
                    </p>
                </div>
                <Button render={<AppLink href={createUrl} />}>Assign Task</Button>
            </div>

            <div className="flex flex-wrap gap-3">
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={filters.period_id ?? ''}
                    onChange={(event) => apply('period_id', event.target.value)}
                >
                    <option value="">All weeks</option>
                    {periods.map((period) => (
                        <option key={period.id} value={period.id}>
                            {period.label}
                            {period.date_range ? ` (${period.date_range})` : ''}
                        </option>
                    ))}
                </select>
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={filters.status ?? ''}
                    onChange={(event) => apply('status', event.target.value)}
                >
                    <option value="">All statuses</option>
                    {statusOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={filters.assignee_user_id ?? ''}
                    onChange={(event) => apply('assignee_user_id', event.target.value)}
                >
                    <option value="">All staff</option>
                    {staffUsers.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.name}
                        </option>
                    ))}
                </select>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Assigned tasks</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Task</TableHead>
                                <TableHead>Assigned By</TableHead>
                                <TableHead>Assigned To</TableHead>
                                <TableHead>Date Assigned</TableHead>
                                <TableHead>Deadline</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Report</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.recipient_id}>
                                    <TableCell>
                                        <AppLink
                                            href={row.show_url}
                                            className="text-sky-700 hover:underline dark:text-sky-400"
                                        >
                                            {row.title}
                                        </AppLink>
                                    </TableCell>
                                    <TableCell>{row.assigned_by || '—'}</TableCell>
                                    <TableCell>{row.assignee || '—'}</TableCell>
                                    <TableCell>{row.date_assigned || '—'}</TableCell>
                                    <TableCell>{row.deadline || '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant(row.status)}>{statusLabel(row.status)}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        {row.report_feedback ? (
                                            <span className="line-clamp-2 text-sm">{row.report_feedback}</span>
                                        ) : row.report_url ? (
                                            <AppLink
                                                href={row.report_url}
                                                className="text-sky-700 hover:underline dark:text-sky-400"
                                            >
                                                View report
                                            </AppLink>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {rows.length === 0 && (
                        <p className="text-muted-foreground py-8 text-center">No management-assigned tasks found.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

AssignedTasksIndex.layout = (page: ReactNode) => (
    <AppLayout title="Management Assigned Tasks">{page}</AppLayout>
);
