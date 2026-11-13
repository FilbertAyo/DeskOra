import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type WeeklyRow = {
    period: { weekNumber: number; year: number; dateRange: string } | null; participants: string[];
    plan: { title: string; showUrl: string; sharedNames: string[] } | null;
    report: { title: string; showUrl: string; sharedNames: string[] } | null; updatedAt: string | null;
};
type ActionLog = { id: string; title: string; userName: string; submittedAt: string | null; submittedAgo: string | null; showUrl: string };
type Props = {
    weeklyRows: WeeklyRow[]; actionLogs: ActionLog[];
    stats: { total_submitted: number; weekly_plans: number; weekly_reports: number; action_logs: number };
    users: Array<{ id: string; name: string }>; periodOptions: Array<{ id: string; label: string }>;
    filters: { userId: string | null; workPeriodId: string; defaultWorkPeriod: string }; indexUrl: string;
};

export default function ReviewerDashboard({ weeklyRows, actionLogs, stats, users, periodOptions, filters, indexUrl }: Props) {
    const apply = (key: string, value: string) => router.get(indexUrl, { user_id: filters.userId || undefined, work_period_id: filters.workPeriodId, [key]: value || undefined }, { preserveState: true });
    return <div className="flex flex-col gap-6">
        <div><h1 className="text-2xl font-semibold">Reviewer dashboard</h1><p className="text-muted-foreground text-sm">Submitted plans, reports and action logs.</p></div>
        <div className="grid gap-3 sm:grid-cols-4">{Object.entries(stats).map(([label, value]) => <Card key={label}><CardContent className="pt-6"><p className="text-muted-foreground text-sm capitalize">{label.replaceAll('_', ' ')}</p><p className="text-2xl font-semibold">{value}</p></CardContent></Card>)}</div>
        <div className="flex flex-wrap gap-3">
            <select className="border-input bg-background h-9 rounded-md border px-3 text-sm" value={filters.workPeriodId} onChange={(e) => apply('work_period_id', e.target.value)}><option value={filters.defaultWorkPeriod}>Latest 2 periods</option>{periodOptions.map((period) => <option key={period.id} value={period.id}>{period.label}</option>)}</select>
            <select className="border-input bg-background h-9 rounded-md border px-3 text-sm" value={filters.userId ?? ''} onChange={(e) => apply('user_id', e.target.value)}><option value="">All users</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select>
        </div>
        <Card><CardHeader><CardTitle>Weekly submissions</CardTitle></CardHeader><CardContent><Table><TableHeader><TableRow><TableHead>Period</TableHead><TableHead>Participants</TableHead><TableHead>Plan</TableHead><TableHead>Report</TableHead><TableHead>Updated</TableHead></TableRow></TableHeader><TableBody>
            {weeklyRows.map((row, index) => <TableRow key={`${row.period?.year}-${row.period?.weekNumber}-${index}`}><TableCell>{row.period ? <>Week {row.period.weekNumber}, {row.period.year}<div className="text-muted-foreground text-xs">{row.period.dateRange}</div></> : '—'}</TableCell><TableCell>{row.participants.join(', ') || '—'}</TableCell><TableCell>{row.plan ? <><a href={row.plan.showUrl}>{row.plan.title}</a>{row.plan.sharedNames.length > 0 && <div className="text-muted-foreground text-xs">Shared: {row.plan.sharedNames.join(', ')}</div>}</> : '—'}</TableCell><TableCell>{row.report ? <><a href={row.report.showUrl}>{row.report.title}</a>{row.report.sharedNames.length > 0 && <div className="text-muted-foreground text-xs">Shared: {row.report.sharedNames.join(', ')}</div>}</> : '—'}</TableCell><TableCell>{row.updatedAt ?? '—'}</TableCell></TableRow>)}
        </TableBody></Table>{weeklyRows.length === 0 && <p className="text-muted-foreground py-8 text-center">No weekly submissions found.</p>}</CardContent></Card>
        <Card><CardHeader><CardTitle>Action logs</CardTitle></CardHeader><CardContent><Table><TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Owner</TableHead><TableHead>Submitted</TableHead></TableRow></TableHeader><TableBody>
            {actionLogs.map((log) => <TableRow key={log.id}><TableCell><a href={log.showUrl}>{log.title}</a></TableCell><TableCell>{log.userName}</TableCell><TableCell>{log.submittedAt ?? '—'}<div className="text-muted-foreground text-xs">{log.submittedAgo}</div></TableCell></TableRow>)}
        </TableBody></Table>{actionLogs.length === 0 && <p className="text-muted-foreground py-8 text-center">No action logs found.</p>}</CardContent></Card>
    </div>;
}

ReviewerDashboard.layout = (page: ReactNode) => <AppLayout title="Reviewer Dashboard">{page}</AppLayout>;
