import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function ActivityLogShow({
    activity,
    detail,
    indexUrl,
}: {
    activity: {
        id: number | string;
        description?: string | null;
        log_name?: string | null;
        event?: string | null;
        created_at?: string | null;
    };
    detail: Record<string, unknown>;
    indexUrl: string;
}) {
    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
            <PageHeader
                title={`Activity #${activity.id}`}
                description={activity.description ?? undefined}
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <div>Log: {activity.log_name || '—'}</div>
                    <div>Event: {activity.event || '—'}</div>
                    <div>When: {activity.created_at || '—'}</div>
                    <pre className="bg-muted overflow-auto rounded-lg p-3 text-xs">{JSON.stringify(detail, null, 2)}</pre>
                </CardContent>
            </Card>
        </div>
    );
}

ActivityLogShow.layout = (page: ReactNode) => <AppLayout title="Activity detail">{page}</AppLayout>;
