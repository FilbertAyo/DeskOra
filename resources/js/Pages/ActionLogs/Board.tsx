import { useForm } from '@inertiajs/react';

import AppLink from '@/components/shared/AppLink';
import { useEffect, useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge, statusBadgeVariant, type BadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';

type UpdateState = 'needs_update' | 'updated_by_you' | 'updated' | 'not_updated' | 'closed';
type Focus = 'all' | 'needs_update' | 'updated_by_you' | 'not_updated';

type Task = {
    task_uid: string;
    task: string;
    progress: string;
    notes_progress?: string;
    due_date?: string | null;
    responsible_names: string[];
    document_id: string;
    editable: boolean;
    mine: boolean;
    update_state: string;
};

type Week = {
    id: string;
    number: number | string;
    year: number | string;
    range: string;
    open: boolean;
};

type Summary = {
    needsUpdate: number;
    updatedByYou: number;
    notUpdated: number;
    assigned: number;
};

type Board = {
    document?: { id: string } | null;
    columns: Record<string, Task[]>;
    can_edit: boolean;
    week: Week | null;
    mine: Task[];
    summary: Summary;
};

type TeamUser = { id: string | number; name: string };

type Props = {
    board: Board;
    progressOptions: string[];
    updateOptions: string[];
    focus: string;
    teamUsers: TeamUser[];
    indexUrl: string;
};

const updateStates = ['needs_update', 'updated_by_you', 'updated', 'not_updated', 'closed'] as const;
const focuses = ['all', 'needs_update', 'updated_by_you', 'not_updated'] as const;

function isUpdateState(value: string): value is UpdateState {
    return (updateStates as readonly string[]).includes(value);
}

function isFocus(value: string): value is Focus {
    return (focuses as readonly string[]).includes(value);
}

function updateBadge(state: UpdateState): { label: string; variant: BadgeVariant } {
    switch (state) {
        case 'needs_update':
            return { label: 'Needs your update', variant: 'warning' };
        case 'updated_by_you':
            return { label: 'Updated by you', variant: 'success' };
        case 'updated':
            return { label: 'Updated this week', variant: 'success' };
        case 'not_updated':
            return { label: 'Not updated yet', variant: 'warning' };
        case 'closed':
            return { label: 'Closed', variant: 'secondary' };
        default: {
            const exhaustive: never = state;
            return exhaustive;
        }
    }
}

function cardTone(state: UpdateState): string {
    switch (state) {
        case 'needs_update':
            return 'border-amber-500/50 bg-amber-500/5';
        case 'updated_by_you':
            return 'border-emerald-500/40 bg-emerald-500/5';
        case 'updated':
            return 'border-emerald-500/30';
        case 'not_updated':
            return 'border-amber-500/25';
        case 'closed':
            return 'border-border opacity-80';
        default: {
            const exhaustive: never = state;
            return exhaustive;
        }
    }
}

function matchesFocus(state: UpdateState, focus: Focus): boolean {
    switch (focus) {
        case 'all':
            return true;
        case 'needs_update':
            return state === 'needs_update';
        case 'updated_by_you':
            return state === 'updated_by_you';
        case 'not_updated':
            return state === 'not_updated' || state === 'needs_update';
        default: {
            const exhaustive: never = focus;
            return exhaustive;
        }
    }
}

function taskState(task: Task): UpdateState {
    return isUpdateState(task.update_state) ? task.update_state : 'not_updated';
}

export default function ActionLogsBoard({ board, progressOptions, updateOptions, focus, teamUsers, indexUrl }: Props) {
    const initialFocus = isFocus(focus) ? focus : 'all';
    const [filter, setFilter] = useState<Focus>(initialFocus);
    const [taskOpen, setTaskOpen] = useState(false);
    const [activeTask, setActiveTask] = useState<Task | null>(null);
    const summary = board.summary ?? { needsUpdate: 0, updatedByYou: 0, notUpdated: 0, assigned: 0 };
    const mine = board.mine ?? [];
    const needsMine = mine.filter((task) => taskState(task) === 'needs_update');
    const updatedMine = mine.filter((task) => taskState(task) === 'updated_by_you');

    const taskForm = useForm({
        task_uid: '',
        progress: 'Not Started',
        notes_progress: '',
        reassign_user_id: '',
    });

    useEffect(() => {
        if (initialFocus !== 'needs_update') {
            return;
        }

        document.getElementById('my-action-updates')?.scrollIntoView({ block: 'start' });
    }, [initialFocus]);

    const openTask = (task: Task) => {
        const progress = updateOptions.includes(task.progress) ? task.progress : 'Not Started';
        setActiveTask(task);
        taskForm.setData({
            task_uid: task.task_uid,
            progress,
            notes_progress: task.notes_progress ?? '',
            reassign_user_id: '',
        });
        taskForm.clearErrors();
        setTaskOpen(true);
    };

    const notesRequired = taskForm.data.progress !== 'Not Started';
    const reassigning = taskForm.data.progress === 'Reassign';

    const submitTask = () => {
        if (!activeTask?.document_id) {
            return;
        }

        if (notesRequired && taskForm.data.notes_progress.trim() === '') {
            taskForm.setError('notes_progress', reassigning ? 'A reason is required.' : 'Progress notes are required.');

            return;
        }

        if (reassigning && taskForm.data.reassign_user_id === '') {
            taskForm.setError('reassign_user_id', 'Select a staff member.');

            return;
        }

        taskForm.transform((data) => ({
            task_uid: data.task_uid,
            progress: data.progress,
            notes_progress: data.progress === 'Not Started' ? '' : data.notes_progress,
            reassign_user_id: data.progress === 'Reassign' ? data.reassign_user_id : '',
        }));
        taskForm.put(route('action-logs.quick-update', { document: activeTask.document_id }), {
            preserveScroll: true,
            onSuccess: () => setTaskOpen(false),
        });
    };

    const weekLabel = board.week ? `Week ${board.week.number} of ${board.week.year}` : 'This week';

    return (
        <PageShell>
            <PageHeader
                title="Action Log Board"
                description={
                    board.week
                        ? `${weekLabel} · ${board.week.range}. Update your tasks here, and see what is already done.`
                        : 'Tasks grouped by progress.'
                }
                actions={
                    <Button variant="outline" render={<AppLink href={indexUrl} />}>
                        List view
                    </Button>
                }
            />

            {!board.document ? (
                <Card>
                    <CardContent className="text-muted-foreground py-10 text-center">No action log found for this work period yet.</CardContent>
                </Card>
            ) : (
                <>
                    <div id="my-action-updates" className="grid gap-3 sm:grid-cols-3">
                        <SummaryStat label="Needs your update" value={summary.needsUpdate} hint="Assigned to you, still open" tone="warning" />
                        <SummaryStat label="Updated by you" value={summary.updatedByYou} hint="Saved on this week’s log" tone="success" />
                        <SummaryStat label="Not updated yet" value={summary.notUpdated} hint="Open tasks still waiting this week" tone="muted" />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Your tasks this week</CardTitle>
                            <CardDescription>
                                {summary.assigned > 0
                                    ? `${weekLabel}. ${summary.needsUpdate === 0 ? 'Every open task assigned to you has an update.' : `${summary.needsUpdate} still need your update.`}`
                                    : 'You are not assigned on this week’s action log.'}
                            </CardDescription>
                        </CardHeader>
                        {summary.assigned > 0 && (
                            <CardContent className="grid gap-4 lg:grid-cols-2">
                                <TaskGroup
                                    title="Needs your update"
                                    empty="Nothing left to update."
                                    tasks={needsMine}
                                    onUpdate={openTask}
                                />
                                <TaskGroup
                                    title="Already updated by you"
                                    empty="You have not saved an update this week yet."
                                    tasks={updatedMine}
                                    onUpdate={openTask}
                                />
                            </CardContent>
                        )}
                    </Card>

                    <div className="flex flex-wrap gap-2">
                        <FilterChip active={filter === 'all'} onClick={() => setFilter('all')}>
                            All tasks
                        </FilterChip>
                        <FilterChip active={filter === 'needs_update'} onClick={() => setFilter('needs_update')}>
                            Needs your update
                        </FilterChip>
                        <FilterChip active={filter === 'updated_by_you'} onClick={() => setFilter('updated_by_you')}>
                            Updated by you
                        </FilterChip>
                        <FilterChip active={filter === 'not_updated'} onClick={() => setFilter('not_updated')}>
                            Not updated yet
                        </FilterChip>
                    </div>

                    <div className="grid items-start gap-4 xl:grid-cols-4">
                        {progressOptions.map((status) => {
                            const tasks = (board.columns[status] ?? []).filter((task) => matchesFocus(taskState(task), filter));

                            return (
                                <Card key={status}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">{status}</CardTitle>
                                        <Badge variant={statusBadgeVariant(status)}>{tasks.length}</Badge>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {tasks.length === 0 ? (
                                            <p className="text-muted-foreground text-sm">No tasks</p>
                                        ) : (
                                            tasks.map((task) => (
                                                <TaskCard key={task.task_uid} task={task} onUpdate={openTask} />
                                            ))
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </>
            )}

            <Dialog open={taskOpen} onOpenChange={setTaskOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update action log</DialogTitle>
                        <DialogDescription>{weekLabel}</DialogDescription>
                    </DialogHeader>
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitTask();
                        }}
                    >
                        <div className="bg-muted rounded-lg px-3 py-2 text-sm">{activeTask?.task}</div>
                        <Field>
                            <FieldLabel htmlFor="boardTaskProgress">Progress status</FieldLabel>
                            <select
                                id="boardTaskProgress"
                                name="progress"
                                required
                                className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                value={taskForm.data.progress}
                                onChange={(event) => taskForm.setData('progress', event.target.value)}
                            >
                                {updateOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {reassigning && (
                            <Field>
                                <FieldLabel htmlFor="boardTaskReassign">Reassign to</FieldLabel>
                                <select
                                    id="boardTaskReassign"
                                    name="reassign_user_id"
                                    required
                                    className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                    value={taskForm.data.reassign_user_id}
                                    onChange={(event) => taskForm.setData('reassign_user_id', event.target.value)}
                                >
                                    <option value="">Select staff member...</option>
                                    {teamUsers.map((member) => (
                                        <option key={member.id} value={member.id}>
                                            {member.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError>{taskForm.errors.reassign_user_id}</FieldError>
                            </Field>
                        )}
                        {notesRequired && (
                            <Field>
                                <FieldLabel htmlFor="boardTaskNotes">{reassigning ? 'Reason for reassignment' : 'Progress notes'}</FieldLabel>
                                <Textarea
                                    id="boardTaskNotes"
                                    name="notes_progress"
                                    required
                                    maxLength={4000}
                                    rows={4}
                                    value={taskForm.data.notes_progress}
                                    placeholder="What did you do this week? Any blockers or context?"
                                    onChange={(event) => taskForm.setData('notes_progress', event.target.value)}
                                />
                                <FieldError>{taskForm.errors.notes_progress}</FieldError>
                            </Field>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setTaskOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={taskForm.processing} loading={taskForm.processing}>
                                Save update
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

function SummaryStat({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: number;
    hint: string;
    tone: 'warning' | 'success' | 'muted';
}) {
    const toneClass =
        tone === 'warning'
            ? 'border-amber-500/40 bg-amber-500/10'
            : tone === 'success'
              ? 'border-emerald-500/40 bg-emerald-500/10'
              : 'bg-muted/40';

    return (
        <div className={cn('rounded-xl border px-4 py-3', toneClass)}>
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
            <div className="text-sm font-medium">{label}</div>
            <div className="text-muted-foreground text-xs">{hint}</div>
        </div>
    );
}

function FilterChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <Button type="button" size="sm" variant={active ? 'default' : 'outline'} onClick={onClick}>
            {children}
        </Button>
    );
}

function TaskGroup({
    title,
    empty,
    tasks,
    onUpdate,
}: {
    title: string;
    empty: string;
    tasks: Task[];
    onUpdate: (task: Task) => void;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-sm font-medium">{title}</h3>
            {tasks.length === 0 ? (
                <p className="text-muted-foreground rounded-lg border border-dashed px-3 py-4 text-sm">{empty}</p>
            ) : (
                tasks.map((task) => <TaskCard key={`${title}-${task.task_uid}`} task={task} onUpdate={onUpdate} />)
            )}
        </section>
    );
}

function formatDueDate(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const match = value.trim().match(/^(\d{4}-\d{2}-\d{2})/);
    if (match) {
        return match[1];
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toISOString().slice(0, 10);
}

function TaskCard({ task, onUpdate }: { task: Task; onUpdate: (task: Task) => void }) {
    const state = taskState(task);
    const badge = updateBadge(state);
    const dueDate = formatDueDate(task.due_date);

    return (
        <div className={cn('rounded-lg border p-3', cardTone(state))}>
            <p className="text-sm font-medium leading-snug text-pretty">{task.task}</p>
            {task.responsible_names.length > 0 && (
                <p className="text-muted-foreground mt-1 text-xs">{task.responsible_names.join(', ')}</p>
            )}
            {task.notes_progress && <p className="mt-2 text-sm">{task.notes_progress}</p>}
            <div className="mt-3 flex items-end justify-between gap-3">
                <div className="min-w-0 space-y-1.5">
                    {dueDate && <p className="text-muted-foreground text-xs">Due {dueDate}</p>}
                    <Badge variant={badge.variant} className="h-4 px-1.5 text-[10px] font-medium">
                        {badge.label}
                    </Badge>
                </div>
                {task.editable && (
                    <Button type="button" size="sm" variant={state === 'needs_update' ? 'default' : 'outline'} onClick={() => onUpdate(task)}>
                        Update
                    </Button>
                )}
            </div>
        </div>
    );
}

ActionLogsBoard.layout = (page: ReactNode) => <AppLayout title="Action Log Board">{page}</AppLayout>;
