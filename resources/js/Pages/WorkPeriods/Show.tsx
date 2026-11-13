import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function WorkPeriodsShow({
    period,
    stats,
}: {
    period: {
        id: number | string;
        year: number;
        weekNumber: number;
        dateRange: string;
        planDeadline: string;
        reportDeadline: string;
        status: string;
        editUrl: string;
        documents: Array<{
            id: number | string;
            typeName: string;
            title: string;
            userName: string;
            state: string;
            showUrl: string;
        }>;
    };
    stats: Record<string, number>;
}) {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title={`Week ${period.weekNumber}, ${period.year}`}
                description={period.dateRange}
                actions={
                    <>
                        <Badge className="capitalize">{period.status}</Badge>
                        <Button variant="outline" render={<Link href={period.editUrl} />}>
                            Edit
                        </Button>
                        <Button variant="outline" render={<Link href="/work-periods" />}>
                            Back
                        </Button>
                    </>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {Object.entries(stats).map(([key, value]) => (
                    <Card key={key}>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-sm capitalize">{key.replace(/_/g, ' ')}</p>
                            <p className="text-2xl font-semibold">{value}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Deadlines</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2 text-sm">
                    <div>
                        <div className="text-muted-foreground">Plan</div>
                        <div className="font-medium">{period.planDeadline}</div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Report</div>
                        <div className="font-medium">{period.reportDeadline}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Documents</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {period.documents.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No documents in this period.</p>
                    ) : (
                        period.documents.map((document) => (
                            <div key={document.id} className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm">
                                <div>
                                    <div className="font-medium">{document.title}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {document.typeName} · {document.userName} · {document.state}
                                    </div>
                                </div>
                                <Button size="sm" variant="outline" render={<Link href={document.showUrl} />}>
                                    Open
                                </Button>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

WorkPeriodsShow.layout = (page: ReactNode) => <AppLayout title="Work Period">{page}</AppLayout>;
