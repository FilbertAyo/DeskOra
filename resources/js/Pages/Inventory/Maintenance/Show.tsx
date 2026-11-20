import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Named = { id: string; name?: string | null; asset_code?: string | null };
type MaintenanceRecord = {
    id: string;
    record_number: string;
    status: string;
    problem_description: string;
    reported_at?: string | null;
    completed_at?: string | null;
    repair_provider?: string | null;
    repaired_on?: string | null;
    repair_cost?: string | number | null;
    condition_after?: string | null;
    repair_notes?: string | null;
    previous_status?: string | null;
    previous_condition?: string | null;
    asset?: Named | null;
    reported_by?: Named | null;
    completed_by?: Named | null;
};

type PageProps = {
    record: MaintenanceRecord;
    statusOptions?: Record<string, string>;
    conditionOptions?: Record<string, string>;
    auth?: { user?: { permissions?: string[] } | null };
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

function MaintenanceShowPage() {
    const {
        record,
        statusOptions = {},
        conditionOptions = {},
        auth,
    } = usePage<PageProps>().props;
    const permissions = auth?.user?.permissions ?? [];
    const canManage = permissions.includes('manage inventory maintenance');
    const canRequestDisposal = permissions.includes('manage inventory assets');
    const isOpen = record.status === 'reported';
    const statusLabel = statusOptions[record.status] ?? record.status;
    const assetLabel = record.asset
        ? `${record.asset.asset_code ?? ''} ${record.asset.name ?? ''}`.trim()
        : '—';
    const conditionAfter =
        record.condition_after != null
            ? (conditionOptions[record.condition_after] ?? record.condition_after)
            : null;

    return (
        <>
            <PageHeader
                title={record.record_number}
                description="Maintenance record details and next actions."
                actions={
                    <>
                        <Button variant="outline" render={<Link href={route('inventory.maintenance.index')} />}>
                            Back to list
                        </Button>
                        {record.asset?.id && (
                            <Button
                                variant="outline"
                                render={<Link href={route('inventory.assets.show', { asset: record.asset.id })} />}
                            >
                                View asset
                            </Button>
                        )}
                        {isOpen && canManage && (
                            <Button
                                render={
                                    <Link
                                        href={route('inventory.maintenance.complete.form', {
                                            maintenance: record.id,
                                        })}
                                    />
                                }
                            >
                                Record repair
                            </Button>
                        )}
                        {isOpen && canRequestDisposal && record.asset?.id && (
                            <Button
                                variant="outline"
                                render={
                                    <Link
                                        href={route('inventory.disposal.create', {
                                            asset_id: record.asset.id,
                                        })}
                                    />
                                }
                            >
                                Request disposal
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <CardTitle className="text-base">Problem report</CardTitle>
                        <Badge variant={statusBadgeVariant(record.status)}>{statusLabel}</Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <DetailItem label="Record number" value={record.record_number} />
                            <DetailItem label="Asset" value={assetLabel} />
                            <DetailItem label="Reported by" value={record.reported_by?.name} />
                            <DetailItem label="Reported on" value={formatDate(record.reported_at)} />
                            <div className="sm:col-span-2">
                                <DetailItem label="Problem" value={record.problem_description} />
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Resolution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isOpen ? (
                            <p className="text-muted-foreground text-sm">
                                Still open. Record a repair to return the asset to service, or request disposal if it
                                cannot be fixed.
                            </p>
                        ) : (
                            <dl className="grid gap-5 sm:grid-cols-2">
                                <DetailItem label="Outcome" value={statusLabel} />
                                <DetailItem label="Completed by" value={record.completed_by?.name} />
                                <DetailItem label="Completed on" value={formatDate(record.completed_at)} />
                                <DetailItem label="Repaired on" value={formatDate(record.repaired_on)} />
                                <DetailItem label="Repair provider" value={record.repair_provider} />
                                <DetailItem
                                    label="Repair cost"
                                    value={
                                        record.repair_cost != null && record.repair_cost !== ''
                                            ? String(record.repair_cost)
                                            : null
                                    }
                                />
                                <DetailItem label="Condition after" value={conditionAfter} />
                                <div className="sm:col-span-2">
                                    <DetailItem label="Notes" value={record.repair_notes} />
                                </div>
                            </dl>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MaintenanceShowPage.layout = withAppLayout('Maintenance');

export default MaintenanceShowPage;
