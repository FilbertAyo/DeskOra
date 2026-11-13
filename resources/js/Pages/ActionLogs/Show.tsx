import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

type Task = {
    task?: string;
    progress?: string;
    is_assignee_self?: boolean;
    assignee_label?: string | null;
    responsible_user_ids?: Array<string | number>;
};

export default function ActionLogsShow({
    document,
    tasks = [],
    canManage,
    backUrl,
}: {
    document: Record<string, unknown>;
    tasks?: Task[];
    responsibleNames?: Record<string, string>;
    canManage?: boolean;
    canDelete?: boolean;
    backUrl: string;
    viewerId?: string;
}) {
    const title = String(document.display_title ?? document.title ?? 'Action Log');

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title={title}
                actions={
                    <>
                        <Button variant="outline" render={<Link href={backUrl} />}>
                            Back
                        </Button>
                        {canManage && document.editUrl && (
                            <Button variant="outline" render={<Link href={String(document.editUrl)} />}>
                                Edit
                            </Button>
                        )}
                    </>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Tasks</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {tasks.map((task, index) => (
                        <div
                            key={index}
                            className="rounded-lg border p-3 text-sm"
                            data-assignee-self={task.is_assignee_self ? '1' : '0'}
                        >
                            {task.is_assignee_self && <div className="text-muted-foreground mb-1 text-xs">Your task:</div>}
                            <div className="font-medium">{task.task}</div>
                            {task.progress && <Badge className="mt-2">{task.progress}</Badge>}
                        </div>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}

ActionLogsShow.layout = (page: ReactNode) => <AppLayout title="Action Log">{page}</AppLayout>;
