import { Link, router } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import DownloadButton from '@/components/shared/DownloadButton';
import RowActions from '@/components/shared/RowActions';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { notify } from '@/lib/notify';
import { PageHeader, PageShell, PaginationLinks } from '@/lib/page-kit';
import { cn } from '@/lib/utils';

type Feedback = {
    id: string;
    category: string;
    body: string;
    status: string;
    createdAt?: string | null;
    updateUrl: string;
};
type Paginated<T> = { data: T[]; links: Array<{ url: string | null; label: string; active: boolean }> };

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function AnonymousFeedbackIndex({
    items,
    statuses,
    statusCounts,
    feedbackUrl,
    activeStatus,
    indexUrl,
    qrDownloadUrl,
}: {
    items: Paginated<Feedback>;
    statuses: string[];
    statusCounts: Record<string, number>;
    feedbackUrl: string;
    activeStatus: string | null;
    indexUrl: string;
    qrDownloadUrl: string;
}) {
    const [copied, setCopied] = useState(false);

    const updateStatus = (url: string, status: string) => {
        router.patch(url, { status }, { preserveScroll: true });
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(feedbackUrl);
            setCopied(true);
            notify('success', 'Feedback link copied.');
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            notify('error', 'Could not copy link.');
        }
    };

    return (
        <PageShell>
            <PageHeader
                title="Anonymous feedback"
                description="Review confidential submissions and keep status moving."
                actions={
                    <>
                        <Button type="button" variant="outline" onClick={copyLink}>
                            <LinkIcon />
                            {copied ? 'Copied' : 'Copy link'}
                        </Button>
                        <DownloadButton href={qrDownloadUrl} size="default">
                            Download QR
                        </DownloadButton>
                    </>
                }
            />

            <Card className="shadow-none">
                <CardContent className="flex flex-col gap-3 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-sm font-medium">Public feedback link</p>
                        <p className="text-muted-foreground mt-0.5 truncate text-xs">{feedbackUrl}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {statuses.map((status) => {
                            const href = `${indexUrl}?status=${status}`;
                            const active = activeStatus === status;
                            return (
                                <Link
                                    key={status}
                                    href={href}
                                    className={cn(
                                        'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-muted/60',
                                    )}
                                >
                                    {statusLabel(status)}{' '}
                                    <span className={cn('tabular-nums', active ? 'opacity-90' : 'text-muted-foreground')}>
                                        {statusCounts[status] ?? 0}
                                    </span>
                                </Link>
                            );
                        })}
                        <Link
                            href={indexUrl + '?status='}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                activeStatus === null || activeStatus === ''
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-muted/60',
                            )}
                        >
                            All
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card className="shadow-none">
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">#</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Submitted</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.map((item, index) => (
                                <TableRow key={item.id}>
                                    <TableCell className="text-muted-foreground tabular-nums">{index + 1}</TableCell>
                                    <TableCell className="capitalize">{item.category}</TableCell>
                                    <TableCell className="max-w-xl whitespace-normal">
                                        <p className="line-clamp-3 text-sm">{item.body}</p>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusBadgeVariant(item.status)}>{statusLabel(item.status)}</Badge>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-sm">
                                        {formatDate(item.createdAt)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <RowActions
                                            items={statuses
                                                .filter((status) => status !== item.status)
                                                .map((status) => ({
                                                    label: `Mark ${statusLabel(status)}`,
                                                    onClick: () => updateStatus(item.updateUrl, status),
                                                }))}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {items.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                        No feedback matches this filter.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <PaginationLinks links={items.links} />
        </PageShell>
    );
}

AnonymousFeedbackIndex.layout = (page: ReactNode) => <AppLayout title="Anonymous Feedback">{page}</AppLayout>;
