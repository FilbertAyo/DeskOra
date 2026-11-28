import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AppLink from '@/components/shared/AppLink';
import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

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
    defaultPeriodId: string;
    submitUrl: string;
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
    defaultPeriodId,
    submitUrl,
    indexUrl,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '',
        description: '',
        period_id: defaultPeriodId,
        deadline: '',
        assignee_user_ids: [] as string[],
    });

    const closeAssignModal = () => {
        setOpen(false);
        form.reset();
        form.setData('period_id', defaultPeriodId);
        form.clearErrors();
    };

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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(submitUrl, {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', 'Task assigned successfully.');
                closeAssignModal();
            },
        });
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
                <Button type="button" onClick={() => setOpen(true)}>
                    Assign Task
                </Button>
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

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) {
                        closeAssignModal();
                    } else {
                        setOpen(true);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Assign Task</DialogTitle>
                        <DialogDescription>
                            Assign a management task to one or more staff members for a selected work week.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <Field>
                            <FieldLabel htmlFor="task-title">Title</FieldLabel>
                            <Input
                                id="task-title"
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                required
                            />
                            <FieldError>{form.errors.title}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-description">Description</FieldLabel>
                            <Textarea
                                id="task-description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                rows={4}
                            />
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-period">Week</FieldLabel>
                            <select
                                id="task-period"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.period_id}
                                onChange={(event) => form.setData('period_id', event.target.value)}
                                required
                            >
                                <option value="" disabled>
                                    Select week
                                </option>
                                {periods.map((period) => (
                                    <option key={period.id} value={period.id}>
                                        {period.label}
                                        {period.date_range ? ` (${period.date_range})` : ''}
                                    </option>
                                ))}
                            </select>
                            <FieldError>{form.errors.period_id}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-deadline">Deadline (optional)</FieldLabel>
                            <Input
                                id="task-deadline"
                                type="date"
                                value={form.data.deadline}
                                onChange={(event) => form.setData('deadline', event.target.value)}
                            />
                            <FieldError>{form.errors.deadline}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-assignees">Assign to</FieldLabel>
                            <select
                                id="task-assignees"
                                multiple
                                className="border-input bg-background min-h-40 w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.assignee_user_ids}
                                onChange={(event) =>
                                    form.setData(
                                        'assignee_user_ids',
                                        Array.from(event.target.selectedOptions, (option) => option.value),
                                    )
                                }
                                required
                            >
                                {staffUsers.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Hold Ctrl (Windows) or Cmd (Mac) to select multiple staff members.
                            </p>
                            <FieldError>{form.errors.assignee_user_ids}</FieldError>
                        </Field>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeAssignModal}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Assign task
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

AssignedTasksIndex.layout = (page: ReactNode) => (
    <AppLayout title="Management Assigned Tasks">{page}</AppLayout>
);
