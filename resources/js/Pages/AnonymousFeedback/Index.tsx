import { Link, router } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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

    const onFormSubmit = (event: FormEvent<HTMLFormElement>, item: Feedback) => {
        event.preventDefault();
        const status = String(new FormData(event.currentTarget).get('status') ?? item.status);
        updateStatus(item.updateUrl, status);
    };

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h1 className="text-2xl font-semibold">Anonymous feedback</h1>
                <p className="text-muted-foreground text-sm">Review and track confidential submissions.</p>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                {statuses.map((status) => (
                    <Link key={status} href={`${indexUrl}?status=${status}`}>
                        <Card className={activeStatus === status ? 'border-primary' : ''}>
                            <CardContent className="pt-6">
                                <p className="capitalize">{statusLabel(status)}</p>
                                <p className="text-2xl font-semibold">
                                    {statusLabel(status)} ({statusCounts[status] ?? 0})
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Public feedback link</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap items-center gap-5">
                    <div className="rounded-lg bg-white p-2" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                    <div className="space-y-3">
                        <a className="block break-all" href={feedbackUrl}>
                            {feedbackUrl}
                        </a>
                        <Button variant="outline" render={<a href={qrDownloadUrl} />}>
                            Download QR code
                        </Button>
                    </div>
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
                    <Card key={item.id}>
                        <CardHeader>
                            <CardTitle className="flex justify-between gap-3">
                                <span className="capitalize">{item.category}</span>
                                <Badge variant="outline">{statusLabel(item.status)}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="whitespace-pre-wrap">{item.body}</p>
                            <div className="flex flex-wrap gap-2">
                                {item.status !== 'in_progress' && (
                                    <Button size="sm" variant="outline" onClick={() => updateStatus(item.updateUrl, 'in_progress')}>
                                        Mark in progress
                                    </Button>
                                )}
                                {item.status !== 'resolved' && (
                                    <Button size="sm" variant="outline" onClick={() => updateStatus(item.updateUrl, 'resolved')}>
                                        Mark resolved
                                    </Button>
                                )}
                            </div>
                            <form
                                action={item.updateUrl}
                                method="post"
                                className="anonymous-feedback-status-form mt-2 flex flex-wrap items-center gap-2"
                                onSubmit={(event) => onFormSubmit(event, item)}
                            >
                                <label className="text-sm">Status</label>
                                <select
                                    name="status"
                                    defaultValue={item.status}
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                >
                                    {statuses.map((status) => (
                                        <option key={status} value={status}>
                                            {statusLabel(status)}
                                        </option>
                                    ))}
                                </select>
                                <Button type="submit">Update</Button>
                            </form>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {items.data.length === 0 && (
                <p className="text-muted-foreground py-10 text-center">No feedback matches this filter.</p>
            )}

            <div className="flex flex-wrap gap-2">
                {items.links.map((link, index) => (
                    <Button
                        key={index}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                        onClick={() => link.url && router.get(link.url)}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

AnonymousFeedbackIndex.layout = (page: ReactNode) => <AppLayout title="Anonymous Feedback">{page}</AppLayout>;
