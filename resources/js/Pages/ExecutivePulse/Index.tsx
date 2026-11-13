import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Metric = { percent: number; submitted: number; owing: number; period_label: string };
type Snapshot = {
    generated_at: string;
    reports: Metric;
    plans: Metric;
    approvals: { open_finance_requests: number; budget_pending_org: number; viewer_pending_total: number };
    action_tasks: { active: number; overdue: number; needs_update: number };
    highlights: string[];
    deadlines: { plan_text?: string | null; report_text?: string | null };
};

export default function ExecutivePulseIndex({
    snapshot,
    downloadUrl,
    approvalsUrl,
}: {
    snapshot: Snapshot;
    downloadUrl: string;
    approvalsUrl: string;
}) {
    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Executive Pulse</h1>
                    <p className="text-muted-foreground text-sm">Generated {snapshot.generated_at}</p>
                </div>
                <Button variant="outline" render={<a href={downloadUrl} />}>Download PDF</Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard title="Weekly reports" value={`${snapshot.reports.percent}%`}>
                    {snapshot.reports.submitted} submitted · {snapshot.reports.owing} owing<br />{snapshot.reports.period_label}
                </MetricCard>
                <MetricCard title="Weekly plans" value={`${snapshot.plans.percent}%`}>
                    {snapshot.plans.submitted} submitted · {snapshot.plans.owing} owing<br />{snapshot.plans.period_label}
                </MetricCard>
                <MetricCard title="Open finance approvals" value={String(snapshot.approvals.open_finance_requests)}>
                    Budget pending: {snapshot.approvals.budget_pending_org}
                </MetricCard>
                <MetricCard title="Action tasks (current week)" value={String(snapshot.action_tasks.active)}>
                    {snapshot.action_tasks.overdue} overdue · {snapshot.action_tasks.needs_update} need update
                </MetricCard>
            </div>
            <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader><CardTitle>Highlights &amp; blockers</CardTitle></CardHeader>
                    <CardContent><ul className="list-disc space-y-2 pl-5">{snapshot.highlights.map((item) => <li key={item}>{item}</li>)}</ul></CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Deadlines</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <Deadline label="Plan deadline" value={snapshot.deadlines.plan_text ?? 'No active deadline info'} />
                        <Deadline label="Report deadline" value={snapshot.deadlines.report_text ?? 'No active deadline info'} />
                        <Deadline label="Your pending finance approvals" value={`${snapshot.approvals.viewer_pending_total} pending for you`} />
                        {snapshot.approvals.viewer_pending_total > 0 && <Button variant="outline" render={<a href={approvalsUrl} />}>Open Approvals</Button>}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function MetricCard({ title, value, children }: { title: string; value: string; children: ReactNode }) {
    return <Card><CardContent className="pt-6"><p className="text-muted-foreground text-sm">{title}</p><p className="text-3xl font-semibold">{value}</p><p className="text-muted-foreground mt-1 text-sm">{children}</p></CardContent></Card>;
}

function Deadline({ label, value }: { label: string; value: string }) {
    return <div><p className="text-muted-foreground text-sm">{label}</p><p>{value}</p></div>;
}

ExecutivePulseIndex.layout = (page: ReactNode) => <AppLayout title="Executive Pulse">{page}</AppLayout>;
