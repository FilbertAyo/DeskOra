import { Link, router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell } from '@/lib/page-kit';


type Doc = {
    id?: string;
    title?: string;
    type?: string;
    typeName?: string;
    state?: string;
    userName?: string | null;
    period?: { weekNumber?: number; year?: number; dateRange?: string } | null;
    mainTarget?: string | null;
    content?: Record<string, unknown>;
    createdAt?: string | null;
    canEdit?: boolean;
    showUrl?: string;
    editUrl?: string;
    downloadUrl?: string | null;
    returnToDraftUrl?: string;
};

export default function DocumentsShow({
    document,
    relatedDocument,
    relatedAction,
    showWeeklyRelatedPanel,
    reviewerPresentation,
    indexUrl = '/documents',
}: {
    document: Doc;
    relatedDocument?: Doc | null;
    relatedAction?: string | null;
    showWeeklyRelatedPanel?: boolean;
    reviewerPresentation?: boolean;
    indexUrl?: string;
    [key: string]: unknown;
}) {
    const title = document.title ?? 'Document';
    const typeLabel = document.typeName ?? document.type ?? '';
    const relatedIsReport = relatedAction === 'report' || String(relatedDocument?.type ?? '').includes('report');
    const relatedTitle = relatedIsReport ? 'Related Weekly Report' : 'Related Weekly Plan';
    const content = document.content ?? {};
    const activities = Array.isArray(content.planned_activities) ? content.planned_activities : [];
    const summary = typeof content.summary === 'string' ? content.summary : null;

    return (
        <PageShell>
            <PageHeader
                title="Document Details"
                description="Home › Documents › Details"
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" render={<Link href={indexUrl} />}>
                            ← Back to Documents
                        </Button>
                        <RowActions
                            items={[
                                {
                                    label: 'Edit',
                                    href: document.editUrl,
                                    icon: <PencilIcon />,
                                    hidden: !(document.canEdit && document.editUrl),
                                },
                                {
                                    label: 'Download PDF',
                                    href: document.downloadUrl ?? undefined,
                                    icon: <DownloadIcon />,
                                    hidden: !document.downloadUrl,
                                },
                                {
                                    label: 'Return to Draft',
                                    hidden: !(reviewerPresentation && document.returnToDraftUrl),
                                    onClick: () => router.post(document.returnToDraftUrl!),
                                },
                            ]}
                        />
                    </div>
                }
            />

            <div className="grid gap-4 lg:grid-cols-2">
                {showWeeklyRelatedPanel && (
                    <Card className="shadow-none">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20">
                            <CardTitle className="text-base">{relatedTitle}</CardTitle>
                            {relatedDocument ? (
                                <Badge variant={relatedDocument.state === 'draft' ? 'outline' : 'default'}>
                                    {relatedDocument.state === 'draft' ? 'Draft' : 'Submitted'}
                                </Badge>
                            ) : (
                                <Badge variant="outline">Not created</Badge>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-3 pt-6 text-sm">
                            {relatedDocument ? (
                                <>
                                    <div className="font-medium">{relatedDocument.title}</div>
                                    <p className="text-muted-foreground">
                                        {relatedDocument.userName ? `Created by ${relatedDocument.userName}` : ''}
                                        {relatedDocument.createdAt ? ` on ${relatedDocument.createdAt}` : ''}
                                    </p>
                                    <RowActions
                                        items={[
                                            {
                                                label: 'View',
                                                href: relatedDocument.showUrl,
                                                icon: <EyeIcon />,
                                                hidden: !relatedDocument.showUrl,
                                            },
                                            {
                                                label: 'Edit',
                                                href: relatedDocument.editUrl,
                                                icon: <PencilIcon />,
                                                hidden: !(relatedDocument.canEdit && relatedDocument.editUrl),
                                            },
                                        ]}
                                    />
                                </>
                            ) : (
                                <p className="text-muted-foreground">Not created yet for this period.</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card className="shadow-none">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20">
                        <CardTitle className="text-base">Document Information</CardTitle>
                        <Badge variant={document.state === 'draft' ? 'outline' : 'default'}>
                            {document.state === 'draft' ? 'Draft' : 'Submitted'}
                        </Badge>
                    </CardHeader>
                    <CardContent className="space-y-3 pt-6 text-sm">
                        <p>
                            Created by: {document.userName ?? '—'}
                            {document.period
                                ? ` | Week ${document.period.weekNumber} of ${document.period.year}`
                                : ''}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card className="shadow-none">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20">
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <p className="text-muted-foreground mt-1 text-sm">{typeLabel}</p>
                    </div>
                    {document.downloadUrl && (
                        <Button variant="outline" render={<a href={document.downloadUrl} />}>
                            <DownloadIcon className="size-4" /> Download PDF
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    {document.mainTarget && (
                        <div className="rounded-lg border border-sky-200 bg-sky-50 p-4">
                            <h3 className="mb-2 font-medium text-sky-950">Objective / Main Target</h3>
                            <p className="text-sm whitespace-pre-wrap text-sky-950">{document.mainTarget}</p>
                        </div>
                    )}
                    {summary && (
                        <div className="rounded-lg border p-4">
                            <h3 className="mb-2 font-medium">Summary</h3>
                            <p className="text-sm whitespace-pre-wrap">{summary}</p>
                        </div>
                    )}
                    {activities.length > 0 && (
                        <div className="rounded-lg border p-4">
                            <h3 className="mb-3 font-medium">Planned Activities</h3>
                            <div className="space-y-2">
                                {activities.map((row, index) => {
                                    const item = row as { day?: string; activity?: string; location?: string };
                                    return (
                                        <div key={index} className="rounded-md border p-3 text-sm">
                                            <div className="font-medium">
                                                {item.day ? `${item.day} · ` : ''}
                                                {item.activity || '—'}
                                            </div>
                                            {item.location && <div className="text-muted-foreground text-xs">{item.location}</div>}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                    {!document.mainTarget && !summary && activities.length === 0 && (
                        <p className="text-muted-foreground text-sm">No additional content to display.</p>
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}

DocumentsShow.layout = (page: ReactNode) => <AppLayout title="Document Details">{page}</AppLayout>;
