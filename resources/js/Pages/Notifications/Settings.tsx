import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

export default function NotificationSettings({
    reminderConfig,
    graceConfig,
    recentReminderLogs,
    lastReminderSentAt,
    lastGraceSentAt,
    urls,
}: {
    reminderConfig: {
        enabled: boolean;
        day_of_week: number;
        time: string;
        channels: string[];
        name?: string;
    };
    graceConfig: { enabled: boolean; name?: string };
    recentReminderLogs: Array<Record<string, unknown>>;
    lastReminderSentAt?: string | null;
    lastGraceSentAt?: string | null;
    urls: { updateReminder: string; updateGrace: string; inbox: string };
}) {
    const reminderForm = useForm({
        enabled: reminderConfig.enabled,
        day_of_week: reminderConfig.day_of_week,
        time: String(reminderConfig.time ?? '').slice(0, 5),
        channels: reminderConfig.channels?.length ? reminderConfig.channels : ['email'],
    });

    const graceForm = useForm({
        enabled: graceConfig.enabled,
    });

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Notification settings"
                description="Configure weekly reminders and grace-period alerts."
                actions={
                    <Button variant="outline" render={<Link href={urls.inbox} />}>
                        Open inbox
                    </Button>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Weekly plan/report reminder</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid max-w-xl gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            reminderForm.put(urls.updateReminder, {
                                onSuccess: () => notify('success', 'Reminder settings updated.'),
                            });
                        }}
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={reminderForm.data.enabled}
                                onChange={(e) => reminderForm.setData('enabled', e.target.checked)}
                            />
                            Enabled
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Day of week (0=Sun)</span>
                            <input
                                type="number"
                                min={0}
                                max={6}
                                className="border-input bg-background h-9 w-full rounded-md border px-3"
                                value={reminderForm.data.day_of_week}
                                onChange={(e) => reminderForm.setData('day_of_week', Number(e.target.value))}
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Time</span>
                            <input
                                type="time"
                                className="border-input bg-background h-9 w-full rounded-md border px-3"
                                value={reminderForm.data.time}
                                onChange={(e) => reminderForm.setData('time', e.target.value)}
                            />
                        </label>
                        <div className="flex gap-4 text-sm">
                            {['email', 'in_app'].map((channel) => (
                                <label key={channel} className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={reminderForm.data.channels.includes(channel)}
                                        onChange={(e) => {
                                            reminderForm.setData(
                                                'channels',
                                                e.target.checked
                                                    ? [...reminderForm.data.channels, channel]
                                                    : reminderForm.data.channels.filter((item) => item !== channel),
                                            );
                                        }}
                                    />
                                    {channel}
                                </label>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-xs">Last sent: {lastReminderSentAt || 'Never'}</p>
                        <Button type="submit" disabled={reminderForm.processing} loading={reminderForm.processing}>
                            Save reminder
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Grace period alerts</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            graceForm.put(urls.updateGrace, {
                                onSuccess: () => notify('success', 'Grace period updated.'),
                            });
                        }}
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={graceForm.data.enabled}
                                onChange={(e) => graceForm.setData('enabled', e.target.checked)}
                            />
                            Enabled
                        </label>
                        <p className="text-muted-foreground text-xs">Last sent: {lastGraceSentAt || 'Never'}</p>
                        <Button type="submit" disabled={graceForm.processing} loading={graceForm.processing}>
                            Save grace period
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recent reminder logs</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {recentReminderLogs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No recent logs.</p>
                    ) : (
                        recentReminderLogs.map((log, index) => (
                            <div key={String(log.id ?? index)} className="rounded-lg border px-3 py-2 text-sm">
                                {String(log.dispatch_type_label ?? 'Reminder')} · {String((log.user as { name?: string } | undefined)?.name ?? '—')} ·{' '}
                                {String(log.status ?? '—')} · {String(log.created_at ?? '')}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

NotificationSettings.layout = (page: ReactNode) => <AppLayout title="Notification settings">{page}</AppLayout>;
