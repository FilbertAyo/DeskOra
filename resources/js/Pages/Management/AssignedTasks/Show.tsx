import type { ReactNode } from 'react';

import AppLink from '@/components/shared/AppLink';
import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Recipient = {
    recipient_id: string;
    assignee: string;
    status: string;
    progress: string | null;
    report_feedback: string | null;
    report_url: string | null;
};

type Task = {
    id: string;
    title: string;
    description: string;
    deadline: string | null;
    date_assigned: string | null;
    assigned_by: string;
    period_label: string | null;
    recipients: Recipient[];
    index_url: string;
};

type Props = { task: Task };

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

export default function AssignedTasksShow({ task }: Props) {
    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-lg font-semibold">{task.title}</h1>
                    <p className="text-muted-foreground text-sm">
                        Assigned by {task.assigned_by || '—'}
                        {task.period_label ? ` · ${task.period_label}` : ''}
                    </p>
                </div>
                <Button type="button" variant="outline" render={<AppLink href={task.index_url} />}>
                    Back to list
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Task details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    <div>
                        <p className="text-muted-foreground text-xs uppercase">Description</p>
                        <p className="whitespace-pre-wrap">{task.description || 'No description provided.'}</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Date assigned</p>
                            <p>{task.date_assigned || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Deadline</p>
                            <p>{task.deadline || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Assignees</p>
                            <p>{task.recipients.length}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Staff progress</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Assigned To</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Progress</TableHead>
                                <TableHead>Report feedback</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {task.recipients.map((recipient) => (
                                <TableRow key={recipient.recipient_id}>
                                    <TableCell>{recipient.assignee || '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant(recipient.status)}>
                                            {statusLabel(recipient.status)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{recipient.progress || '—'}</TableCell>
                                    <TableCell>
                                        {recipient.report_feedback ? (
                                            <span className="whitespace-pre-wrap text-sm">
                                                {recipient.report_feedback}
                                            </span>
                                        ) : recipient.report_url ? (
                                            <AppLink
                                                href={recipient.report_url}
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
                    {task.recipients.length === 0 && (
                        <p className="text-muted-foreground py-8 text-center">No assignees on this task.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

AssignedTasksShow.layout = (page: ReactNode) => <AppLayout title="Assigned Task">{page}</AppLayout>;
