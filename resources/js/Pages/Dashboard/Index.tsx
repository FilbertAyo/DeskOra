import { useForm } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { saveOfflineWorkLog } from '@/lib/offline-work-logs';
import { notify } from '@/lib/notify';
import { route } from '@/lib/route';

type Greeting = { salutation: string; firstName: string; message: string };

type Matrix = {
    week: number | string;
    year: number | string;
    tableUrl: string;
    openUrl: string;
    downloadUrl: string;
};

type Coverage = {
    show: boolean;
    year: number;
    totalActivities: number;
    missingMonthCount: number;
    actionUrl: string | null;
};

type Stats = {
    openTasks: number;
    shared: number;
    assigned: number;
    weeklyPlansUrl: string | null;
    weeklyReportsUrl: string | null;
};

type WorkLog = {
    canLog: boolean;
    todayLabel: string;
    todayKey: string;
    periodId: string | null;
    storeUrl: string;
    updateUrl: string | null;
    entry: { done: string; blocker: string; next: string } | null;
    reportReady: boolean;
    reportUrl: string;
};

type Series = { name: string; data: Array<number | null> };

type Performance = {
    categories: string[];
    series: Series[];
    tips: string[];
    overallScore: number;
    pendingReviews: number;
};

type ReminderSegment = {
    showCta: boolean;
    week: number | string;
    year: number | string;
    url: string | null;
    needsRevision: boolean;
    hasDocument: boolean;
};

type Reminder = {
    showToast: boolean;
    missedMessages: string[];
    plan: ReminderSegment | null;
    report: ReminderSegment | null;
};

type GraceEntry = {
    week: number | string | null;
    year: number | string | null;
    until: string | null;
    actionUrl: string | null;
    actionLabel: string | null;
};

type TodayTask = { activity: string; source: string };

type Collaboration = {
    message: string;
    badge: string;
    removed: boolean;
    href: string | null;
    when: string;
};

type ApprovalAction = { label: string; count: number; url: string };

type Scorecard = {
    week: number | string;
    planSubmitted: boolean;
    reportSubmitted: boolean;
    workLogDays: number;
    workLogExpectedDays: number;
    openActions: number;
    carryOvers: number;
};

type ActionTask = {
    task: string;
    latestProgress: string;
    periodLabel: string;
    needsUpdate: boolean;
    carriedFrom: string;
    editable: boolean;
    documentId: string | number | null;
    taskUid: string;
    notes: string;
    showUrl: string | null;
};

type TeamUser = { id: string | number; name: string };

type PageProps = {
    greeting: Greeting;
    matrix: Matrix | null;
    coverage: Coverage;
    stats: Stats;
    workLog: WorkLog;
    performance: Performance;
    deadlines: { planDue: string | null; reportDue: string | null };
    reminder: Reminder;
    planGrace: GraceEntry[];
    reportGrace: GraceEntry[];
    today: { day: string; showTasks: boolean; tasks: TodayTask[] };
    collaboration: Collaboration[];
    actions: ApprovalAction[];
    scorecard: Scorecard | null;
    actionTasks: ActionTask[];
    actionLogsUrl: string;
    teamUsers: TeamUser[];
};

const chartColors = ['#0077b6', '#0caf60', '#faad14', '#dc2626', '#7c3aed'];

const progressOptions = ['Not Started', 'In Progress', 'Done', 'Cancelled', 'Reassign'] as const;

type Progress = (typeof progressOptions)[number];

function isProgress(value: string): value is Progress {
    return (progressOptions as readonly string[]).includes(value);
}

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function scoreTone(score: number): string {
    if (score >= 80) {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (score >= 50) {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-300';
}

function actionTone(label: string): string {
    if (label.startsWith('Authorize') || label.includes('disposal')) {
        return 'bg-red-600 text-white';
    }

    if (label.startsWith('Approve')) {
        return 'bg-primary text-primary-foreground';
    }

    return 'bg-amber-500 text-white';
}

function actionCta(label: string): string {
    if (label.startsWith('Authorize')) {
        return 'Authorize';
    }

    if (label.startsWith('Approve')) {
        return 'Approve';
    }

    if (label.startsWith('Review')) {
        return 'Review';
    }

    return 'Open';
}

export default function DashboardIndex({
    greeting,
    matrix,
    coverage,
    stats,
    workLog,
    performance,
    deadlines,
    reminder,
    planGrace,
    reportGrace,
    today,
    collaboration,
    actions,
    scorecard,
    actionTasks,
    actionLogsUrl,
    teamUsers,
}: PageProps) {
    const [logOpen, setLogOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [matrixOpen, setMatrixOpen] = useState(false);
    const [matrixHtml, setMatrixHtml] = useState<string | null>(null);
    const [matrixError, setMatrixError] = useState(false);
    const [taskOpen, setTaskOpen] = useState(false);
    const [activeTask, setActiveTask] = useState<ActionTask | null>(null);

    const logForm = useForm({
        period_id: workLog.periodId ?? '',
        log_date: workLog.todayKey,
        done: workLog.entry?.done ?? '',
        blocker: workLog.entry?.blocker ?? '',
        next: workLog.entry?.next ?? '',
    });

    const taskForm = useForm({
        task_uid: '',
        progress: 'Not Started',
        notes_progress: '',
        reassign_user_id: '',
    });

    useEffect(() => {
        if (logForm.errors.done || logForm.errors.blocker || logForm.errors.next || logForm.errors.period_id || logForm.errors.log_date) {
            setLogOpen(true);
        }
    }, [logForm.errors]);

    useEffect(() => {
        if (!matrixOpen || !matrix || matrixHtml || matrixError) {
            return;
        }

        let cancelled = false;

        fetch(matrix.tableUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((data: { html?: string }) => {
                if (!cancelled) {
                    setMatrixHtml(data.html || '<p class="text-muted-foreground">No matrix present.</p>');
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setMatrixError(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [matrix, matrixError, matrixHtml, matrixOpen]);

    const openLog = () => {
        logForm.setData({
            period_id: workLog.periodId ?? '',
            log_date: workLog.todayKey,
            done: workLog.entry?.done ?? '',
            blocker: workLog.entry?.blocker ?? '',
            next: workLog.entry?.next ?? '',
        });
        logForm.clearErrors();
        setLogOpen(true);
    };

    const submitLog = () => {
        const payload = {
            done: logForm.data.done,
            blocker: logForm.data.blocker,
            next: logForm.data.next,
            ...(workLog.updateUrl
                ? {}
                : {
                      period_id: logForm.data.period_id,
                      log_date: logForm.data.log_date,
                  }),
        };

        if (!navigator.onLine) {
            saveOfflineWorkLog(payload);
            notify('info', 'You are offline. This work log was saved on this device and will sync when you are back online.');
            setLogOpen(false);

            return;
        }

        logForm.transform(() => payload);

        const options = { preserveScroll: true, onSuccess: () => setLogOpen(false) };

        if (workLog.updateUrl) {
            logForm.put(workLog.updateUrl, options);
        } else {
            logForm.post(workLog.storeUrl, options);
        }
    };

    const openTask = (task: ActionTask) => {
        setActiveTask(task);
        taskForm.setData({
            task_uid: task.taskUid,
            progress: isProgress(task.latestProgress) ? task.latestProgress : 'Not Started',
            notes_progress: task.notes,
            reassign_user_id: '',
        });
        taskForm.clearErrors();
        setTaskOpen(true);
    };

    const progress = isProgress(taskForm.data.progress) ? taskForm.data.progress : 'Not Started';
    const notesRequired = progress !== 'Not Started';
    const reassigning = progress === 'Reassign';

    const submitTask = () => {
        if (!activeTask?.documentId) {
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
        taskForm.put(route('action-logs.quick-update', { document: activeTask.documentId }), {
            preserveScroll: true,
            onSuccess: () => setTaskOpen(false),
        });
    };

    const chartRows = performance.categories.map((category, index) => {
        const row: Record<string, string | number | null> = { category };

        performance.series.forEach((series) => {
            row[series.name] = series.data[index] ?? null;
        });

        return row;
    });

    const weeklyPlan = reminder.showToast ? reminder.plan : null;
    const weeklyReport = reminder.showToast ? reminder.report : null;
    const weeklyActions = [weeklyPlan, weeklyReport].filter((segment) => segment?.showCta).length;
    const actionCount = actions.reduce((sum, action) => sum + action.count, 0) + weeklyActions;
    const tasksClear = !today.showTasks || today.tasks.length === 0;
    const activitiesEmpty = actionTasks.length === 0 && tasksClear && collaboration.length === 0;

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        <span className="text-foreground font-medium">
                            {greeting.salutation}, {greeting.firstName}!
                        </span>{' '}
                        {greeting.message}
                    </p>
                </div>
                {matrix && (
                    <Button type="button" variant="outline" data-feature="feature.dashboard.matrix_modal" onClick={() => setMatrixOpen(true)}>
                        View weekly matrix
                    </Button>
                )}
            </div>

            {coverage.show && (
                <div className="flex flex-col gap-3 rounded-xl border border-red-500/40 bg-red-500/10 p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="font-medium">Year Plan Update Required</h2>
                        <p className="mt-1 text-sm">
                            Update your sub-department tasks for {coverage.year}. Some tasks still have no planned month selected.
                        </p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {coverage.missingMonthCount.toLocaleString()} of {coverage.totalActivities.toLocaleString()} tasks in your assigned
                            sub-department(s) need at least one month (Jan-Dec) checked.
                        </p>
                    </div>
                    {coverage.actionUrl && (
                        <Button render={<a href={coverage.actionUrl} />} variant="destructive">
                            Update Year Plan Months
                        </Button>
                    )}
                </div>
            )}

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)]">
                <div className="flex flex-col gap-6">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Stat label="Open" value={stats.openTasks} />
                        <Stat label="Shared" value={stats.shared}>
                            {stats.shared > 0 && (
                                <div className="mt-2 flex flex-col gap-1 text-sm">
                                    {stats.weeklyPlansUrl && <a href={stats.weeklyPlansUrl}>Weekly plans</a>}
                                    {stats.weeklyReportsUrl && <a href={stats.weeklyReportsUrl}>Weekly reports</a>}
                                </div>
                            )}
                        </Stat>
                        <Stat label="Assigned" value={stats.assigned} />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Today’s work log</CardTitle>
                            <CardAction>
                                {workLog.canLog && (
                                    <Button type="button" variant="link" data-feature="feature.dashboard.work_log_open" onClick={openLog}>
                                        {workLog.entry ? 'Edit' : 'Add'}
                                    </Button>
                                )}
                            </CardAction>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {workLog.entry ? (
                                <>
                                    <p className="text-muted-foreground text-sm">Logged for {workLog.todayLabel}</p>
                                    <p>{workLog.entry.done}</p>
                                    {workLog.entry.blocker && <p className="text-sm text-amber-700 dark:text-amber-300">{workLog.entry.blocker}</p>}
                                    <Button type="button" variant="outline" className="w-fit" data-feature="feature.dashboard.work_log_open" onClick={openLog}>
                                        Edit today
                                    </Button>
                                </>
                            ) : workLog.canLog ? (
                                <>
                                    <p className="text-muted-foreground">Capture what you did today — it helps fill your weekly report later.</p>
                                    <Button type="button" className="w-fit" data-feature="feature.dashboard.work_log_open" onClick={openLog}>
                                        Add work log
                                    </Button>
                                </>
                            ) : (
                                <p className="text-muted-foreground">No work period is available yet.</p>
                            )}
                            {workLog.reportReady && (
                                <p className="bg-muted rounded-lg px-3 py-2 text-sm">
                                    You have work logs for this week. <a href={workLog.reportUrl}>Start your weekly report</a>.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>My monthly performance</CardTitle>
                            <CardAction className="flex items-center gap-2">
                                <Badge variant="outline" className={scoreTone(performance.overallScore)}>
                                    {performance.overallScore}% this month
                                </Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    data-feature="feature.dashboard.performance_details"
                                    onClick={() => setDetailsOpen(true)}
                                >
                                    Details{performance.tips.length > 0 ? ` ${performance.tips.length}` : ''}
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent className="h-72">
                            {performance.series.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No performance series available for your role yet.</p>
                            ) : (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={chartRows}>
                                        <CartesianGrid strokeDasharray="4 4" vertical={false} />
                                        <XAxis dataKey="category" tick={{ fontSize: 12 }} />
                                        <YAxis domain={[0, 100]} tickFormatter={(value: number) => `${Math.round(value)}%`} tick={{ fontSize: 12 }} />
                                        <Tooltip formatter={(value) => (value === null || value === undefined ? 'n/a' : `${value}%`)} />
                                        <Legend />
                                        {performance.series.map((series, index) => (
                                            <Bar key={series.name} dataKey={series.name} fill={chartColors[index % chartColors.length]} radius={2} />
                                        ))}
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>

                    {(reminder.missedMessages.length > 0 || planGrace.length > 0 || reportGrace.length > 0) && (
                        <div className="flex flex-col gap-3">
                            {reminder.missedMessages.length > 0 && (
                                <div className="rounded-xl border border-red-500/40 bg-red-500/10 p-4">
                                    <h2 className="font-medium">Missed submission window</h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {deadlines.planDue || deadlines.reportDue ? (
                                            <>
                                                {deadlines.planDue && <>Plan cutoff: {deadlines.planDue}. </>}
                                                {deadlines.reportDue && <>Report cutoff: {deadlines.reportDue}.</>}
                                            </>
                                        ) : (
                                            'Weekly plan and report cut-off is managed by the active work period deadlines.'
                                        )}
                                    </p>
                                    <ul className="mt-2 list-disc pl-5 text-sm">
                                        {reminder.missedMessages.map((line) => (
                                            <li key={line}>{line}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {(planGrace.length > 0 || reportGrace.length > 0) && (
                                <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
                                    <h2 className="font-medium">Grace period is active</h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Your administrator extended the deadline. Use the buttons below to create or finish the document before the new cutoff.
                                    </p>
                                    <ul className="mt-3 flex flex-col gap-3 text-sm">
                                        {planGrace.map((entry, index) => (
                                            <GraceRow key={`plan-${index}`} kind="Weekly plan" entry={entry} />
                                        ))}
                                        {reportGrace.map((entry, index) => (
                                            <GraceRow key={`report-${index}`} kind="Weekly report" entry={entry} />
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>My Weekly Activities</CardTitle>
                            <CardDescription>Today&apos;s plan, revisions &amp; shares</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {today.showTasks && today.tasks.length > 0 && (
                                <section>
                                    <h3 className="mb-2 text-sm font-medium">{today.day}&apos;s tasks</h3>
                                    <div className="flex flex-col gap-2">
                                        {today.tasks.map((task, index) => (
                                            <div key={`${task.activity}-${index}`} className="flex items-start justify-between gap-3">
                                                <span>{task.activity}</span>
                                                <span className="text-muted-foreground text-xs whitespace-nowrap">{task.source}</span>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                            {collaboration.length > 0 && (
                                <section>
                                    <h3 className="mb-2 text-sm font-medium">Shared with you</h3>
                                    <div className="flex flex-col gap-2">
                                        {collaboration.map((event, index) => (
                                            <div key={`${event.message}-${index}`} className="flex items-start gap-2">
                                                <Badge variant="outline">{event.badge}</Badge>
                                                <div className="min-w-0 flex-1 text-sm">
                                                    {event.href ? <a href={event.href}>{event.message}</a> : <span>{event.message}</span>}
                                                </div>
                                                <span className="text-muted-foreground text-xs whitespace-nowrap">{event.when}</span>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                            {activitiesEmpty && <p className="text-muted-foreground py-4 text-center">Nothing pending right now.</p>}
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Action needed</CardTitle>
                            {actionCount > 0 && (
                                <CardAction>
                                    <Badge variant="outline">{actionCount}</Badge>
                                </CardAction>
                            )}
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 px-3">
                            {actionCount === 0 && <p className="text-muted-foreground py-6 text-center">No action needed right now.</p>}
                            {actions.map((action) => (
                                <a
                                    key={action.label}
                                    href={action.url}
                                    data-feature="feature.dashboard.action_needed"
                                    className="hover:bg-muted flex items-center justify-between gap-3 rounded-lg px-2 py-2"
                                >
                                    <span>
                                        <span className="block font-medium">{action.label}</span>
                                        <span className="text-muted-foreground text-sm">{plural(action.count, 'item')} waiting</span>
                                    </span>
                                    <Badge className={actionTone(action.label)}>{actionCta(action.label)}</Badge>
                                </a>
                            ))}
                            {weeklyPlan?.showCta && weeklyPlan.url && <WeeklyAction kind="plan" segment={weeklyPlan} />}
                            {weeklyReport?.showCta && weeklyReport.url && <WeeklyAction kind="report" segment={weeklyReport} />}
                        </CardContent>
                    </Card>

                    {scorecard && (
                        <Card data-feature="feature.dashboard.scorecard">
                            <CardHeader>
                                <CardTitle>Weekly scorecard</CardTitle>
                                <CardAction>
                                    <span className="text-muted-foreground text-sm">W{scorecard.week}</span>
                                </CardAction>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-2 text-center">
                                <Score label="Plan submitted" value={scorecard.planSubmitted ? 'Yes' : 'No'} />
                                <Score label="Report submitted" value={scorecard.reportSubmitted ? 'Yes' : 'No'} />
                                <Score label="Log days" value={`${scorecard.workLogDays}/${scorecard.workLogExpectedDays}`} />
                                <Score label="Open actions" value={String(scorecard.openActions)} />
                                {scorecard.carryOvers > 0 && (
                                    <p className="text-muted-foreground col-span-2 text-left text-sm">
                                        {plural(scorecard.carryOvers, 'activity')} marked to carry over.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                My Action Tasks
                                {actionTasks.length > 0 && (
                                    <Badge variant="outline" className="ml-2">
                                        {actionTasks.length}
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardAction>
                                <a href={actionLogsUrl} className="text-sm" data-feature="feature.dashboard.action_tasks">
                                    View all
                                </a>
                            </CardAction>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {actionTasks.length === 0 ? (
                                <p className="text-muted-foreground py-4 text-center">No action tasks need your attention right now.</p>
                            ) : (
                                <>
                                    <p className="text-muted-foreground text-sm">Tasks assigned to you that still need an update.</p>
                                    {actionTasks.map((task) => (
                                        <div key={`${task.documentId}-${task.taskUid}`} className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm">{task.task}</p>
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    <Badge variant="outline">{task.latestProgress}</Badge>
                                                    {task.periodLabel && <Badge variant="outline">{task.periodLabel}</Badge>}
                                                    {task.needsUpdate && <Badge variant="outline">Carried forward</Badge>}
                                                </div>
                                                {task.needsUpdate && task.carriedFrom && (
                                                    <p className="text-muted-foreground mt-1 text-xs">From {task.carriedFrom} — no update this week yet.</p>
                                                )}
                                            </div>
                                            {task.editable ? (
                                                <Button type="button" size="sm" data-feature="feature.dashboard.action_tasks" onClick={() => openTask(task)}>
                                                    Update
                                                </Button>
                                            ) : (
                                                task.showUrl && (
                                                    <Button variant="outline" size="sm" render={<a href={task.showUrl} />}>
                                                        View
                                                    </Button>
                                                )
                                            )}
                                        </div>
                                    ))}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={logOpen} onOpenChange={setLogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {workLog.entry ? 'Edit' : 'Add'} work log · {workLog.todayLabel}
                        </DialogTitle>
                        <DialogDescription>What you record here is available when you write the weekly report.</DialogDescription>
                    </DialogHeader>
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitLog();
                        }}
                    >
                        <Field>
                            <FieldLabel htmlFor="dashboardWorkLogDone">What I did</FieldLabel>
                            <Textarea
                                id="dashboardWorkLogDone"
                                name="done"
                                required
                                maxLength={4000}
                                rows={3}
                                value={logForm.data.done}
                                placeholder="What did you accomplish today?"
                                onChange={(event) => logForm.setData('done', event.target.value)}
                            />
                            <FieldError>{logForm.errors.done}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="dashboardWorkLogBlocker">Blocker (optional)</FieldLabel>
                            <Textarea
                                id="dashboardWorkLogBlocker"
                                name="blocker"
                                maxLength={4000}
                                rows={2}
                                value={logForm.data.blocker}
                                placeholder="Anything blocking progress?"
                                onChange={(event) => logForm.setData('blocker', event.target.value)}
                            />
                            <FieldError>{logForm.errors.blocker}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="dashboardWorkLogNext">Next (optional)</FieldLabel>
                            <Textarea
                                id="dashboardWorkLogNext"
                                name="next"
                                maxLength={4000}
                                rows={2}
                                value={logForm.data.next}
                                placeholder="What’s next?"
                                onChange={(event) => logForm.setData('next', event.target.value)}
                            />
                            <FieldError>{logForm.errors.next}</FieldError>
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setLogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={logForm.processing} loading={logForm.processing}>
                                {workLog.entry ? 'Update' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Performance details · {performance.overallScore}%</DialogTitle>
                        <DialogDescription>Coach tips for this month.</DialogDescription>
                    </DialogHeader>
                    {performance.pendingReviews > 0 && (
                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm">
                            {plural(performance.pendingReviews, 'approval item')} waiting for you now.
                        </p>
                    )}
                    <ul className="list-disc pl-5 text-sm">
                        {performance.tips.length === 0 ? (
                            <li className="text-muted-foreground">No tips right now — keep up the good work.</li>
                        ) : (
                            performance.tips.map((tip) => <li key={tip}>{tip}</li>)
                        )}
                    </ul>
                </DialogContent>
            </Dialog>

            <Dialog open={matrixOpen} onOpenChange={setMatrixOpen}>
                <DialogContent className="sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            Weekly Matrix{matrix ? ` — Week ${matrix.week} · ${matrix.year}` : ''}
                        </DialogTitle>
                    </DialogHeader>
                    {matrixError ? (
                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm">
                            Could not load the weekly matrix. Please try again.
                        </p>
                    ) : matrixHtml ? (
                        <div
                            className="max-h-[60vh] overflow-auto [&_table]:w-full [&_td]:border [&_td]:p-2 [&_th]:border [&_th]:p-2"
                            dangerouslySetInnerHTML={{ __html: matrixHtml }}
                        />
                    ) : (
                        <p className="text-muted-foreground text-sm">Loading matrix…</p>
                    )}
                    {matrix && (
                        <DialogFooter>
                            <Button variant="outline" render={<a href={matrix.openUrl} />}>
                                Open period
                            </Button>
                            <Button variant="outline" render={<a href={matrix.downloadUrl} />}>
                                Download PDF
                            </Button>
                        </DialogFooter>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={taskOpen} onOpenChange={setTaskOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Quick Update</DialogTitle>
                        <DialogDescription>{activeTask?.periodLabel}</DialogDescription>
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
                            <FieldLabel htmlFor="quickTaskProgress">Progress status</FieldLabel>
                            <select
                                id="quickTaskProgress"
                                name="progress"
                                required
                                className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                value={taskForm.data.progress}
                                onChange={(event) => taskForm.setData('progress', event.target.value)}
                            >
                                {progressOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {reassigning && (
                            <Field>
                                <FieldLabel htmlFor="quickTaskReassign">Reassign to</FieldLabel>
                                <select
                                    id="quickTaskReassign"
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
                                <FieldLabel htmlFor="quickTaskNotes">{reassigning ? 'Reason for reassignment' : 'Progress notes'}</FieldLabel>
                                <Textarea
                                    id="quickTaskNotes"
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
        </div>
    );
}

function Stat({ label, value, children }: { label: string; value: number; children?: ReactNode }) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl">{value}</CardTitle>
            </CardHeader>
            {children && <CardContent>{children}</CardContent>}
        </Card>
    );
}

function Score({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border px-2 py-2">
            <div className="font-medium">{value}</div>
            <div className="text-muted-foreground text-xs">{label}</div>
        </div>
    );
}

function GraceRow({ kind, entry }: { kind: string; entry: GraceEntry }) {
    return (
        <li>
            <span className="block">
                {kind} deadline was extended
                {entry.week ? ` for Week ${entry.week} of ${entry.year}` : ''}.
                {entry.until ? ` You can still submit until ${entry.until}.` : ''}
            </span>
            {entry.actionUrl && entry.actionLabel && (
                <Button className="mt-1" size="sm" variant="outline" render={<a href={entry.actionUrl} />}>
                    {entry.actionLabel}
                </Button>
            )}
        </li>
    );
}

function WeeklyAction({ kind, segment }: { kind: 'plan' | 'report'; segment: ReminderSegment }) {
    const title = kind === 'plan' ? 'Weekly plan' : 'Weekly report';
    const cta = segment.needsRevision ? 'Revise' : segment.hasDocument ? 'Open' : 'Create';
    const subtitle = segment.needsRevision
        ? 'Returned for revision — resubmit soon'
        : segment.hasDocument
          ? 'Draft in progress — submit before deadline'
          : 'Not started yet';

    return (
        <a
            href={segment.url ?? '#'}
            data-feature="feature.dashboard.action_needed"
            className="hover:bg-muted flex items-center justify-between gap-3 rounded-lg px-2 py-2"
        >
            <span>
                <span className="block font-medium">{title}</span>
                <span className="text-muted-foreground text-sm">
                    W{segment.week}, {segment.year} · {subtitle}
                </span>
            </span>
            <Badge>{cta}</Badge>
        </a>
    );
}

DashboardIndex.layout = (page: ReactNode) => <AppLayout title="Dashboard">{page}</AppLayout>;
