import { router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Log = {
    id: string;
    title: string;
    state: string;
    userName?: string | null;
    taskCount: number;
    updatedAt?: string | null;
    period?: { weekNumber: number; year: number; dateRange: string } | null;
    showUrl: string;
    editUrl: string;
    downloadUrl: string;
};
type Props = {
    documents: { data: Log[] };
    canManageActionLogs: boolean;
    stateFilter?: string;
    indexUrl: string;
    boardUrl: string;
    createUrl: string;
};

export default function ActionLogsIndex({ documents, canManageActionLogs, stateFilter, indexUrl, boardUrl, createUrl }: Props) {
    return (
        <PageShell>
            <PageHeader
                title="Action Logs"
                description="Weekly action tasks and progress."
                actions={
                    <>
                        <Button variant="outline" render={<a href={boardUrl} />}>
                            Board
                        </Button>
                        {canManageActionLogs && <Button render={<a href={createUrl} />}>Open Current Week Log</Button>}
                    </>
                }
            />
            {canManageActionLogs && (
                <div className="flex gap-2">
                    {['', 'draft', 'submitted'].map((state) => (
                        <Button
                            key={state || 'all'}
                            size="sm"
                            variant={(stateFilter ?? '') === state ? 'default' : 'outline'}
                            onClick={() => router.get(indexUrl, state ? { state } : {})}
                        >
                            {state || 'All'}
                        </Button>
                    ))}
                </div>
            )}
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Weekly Action Log Register</CardTitle>
                </CardHeader>
                <CardContent className="pt-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Action Log</TableHead>
                                <TableHead>Work Period</TableHead>
                                <TableHead>Tasks</TableHead>
                                <TableHead>State</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-10 text-center">
                                        No action logs found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                documents.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell>
                                            <strong>{log.title}</strong>
                                            <span className="text-muted-foreground block text-xs">Created by {log.userName ?? 'N/A'}</span>
                                        </TableCell>
                                        <TableCell>
                                            {log.period ? `Week ${log.period.weekNumber} of ${log.period.year}` : 'N/A'}
                                        </TableCell>
                                        <TableCell>{log.taskCount}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{log.state}</Badge>
                                        </TableCell>
                                        <TableCell>{log.updatedAt}</TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={[
                                                    { label: 'View', href: log.showUrl, icon: <EyeIcon /> },
                                                    {
                                                        label: 'Edit',
                                                        href: log.editUrl,
                                                        icon: <PencilIcon />,
                                                        hidden: !canManageActionLogs,
                                                    },
                                                    { label: 'Download PDF', href: log.downloadUrl, icon: <DownloadIcon /> },
                                                ]}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </PageShell>
    );
}

ActionLogsIndex.layout = (page: ReactNode) => <AppLayout title="Action Logs">{page}</AppLayout>;
