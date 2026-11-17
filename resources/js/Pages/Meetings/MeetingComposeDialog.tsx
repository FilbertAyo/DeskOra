import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';

import StaffPicker, { type StaffUser } from '@/components/shared/StaffPicker';
import { Button } from '@/components/ui/button';
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

export type ComposeMode = 'instant' | 'schedule';

type Props = {
    open: boolean;
    mode: ComposeMode;
    onOpenChange: (open: boolean) => void;
    staffUsers: StaffUser[];
    workPeriods: Array<{ id: string; label: string }>;
    storeUrl: string;
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

export default function MeetingComposeDialog({ open, mode, onOpenChange, staffUsers, workPeriods, storeUrl }: Props) {
    const isInstant = mode === 'instant';
    const form = useForm({
        title: '',
        agenda: '',
        timing: isInstant ? 'instant' : 'scheduled',
        duration_minutes: 60,
        starts_at: defaultStart(),
        ends_at: defaultEnd(),
        location: '',
        meeting_type: 'adhoc',
        period_id: '',
        participant_user_ids: [] as string[],
        invite_all_active: false,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            title: '',
            agenda: '',
            timing: isInstant ? 'instant' : 'scheduled',
            duration_minutes: 60,
            starts_at: defaultStart(),
            ends_at: defaultEnd(),
            location: '',
            meeting_type: 'adhoc',
            period_id: '',
            participant_user_ids: [],
            invite_all_active: false,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when dialog opens/mode changes
    }, [open, mode]);

    const isWeekly = !isInstant && form.data.meeting_type === 'weekly';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', isInstant ? 'Opening voice call…' : 'Meeting scheduled.');
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isInstant ? 'Start voice call' : 'Schedule voice meeting'}</DialogTitle>
                    <DialogDescription>
                        {isInstant
                            ? 'Pick who to call. The portal voice room opens right away and waits for them to join.'
                            : 'Schedule a voice meeting. Staff can join the in-portal call when it starts.'}
                    </DialogDescription>
                </DialogHeader>

                <form className="grid gap-4" onSubmit={submit}>
                    {isInstant ? (
                        <>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.invite_all_active}
                                    onChange={(event) => form.setData('invite_all_active', event.target.checked)}
                                />
                                Call all active staff
                            </label>
                            {!form.data.invite_all_active ? (
                                <StaffPicker
                                    users={staffUsers}
                                    value={form.data.participant_user_ids}
                                    onChange={(ids) => form.setData('participant_user_ids', ids)}
                                    label="Who do you want to call?"
                                />
                            ) : (
                                <p className="text-muted-foreground text-sm">All active staff will be invited to this voice call.</p>
                            )}
                            {form.errors.participant_user_ids ? (
                                <span className="text-destructive text-sm">{form.errors.participant_user_ids}</span>
                            ) : null}
                        </>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
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
                                    rows={3}
                                    value={form.data.agenda}
                                    onChange={(event) => form.setData('agenda', event.target.value)}
                                    placeholder="Optional notes for attendees"
                                />
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
                            </label>

                            {isWeekly ? (
                                <label className="space-y-1.5">
                                    <span className="text-sm font-medium">Work period</span>
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
                                {form.errors.ends_at ? (
                                    <span className="text-destructive block text-sm">{form.errors.ends_at}</span>
                                ) : null}
                            </label>

                            <label className="space-y-1.5 md:col-span-2">
                                <span className="text-sm font-medium">Location / venue (optional)</span>
                                <Input
                                    value={form.data.location}
                                    onChange={(event) => form.setData('location', event.target.value)}
                                    placeholder="Main office, Board room…"
                                />
                            </label>

                            <div className="space-y-3 md:col-span-2">
                                {isWeekly ? (
                                    <p className="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-sm">
                                        Weekly Monday meetings invite all active staff automatically. They can join the portal voice call.
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
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} loading={form.processing}>
                            {isInstant ? 'Start voice call' : 'Schedule meeting'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
