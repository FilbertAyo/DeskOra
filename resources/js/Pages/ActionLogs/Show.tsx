import { DownloadIcon, PencilIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import RowActions from '@/components/shared/RowActions';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

type HistoryEntry = {
    period_id?: string | null;
    period_label?: string | null;
    updated_by?: string | null;
    from_progress?: string | null;
    to_progress?: string | null;
    notes_progress?: string | null;
    status_reason?: string | null;
    changed_at?: string | null;
    source?: string | null;
    is_empty?: boolean;
    reassigned_from?: string[];
    reassigned_to?: string | null;
};

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
    display_history?: HistoryEntry[];
};

type Period = {
    id?: string;
    weekNumber?: number;
    year?: number;
    dateRange?: string;
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

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
    const [historyTask, setHistoryTask] = useState<Task | null>(null);
    const title = String(document.display_title ?? document.title ?? 'Action Log');
    const state = String(document.state ?? '');
    const period = document.period as Period | null | undefined;
    const createdBy = String(document.userName ?? document.creator ?? '—');
    const downloadUrl = document.downloadUrl ? String(document.downloadUrl) : undefined;
    const editUrl = document.editUrl ? String(document.editUrl) : undefined;
    const periodLabel = period
        ? `Week ${period.weekNumber} of ${period.year}${period.dateRange ? ` · ${period.dateRange}` : ''}`
        : '—';
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
    const historyEntries = historyTask ? previousUpdates(historyTask, period?.id) : [];

    return (
        <PageShell>
            <PageHeader
                title={title}
                description={period ? periodLabel : 'Action log details and task progress.'}
                actions={
                    <>
                        <BackButton href={backUrl} />
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

            <Card size="sm" className="shadow-none">
                <CardContent className="grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-4">
                    <SummaryItem label="State">
                        <Badge variant={statusBadgeVariant(state || '')}>
                            {state === 'draft' ? 'Draft' : state === 'submitted' ? 'Submitted' : state || '—'}
                        </Badge>
                    </SummaryItem>
                    <SummaryItem label="Created by">{createdBy}</SummaryItem>
                    <SummaryItem label="Work period">{periodLabel}</SummaryItem>
                    <SummaryItem label="Task progress">
                        <span className="text-emerald-700">{progressCounts.done} done</span>
                        <span className="text-muted-foreground"> · </span>
                        <span className="text-amber-700">{progressCounts.inProgress} in progress</span>
                        <span className="text-muted-foreground"> · </span>
                        <span>{progressCounts.notStarted} not started</span>
                    </SummaryItem>
                </CardContent>
            </Card>

            <Card className="min-w-0 shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Tasks</CardTitle>
                </CardHeader>
                <CardContent className="px-0 pt-0">
                    <Table className="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[4%]">No</TableHead>
                                <TableHead className="w-[22%]">Task</TableHead>
                                <TableHead className="w-[14%]">Responsible</TableHead>
                                <TableHead className="w-[11%]">Progress</TableHead>
                                <TableHead className="w-[10%]">Due</TableHead>
                                <TableHead className="w-[11%]">Compilation</TableHead>
                                <TableHead className="w-[18%]">Notes</TableHead>
                                <TableHead className="w-[10%]">Previous</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tasks.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center whitespace-normal">
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
                                    const progress = String(task.display_progress ?? task.progress ?? 'Not Started');
                                    const notes = String(task.display_notes_progress ?? task.notes_progress ?? '');
                                    const earlierCount = previousUpdates(task, period?.id).length;

                                    return (
                                        <TableRow key={index} data-assignee-self={task.is_assignee_self ? '1' : '0'}>
                                            <TableCell className="align-top whitespace-normal tabular-nums">
                                                {task.task_no ?? index + 1}
                                            </TableCell>
                                            <TableCell className="align-top whitespace-pre-wrap break-words">
                                                <div className="font-medium">{task.task || '—'}</div>
                                                {task.is_assignee_self && (
                                                    <p className="text-muted-foreground text-xs">Your task</p>
                                                )}
                                            </TableCell>
                                            <TableCell className="align-top whitespace-normal break-words">
                                                {names || task.assignee_label || '—'}
                                            </TableCell>
                                            <TableCell className="align-top whitespace-normal">
                                                <Badge variant={statusBadgeVariant(progress || 'not started')}>
                                                    {progress || '—'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="align-top whitespace-normal break-words">
                                                {task.due_date || '—'}
                                            </TableCell>
                                            <TableCell className="align-top whitespace-normal break-words">
                                                {task.compilation_date || '—'}
                                            </TableCell>
                                            <TableCell className="align-top text-sm whitespace-pre-wrap break-words">
                                                {notes || '—'}
                                            </TableCell>
                                            <TableCell className="align-top whitespace-normal">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    aria-label={`Previous updates for ${task.task || 'this task'}`}
                                                    onClick={() => setHistoryTask(task)}
                                                >
                                                    {earlierCount > 0 ? `View ${earlierCount}` : 'View'}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Dialog open={historyTask !== null} onOpenChange={(open) => !open && setHistoryTask(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Previous updates</DialogTitle>
                        <DialogDescription className="whitespace-pre-wrap">
                            {historyTask?.task || 'This task'}
                        </DialogDescription>
                    </DialogHeader>
                    {historyEntries.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No previous updates for this task.</p>
                    ) : (
                        <div className="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                            {historyEntries.map((entry, index) => {
                                const progress = String(entry.to_progress ?? '').trim();
                                const notes = String(entry.notes_progress ?? '').trim();
                                const reason = String(entry.status_reason ?? '').trim();
                                const author = personName(entry.updated_by, responsibleNames);
                                const when = formatWhen(entry.changed_at);
                                const reassignedTo = personName(entry.reassigned_to, responsibleNames);

                                return (
                                    <div key={`${entry.period_id ?? 'week'}-${index}`} className="rounded-md border p-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">{entry.period_label || 'Earlier week'}</p>
                                            {progress !== '' && (
                                                <Badge variant={statusBadgeVariant(progress)}>{progress}</Badge>
                                            )}
                                        </div>
                                        {(author || when) && (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {[author, when].filter(Boolean).join(' · ')}
                                            </p>
                                        )}
                                        {notes !== '' && (
                                            <p className="mt-2 text-sm whitespace-pre-wrap break-words">{notes}</p>
                                        )}
                                        {reason !== '' && (
                                            <p className="text-muted-foreground mt-2 text-sm whitespace-pre-wrap break-words">
                                                {reason}
                                            </p>
                                        )}
                                        {reassignedTo && (
                                            <p className="text-muted-foreground mt-2 text-xs">
                                                Reassigned to {reassignedTo}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

function SummaryItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <p className="text-muted-foreground text-xs">{label}</p>
            <div className="mt-0.5 text-sm font-medium break-words">{children}</div>
        </div>
    );
}

function previousUpdates(task: Task, currentPeriodId?: string): HistoryEntry[] {
    return (task.display_history ?? [])
        .filter((entry) => {
            if (!isRealUpdate(entry)) {
                return false;
            }
            if (!currentPeriodId) {
                return true;
            }

            return String(entry.period_id ?? '') !== String(currentPeriodId);
        })
        .slice()
        .reverse();
}

function isRealUpdate(entry: HistoryEntry): boolean {
    if (entry.is_empty) {
        return false;
    }

    const source = String(entry.source ?? '').trim();
    if (source === 'auto_init') {
        return false;
    }
    if (source === 'update' || source === 'reassign') {
        return true;
    }

    const notes = String(entry.notes_progress ?? '').trim();
    const reason = String(entry.status_reason ?? '').trim();
    if (notes !== '' || reason !== '') {
        return true;
    }

    const from = String(entry.from_progress ?? '').trim();
    const to = String(entry.to_progress ?? '').trim();

    return from !== '' && to !== '' && from !== to;
}

function personName(id: string | null | undefined, names: Record<string, string>): string {
    const key = String(id ?? '').trim();
    if (key === '') {
        return '';
    }

    return names[key] ?? '';
}

function formatWhen(value?: string | null): string {
    const raw = String(value ?? '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) {
        return raw;
    }

    const month = MONTHS[Number(match[2]) - 1] ?? match[2];
    const day = Number(match[3]);
    if (!match[4]) {
        return `${month} ${day}, ${match[1]}`;
    }

    const hour = Number(match[4]);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;

    return `${month} ${day}, ${match[1]} ${hour12}:${match[5]} ${suffix}`;
}

ActionLogsShow.layout = (page: ReactNode) => <AppLayout title="Action Log">{page}</AppLayout>;
