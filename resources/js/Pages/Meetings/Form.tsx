import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import StaffPicker, { type StaffUser } from '@/components/shared/StaffPicker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader, PageShell } from '@/lib/page-kit';

type MeetingFormData = {
    id?: string;
    title: string;
    agenda?: string | null;
    starts_at: string;
    ends_at: string;
    location?: string | null;
    join_url?: string | null;
    meeting_type: string;
    period_id?: string | null;
    participant_user_ids: string[];
    invite_all_active?: boolean;
};

type Props = {
    mode: 'create' | 'edit';
    meeting: MeetingFormData | null;
    staffUsers: StaffUser[];
    workPeriods: Array<{ id: string; label: string }>;
    submitUrl: string;
    backUrl: string;
};

function defaultStart(): string {
    const date = new Date();
    date.setMinutes(0, 0, 0);
    date.setHours(date.getHours() + 1);
    return toLocalInput(date);
}

function defaultEnd(): string {
    const date = new Date();
    date.setMinutes(0, 0, 0);
    date.setHours(date.getHours() + 2);
    return toLocalInput(date);
}

function toLocalInput(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function MeetingsForm({ mode, meeting, staffUsers, workPeriods, submitUrl, backUrl }: Props) {
    const form = useForm({
        title: meeting?.title ?? '',
        agenda: meeting?.agenda ?? '',
        timing: 'scheduled' as const,
        starts_at: meeting?.starts_at ?? defaultStart(),
        ends_at: meeting?.ends_at ?? defaultEnd(),
        location: meeting?.location ?? '',
        meeting_type: meeting?.meeting_type ?? 'adhoc',
        period_id: meeting?.period_id ?? '',
        participant_user_ids: meeting?.participant_user_ids ?? [],
        invite_all_active: meeting?.invite_all_active ?? false,
    });

    const isWeekly = form.data.meeting_type === 'weekly';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            onSuccess: () => notify('success', mode === 'edit' ? 'Meeting updated.' : 'Meeting scheduled.'),
        };

        if (mode === 'edit') {
            form.put(submitUrl, options);
            return;
        }

        form.post(submitUrl, options);
    };

    return (
        <PageShell>
            <PageHeader
                title={mode === 'edit' ? 'Edit meeting' : 'Schedule meeting'}
                description="Set the details, invite colleagues, and optionally add a call link for remote staff."
                actions={
                    <Button variant="outline" render={<Link href={backUrl} />}>
                        Back
                    </Button>
                }
            />

            <Card className="max-w-3xl shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Meeting details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                        <label className="space-y-1.5 md:col-span-2">
                            <span className="text-sm font-medium">Title</span>
                            <Input
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                required
                                placeholder="e.g. Monday standup"
                            />
                            {form.errors.title ? <span className="text-destructive block text-sm">{form.errors.title}</span> : null}
                        </label>

                        <label className="space-y-1.5 md:col-span-2">
                            <span className="text-sm font-medium">Agenda / description</span>
                            <Textarea
                                rows={4}
                                value={form.data.agenda}
                                onChange={(event) => form.setData('agenda', event.target.value)}
                                placeholder="What will you cover?"
                            />
                            {form.errors.agenda ? <span className="text-destructive block text-sm">{form.errors.agenda}</span> : null}
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-sm font-medium">Meeting type</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.meeting_type}
                                onChange={(event) => {
                                    const next = event.target.value;
                                    form.setData({
                                        ...form.data,
                                        meeting_type: next,
                                        invite_all_active: next === 'weekly' ? true : form.data.invite_all_active,
                                        period_id: next === 'weekly' ? form.data.period_id : '',
                                    });
                                }}
                            >
                                <option value="adhoc">Colleague meeting</option>
                                <option value="weekly">Weekly Monday meeting</option>
                            </select>
                            {form.errors.meeting_type ? (
                                <span className="text-destructive block text-sm">{form.errors.meeting_type}</span>
                            ) : null}
                        </label>

                        {isWeekly ? (
                            <label className="space-y-1.5">
                                <span className="text-sm font-medium">Work period (for weekly minutes)</span>
                                <select
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={form.data.period_id}
                                    onChange={(event) => form.setData('period_id', event.target.value)}
                                >
                                    <option value="">Optional — link to a week</option>
                                    {workPeriods.map((period) => (
                                        <option key={period.id} value={period.id}>
                                            {period.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.period_id ? (
                                    <span className="text-destructive block text-sm">{form.errors.period_id}</span>
                                ) : null}
                            </label>
                        ) : (
                            <div />
                        )}

                        <label className="space-y-1.5">
                            <span className="text-sm font-medium">Starts</span>
                            <Input
                                type="datetime-local"
                                value={form.data.starts_at}
                                onChange={(event) => form.setData('starts_at', event.target.value)}
                                required
                            />
                            {form.errors.starts_at ? (
                                <span className="text-destructive block text-sm">{form.errors.starts_at}</span>
                            ) : null}
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-sm font-medium">Ends</span>
                            <Input
                                type="datetime-local"
                                value={form.data.ends_at}
                                onChange={(event) => form.setData('ends_at', event.target.value)}
                                required
                            />
                            {form.errors.ends_at ? <span className="text-destructive block text-sm">{form.errors.ends_at}</span> : null}
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-sm font-medium">Location / venue</span>
                            <Input
                                value={form.data.location}
                                onChange={(event) => form.setData('location', event.target.value)}
                                placeholder="Main office, Board room…"
                            />
                            {form.errors.location ? (
                                <span className="text-destructive block text-sm">{form.errors.location}</span>
                            ) : null}
                        </label>

                        <p className="text-muted-foreground md:col-span-2 rounded-md border border-dashed px-3 py-2 text-sm">
                            Staff join through the portal voice call on the meeting page — no external Zoom/Meet link needed.
                        </p>

                        <div className="space-y-3 md:col-span-2">
                            {isWeekly ? (
                                <p className="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-sm">
                                    Weekly Monday meetings invite all active staff automatically.
                                </p>
                            ) : (
                                <>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.invite_all_active}
                                            onChange={(event) => form.setData('invite_all_active', event.target.checked)}
                                        />
                                        Invite all active staff
                                    </label>
                                    {!form.data.invite_all_active ? (
                                        <StaffPicker
                                            users={staffUsers}
                                            value={form.data.participant_user_ids}
                                            onChange={(ids) => form.setData('participant_user_ids', ids)}
                                        />
                                    ) : null}
                                    {form.errors.participant_user_ids ? (
                                        <span className="text-destructive block text-sm">{form.errors.participant_user_ids}</span>
                                    ) : null}
                                </>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2 md:col-span-2">
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                {mode === 'edit' ? 'Save changes' : 'Schedule meeting'}
                            </Button>
                            <Button type="button" variant="outline" render={<Link href={backUrl} />}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}

MeetingsForm.layout = (page: ReactNode) => <AppLayout title="Schedule meeting">{page}</AppLayout>;
