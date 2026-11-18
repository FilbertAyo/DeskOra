import { Link, router, useForm } from '@inertiajs/react';
import {
    ArchiveIcon,
    CheckIcon,
    MapPinIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    VideoIcon,
    XIcon,
} from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import type { StaffUser } from '@/components/shared/StaffPicker';
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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader, PageShell } from '@/lib/page-kit';

type ActionItem = {
    id?: string;
    title: string;
    assignee_id?: string | null;
    assignee_name?: string | null;
    due_date?: string | null;
    status: string;
};

type Meeting = {
    id: string;
    title: string;
    agenda?: string | null;
    when_label: string;
    location?: string | null;
    meeting_type: string;
    status: string;
    minutes_notes?: string | null;
    organizer: { id?: string; name?: string | null; title?: string | null };
    is_organizer: boolean;
    period?: { id: string; weekNumber: number; year: number; dateRange: string } | null;
    period_minute?: {
        showUrl: string;
        downloadUrl: string;
        editUrl: string;
        uploadedBy?: string | null;
    } | null;
    participants: Array<{
        id: string;
        user_id: string;
        name?: string | null;
        title?: string | null;
        response: string;
    }>;
    rsvp: { accepted: number; declined: number; pending: number; total: number };
    action_items: ActionItem[];
};

type Props = {
    meeting: Meeting;
    myResponse?: string | null;
    canEdit: boolean;
    canCancel: boolean;
    canComplete: boolean;
    canManageRecords: boolean;
    canEditMinutes?: boolean;
    canRsvp: boolean;
    canManageMinutes: boolean;
    staffUsers: StaffUser[];
    urls: {
        index: string;
        edit: string;
        cancel: string;
        complete: string;
        rsvp: string;
        records: string;
        call: string;
        minutesStore: string;
    };
};

function responseBadge(response: string) {
    if (response === 'accepted') {
        return <Badge variant="success">Accepted</Badge>;
    }
    if (response === 'declined') {
        return <Badge variant="destructive">Declined</Badge>;
    }
    return <Badge variant="warning">Pending</Badge>;
}

export default function MeetingsShow({
    meeting,
    myResponse,
    canEdit,
    canCancel,
    canComplete,
    canManageRecords,
    canEditMinutes = false,
    canRsvp,
    canManageMinutes,
    staffUsers,
    urls,
}: Props) {
    const [minutesOpen, setMinutesOpen] = useState(false);
    const [actionsOpen, setActionsOpen] = useState(false);

    const minutesForm = useForm({
        minutes_notes: meeting.minutes_notes ?? '',
    });

    const actionsForm = useForm({
        action_items: meeting.action_items.map((item) => ({
            title: item.title,
            assignee_id: item.assignee_id ?? '',
            due_date: item.due_date ?? '',
            status: item.status || 'open',
        })),
    });

    const sendRsvp = (response: 'accepted' | 'declined') => {
        router.post(
            urls.rsvp,
            { response },
            {
                preserveScroll: true,
                onSuccess: () => notify('success', response === 'accepted' ? 'Invitation accepted.' : 'Invitation declined.'),
            },
        );
    };

    const cancelMeeting = () => {
        if (!window.confirm('Cancel this meeting? Invitees will be notified.')) {
            return;
        }
        router.post(urls.cancel, {}, { onSuccess: () => notify('success', 'Meeting cancelled.') });
    };

    const completeMeeting = () => {
        router.post(urls.complete, {}, { onSuccess: () => notify('success', 'Meeting marked completed.') });
    };

    const saveMinutes = (event: FormEvent) => {
        event.preventDefault();
        minutesForm.put(urls.records, {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', 'Minutes saved.');
                setMinutesOpen(false);
            },
        });
    };

    const saveActions = (event: FormEvent) => {
        event.preventDefault();
        actionsForm.put(urls.records, {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', 'Action plan saved.');
                setActionsOpen(false);
            },
        });
    };

    const updateActionItem = (index: number, patch: Partial<(typeof actionsForm.data.action_items)[number]>) => {
        const next = actionsForm.data.action_items.map((item, itemIndex) => (itemIndex === index ? { ...item, ...patch } : item));
        actionsForm.setData('action_items', next);
    };

    const openMinutes = () => {
        minutesForm.setData('minutes_notes', meeting.minutes_notes ?? '');
        minutesForm.clearErrors();
        setMinutesOpen(true);
    };

    const openActions = () => {
        actionsForm.setData(
            'action_items',
            meeting.action_items.map((item) => ({
                title: item.title,
                assignee_id: item.assignee_id ?? '',
                due_date: item.due_date ?? '',
                status: item.status || 'open',
            })),
        );
        actionsForm.clearErrors();
        setActionsOpen(true);
    };

    return (
        <PageShell>
            <PageHeader
                title={meeting.title}
                description={meeting.when_label}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" render={<Link href={urls.index} />}>
                            Back
                        </Button>
                        {meeting.status !== 'cancelled' && meeting.status !== 'completed' ? (
                            <Button render={<Link href={urls.call} />}>
                                <VideoIcon className="size-4" />
                                Join voice call
                            </Button>
                        ) : null}
                        {canEdit ? (
                            <Button variant="outline" render={<Link href={urls.edit} />}>
                                <PencilIcon className="size-4" />
                                Edit
                            </Button>
                        ) : null}
                        {canComplete ? (
                            <Button variant="outline" onClick={completeMeeting}>
                                <CheckIcon className="size-4" />
                                Mark completed
                            </Button>
                        ) : null}
                        {canCancel ? (
                            <Button variant="destructive" onClick={cancelMeeting}>
                                <XIcon className="size-4" />
                                Cancel meeting
                            </Button>
                        ) : null}
                    </div>
                }
            />

            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={statusBadgeVariant(meeting.status)}>{meeting.status}</Badge>
                {meeting.meeting_type === 'weekly' ? <Badge variant="info">Weekly Monday</Badge> : <Badge variant="outline">Colleague</Badge>}
                {meeting.is_organizer ? <Badge variant="secondary">You are the organizer</Badge> : null}
                {!meeting.is_organizer && myResponse ? responseBadge(myResponse) : null}
            </div>

            {canRsvp ? (
                <Card className="border-primary/20 bg-primary/5 shadow-none">
                    <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="font-medium">You’re invited</p>
                            <p className="text-muted-foreground text-sm">Confirm whether you can attend.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={() => sendRsvp('accepted')}>
                                <CheckIcon className="size-4" />
                                Accept
                            </Button>
                            <Button variant="outline" onClick={() => sendRsvp('declined')}>
                                <XIcon className="size-4" />
                                Can’t attend
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
                <div className="space-y-4">
                    {meeting.agenda ? (
                        <Card className="shadow-none">
                            <CardHeader className="border-b bg-muted/20">
                                <CardTitle className="text-base">Agenda</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <p className="whitespace-pre-wrap text-sm leading-relaxed">{meeting.agenda}</p>
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="shadow-none">
                        <CardHeader className="border-b bg-muted/20">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-base">Minutes</CardTitle>
                                {canEditMinutes ? (
                                    <Button size="sm" variant="outline" onClick={openMinutes}>
                                        {meeting.minutes_notes ? 'Edit minutes' : 'Add minutes'}
                                    </Button>
                                ) : null}
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {meeting.minutes_notes ? (
                                <p className="whitespace-pre-wrap text-sm leading-relaxed">{meeting.minutes_notes}</p>
                            ) : (
                                <p className="text-muted-foreground text-sm">No minutes yet. Add them after the call ends.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader className="border-b bg-muted/20">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-base">Action plan</CardTitle>
                                {canManageRecords ? (
                                    <Button size="sm" variant="outline" onClick={openActions}>
                                        {meeting.action_items.length > 0 ? 'Edit action plan' : 'Add action plan'}
                                    </Button>
                                ) : null}
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {meeting.action_items.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No action items yet.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {meeting.action_items.map((item) => (
                                        <li
                                            key={item.id ?? item.title}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
                                        >
                                            <span>{item.title}</span>
                                            <span className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                                {item.assignee_name ? <span>{item.assignee_name}</span> : null}
                                                {item.due_date ? <span>Due {item.due_date}</span> : null}
                                                <Badge variant={statusBadgeVariant(item.status)}>{item.status}</Badge>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card className="shadow-none">
                        <CardHeader className="border-b bg-muted/20">
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 pt-6 text-sm">
                            <DetailRow label="Organizer" value={meeting.organizer.name ?? '—'} />
                            {meeting.location ? (
                                <DetailRow
                                    label="Venue"
                                    value={
                                        <span className="inline-flex items-center gap-1">
                                            <MapPinIcon className="size-3.5" />
                                            {meeting.location}
                                        </span>
                                    }
                                />
                            ) : null}
                            <DetailRow
                                label="Voice call"
                                value={
                                    meeting.status === 'cancelled' ? (
                                        'Cancelled'
                                    ) : (
                                        <Link className="text-primary inline-flex items-center gap-1 underline-offset-2 hover:underline" href={urls.call}>
                                            <VideoIcon className="size-3.5" />
                                            Open portal voice call
                                        </Link>
                                    )
                                }
                            />
                            <DetailRow
                                label="RSVP"
                                value={`${meeting.rsvp.accepted} accepted · ${meeting.rsvp.declined} declined · ${meeting.rsvp.pending} pending`}
                            />
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader className="border-b bg-muted/20">
                            <CardTitle className="text-base">Participants ({meeting.participants.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 pt-4">
                            {meeting.participants.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No invitees.</p>
                            ) : (
                                meeting.participants.map((participant) => (
                                    <div key={participant.id} className="flex items-center justify-between gap-2 rounded-md px-1 py-1.5 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{participant.name}</p>
                                            {participant.title ? <p className="text-muted-foreground truncate text-xs">{participant.title}</p> : null}
                                        </div>
                                        {responseBadge(participant.response)}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {meeting.period ? (
                        <Card className="shadow-none">
                            <CardHeader className="border-b bg-muted/20">
                                <CardTitle className="inline-flex items-center gap-2 text-base">
                                    <ArchiveIcon className="size-4" />
                                    Weekly minutes
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-6 text-sm">
                                <p>
                                    Week {meeting.period.weekNumber} · {meeting.period.year}
                                    <span className="text-muted-foreground block text-xs">{meeting.period.dateRange}</span>
                                </p>
                                {meeting.period_minute ? (
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" render={<Link href={meeting.period_minute.showUrl} />}>
                                            View PDF
                                        </Button>
                                        <Button size="sm" variant="outline" render={<a href={meeting.period_minute.downloadUrl} />}>
                                            Download
                                        </Button>
                                        {canManageMinutes ? (
                                            <Button size="sm" variant="outline" render={<Link href={meeting.period_minute.editUrl} />}>
                                                Replace
                                            </Button>
                                        ) : null}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground">
                                        No PDF uploaded for this week yet.
                                        {canManageMinutes ? ' Upload from Weekly minutes archive after the meeting.' : null}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </div>

            <Dialog open={minutesOpen} onOpenChange={setMinutesOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Meeting minutes</DialogTitle>
                        <DialogDescription>Capture what was discussed and decided.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={saveMinutes}>
                        <Textarea
                            rows={10}
                            value={minutesForm.data.minutes_notes}
                            onChange={(event) => minutesForm.setData('minutes_notes', event.target.value)}
                            placeholder="Write the meeting minutes…"
                        />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setMinutesOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={minutesForm.processing} loading={minutesForm.processing}>
                                Save minutes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={actionsOpen} onOpenChange={setActionsOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Action plan</DialogTitle>
                        <DialogDescription>Assign follow-up tasks from this meeting.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={saveActions}>
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium">Action items</p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    actionsForm.setData('action_items', [
                                        ...actionsForm.data.action_items,
                                        { title: '', assignee_id: '', due_date: '', status: 'open' },
                                    ])
                                }
                            >
                                <PlusIcon className="size-4" />
                                Add item
                            </Button>
                        </div>

                        {actionsForm.data.action_items.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No action items yet. Add one to get started.</p>
                        ) : (
                            <div className="max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                                {actionsForm.data.action_items.map((item, index) => (
                                    <div key={index} className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_150px_130px_100px_auto]">
                                        <Input
                                            placeholder="What needs to be done?"
                                            value={item.title}
                                            onChange={(event) => updateActionItem(index, { title: event.target.value })}
                                            required
                                        />
                                        <select
                                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                            value={item.assignee_id}
                                            onChange={(event) => updateActionItem(index, { assignee_id: event.target.value })}
                                        >
                                            <option value="">Assignee</option>
                                            {staffUsers.map((user) => (
                                                <option key={user.id} value={user.id}>
                                                    {user.name}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            type="date"
                                            value={item.due_date}
                                            onChange={(event) => updateActionItem(index, { due_date: event.target.value })}
                                        />
                                        <select
                                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                            value={item.status}
                                            onChange={(event) => updateActionItem(index, { status: event.target.value })}
                                        >
                                            <option value="open">Open</option>
                                            <option value="done">Done</option>
                                        </select>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            onClick={() =>
                                                actionsForm.setData(
                                                    'action_items',
                                                    actionsForm.data.action_items.filter((_, itemIndex) => itemIndex !== index),
                                                )
                                            }
                                        >
                                            <Trash2Icon className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActionsOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={actionsForm.processing} loading={actionsForm.processing}>
                                Save action plan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-0.5">
            <p className="text-muted-foreground text-xs tracking-wide uppercase">{label}</p>
            <div>{value}</div>
        </div>
    );
}

MeetingsShow.layout = (page: ReactNode) => <AppLayout title="Meeting">{page}</AppLayout>;
