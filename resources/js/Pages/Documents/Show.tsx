import { Link, useForm } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    CalendarDaysIcon,
    EyeIcon,
    FileTextIcon,
    Link2Icon,
    ListChecksIcon,
    PencilIcon,
    TargetIcon,
    TrophyIcon,
    UsersIcon,
    WalletIcon,
    XCircleIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import DownloadButton from '@/components/shared/DownloadButton';
import RowActions from '@/components/shared/RowActions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { PageHeader, PageShell } from '@/lib/page-kit';

type NamedUser = { id?: string | number; name: string };

type Doc = {
    id?: string;
    title?: string;
    type?: string;
    typeName?: string;
    state?: string;
    userName?: string | null;
    period?: { weekNumber?: number; year?: number; dateRange?: string } | null;
    month?: number | null;
    year?: number | null;
    mainTarget?: string | null;
    targetAccomplished?: boolean | null;
    targetExplanation?: string | null;
    content?: Record<string, unknown>;
    createdAt?: string | null;
    canEdit?: boolean;
    reviewRejectionReason?: string | null;
    budgetReviewStatus?: string | null;
    showUrl?: string;
    editUrl?: string;
    fileName?: string | null;
    downloadUrl?: string | null;
    rejectUrl?: string | null;
};

const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

type MeetingRow = {
    date?: string;
    meeting?: string;
    location?: string;
    status?: string;
    reactions_notes?: string;
    participant_user_ids?: Array<string | number>;
    participants?: string;
    incoming_source_user_name?: string;
    is_readonly_for_current_user?: boolean;
};

function asRows<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

function namesForIds(ids: Array<string | number> | undefined, users: NamedUser[]): string {
    if (!ids?.length) {
        return '—';
    }
    const map = new Map(users.map((user) => [String(user.id), user.name]));
    const names = ids.map((id) => map.get(String(id))).filter(Boolean);
    return names.length > 0 ? names.join(', ') : '—';
}

function money(value: unknown): string {
    const amount = Number(value ?? 0);
    if (!Number.isFinite(amount)) {
        return String(value ?? '—');
    }
    return amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function stateBadge(state?: string | null) {
    const isDraft = state === 'draft';
    return <Badge variant={isDraft ? 'warning' : 'success'}>{isDraft ? 'Draft' : 'Submitted'}</Badge>;
}

function targetBadge(label: string) {
    return <Badge variant={statusBadgeVariant(label)}>{label}</Badge>;
}

function Section({
    title,
    icon,
    extra,
    children,
    className,
}: {
    title: string;
    icon?: ReactNode;
    extra?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('overflow-hidden rounded-lg border bg-card', className)}>
            <div className="flex items-center gap-2 border-b bg-muted/30 px-4 py-3">
                {icon}
                <h3 className="text-sm font-semibold">{title}</h3>
                {extra ? <div className="ml-auto">{extra}</div> : null}
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function EmptyNote({ children }: { children: ReactNode }) {
    return <p className="text-muted-foreground text-sm">{children}</p>;
}

function DataTable({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className="overflow-x-auto rounded-md border">
            <Table className={cn('table-fixed', className)}>{children}</Table>
        </div>
    );
}

function assignedByName(row: Record<string, unknown>, users: NamedUser[]): string {
    const nested = row.source_user ?? row.sourceUser;
    if (nested && typeof nested === 'object' && 'name' in nested) {
        const name = String((nested as { name?: unknown }).name ?? '').trim();
        if (name !== '') {
            return name;
        }
    }

    const direct = String(row.source_user_name ?? '').trim();
    if (direct !== '') {
        return direct;
    }

    const sourceId = String(row.source_user_id ?? '');
    if (sourceId === '') {
        return '';
    }

    return users.find((user) => String(user.id) === sourceId)?.name ?? '';
}

function WrapCell({ children, pre = false }: { children: ReactNode; pre?: boolean }) {
    return (
        <TableCell className="max-w-0 align-top overflow-hidden" style={{ whiteSpace: 'normal' }}>
            <div className="leading-5" style={{ overflowWrap: 'anywhere', whiteSpace: pre ? 'pre-wrap' : 'normal' }}>
                {children}
            </div>
        </TableCell>
    );
}

const WEEKDAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as const;

function weekdayRank(day: string): number {
    const normalized = day.trim().toLowerCase();
    const index = WEEKDAY_ORDER.findIndex((name) => name.toLowerCase() === normalized);
    return index === -1 ? WEEKDAY_ORDER.length : index;
}

function dayLabelFromActivityOutput(value: unknown): string {
    const text = String(value ?? '').trim();
    if (text === '' || text === '__off_plan__') {
        return '';
    }
    const match = text.match(/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\b/i);
    return match?.[1] ?? '';
}

function sortRowsByWeekday(
    rows: Array<Record<string, unknown>>,
    dayFromRow: (row: Record<string, unknown>) => string,
): Array<Record<string, unknown>> {
    return [...rows].sort((left, right) => {
        const leftDay = dayFromRow(left);
        const rightDay = dayFromRow(right);
        const byDay = weekdayRank(leftDay) - weekdayRank(rightDay);
        if (byDay !== 0) {
            return byDay;
        }
        return 0;
    });
}

function groupActivitiesByDay(rows: Array<Record<string, unknown>>) {
    const ordered = sortRowsByWeekday(rows, (row) => String(row.day ?? ''));
    const groups: Array<{ day: string; rows: Array<Record<string, unknown>> }> = [];
    for (const row of ordered) {
        const day = String(row.day ?? '').trim() || '—';
        const last = groups[groups.length - 1];
        if (last && last.day === day) {
            last.rows.push(row);
        } else {
            groups.push({ day, rows: [row] });
        }
    }
    return groups;
}

export default function DocumentsShow({
    document,
    relatedDocument,
    relatedAction,
    showWeeklyRelatedPanel,
    teamUsers = [],
    reportMeetingRows = [],
    incomingMeetings = [],
    assignedWeeklyActivities = [],
    carriedActivities = [],
    indexUrl = '/documents',
}: {
    document: Doc;
    relatedDocument?: Doc | null;
    relatedAction?: string | { type?: string; label?: string; route?: string; description?: string } | null;
    showWeeklyRelatedPanel?: boolean;
    reviewerPresentation?: boolean;
    teamUsers?: NamedUser[];
    reportMeetingRows?: MeetingRow[];
    incomingMeetings?: MeetingRow[];
    assignedWeeklyActivities?: Array<Record<string, unknown>>;
    carriedActivities?: Array<Record<string, unknown>>;
    indexUrl?: string;
    [key: string]: unknown;
}) {
    const rejectForm = useForm({ reason: '' });
    const [rejectOpen, setRejectOpen] = useState(false);
    const title = document.title ?? 'Document';
    const typeLabel = document.typeName ?? document.type ?? '';
    const rejectionReason = (document.reviewRejectionReason ?? '').trim();
    const showRejectionReason = document.state === 'draft' && rejectionReason !== '';
    const isPlan = document.type === 'weekly_plan';
    const isReport = document.type === 'weekly_report';
    const documentKind = isReport ? 'weekly report' : 'weekly plan';
    const isMonthly = document.type === 'monthly_report';
    const content = document.content ?? {};
    const createRelated =
        relatedAction && typeof relatedAction === 'object' && !Array.isArray(relatedAction)
            ? (relatedAction as { type?: string; label?: string; route?: string; description?: string })
            : null;
    const relatedIsReport =
        createRelated?.type === 'weekly_report' || String(relatedDocument?.type ?? '').includes('report');
    const relatedTitle = relatedIsReport ? 'Related Weekly Report' : 'Related Weekly Plan';

    const objective = (content.objective as { reflection_progress?: string } | undefined) ?? {};
    const plannedActivities = asRows<Record<string, unknown>>(content.planned_activities);
    const activityRows = [...carriedActivities, ...plannedActivities];
    const activityGroups = groupActivitiesByDay(activityRows);
    const keyMeetings = asRows<MeetingRow>(content.key_meetings);
    const budget = asRows<Record<string, unknown>>(content.budget).filter(
        (row) =>
            String(row.item ?? '').trim() !== '' ||
            String(row.description_purpose ?? '').trim() !== '' ||
            String(row.unit ?? '').trim() !== '' ||
            String(row.amount ?? '').trim() !== '',
    );
    const activityProgress = sortRowsByWeekday(
        asRows<Record<string, unknown>>(content.activity_progress).filter((row) => {
            return ['activity_output', 'off_plan_activity', 'progress', 'remarks_challenges', 'next_step'].some(
                (key) => String(row[key] ?? '').trim() !== '',
            );
        }),
        (row) => dayLabelFromActivityOutput(row.activity_output) || String(row.day ?? ''),
    );
    const keyAchievements = asRows<unknown>(content.key_achievements)
        .map((item) => (typeof item === 'string' ? item : String((item as { achievement?: string })?.achievement ?? '')))
        .filter((item) => item.trim() !== '');
    const challenges = asRows<Record<string, unknown>>(content.challenges_mitigation).filter(
        (row) => String(row.challenge ?? '').trim() !== '' || String(row.action_taken_planned ?? '').trim() !== '',
    );
    const budgetStatuses = asRows<Record<string, unknown>>(content.budget_statuses);
    const meetings = reportMeetingRows.length > 0 ? reportMeetingRows : keyMeetings;
    const targetStatus = String(content.target_accomplished_status ?? '').trim();
    const targetLabel =
        targetStatus === 'completed'
            ? 'Completed'
            : targetStatus === 'partially_completed'
              ? 'Partially completed'
              : targetStatus === 'not_completed'
                ? 'Not completed'
                : document.targetAccomplished === true
                  ? 'Completed'
                  : document.targetAccomplished === false
                    ? 'Not completed'
                    : null;
    const periodLine = document.period
        ? `Week ${document.period.weekNumber} of ${document.period.year}`
        : document.month && document.year
          ? `${MONTH_NAMES[document.month - 1] ?? `Month ${document.month}`} ${document.year}`
          : null;
    const budgetTotal = budget.reduce((sum, row) => {
        const amount = Number(row.amount ?? 0);
        return sum + (Number.isFinite(amount) ? amount : 0);
    }, 0);
    const planBudget = asRows<Record<string, unknown>>(relatedDocument?.content?.budget).filter(
        (row) =>
            String(row.item ?? '').trim() !== '' ||
            String(row.description_purpose ?? '').trim() !== '' ||
            String(row.unit ?? '').trim() !== '' ||
            String(row.amount ?? '').trim() !== '',
    );

    const submitReject = () => {
        if (!document.rejectUrl) {
            return;
        }
        rejectForm.post(document.rejectUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectOpen(false);
                rejectForm.reset('reason');
            },
        });
    };

    return (
        <PageShell>
            <PageHeader
                title={title}
                description={`${typeLabel}${periodLine ? ` · ${periodLine}` : ''}`}
                actions={
                    <div className="flex items-center gap-2">
                        <BackButton href={indexUrl} />
                        {document.rejectUrl ? (
                            <Button type="button" variant="destructive" size="sm" onClick={() => setRejectOpen(true)}>
                                <XCircleIcon className="size-4" />
                                Reject
                            </Button>
                        ) : null}
                        {document.canEdit && document.editUrl ? (
                            <RowActions
                                items={[
                                    {
                                        label: 'Edit',
                                        href: document.editUrl,
                                        icon: <PencilIcon />,
                                    },
                                ]}
                            />
                        ) : null}
                    </div>
                }
            />

            {showRejectionReason && (
                <Alert className="border-amber-500/40 bg-amber-500/10">
                    <AlertTriangleIcon />
                    <AlertTitle>Returned for revision</AlertTitle>
                    <AlertDescription className="whitespace-pre-wrap text-foreground">{rejectionReason}</AlertDescription>
                </Alert>
            )}

            <Dialog
                open={rejectOpen}
                onOpenChange={(open) => {
                    setRejectOpen(open);
                    if (!open) {
                        rejectForm.reset('reason');
                        rejectForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reject {documentKind}</DialogTitle>
                        <DialogDescription>
                            This {documentKind} goes back to draft so staff can edit it and submit again.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <Field>
                            <FieldLabel htmlFor="reject-reason">Reason</FieldLabel>
                            <Textarea
                                id="reject-reason"
                                rows={4}
                                value={rejectForm.data.reason}
                                onChange={(event) => rejectForm.setData('reason', event.target.value)}
                                placeholder="Explain what needs to change"
                                required
                                minLength={3}
                            />
                            <FieldError>{rejectForm.errors.reason}</FieldError>
                        </Field>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setRejectOpen(false)} disabled={rejectForm.processing}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={submitReject}
                            disabled={rejectForm.processing || rejectForm.data.reason.trim().length < 3}
                            loading={rejectForm.processing}
                        >
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {(isPlan || isReport) && (
                <div className="grid items-start gap-3 lg:grid-cols-2">
                    {showWeeklyRelatedPanel && (
                        <Card className="gap-0 py-0 shadow-none">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20 !px-4 !py-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                    <Link2Icon className="text-muted-foreground size-4" />
                                    {relatedTitle}
                                </CardTitle>
                                {relatedDocument ? stateBadge(relatedDocument.state) : <Badge variant="outline">Not created</Badge>}
                            </CardHeader>
                            <CardContent className="flex items-start justify-between gap-3 !px-4 !py-2.5 text-sm">
                                {relatedDocument ? (
                                    <>
                                        <div className="min-w-0 space-y-0.5">
                                            <div className="font-semibold">{relatedDocument.title}</div>
                                            <p className="text-muted-foreground text-xs">
                                                {[relatedDocument.userName, relatedDocument.createdAt].filter(Boolean).join(' · ') ||
                                                    '—'}
                                            </p>
                                        </div>
                                        {relatedDocument.showUrl && (
                                            <Button size="sm" render={<Link href={relatedDocument.showUrl} />}>
                                                <EyeIcon className="size-4" />
                                                View
                                            </Button>
                                        )}
                                    </>
                                ) : (
                                    <div className="space-y-2">
                                        <EmptyNote>Not created yet for this period.</EmptyNote>
                                        {createRelated?.route && (
                                            <Button size="sm" render={<Link href={createRelated.route} />}>
                                                {createRelated.label ?? 'Create related document'}
                                            </Button>
                                        )}
                                        {createRelated?.description && (
                                            <p className="text-muted-foreground text-xs">{createRelated.description}</p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card className={cn('gap-0 py-0 shadow-none', !showWeeklyRelatedPanel && 'lg:col-span-2')}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20 !px-4 !py-2">
                            <CardTitle className="text-sm font-semibold">Document Information & Actions</CardTitle>
                            {stateBadge(document.state)}
                        </CardHeader>
                        <CardContent className="!px-4 !py-2.5 text-sm">
                            <p className="text-muted-foreground">
                                Created by: {document.userName ?? '—'}
                                {periodLine ? ` | ${periodLine}` : ''}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            )}

            {(isPlan || isReport) && (
                <Card className="shadow-none">
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileTextIcon className="text-muted-foreground size-4" />
                            {title}
                        </CardTitle>
                        {document.downloadUrl && <DownloadButton href={document.downloadUrl} />}
                    </CardHeader>
                    <CardContent className="space-y-4 pt-6">
                        {isReport && (
                            <div className="rounded-lg border border-sky-200 bg-sky-50/40 p-4 dark:border-sky-900 dark:bg-sky-950/20">
                                <div className="mb-4 flex items-center gap-2">
                                    <TargetIcon className="size-4 text-sky-700 dark:text-sky-400" />
                                    <h3 className="text-sm font-semibold">Main Target Accomplishment Status</h3>
                                </div>
                                <div className="space-y-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs">Main Target from Weekly Plan:</p>
                                        <div className="rounded-md border bg-background/80 p-3 whitespace-pre-wrap">
                                            {relatedDocument?.mainTarget || document.mainTarget || 'No main target recorded.'}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-muted-foreground text-xs">Accomplishment Status:</span>
                                        {targetLabel ? targetBadge(targetLabel) : <EmptyNote>Not set yet.</EmptyNote>}
                                    </div>
                                    {document.targetExplanation && (
                                        <div>
                                            <p className="text-muted-foreground mb-1 text-xs">Explanation:</p>
                                            <div className="rounded-md border border-amber-300/70 bg-amber-50/70 p-3 whitespace-pre-wrap dark:border-amber-900 dark:bg-amber-950/30">
                                                {document.targetExplanation}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {isPlan && (
                            <div className="rounded-lg border border-sky-200 bg-sky-50/30 dark:border-sky-900 dark:bg-sky-950/20">
                                <div className="flex items-center gap-2 border-b border-sky-200/70 px-4 py-3 dark:border-sky-900">
                                    <TargetIcon className="size-4 text-sky-700 dark:text-sky-400" />
                                    <h3 className="text-sm font-semibold">Objective / Main Target</h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                <TableHead className="w-1/2">Main Target</TableHead>
                                                <TableHead className="w-1/2">Reflection / Progress</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow>
                                                <TableCell className="align-top whitespace-pre-wrap text-sm">
                                                    {document.mainTarget || '—'}
                                                </TableCell>
                                                <TableCell className="align-top whitespace-pre-wrap text-sm">
                                                    {objective.reflection_progress || '—'}
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        {isPlan && (
                            <>
                                <Section title="Planned Activities" icon={<CalendarDaysIcon className="text-muted-foreground size-4" />}>
                                    {activityRows.length === 0 ? (
                                        <EmptyNote>No planned activities.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead className="w-24 text-center">Day</TableHead>
                                                    <TableHead className="w-[34%]">Activity</TableHead>
                                                    <TableHead className="w-[16%]">Location</TableHead>
                                                    <TableHead className="w-[22%]">Lead Staff/Team</TableHead>
                                                    <TableHead className="w-[22%]">Notes</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activityGroups.map((group) =>
                                                    group.rows.map((row, index) => (
                                                        <TableRow key={`${group.day}-${index}`}>
                                                            {index === 0 && (
                                                                <TableCell
                                                                    rowSpan={group.rows.length}
                                                                    className="bg-muted/10 align-middle text-center font-semibold"
                                                                >
                                                                    {group.day}
                                                                </TableCell>
                                                            )}
                                                            <TableCell className="align-top whitespace-normal break-words">{String(row.activity ?? '—')}</TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">{String(row.location ?? '—')}</TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">
                                                                {namesForIds(
                                                                    Array.isArray(row.team_member_ids)
                                                                        ? (row.team_member_ids as Array<string | number>)
                                                                        : [],
                                                                    teamUsers,
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">
                                                                {String(row.notes_inclusion ?? '—')}
                                                            </TableCell>
                                                        </TableRow>
                                                    )),
                                                )}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                {(assignedWeeklyActivities.length > 0 || incomingMeetings.length > 0) && (
                                    <Section title="Assigned by others" icon={<UsersIcon className="text-muted-foreground size-4" />}>
                                        {assignedWeeklyActivities.length > 0 && (
                                            <div className="mb-4 space-y-2">
                                                <h4 className="text-sm font-medium">Assigned activities</h4>
                                                {assignedWeeklyActivities.map((row, index) => {
                                                    const payload =
                                                        (row.activity_payload as Record<string, unknown> | undefined) ?? row;
                                                    const assignedBy = assignedByName(row, teamUsers);
                                                    return (
                                                        <div key={index} className="rounded-md border p-3 text-sm">
                                                            <div className="font-medium">{String(payload.activity ?? 'Activity')}</div>
                                                            <div className="text-muted-foreground text-xs">
                                                                {[payload.day, payload.location].filter(Boolean).join(' · ')}
                                                            </div>
                                                            {assignedBy !== '' && (
                                                                <div className="text-muted-foreground text-xs">Assigned by {assignedBy}</div>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                        {incomingMeetings.length > 0 && (
                                            <div className="space-y-2">
                                                <h4 className="text-sm font-medium">Assigned meetings</h4>
                                                {incomingMeetings.map((row, index) => (
                                                    <div key={index} className="rounded-md border p-3 text-sm">
                                                        <div className="font-medium">{row.meeting || 'Meeting'}</div>
                                                        <div className="text-muted-foreground text-xs">
                                                            {[row.date, row.location].filter(Boolean).join(' · ')}
                                                        </div>
                                                        {row.incoming_source_user_name && (
                                                            <div className="text-muted-foreground text-xs">
                                                                Assigned by {row.incoming_source_user_name}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </Section>
                                )}

                                <Section
                                    title="Key Meetings / Engagements"
                                    icon={<UsersIcon className="text-muted-foreground size-4" />}
                                >
                                    {keyMeetings.length === 0 ? (
                                        <EmptyNote>No meetings planned.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <colgroup>
                                                <col style={{ width: '14%' }} />
                                                <col style={{ width: '28%' }} />
                                                <col style={{ width: '16%' }} />
                                                <col style={{ width: '12%' }} />
                                                <col style={{ width: '30%' }} />
                                            </colgroup>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>Meeting</TableHead>
                                                    <TableHead>Participants</TableHead>
                                                    <TableHead>Location</TableHead>
                                                    <TableHead>Notes</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {keyMeetings.map((row, index) => (
                                                    <TableRow key={index}>
                                                        <WrapCell>{row.date || '—'}</WrapCell>
                                                        <WrapCell>{row.meeting || '—'}</WrapCell>
                                                        <WrapCell>
                                                            {row.participants || namesForIds(row.participant_user_ids, teamUsers)}
                                                        </WrapCell>
                                                        <WrapCell>{row.location || '—'}</WrapCell>
                                                        <WrapCell pre>{row.reactions_notes || ''}</WrapCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                <Section
                                    title="Budget"
                                    icon={<WalletIcon className="text-muted-foreground size-4" />}
                                    extra={
                                        document.budgetReviewStatus ? (
                                            <Badge variant={statusBadgeVariant(document.budgetReviewStatus)}>
                                                {document.budgetReviewStatus}
                                            </Badge>
                                        ) : null
                                    }
                                >
                                    {budget.length === 0 ? (
                                        <EmptyNote>No budget items.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead>Budget Item / Activity</TableHead>
                                                    <TableHead>Description / Purpose</TableHead>
                                                    <TableHead>Unit</TableHead>
                                                    <TableHead className="text-right">Amount (TZS)</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {budget.map((row, index) => (
                                                    <TableRow key={index}>
                                                        <TableCell>{String(row.item ?? '—')}</TableCell>
                                                        <TableCell className="whitespace-pre-wrap">
                                                            {String(row.description_purpose ?? '—')}
                                                        </TableCell>
                                                        <TableCell>{String(row.unit ?? '') || '—'}</TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {money(row.amount)}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                                <TableRow className="bg-muted/20 font-semibold">
                                                    <TableCell />
                                                    <TableCell />
                                                    <TableCell className="text-right">Total</TableCell>
                                                    <TableCell className="text-right tabular-nums">{money(budgetTotal)}</TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>
                            </>
                        )}

                        {isReport && (
                            <>
                                <Section title="Summary of the Week" icon={<FileTextIcon className="text-muted-foreground size-4" />}>
                                    {typeof content.summary === 'string' && content.summary.trim() !== '' ? (
                                        <p className="whitespace-pre-wrap text-sm leading-relaxed">{content.summary}</p>
                                    ) : (
                                        <EmptyNote>No summary provided.</EmptyNote>
                                    )}
                                </Section>

                                <Section
                                    title="Weekly Activity Progress"
                                    icon={<ListChecksIcon className="text-muted-foreground size-4" />}
                                >
                                    {activityProgress.length === 0 ? (
                                        <EmptyNote>No activity progress recorded.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead className="w-14 text-center">No.</TableHead>
                                                    <TableHead className="w-[32%]">Activity/Output</TableHead>
                                                    <TableHead className="w-[16%]">Progress</TableHead>
                                                    <TableHead className="w-[26%]">Remarks/Challenges</TableHead>
                                                    <TableHead className="w-[26%]">Next Step</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activityProgress.map((row, index) => {
                                                    const progress = String(row.progress ?? '');
                                                    const carry =
                                                        progress === 'Partially done' ||
                                                        progress === 'Done and carry' ||
                                                        Boolean(row.carry_to_next_plan);
                                                    return (
                                                        <TableRow key={index}>
                                                            <TableCell className="text-center tabular-nums">{index + 1}</TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">
                                                                {String(row.activity_output) === '__off_plan__'
                                                                    ? String(row.off_plan_activity ?? 'Off-plan activity')
                                                                    : String(row.activity_output ?? '—')}
                                                            </TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">{progress || '—'}</TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">
                                                                {String(row.remarks_challenges ?? '') || '—'}
                                                            </TableCell>
                                                            <TableCell className="align-top whitespace-normal break-words">
                                                                <div>{String(row.next_step ?? '') || '—'}</div>
                                                                {carry && (
                                                                    <div className="text-sky-700 dark:text-sky-400 mt-1 text-xs font-medium">
                                                                        Will be carried to next weekly plan
                                                                    </div>
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                <Section title="Key Achievements" icon={<TrophyIcon className="text-muted-foreground size-4" />}>
                                    {keyAchievements.length === 0 ? (
                                        <EmptyNote>No achievements listed.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead className="w-12 text-center">No.</TableHead>
                                                    <TableHead>Achievement</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {keyAchievements.map((item, index) => (
                                                    <TableRow key={index}>
                                                        <TableCell className="text-center tabular-nums">{index + 1}</TableCell>
                                                        <TableCell className="whitespace-pre-wrap">{item}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                <Section
                                    title="Meetings Progress"
                                    icon={<UsersIcon className="text-muted-foreground size-4" />}
                                >
                                    {meetings.length === 0 ? (
                                        <EmptyNote>No meetings recorded.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <colgroup>
                                                <col style={{ width: '12%' }} />
                                                <col style={{ width: '24%' }} />
                                                <col style={{ width: '16%' }} />
                                                <col style={{ width: '12%' }} />
                                                <col style={{ width: '12%' }} />
                                                <col style={{ width: '24%' }} />
                                            </colgroup>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>Meeting / Engagement</TableHead>
                                                    <TableHead>Participant(s)</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead>Location</TableHead>
                                                    <TableHead>Reactions / Notes</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {meetings.map((row, index) => (
                                                    <TableRow key={index}>
                                                        <WrapCell>{row.date || '—'}</WrapCell>
                                                        <WrapCell>
                                                            {row.meeting || '—'}
                                                            {row.is_readonly_for_current_user && (
                                                                <Badge variant="outline" className="ml-2">
                                                                    Assigned
                                                                </Badge>
                                                            )}
                                                        </WrapCell>
                                                        <WrapCell>
                                                            {row.participants || namesForIds(row.participant_user_ids, teamUsers)}
                                                        </WrapCell>
                                                        <WrapCell>{row.status || '—'}</WrapCell>
                                                        <WrapCell>{row.location || '—'}</WrapCell>
                                                        <WrapCell pre>{row.reactions_notes || ''}</WrapCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                <Section
                                    title="Challenges & Mitigation"
                                    icon={<AlertTriangleIcon className="text-muted-foreground size-4" />}
                                >
                                    {challenges.length === 0 ? (
                                        <EmptyNote>No challenges recorded.</EmptyNote>
                                    ) : (
                                        <DataTable>
                                            <TableHeader>
                                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                    <TableHead>Challenge(s)</TableHead>
                                                    <TableHead>Action Taken/Planned</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {challenges.map((row, index) => (
                                                    <TableRow key={index}>
                                                        <TableCell className="align-top whitespace-pre-wrap">
                                                            {String(row.challenge ?? '—')}
                                                        </TableCell>
                                                        <TableCell className="align-top whitespace-pre-wrap">
                                                            {String(row.action_taken_planned ?? '—')}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </DataTable>
                                    )}
                                </Section>

                                {(planBudget.length > 0 || budgetStatuses.length > 0) && (
                                    <Section
                                        title="Budget Status (from weekly plan)"
                                        icon={<WalletIcon className="text-muted-foreground size-4" />}
                                    >
                                        {(() => {
                                            const statusByUid = new Map(
                                                budgetStatuses
                                                    .filter((row) => String(row.budget_uid ?? '').trim() !== '')
                                                    .map((row) => [String(row.budget_uid), row]),
                                            );
                                            const rows =
                                                planBudget.length > 0
                                                    ? planBudget.map((planRow, index) => {
                                                          const uid = String(planRow.budget_uid ?? '').trim();
                                                          const status =
                                                              (uid && statusByUid.get(uid)) || budgetStatuses[index] || {};
                                                          return {
                                                              item: String(planRow.item ?? status.item ?? '—'),
                                                              purpose: String(planRow.description_purpose ?? '—'),
                                                              amount: planRow.amount,
                                                              status: String(status.status ?? '—'),
                                                              notes: String(status.notes ?? ''),
                                                          };
                                                      })
                                                    : budgetStatuses.map((row) => ({
                                                          item: String(row.item ?? row.budget_uid ?? '—'),
                                                          purpose: '—',
                                                          amount: '',
                                                          status: String(row.status ?? '—'),
                                                          notes: String(row.notes ?? ''),
                                                      }));
                                            const total = rows.reduce((sum, row) => {
                                                const amount = Number(row.amount ?? 0);
                                                return sum + (Number.isFinite(amount) ? amount : 0);
                                            }, 0);

                                            return (
                                                <DataTable>
                                                    <TableHeader>
                                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                                            <TableHead>Budget Item</TableHead>
                                                            <TableHead>Description / Purpose</TableHead>
                                                            <TableHead className="text-right">Amount</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead>Notes</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {rows.map((row, index) => (
                                                            <TableRow key={index}>
                                                                <TableCell>{row.item}</TableCell>
                                                                <TableCell className="whitespace-pre-wrap">
                                                                    {row.purpose}
                                                                </TableCell>
                                                                <TableCell className="text-right tabular-nums">
                                                                    {row.amount === '' ? '—' : money(row.amount)}
                                                                </TableCell>
                                                                <TableCell>{row.status}</TableCell>
                                                                <TableCell className="whitespace-pre-wrap">
                                                                    {row.notes || ''}
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                        {planBudget.length > 0 && (
                                                            <TableRow className="bg-muted/20 font-semibold">
                                                                <TableCell />
                                                                <TableCell className="text-right">Total</TableCell>
                                                                <TableCell className="text-right tabular-nums">
                                                                    {money(total)}
                                                                </TableCell>
                                                                <TableCell colSpan={2} />
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </DataTable>
                                            );
                                        })()}
                                    </Section>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            )}

            {isMonthly && (
                <Card className="shadow-none">
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileTextIcon className="text-muted-foreground size-4" />
                            {title}
                        </CardTitle>
                        {stateBadge(document.state)}
                    </CardHeader>
                    <CardContent className="space-y-4 pt-6">
                        <p className="text-muted-foreground text-sm">
                            Created by: {document.userName ?? '—'}
                            {periodLine ? ` · ${periodLine}` : ''}
                        </p>
                        {document.downloadUrl ? (
                            <div className="flex flex-wrap items-center gap-3">
                                <DownloadButton href={document.downloadUrl} external>
                                    Open PDF
                                </DownloadButton>
                                {document.fileName && (
                                    <span className="text-muted-foreground text-sm">{document.fileName}</span>
                                )}
                            </div>
                        ) : (
                            <EmptyNote>No PDF uploaded for this monthly report.</EmptyNote>
                        )}
                    </CardContent>
                </Card>
            )}

            {!isPlan && !isReport && !isMonthly && (
                <Card className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="text-base">Content</CardTitle>
                    </CardHeader>
                    <CardContent className="pt-6">
                        {typeof content.summary === 'string' && content.summary.trim() !== '' ? (
                            <p className="whitespace-pre-wrap text-sm">{content.summary}</p>
                        ) : (
                            <EmptyNote>No additional content to display.</EmptyNote>
                        )}
                    </CardContent>
                </Card>
            )}
        </PageShell>
    );
}

DocumentsShow.layout = (page: ReactNode) => <AppLayout title="Document Details">{page}</AppLayout>;
