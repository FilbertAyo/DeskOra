import { Form, Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Named = { id: string; name?: string | null; asset_code?: string | null };
type DisposalRecord = {
    id: string;
    request_number: string;
    reason: string;
    method: string;
    status: string;
    review_notes?: string | null;
    reviewed_at?: string | null;
    disposed_at?: string | null;
    created_at?: string | null;
    asset?: Named | null;
    requested_by?: Named | null;
    reviewed_by?: Named | null;
};

type PageProps = {
    disposal: DisposalRecord;
    statusOptions?: Record<string, string>;
    methodOptions?: Record<string, string>;
    canReview?: boolean;
};

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function DetailItem({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="min-w-0 space-y-1">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</dt>
            <dd className="text-sm break-words">{value || '—'}</dd>
        </div>
    );
}

function DisposalShowPage() {
    const {
        disposal,
        statusOptions = {},
        methodOptions = {},
        canReview = false,
    } = usePage<PageProps>().props;
    const [reviewNotes, setReviewNotes] = useState('');
    const isPending = disposal.status === 'requested';
    const statusLabel = statusOptions[disposal.status] ?? disposal.status;
    const methodLabel = methodOptions[disposal.method] ?? disposal.method;
    const assetLabel = disposal.asset
        ? `${disposal.asset.asset_code ?? ''} ${disposal.asset.name ?? ''}`.trim()
        : '—';

    return (
        <>
            <PageHeader
                title={disposal.request_number}
                description="Disposal request details and review actions."
                actions={
                    <>
                        <Button variant="outline" render={<Link href={route('inventory.disposal.index')} />}>
                            Back to list
                        </Button>
                        {disposal.asset?.id && (
                            <Button
                                variant="outline"
                                render={<Link href={route('inventory.assets.show', { asset: disposal.asset.id })} />}
                            >
                                View asset
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mb-6 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <CardTitle className="text-base">Request details</CardTitle>
                        <Badge variant={statusBadgeVariant(disposal.status)}>{statusLabel}</Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <DetailItem label="Request number" value={disposal.request_number} />
                            <DetailItem label="Asset" value={assetLabel} />
                            <DetailItem label="Method" value={methodLabel} />
                            <DetailItem label="Requested by" value={disposal.requested_by?.name} />
                            <DetailItem label="Requested on" value={formatDate(disposal.created_at)} />
                            <DetailItem label="Reviewed by" value={disposal.reviewed_by?.name} />
                            <DetailItem label="Reviewed on" value={formatDate(disposal.reviewed_at)} />
                            <DetailItem label="Disposed on" value={formatDate(disposal.disposed_at)} />
                            <div className="sm:col-span-2">
                                <DetailItem label="Reason" value={disposal.reason} />
                            </div>
                            <div className="sm:col-span-2">
                                <DetailItem label="Review notes" value={disposal.review_notes} />
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Review</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!isPending && (
                            <p className="text-muted-foreground text-sm">
                                This request has already been {statusLabel.toLowerCase()}.
                            </p>
                        )}

                        {isPending && !canReview && (
                            <p className="text-muted-foreground text-sm">
                                Waiting for an authorized reviewer. You cannot approve your own request.
                            </p>
                        )}

                        {isPending && canReview && (
                            <>
                                <div className="space-y-1.5">
                                    <Label htmlFor="disposal-review-notes">Review notes</Label>
                                    <Textarea
                                        id="disposal-review-notes"
                                        value={reviewNotes}
                                        onChange={(event) => setReviewNotes(event.target.value)}
                                        placeholder="Optional for approval. Required when rejecting."
                                        rows={4}
                                    />
                                </div>

                                <Form
                                    action={route('inventory.disposal.approve', { disposal: disposal.id })}
                                    method="post"
                                    onSuccess={() => notify('success', 'Disposal approved. Asset marked disposed.')}
                                    onError={() => notify('error', 'Could not approve this disposal request.')}
                                    className="space-y-3"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="review_notes" value={reviewNotes} />
                                            <Button type="submit" className="w-full" disabled={processing} loading={processing}>
                                                Approve & dispose
                                            </Button>
                                        </>
                                    )}
                                </Form>

                                <Form
                                    action={route('inventory.disposal.reject', { disposal: disposal.id })}
                                    method="post"
                                    onSuccess={() => notify('success', 'Disposal request rejected.')}
                                    onError={() =>
                                        notify('error', 'Add review notes before rejecting this request.')
                                    }
                                    className="space-y-3 border-t pt-4"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="review_notes" value={reviewNotes} />
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                className="w-full"
                                                disabled={processing || reviewNotes.trim() === ''}
                                                loading={processing}
                                            >
                                                Reject request
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DisposalShowPage.layout = withAppLayout('Disposal');

export default DisposalShowPage;
