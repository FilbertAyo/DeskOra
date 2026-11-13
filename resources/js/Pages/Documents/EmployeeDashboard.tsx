import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function EmployeeDashboard(props: Record<string, unknown>) {
    const stats = (props.stats ?? {}) as Record<string, unknown>;
    const pendingDocuments = Array.isArray(props.pendingDocuments) ? props.pendingDocuments : [];
    const timelineData = Array.isArray(props.timelineData) ? props.timelineData : [];

    return (
        <div className="flex flex-col gap-6">
            <PageHeader title="My submissions" description="Track your weekly plans and reports." />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {Object.entries(stats).map(([key, value]) => (
                    <Card key={key}>
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-xs tracking-wide uppercase">{key}</div>
                            <div className="mt-1 text-2xl font-semibold">{String(value)}</div>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Pending</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {pendingDocuments.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing pending.</p>
                    ) : (
                        pendingDocuments.map((item, index) => {
                            const row = item as Record<string, unknown>;
                            const href = String(row.show_url ?? row.url ?? '');
                            return (
                                <div key={String(row.id ?? index)} className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm">
                                    <span>{String(row.title ?? row.display_title ?? `Document ${index + 1}`)}</span>
                                    {href && (
                                        <Button size="sm" variant="outline" render={<Link href={href} />}>
                                            Open
                                        </Button>
                                    )}
                                </div>
                            );
                        })
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Timeline</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {timelineData.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No timeline entries.</p>
                    ) : (
                        timelineData.map((item, index) => {
                            const row = item as {
                                period?: { week_number?: number; year?: number; date_range?: string };
                                plan_status?: string;
                                report_status?: string;
                            };
                            return (
                                <div key={index} className="rounded-lg border px-3 py-2 text-sm">
                                    Week {row.period?.week_number}, {row.period?.year} · Plan: {row.plan_status} · Report:{' '}
                                    {row.report_status}
                                </div>
                            );
                        })
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

EmployeeDashboard.layout = (page: ReactNode) => <AppLayout title="My submissions">{page}</AppLayout>;
