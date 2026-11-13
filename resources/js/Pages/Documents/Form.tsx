import { Link, useForm } from '@inertiajs/react';
import { PlusIcon, Target, Trash2Icon } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader } from '@/lib/page-kit';

type StaffUser = { id: string; name: string };
type Period = { id: string; weekNumber: number; year: number; dateRange: string };

type ActivityRow = {
    day: string;
    activity: string;
    location: string;
    team_member_ids: string[];
    notes_inclusion: string;
    activity_uid?: string;
    carry_over_source_weekly_report_id?: string;
};

type MeetingRow = {
    meeting_uid?: string;
    date: string;
    meeting: string;
    participant_user_ids: string[];
    location: string;
    reactions_notes: string;
    status?: string;
    source_type?: string;
    source_plan_id?: string;
    source_owner_user_id?: string;
    is_readonly_for_current_user?: boolean | string;
};

type BudgetRow = { item: string; description_purpose: string; amount: string; budget_uid?: string };
type ProgressRow = {
    activity_output: string;
    off_plan_activity?: string;
    progress: string;
    remarks_challenges: string;
    next_step: string;
};
type ChallengeRow = { challenge: string; action_taken_planned: string };

type DocumentPayload = {
    id?: string;
    type?: string;
    title?: string;
    mainTarget?: string | null;
    content?: Record<string, unknown>;
    periodId?: string | null;
    sharedUsers?: StaffUser[];
    canEdit?: boolean;
};

type Props = {
    mode: 'create' | 'edit';
    document?: DocumentPayload | null;
    selectedType?: string | null;
    availableTypes?: Record<string, string>;
    workPeriods?: Period[];
    defaultPeriodId?: string | null;
    relatedPlan?: DocumentPayload | null;
    carriedActivities?: ActivityRow[];
    reportMeetingRows?: MeetingRow[];
    teamUsers?: StaffUser[];
    shareableUsers?: StaffUser[];
    currentUserId?: string;
    indexUrl?: string;
    submitUrl: string;
};

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const LOCATIONS = ['Office', 'Field', 'Virtual', 'On site'];
const PROGRESS = ['Done', 'Done and carry', 'Partially done', 'Not started'];

function emptyActivity(userId: string): ActivityRow {
    return {
        day: 'Monday',
        activity: '',
        location: '',
        team_member_ids: userId ? [userId] : [],
        notes_inclusion: '',
        activity_uid: '',
        carry_over_source_weekly_report_id: '',
    };
}

function emptyMeeting(userId: string): MeetingRow {
    return {
        meeting_uid: '',
        date: '',
        meeting: '',
        participant_user_ids: userId ? [userId] : [],
        location: '',
        reactions_notes: '',
        status: '',
        source_type: 'report_manual',
        source_plan_id: '',
        source_owner_user_id: userId,
        is_readonly_for_current_user: false,
    };
}

function emptyBudget(): BudgetRow {
    return { item: '', description_purpose: '', amount: '' };
}

function StaffMultiSelect({
    users,
    value,
    onChange,
}: {
    users: StaffUser[];
    value: string[];
    onChange: (ids: string[]) => void;
}) {
    return (
        <div className="border-input bg-background max-h-36 space-y-1 overflow-y-auto rounded-md border p-2">
            {users.length === 0 ? (
                <p className="text-muted-foreground text-xs">No staff available</p>
            ) : (
                users.map((user) => {
                    const checked = value.includes(user.id);
                    return (
                        <label key={user.id} className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={checked}
                                onChange={(event) => {
                                    if (event.target.checked) {
                                        onChange([...value, user.id]);
                                    } else {
                                        onChange(value.filter((id) => id !== user.id));
                                    }
                                }}
                            />
                            <span className="truncate">{user.name}</span>
                        </label>
                    );
                })
            )}
        </div>
    );
}

export default function DocumentsForm({
    mode,
    document = null,
    selectedType = null,
    availableTypes = {},
    workPeriods = [],
    defaultPeriodId = null,
    relatedPlan = null,
    carriedActivities = [],
    reportMeetingRows = [],
    teamUsers = [],
    shareableUsers = [],
    currentUserId = '',
    indexUrl = '/documents',
    submitUrl,
}: Props) {
    const type = String(document?.type ?? selectedType ?? '');
    const isPlan = type === 'weekly_plan';
    const isReport = type === 'weekly_report';
    const content = (document?.content ?? {}) as Record<string, unknown>;

    const initialActivities: ActivityRow[] = useMemo(() => {
        if (isPlan) {
            const existing = Array.isArray(content.planned_activities) ? (content.planned_activities as ActivityRow[]) : [];
            if (existing.length > 0) {
                return existing.map((row) => ({
                    ...emptyActivity(currentUserId),
                    ...row,
                    team_member_ids: (row.team_member_ids ?? []).map(String),
                }));
            }
            if (carriedActivities.length > 0) {
                return carriedActivities.map((row) => ({
                    ...emptyActivity(currentUserId),
                    ...row,
                    team_member_ids: (row.team_member_ids ?? []).map(String),
                }));
            }
            return [emptyActivity(currentUserId)];
        }
        return [];
    }, [isPlan, content.planned_activities, carriedActivities, currentUserId]);

    const initialMeetings: MeetingRow[] = useMemo(() => {
        if (isPlan) {
            const existing = Array.isArray(content.key_meetings) ? (content.key_meetings as MeetingRow[]) : [];
            if (existing.length > 0) {
                return existing.map((row) => ({
                    ...emptyMeeting(currentUserId),
                    ...row,
                    participant_user_ids: (row.participant_user_ids ?? []).map(String),
                }));
            }
            return [emptyMeeting(currentUserId)];
        }
        if (isReport) {
            const existing = Array.isArray(content.key_meetings) ? (content.key_meetings as MeetingRow[]) : [];
            const seed = existing.length > 0 ? existing : reportMeetingRows;
            if (seed.length > 0) {
                return seed.map((row) => ({
                    ...emptyMeeting(currentUserId),
                    ...row,
                    participant_user_ids: (row.participant_user_ids ?? []).map(String),
                }));
            }
            return [emptyMeeting(currentUserId)];
        }
        return [];
    }, [isPlan, isReport, content.key_meetings, reportMeetingRows, currentUserId]);

    const initialBudget: BudgetRow[] = useMemo(() => {
        if (!isPlan) {
            return [];
        }
        const existing = Array.isArray(content.budget) ? (content.budget as BudgetRow[]) : [];
        return existing.length > 0 ? existing.map((row) => ({ ...emptyBudget(), ...row })) : [emptyBudget()];
    }, [isPlan, content.budget]);

    const planActivityLabels = useMemo(() => {
        const planContent = (relatedPlan?.content ?? {}) as Record<string, unknown>;
        const rows = Array.isArray(planContent.planned_activities) ? (planContent.planned_activities as ActivityRow[]) : [];
        return rows
            .filter((row) => String(row.activity ?? '').trim() !== '')
            .map((row) => {
                const day = String(row.day ?? '').trim();
                const activity = String(row.activity ?? '').trim();
                return day ? `${day} - ${activity}` : activity;
            });
    }, [relatedPlan]);

    const initialProgress: ProgressRow[] = useMemo(() => {
        if (!isReport) {
            return [];
        }
        const existing = Array.isArray(content.activity_progress) ? (content.activity_progress as ProgressRow[]) : [];
        if (existing.length > 0) {
            return existing;
        }
        if (planActivityLabels.length > 0) {
            return planActivityLabels.map((label) => ({
                activity_output: label,
                off_plan_activity: '',
                progress: '',
                remarks_challenges: '',
                next_step: '',
            }));
        }
        return [{ activity_output: '', off_plan_activity: '', progress: '', remarks_challenges: '', next_step: '' }];
    }, [isReport, content.activity_progress, planActivityLabels]);

    const initialAchievements: string[] = useMemo(() => {
        if (!isReport) {
            return [];
        }
        const existing = Array.isArray(content.key_achievements) ? (content.key_achievements as string[]) : [];
        return existing.length > 0 ? existing.map(String) : [''];
    }, [isReport, content.key_achievements]);

    const initialChallenges: ChallengeRow[] = useMemo(() => {
        if (!isReport) {
            return [];
        }
        const existing = Array.isArray(content.challenges_mitigation)
            ? (content.challenges_mitigation as ChallengeRow[])
            : [];
        return existing.length > 0 ? existing : [{ challenge: '', action_taken_planned: '' }];
    }, [isReport, content.challenges_mitigation]);

    const form = useForm({
        type,
        period_id: String(document?.periodId ?? defaultPeriodId ?? ''),
        action: 'draft' as string,
        main_target: String(document?.mainTarget ?? ''),
        shared_user_ids: (document?.sharedUsers ?? []).map((user) => String(user.id)),
        content: {
            objective: {
                reflection_progress: String((content.objective as { reflection_progress?: string } | undefined)?.reflection_progress ?? ''),
            },
            planned_activities: initialActivities,
            key_meetings: initialMeetings,
            budget: initialBudget,
            summary: String(content.summary ?? ''),
            activity_progress: initialProgress,
            key_achievements: initialAchievements,
            challenges_mitigation: initialChallenges,
            budget_statuses: Array.isArray(content.budget_statuses) ? content.budget_statuses : [],
        },
    });

    const [collabOpen, setCollabOpen] = useState(form.data.shared_user_ids.length > 0);
    const periodLabel = workPeriods.find((period) => period.id === form.data.period_id);
    const pageTitle = isPlan
        ? periodLabel
            ? `Weekly Plan · Week ${periodLabel.weekNumber}`
            : 'Weekly Plan'
        : isReport
          ? periodLabel
              ? `Weekly Report · Week ${periodLabel.weekNumber}`
              : 'Weekly Report'
          : mode === 'edit'
            ? 'Edit document'
            : 'Create document';

    const submit = (action: string) => {
        form.transform((data) => ({ ...data, action }));
        const options = {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        };
        if (mode === 'edit') {
            form.put(submitUrl, options);
        } else {
            form.post(submitUrl, options);
        }
    };

    const updateActivity = (index: number, patch: Partial<ActivityRow>) => {
        const rows = [...form.data.content.planned_activities];
        rows[index] = { ...rows[index], ...patch };
        form.setData('content', { ...form.data.content, planned_activities: rows });
    };

    const updateMeeting = (index: number, patch: Partial<MeetingRow>) => {
        const rows = [...form.data.content.key_meetings];
        rows[index] = { ...rows[index], ...patch };
        form.setData('content', { ...form.data.content, key_meetings: rows });
    };

    const updateBudget = (index: number, patch: Partial<BudgetRow>) => {
        const rows = [...form.data.content.budget];
        rows[index] = { ...rows[index], ...patch };
        form.setData('content', { ...form.data.content, budget: rows });
    };

    const updateProgress = (index: number, patch: Partial<ProgressRow>) => {
        const rows = [...form.data.content.activity_progress];
        rows[index] = { ...rows[index], ...patch };
        form.setData('content', { ...form.data.content, activity_progress: rows });
    };

    return (
        <div className="mx-auto flex max-w-6xl flex-col gap-6">
            <PageHeader
                title={pageTitle}
                description={
                    isPlan
                        ? 'Fill the form below and save draft or submit.'
                        : isReport
                          ? 'Complete your weekly report, then continue to review.'
                          : 'Document information'
                }
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back to Documents
                    </Button>
                }
            />

            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Document Information</CardTitle>
                    <CardDescription>
                        {availableTypes[type] ?? type}
                        {periodLabel ? ` · Week ${periodLabel.weekNumber} of ${periodLabel.year} (${periodLabel.dateRange})` : ''}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6 pt-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block space-y-1.5 text-sm">
                            <span className="font-medium">Document Type</span>
                            <Input value={availableTypes[type] ?? type} disabled />
                            <input type="hidden" value={form.data.type} readOnly />
                        </label>
                        <label className="block space-y-1.5 text-sm">
                            <span className="font-medium">Work Period</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm disabled:opacity-70"
                                value={form.data.period_id}
                                disabled={mode === 'edit' || Boolean(defaultPeriodId)}
                                onChange={(event) => form.setData('period_id', event.target.value)}
                            >
                                <option value="">Select period</option>
                                {workPeriods.map((period) => (
                                    <option key={period.id} value={period.id}>
                                        Week {period.weekNumber} of {period.year} ({period.dateRange})
                                    </option>
                                ))}
                            </select>
                            {form.errors.period_id && <p className="text-destructive text-sm">{form.errors.period_id}</p>}
                        </label>
                    </div>

                    {isPlan && (
                        <>
                            <div className="rounded-lg border">
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                                    onClick={() => setCollabOpen((open) => !open)}
                                >
                                    <span>Collaboration (Optional)</span>
                                    <span className="text-muted-foreground">{collabOpen ? 'Hide' : 'Show'}</span>
                                </button>
                                {collabOpen && (
                                    <div className="border-t p-4">
                                        <p className="text-muted-foreground mb-3 text-xs">
                                            Selected staff can view, edit, and submit this weekly plan while it remains draft.
                                        </p>
                                        <StaffMultiSelect
                                            users={shareableUsers}
                                            value={form.data.shared_user_ids}
                                            onChange={(ids) => form.setData('shared_user_ids', ids)}
                                        />
                                        {form.errors.shared_user_ids && (
                                            <p className="text-destructive mt-2 text-sm">{form.errors.shared_user_ids}</p>
                                        )}
                                    </div>
                                )}
                            </div>

                            <section className="space-y-3 rounded-lg border p-4">
                                <h3 className="flex items-center gap-2 font-medium">
                                    <Target className="size-4" />
                                    1. Key Objective for the Week
                                </h3>
                                <label className="block space-y-1.5 text-sm">
                                    <span className="font-medium">Objective (s) / Main Target (s) *</span>
                                    <Textarea
                                        rows={5}
                                        value={form.data.main_target}
                                        placeholder="Enter the main objective for this week"
                                        onChange={(event) => form.setData('main_target', event.target.value)}
                                    />
                                    {form.errors.main_target && <p className="text-destructive text-sm">{form.errors.main_target}</p>}
                                </label>
                                <label className="block space-y-1.5 text-sm">
                                    <span className="font-medium">Reflection / Progress</span>
                                    <Textarea
                                        rows={4}
                                        value={form.data.content.objective.reflection_progress}
                                        placeholder="What progress/reflection did you have?"
                                        onChange={(event) =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                objective: { reflection_progress: event.target.value },
                                            })
                                        }
                                    />
                                </label>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">2. Planned Activities</h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                planned_activities: [...form.data.content.planned_activities, emptyActivity(currentUserId)],
                                            })
                                        }
                                    >
                                        <PlusIcon className="size-4" /> Add Activity
                                    </Button>
                                </div>
                                <div className="space-y-4">
                                    {form.data.content.planned_activities.map((row, index) => (
                                        <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Day</span>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                    value={row.day}
                                                    onChange={(event) => updateActivity(index, { day: event.target.value })}
                                                >
                                                    <option value="">-- Select --</option>
                                                    {DAYS.map((day) => (
                                                        <option key={day} value={day}>
                                                            {day}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Location</span>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                    value={row.location}
                                                    onChange={(event) => updateActivity(index, { location: event.target.value })}
                                                >
                                                    <option value="">-- Select --</option>
                                                    {LOCATIONS.map((location) => (
                                                        <option key={location} value={location}>
                                                            {location}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Activity</span>
                                                <Textarea
                                                    rows={4}
                                                    value={row.activity}
                                                    onChange={(event) => updateActivity(index, { activity: event.target.value })}
                                                />
                                            </label>
                                            <div className="space-y-1 text-sm">
                                                <span className="font-medium">Staff / Team</span>
                                                <StaffMultiSelect
                                                    users={teamUsers}
                                                    value={row.team_member_ids}
                                                    onChange={(ids) => updateActivity(index, { team_member_ids: ids })}
                                                />
                                            </div>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Notes / Inclusion</span>
                                                <Textarea
                                                    rows={4}
                                                    value={row.notes_inclusion}
                                                    onChange={(event) => updateActivity(index, { notes_inclusion: event.target.value })}
                                                />
                                            </label>
                                            <div className="md:col-span-2 flex justify-end">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={form.data.content.planned_activities.length <= 1}
                                                    onClick={() =>
                                                        form.setData('content', {
                                                            ...form.data.content,
                                                            planned_activities: form.data.content.planned_activities.filter((_, i) => i !== index),
                                                        })
                                                    }
                                                >
                                                    <Trash2Icon className="size-4" /> Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">3. Key Meetings / Engagements</h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                key_meetings: [...form.data.content.key_meetings, emptyMeeting(currentUserId)],
                                            })
                                        }
                                    >
                                        <PlusIcon className="size-4" /> Add Meeting
                                    </Button>
                                </div>
                                <div className="space-y-4">
                                    {form.data.content.key_meetings.map((row, index) => (
                                        <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Date</span>
                                                <Input
                                                    type="date"
                                                    value={row.date}
                                                    onChange={(event) => updateMeeting(index, { date: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Location</span>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                    value={row.location}
                                                    onChange={(event) => updateMeeting(index, { location: event.target.value })}
                                                >
                                                    <option value="">-- Select --</option>
                                                    {LOCATIONS.map((location) => (
                                                        <option key={location} value={location}>
                                                            {location}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Meeting / Engagement</span>
                                                <Input
                                                    value={row.meeting}
                                                    placeholder="e.g., MIT Engagement ..."
                                                    onChange={(event) => updateMeeting(index, { meeting: event.target.value })}
                                                />
                                            </label>
                                            <div className="space-y-1 text-sm">
                                                <span className="font-medium">Participant(s)</span>
                                                <StaffMultiSelect
                                                    users={teamUsers}
                                                    value={row.participant_user_ids}
                                                    onChange={(ids) => updateMeeting(index, { participant_user_ids: ids })}
                                                />
                                            </div>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Reactions / Notes</span>
                                                <Textarea
                                                    rows={4}
                                                    value={row.reactions_notes}
                                                    onChange={(event) => updateMeeting(index, { reactions_notes: event.target.value })}
                                                />
                                            </label>
                                            <div className="md:col-span-2 flex justify-end">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={form.data.content.key_meetings.length <= 1}
                                                    onClick={() =>
                                                        form.setData('content', {
                                                            ...form.data.content,
                                                            key_meetings: form.data.content.key_meetings.filter((_, i) => i !== index),
                                                        })
                                                    }
                                                >
                                                    <Trash2Icon className="size-4" /> Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">4. Budget</h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                budget: [...form.data.content.budget, emptyBudget()],
                                            })
                                        }
                                    >
                                        <PlusIcon className="size-4" /> Add Budget
                                    </Button>
                                </div>
                                <div className="space-y-4">
                                    {form.data.content.budget.map((row, index) => (
                                        <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_1fr_10rem_auto]">
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Budget Item / Activity</span>
                                                <Input
                                                    value={row.item}
                                                    placeholder="e.g., Printing books .."
                                                    onChange={(event) => updateBudget(index, { item: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Description / Purpose</span>
                                                <Textarea
                                                    rows={3}
                                                    value={row.description_purpose}
                                                    onChange={(event) => updateBudget(index, { description_purpose: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Amount (TZS)</span>
                                                <Input
                                                    value={row.amount}
                                                    placeholder="e.g., 25000"
                                                    onChange={(event) => updateBudget(index, { amount: event.target.value })}
                                                />
                                            </label>
                                            <div className="flex items-end">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={form.data.content.budget.length <= 1}
                                                    onClick={() =>
                                                        form.setData('content', {
                                                            ...form.data.content,
                                                            budget: form.data.content.budget.filter((_, i) => i !== index),
                                                        })
                                                    }
                                                >
                                                    <Trash2Icon className="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </>
                    )}

                    {isReport && (
                        <>
                            <section className="space-y-3 rounded-lg border p-4">
                                <h3 className="font-medium">Main Target (from weekly plan)</h3>
                                {relatedPlan?.mainTarget ? (
                                    <div className="bg-muted rounded-md border p-3 text-sm">
                                        <strong>{relatedPlan.mainTarget}</strong>
                                    </div>
                                ) : (
                                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                        No weekly plan found for the selected work period. Create a weekly plan first.
                                    </div>
                                )}
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <h3 className="font-medium">1. Summary of the Week</h3>
                                <Textarea
                                    rows={5}
                                    value={form.data.content.summary}
                                    onChange={(event) =>
                                        form.setData('content', { ...form.data.content, summary: event.target.value })
                                    }
                                />
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">2. Activity Progress</h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                activity_progress: [
                                                    ...form.data.content.activity_progress,
                                                    {
                                                        activity_output: '__off_plan__',
                                                        off_plan_activity: '',
                                                        progress: '',
                                                        remarks_challenges: '',
                                                        next_step: '',
                                                    },
                                                ],
                                            })
                                        }
                                    >
                                        <PlusIcon className="size-4" /> Add off-plan
                                    </Button>
                                </div>
                                <div className="space-y-4">
                                    {form.data.content.activity_progress.map((row, index) => (
                                        <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Activity</span>
                                                {row.activity_output === '__off_plan__' ? (
                                                    <Input
                                                        value={row.off_plan_activity ?? ''}
                                                        placeholder="Off-plan activity"
                                                        onChange={(event) =>
                                                            updateProgress(index, { off_plan_activity: event.target.value })
                                                        }
                                                    />
                                                ) : (
                                                    <Input value={row.activity_output} disabled />
                                                )}
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Progress</span>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                    value={row.progress}
                                                    onChange={(event) => updateProgress(index, { progress: event.target.value })}
                                                >
                                                    <option value="">-- Select --</option>
                                                    {PROGRESS.map((option) => (
                                                        <option key={option} value={option}>
                                                            {option}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Next step</span>
                                                <Input
                                                    value={row.next_step}
                                                    onChange={(event) => updateProgress(index, { next_step: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Remarks / Challenges</span>
                                                <Textarea
                                                    rows={3}
                                                    value={row.remarks_challenges}
                                                    onChange={(event) =>
                                                        updateProgress(index, { remarks_challenges: event.target.value })
                                                    }
                                                />
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">3. Key Meetings</h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('content', {
                                                ...form.data.content,
                                                key_meetings: [...form.data.content.key_meetings, emptyMeeting(currentUserId)],
                                            })
                                        }
                                    >
                                        <PlusIcon className="size-4" /> Add Meeting
                                    </Button>
                                </div>
                                <div className="space-y-4">
                                    {form.data.content.key_meetings.map((row, index) => (
                                        <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Date</span>
                                                <Input
                                                    type="date"
                                                    value={row.date}
                                                    disabled={Boolean(row.is_readonly_for_current_user)}
                                                    onChange={(event) => updateMeeting(index, { date: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm">
                                                <span className="font-medium">Status</span>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                    value={row.status ?? ''}
                                                    disabled={Boolean(row.is_readonly_for_current_user)}
                                                    onChange={(event) => updateMeeting(index, { status: event.target.value })}
                                                >
                                                    <option value="">-- Select --</option>
                                                    <option value="Done">Done</option>
                                                    <option value="Not done">Not done</option>
                                                </select>
                                            </label>
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Meeting</span>
                                                <Input
                                                    value={row.meeting}
                                                    disabled={Boolean(row.is_readonly_for_current_user)}
                                                    onChange={(event) => updateMeeting(index, { meeting: event.target.value })}
                                                />
                                            </label>
                                            <label className="space-y-1 text-sm md:col-span-2">
                                                <span className="font-medium">Reactions / Notes</span>
                                                <Textarea
                                                    rows={3}
                                                    value={row.reactions_notes}
                                                    disabled={Boolean(row.is_readonly_for_current_user)}
                                                    onChange={(event) => updateMeeting(index, { reactions_notes: event.target.value })}
                                                />
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <h3 className="font-medium">4. Key Achievements</h3>
                                {form.data.content.key_achievements.map((item, index) => (
                                    <Input
                                        key={index}
                                        value={item}
                                        placeholder={`Achievement ${index + 1}`}
                                        onChange={(event) => {
                                            const rows = [...form.data.content.key_achievements];
                                            rows[index] = event.target.value;
                                            form.setData('content', { ...form.data.content, key_achievements: rows });
                                        }}
                                    />
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('content', {
                                            ...form.data.content,
                                            key_achievements: [...form.data.content.key_achievements, ''],
                                        })
                                    }
                                >
                                    <PlusIcon className="size-4" /> Add achievement
                                </Button>
                            </section>

                            <section className="space-y-3 rounded-lg border p-4">
                                <h3 className="font-medium">5. Challenges & Mitigation</h3>
                                {form.data.content.challenges_mitigation.map((row, index) => (
                                    <div key={index} className="grid gap-3 md:grid-cols-2">
                                        <Input
                                            value={row.challenge}
                                            placeholder="Challenge"
                                            onChange={(event) => {
                                                const rows = [...form.data.content.challenges_mitigation];
                                                rows[index] = { ...rows[index], challenge: event.target.value };
                                                form.setData('content', { ...form.data.content, challenges_mitigation: rows });
                                            }}
                                        />
                                        <Input
                                            value={row.action_taken_planned}
                                            placeholder="Action taken / planned"
                                            onChange={(event) => {
                                                const rows = [...form.data.content.challenges_mitigation];
                                                rows[index] = { ...rows[index], action_taken_planned: event.target.value };
                                                form.setData('content', { ...form.data.content, challenges_mitigation: rows });
                                            }}
                                        />
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('content', {
                                            ...form.data.content,
                                            challenges_mitigation: [
                                                ...form.data.content.challenges_mitigation,
                                                { challenge: '', action_taken_planned: '' },
                                            ],
                                        })
                                    }
                                >
                                    <PlusIcon className="size-4" /> Add challenge
                                </Button>
                            </section>
                        </>
                    )}

                    {!isPlan && !isReport && (
                        <p className="text-muted-foreground text-sm">
                            Open this form from Weekly Plans or Weekly Reports so the document type and period are set.
                        </p>
                    )}
                </CardContent>
                <CardFooter className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-t">
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Cancel
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            data-action="draft"
                            onClick={() => submit('draft')}
                        >
                            Save Draft
                        </Button>
                        {isReport ? (
                            <Button
                                type="button"
                                disabled={form.processing}
                                data-action="continue"
                                onClick={() => submit('continue')}
                            >
                                Continue
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={form.processing || !isPlan}
                                data-action="submit"
                                onClick={() => submit('submit')}
                            >
                                Submit
                            </Button>
                        )}
                    </div>
                </CardFooter>
            </Card>
        </div>
    );
}

DocumentsForm.layout = (page: ReactNode) => <AppLayout title="Document form">{page}</AppLayout>;
