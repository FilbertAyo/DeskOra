import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell, PaginationLinks } from '@/lib/page-kit';

type Feedback = { id: string; category: string; body: string; status: string; updateUrl: string };
type Paginated<T> = { data: T[]; links: Array<{ url: string | null; label: string; active: boolean }> };

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function AnonymousFeedbackIndex({
    items,
    statuses,
    statusCounts,
    feedbackUrl,
    qrSvg,
    activeStatus,
    indexUrl,
    qrDownloadUrl,
}: {
    items: Paginated<Feedback>;
    statuses: string[];
    statusCounts: Record<string, number>;
    feedbackUrl: string;
    qrSvg: string;
    activeStatus: string | null;
    indexUrl: string;
    qrDownloadUrl: string;
}) {
    const updateStatus = (url: string, status: string) => {
        router.patch(url, { status }, { preserveScroll: true });
    };

    return (
        <PageShell>
            <PageHeader
                title="Anonymous feedback"
                description="Review and track confidential submissions."
                actions={
                    <Button variant="outline" render={<a href={qrDownloadUrl} />}>
                        Download QR code
                    </Button>
                }
            />

            <div className="grid gap-3 sm:grid-cols-3">
                {statuses.map((status) => (
                    <Link key={status} href={`${indexUrl}?status=${status}`}>
                        <Card className={activeStatus === status ? 'border-primary shadow-none' : 'shadow-none'}>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-sm capitalize">{statusLabel(status)}</p>
                                <p className="text-2xl font-semibold">{statusCounts[status] ?? 0}</p>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
            </div>

            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Public feedback link</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap items-center gap-5 pt-6">
                    <div className="rounded-lg bg-white p-2" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                    <a className="block break-all text-sm" href={feedbackUrl}>
                        {feedbackUrl}
                    </a>
                </CardContent>
            </Card>

            <div className="flex flex-wrap items-center gap-2">
                <Button variant={!activeStatus ? 'default' : 'outline'} render={<Link href={indexUrl} />}>
                    All
                </Button>
                {statuses.map((status) => (
                    <Button
                        key={status}
                        variant={activeStatus === status ? 'default' : 'outline'}
                        render={<Link href={`${indexUrl}?status=${status}`} />}
                    >
                        {statusLabel(status)} ({statusCounts[status] ?? 0})
                    </Button>
                ))}
            </div>

            <div className="grid gap-4">
                {items.data.map((item) => (
                    <Card key={item.id} className="shadow-none">
                        <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 border-b bg-muted/20">
                            <CardTitle className="text-base capitalize">{item.category}</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline">{statusLabel(item.status)}</Badge>
                                <RowActions
                                    items={statuses
                                        .filter((status) => status !== item.status)
                                        .map((status) => ({
                                            label: `Mark ${statusLabel(status)}`,
                                            onClick: () => updateStatus(item.updateUrl, status),
                                        }))}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <p className="whitespace-pre-wrap text-sm">{item.body}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {items.data.length === 0 && (
                <p className="text-muted-foreground py-10 text-center">No feedback matches this filter.</p>
            )}

            <PaginationLinks links={items.links} />
        </PageShell>
    );
}

AnonymousFeedbackIndex.layout = (page: ReactNode) => <AppLayout title="Anonymous Feedback">{page}</AppLayout>;
