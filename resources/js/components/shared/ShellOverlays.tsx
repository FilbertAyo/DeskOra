import { usePage } from '@inertiajs/react';

import AppLink from '@/components/shared/AppLink';
import { Loader2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
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
import { route } from '@/lib/route';
import { notify } from '@/lib/notify';
import type { SharedProps } from '@/types/inertia';

type Colleague = {
    id: string | number;
    name: string;
    title?: string | null;
    department?: string | null;
    avatar_url: string;
};

const APPROVAL_GATE_SNOOZE_PREFIX = 'deskora.approval_gate.snoozed.';
const ACTION_LOG_SNOOZE_PREFIX = 'deskora.action_log.snoozed.';

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function xsrfTokenFromCookie(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!match?.[1]) {
        return '';
    }

    try {
        return decodeURIComponent(match[1]);
    } catch {
        return match[1];
    }
}

function approvalGateSnoozeKey(userId: string | number | null | undefined): string | null {
    if (userId === null || userId === undefined || String(userId) === '') {
        return null;
    }

    return `${APPROVAL_GATE_SNOOZE_PREFIX}${String(userId)}`;
}

function isApprovalGateLocallySnoozed(userId: string | number | null | undefined): boolean {
    const key = approvalGateSnoozeKey(userId);
    if (!key) {
        return false;
    }

    try {
        return window.sessionStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function actionLogSnoozeKey(
    userId: string | number | null | undefined,
    week: number | string | null | undefined,
    year: number | string | null | undefined,
): string | null {
    if (userId === null || userId === undefined || String(userId) === '' || week == null || year == null) {
        return null;
    }

    return `${ACTION_LOG_SNOOZE_PREFIX}${String(userId)}.${String(week)}.${String(year)}`;
}

function isActionLogLocallySnoozed(
    userId: string | number | null | undefined,
    week: number | string | null | undefined,
    year: number | string | null | undefined,
): boolean {
    const key = actionLogSnoozeKey(userId, week, year);
    if (!key) {
        return false;
    }

    try {
        return window.sessionStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function markActionLogLocallySnoozed(
    userId: string | number | null | undefined,
    week: number | string | null | undefined,
    year: number | string | null | undefined,
): void {
    const key = actionLogSnoozeKey(userId, week, year);
    if (!key) {
        return;
    }

    try {
        window.sessionStorage.setItem(key, '1');
    } catch {
        // Ignore private-mode / storage quota failures; server snooze still applies.
    }
}

function markApprovalGateLocallySnoozed(userId: string | number | null | undefined): void {
    const key = approvalGateSnoozeKey(userId);
    if (!key) {
        return;
    }

    try {
        window.sessionStorage.setItem(key, '1');
    } catch {
        // Ignore private-mode / storage quota failures; server snooze still applies.
    }
}

async function postJson(url: string, body: Record<string, string> = {}): Promise<{ ok: boolean; body: { ok?: boolean; message?: string } }> {
    const token = csrfToken();
    const xsrf = xsrfTokenFromCookie();
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(token ? { ...body, _token: token } : body),
    });
    const payload = (await response.json().catch(() => ({}))) as { ok?: boolean; message?: string };

    return { ok: response.ok, body: payload };
}

export default function ShellOverlays() {
    const { overlays, auth } = usePage<SharedProps>().props;
    const userId = auth.user?.id;
    const gateLocallySnoozed = isApprovalGateLocallySnoozed(userId);
    const [gateOpen, setGateOpen] = useState(overlays.approvalGate !== null && !gateLocallySnoozed);
    const [nominationOpen, setNominationOpen] = useState(
        overlays.approvalGate === null && !gateLocallySnoozed && overlays.nomination !== null,
    );
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Colleague[]>([]);
    const [selected, setSelected] = useState<Colleague | null>(null);
    const [reason, setReason] = useState('');
    const reminder = overlays.actionLogReminder;
    const reminderLocallySnoozed = isActionLogLocallySnoozed(userId, reminder?.week, reminder?.year);
    const [reminderOpen, setReminderOpen] = useState(
        reminder !== null && !reminderLocallySnoozed && overlays.approvalGate === null && overlays.nomination === null && !gateLocallySnoozed,
    );
    const [pendingAction, setPendingAction] = useState<'snooze' | 'skip' | 'later' | 'nominate' | 'log' | null>(null);

    useEffect(() => {
        const snoozed = isApprovalGateLocallySnoozed(userId);
        const reminderSnoozed = isActionLogLocallySnoozed(userId, overlays.actionLogReminder?.week, overlays.actionLogReminder?.year);
        setGateOpen(overlays.approvalGate !== null && !snoozed);
        setNominationOpen(overlays.approvalGate === null && !snoozed && overlays.nomination !== null);
        setReminderOpen(
            overlays.actionLogReminder !== null &&
                !reminderSnoozed &&
                (overlays.approvalGate === null || snoozed) &&
                overlays.nomination === null,
        );
    }, [overlays.approvalGate, overlays.nomination, overlays.actionLogReminder, userId]);

    useEffect(() => {
        if (!nominationOpen || query.trim() === '') {
            setResults([]);

            return;
        }

        const timer = window.setTimeout(() => {
            const url = `${route('appreciation.staff-search')}?q=${encodeURIComponent(query.trim())}`;

            fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : { data: [] }))
                .then((payload: { data?: Colleague[] }) => setResults(Array.isArray(payload.data) ? payload.data : []))
                .catch(() => setResults([]));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [nominationOpen, query]);

    const snoozeGate = () => {
        // Persist immediately so navigation/refresh cannot reopen this session's prompt.
        markApprovalGateLocallySnoozed(userId);
        setGateOpen(false);
        setPendingAction('snooze');
        // Local snooze already closed the dialog; sync server quietly so CSRF noise never blocks staff.
        void postJson(route('approval-gate.remind-later')).finally(() => {
            setPendingAction(null);
        });
    };

    const closeNomination = (url: string, action: 'skip' | 'later') => {
        setPendingAction(action);
        void postJson(url).finally(() => {
            setPendingAction(null);
            setNominationOpen(false);
        });
    };

    const nominate = () => {
        if (!selected) {
            return;
        }

        setPendingAction('nominate');
        void postJson(route('appreciation.nominate'), { nominee_id: String(selected.id), reason }).then((result) => {
            setPendingAction(null);

            if (result.ok && result.body.ok) {
                setNominationOpen(false);

                return;
            }

            notify('error', result.body.message || 'Could not nominate');
        });
    };

    const snoozeActionLog = () => {
        markActionLogLocallySnoozed(userId, reminder?.week, reminder?.year);
        setReminderOpen(false);
        setPendingAction('log');
        void postJson(route('action-logs.remind-later')).finally(() => {
            setPendingAction(null);
        });
    };

    const gate = overlays.approvalGate;

    return (
        <>
            {auth.user?.isDemo && (
                <div className="mb-4 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
                    <strong>Demo — view only.</strong> You can open every screen and click any button. Submitting or saving changes is disabled.
                </div>
            )}

            <Dialog open={gateOpen} onOpenChange={(open) => open && setGateOpen(true)} disablePointerDismissal>
                <DialogContent className="sm:max-w-2xl" showCloseButton={false}>
                    <DialogHeader>
                        <DialogTitle>Action needed</DialogTitle>
                        <DialogDescription>
                            You have pending requisitions or weekly budgets. Open them now, or choose Remind me later to hide this until your next browser session.
                        </DialogDescription>
                    </DialogHeader>
                    {gate && gate.requisition_count > 0 && (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-sm">
                                    {gate.requisition_count} requisition{gate.requisition_count === 1 ? '' : 's'} waiting
                                </span>
                                {gate.requisition_action_url && (
                                    <Button size="sm" render={<AppLink href={gate.requisition_action_url} />}>
                                        Open requisitions
                                    </Button>
                                )}
                            </div>
                            <div className="flex flex-col gap-2">
                                {gate.requisitions.map((item) => (
                                    <AppLink key={item.url} href={item.url} className="hover:bg-muted flex items-start justify-between gap-3 rounded-lg border px-3 py-2">
                                        <span>
                                            <span className="block font-medium">{item.summary}</span>
                                            <span className="text-muted-foreground text-xs">
                                                {item.ref}
                                                {item.requester_name ? ` · ${item.requester_name}` : ''}
                                                {item.department_name ? ` · ${item.department_name}` : ''}
                                            </span>
                                        </span>
                                        <span className="text-right text-sm">
                                            <span className="block font-medium">TZS {item.amount}</span>
                                            <span className="text-muted-foreground text-xs">{item.stage_label}</span>
                                        </span>
                                    </AppLink>
                                ))}
                            </div>
                        </div>
                    )}
                    {gate && gate.budget_remaining > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
                            <div>
                                <div className="font-medium">Weekly budgets</div>
                                <p className="text-muted-foreground text-sm">
                                    {gate.budget_remaining} item{gate.budget_remaining === 1 ? '' : 's'} waiting in the latest two work periods.
                                </p>
                            </div>
                            {gate.budget_action_url && (
                                <Button render={<AppLink href={gate.budget_action_url} />}>Open weekly budgets</Button>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" disabled={pendingAction !== null} onClick={snoozeGate}>
                            {pendingAction === 'snooze' ? <Loader2Icon className="animate-spin" /> : null}
                            Remind me later
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={nominationOpen} onOpenChange={(open) => open && setNominationOpen(true)} disablePointerDismissal>
                <DialogContent className="sm:max-w-2xl" showCloseButton={false}>
                    <DialogHeader>
                        <DialogTitle>Monthly Staff Appreciation Nomination</DialogTitle>
                        <DialogDescription>For {overlays.nomination?.label}</DialogDescription>
                    </DialogHeader>
                    <p className="text-muted-foreground text-sm">
                        Who made a positive impact on your work this month? Nominate one colleague who demonstrated teamwork, leadership,
                        helpfulness, or exceptional performance. Your nomination is <strong>anonymous</strong> and can help management on staff
                        recognition.
                    </p>
                    <div className="relative">
                        <Input
                            value={query}
                            placeholder="Search by name, title, or email..."
                            autoComplete="off"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                        {results.length > 0 && (
                            <div className="bg-popover absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border shadow-md">
                                {results.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className="hover:bg-muted flex w-full items-center gap-2 px-3 py-2 text-left"
                                        onClick={() => {
                                            setSelected(item);
                                            setQuery('');
                                            setResults([]);
                                        }}
                                    >
                                        <img src={item.avatar_url} alt="" className="size-8 rounded-full object-cover" />
                                        <span>
                                            <span className="block text-sm font-medium">{item.name}</span>
                                            <span className="text-muted-foreground text-xs">
                                                {[item.title, item.department].filter(Boolean).join(' • ')}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    {selected && (
                        <div className="bg-muted flex items-center justify-between gap-3 rounded-lg px-3 py-2">
                            <span className="flex items-center gap-2">
                                <img src={selected.avatar_url} alt="" className="size-10 rounded-full object-cover" />
                                <span>
                                    <span className="block font-medium">{selected.name}</span>
                                    <span className="text-muted-foreground text-xs">
                                        {[selected.title, selected.department].filter(Boolean).join(' • ')}
                                    </span>
                                </span>
                            </span>
                            <Button type="button" variant="link" onClick={() => setSelected(null)}>
                                Change
                            </Button>
                        </div>
                    )}
                    <div>
                        <label htmlFor="nomination-reason" className="mb-1 block text-sm font-medium">
                            Reason <span className="text-muted-foreground font-normal">(optional)</span>
                        </label>
                        <Textarea
                            id="nomination-reason"
                            maxLength={500}
                            rows={3}
                            value={reason}
                            placeholder="Briefly, why are you nominating this colleague?"
                            onChange={(event) => setReason(event.target.value)}
                        />
                        <p className="text-muted-foreground mt-1 text-xs">{reason.length}/500</p>
                    </div>
                    <DialogFooter className="sm:justify-between">
                        <Button type="button" variant="link" disabled={pendingAction !== null} onClick={() => closeNomination(route('appreciation.skip'), 'skip')}>
                            {pendingAction === 'skip' ? <Loader2Icon className="animate-spin" /> : null}
                            Skip This Month
                        </Button>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" disabled={pendingAction !== null} onClick={() => closeNomination(route('appreciation.remind-later'), 'later')}>
                                {pendingAction === 'later' ? <Loader2Icon className="animate-spin" /> : null}
                                Remind Me Later
                            </Button>
                            <Button type="button" disabled={!selected || pendingAction !== null} onClick={nominate}>
                                {pendingAction === 'nominate' ? <Loader2Icon className="animate-spin" /> : null}
                                Nominate a Colleague
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={reminderOpen} onOpenChange={(open) => open && setReminderOpen(true)} disablePointerDismissal>
                <DialogContent className="sm:max-w-lg" showCloseButton={false}>
                    <DialogHeader>
                        <DialogTitle>Update your action log</DialogTitle>
                        <DialogDescription>
                            You are on week {reminder?.week} of {reminder?.year}
                            {reminder?.dateRange ? ` (${reminder.dateRange})` : ''} and {reminder?.pending ?? 0}{' '}
                            {(reminder?.pending ?? 0) === 1 ? 'task still needs' : 'tasks still need'} your update.
                        </DialogDescription>
                    </DialogHeader>
                    {reminder && reminder.tasks.length > 0 && (
                        <div className="flex flex-col gap-2">
                            {reminder.tasks.map((task, index) => (
                                <div key={`${task.task}-${index}`} className="flex items-start justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2">
                                    <span>
                                        <span className="block text-sm font-medium">{task.task}</span>
                                        <span className="text-muted-foreground text-xs">{task.progress}</span>
                                    </span>
                                    <Badge variant="warning">Needs update</Badge>
                                </div>
                            ))}
                            {reminder.pending > reminder.tasks.length && (
                                <p className="text-muted-foreground text-sm">
                                    And {reminder.pending - reminder.tasks.length} more on the board.
                                </p>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" disabled={pendingAction !== null} onClick={snoozeActionLog}>
                            {pendingAction === 'log' ? <Loader2Icon className="animate-spin" /> : null}
                            Remind me later
                        </Button>
                        {reminder?.boardUrl && (
                            <Button render={<AppLink href={reminder.boardUrl} />}>Update on the board</Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
