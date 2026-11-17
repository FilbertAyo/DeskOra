import { Link } from '@inertiajs/react';
import { DownloadIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Task = {
    task_no?: string | number;
    task?: string;
    progress?: string | null;
    display_progress?: string | null;
    notes_progress?: string | null;
    display_notes_progress?: string | null;
    due_date?: string | null;
    compilation_date?: string | null;
    is_assignee_self?: boolean;
    assignee_label?: string | null;
    responsible_user_ids?: Array<string | number>;
};

export default function ActionLogsShow({
    document,
    tasks = [],
    responsibleNames = {},
    canManage,
    backUrl,
}: {
    document: Record<string, unknown>;
    tasks?: Task[];
    responsibleNames?: Record<string, string>;
    canManage?: boolean;
    canDelete?: boolean;
    backUrl: string;
    viewerId?: string;
}) {
    const title = String(document.display_title ?? document.title ?? 'Action Log');
    const state = String(document.state ?? '');
    const period = document.period as { weekNumber?: number; year?: number; dateRange?: string } | null | undefined;
    const createdBy = String(document.userName ?? document.creator ?? '—');
    const updatedAt = String(document.updatedAt ?? document.updated_at ?? '—');
    const downloadUrl = document.downloadUrl ? String(document.downloadUrl) : undefined;
    const editUrl = document.editUrl ? String(document.editUrl) : undefined;
    const progressCounts = tasks.reduce(
        (acc, task) => {
            const progress = String(task.display_progress ?? task.progress ?? 'Not Started').toLowerCase();
            if (progress.includes('done') && !progress.includes('partially') && !progress.includes('carry')) {
                acc.done += 1;
            } else if (progress.includes('progress') || progress.includes('partially') || progress.includes('carry')) {
                acc.inProgress += 1;
            } else {
                acc.notStarted += 1;
            }
            return acc;
        },
        { done: 0, inProgress: 0, notStarted: 0 },
    );

    return (
        <PageShell>
            <PageHeader
                title={title}
                description={
                    period
                        ? `Week ${period.weekNumber} of ${period.year}${period.dateRange ? ` · ${period.dateRange}` : ''}`
                        : 'Action log details and task progress.'
                }
                actions={
                    <>
                        <Button variant="outline" render={<Link href={backUrl} />}>
                            Back
                        </Button>
                        <RowActions
                            items={[
                                {
                                    label: 'Edit',
                                    href: editUrl,
                                    icon: <PencilIcon />,
                                    hidden: !(canManage && editUrl),
                                },
                                {
                                    label: 'Download PDF',
                                    href: downloadUrl,
                                    icon: <DownloadIcon />,
                                    hidden: !downloadUrl,
                                },
                            ]}
                        />
                    </>
                }
            />

            <div className="grid gap-4 lg:grid-cols-4">
                <Card className="shadow-none lg:col-span-1">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="text-base">Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 pt-6 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">State</span>
                            <Badge variant={statusBadgeVariant(state || '')}>
                                {state === 'draft' ? 'Draft' : state === 'submitted' ? 'Submitted' : state || '—'}
                            </Badge>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs">Created by</p>
                            <p className="font-medium">{createdBy}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs">Work period</p>
                            <p className="font-medium">
                                {period
                                    ? `Week ${period.weekNumber} of ${period.year}${period.dateRange ? ` · ${period.dateRange}` : ''}`
                                    : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs">Updated</p>
                            <p className="font-medium">{updatedAt}</p>
                        </div>
                        <div className="rounded-md border p-3">
                            <p className="text-muted-foreground mb-2 text-xs">Task progress</p>
                            <div className="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p className="font-medium tabular-nums text-emerald-700">{progressCounts.done}</p>
                                    <p className="text-muted-foreground text-[11px]">Done</p>
                                </div>
                                <div>
                                    <p className="font-medium tabular-nums text-amber-700">{progressCounts.inProgress}</p>
                                    <p className="text-muted-foreground text-[11px]">In progress</p>
                                </div>
                                <div>
                                    <p className="font-medium tabular-nums">{progressCounts.notStarted}</p>
                                    <p className="text-muted-foreground text-[11px]">Not started</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="shadow-none lg:col-span-3">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="text-base">Tasks</CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">No</TableHead>
                                    <TableHead>Task</TableHead>
                                    <TableHead>Responsible</TableHead>
                                    <TableHead>Progress</TableHead>
                                    <TableHead>Due</TableHead>
                                    <TableHead>Compilation</TableHead>
                                    <TableHead>Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-10 text-center">
                                            No tasks found for this action log.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    tasks.map((task, index) => {
                                        const ids = (task.responsible_user_ids ?? []).map(String);
                                        const names = ids
                                            .map((id) => responsibleNames[id])
                                            .filter(Boolean)
                                            .join(', ');
                                        const progress = String(
                                            task.display_progress ?? task.progress ?? 'Not Started',
                                        );
                                        const notes = String(
                                            task.display_notes_progress ?? task.notes_progress ?? '',
                                        );

                                        return (
                                            <TableRow key={index} data-assignee-self={task.is_assignee_self ? '1' : '0'}>
                                                <TableCell className="tabular-nums">{task.task_no ?? index + 1}</TableCell>
                                                <TableCell>
                                                    <div className="font-medium">{task.task || '—'}</div>
                                                    {task.is_assignee_self && (
                                                        <p className="text-muted-foreground text-xs">Your task</p>
                                                    )}
                                                </TableCell>
                                                <TableCell>{names || task.assignee_label || '—'}</TableCell>
                                                <TableCell>
                                                    <Badge variant={statusBadgeVariant(progress || 'not started')}>
                                                        {progress || '—'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>{task.due_date || '—'}</TableCell>
                                                <TableCell>{task.compilation_date || '—'}</TableCell>
                                                <TableCell className="max-w-64 whitespace-pre-wrap text-sm">
                                                    {notes || '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </PageShell>
    );
}

ActionLogsShow.layout = (page: ReactNode) => <AppLayout title="Action Log">{page}</AppLayout>;
