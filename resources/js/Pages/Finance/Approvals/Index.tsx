import { router } from '@inertiajs/react';
import { EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import {
    FinanceListToolbar,
    FinancePagination,
    FinanceTable,
    TruncateCell,
    type FinanceItem,
    type ListFilters,
    type Paginated,
    money,
    shortDate,
    StatusBadge,
} from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'resubmission', label: 'Resubmission' },
];

export default function Index({
    requests,
    stage,
    typeFilter,
    title,
    emptyMessage,
    filters,
}: {
    requests: Paginated<FinanceItem>;
    stage: string | null;
    stageLabel: string | null;
    typeFilter: string | null;
    title: string;
    emptyMessage: string;
    filters: ListFilters;
}) {
    const rows = requests.data ?? [];
    const pageStart = ((requests.current_page ?? 1) - 1) * (requests.per_page ?? rows.length);

    return (
        <PageShell>
            <PageHeader title={title} actions={<Button variant="outline" onClick={() => router.reload()}>Refresh</Button>} />
            <FinanceListToolbar
                filters={filters}
                statusOptions={statusOptions}
                actionUrl={route('petty.list')}
                extraParams={{
                    stage: stage ?? undefined,
                    type: typeFilter ?? undefined,
                }}
            />
            <FinanceTable
                headers={[
                    'No.',
                    'Request By',
                    'Request For / Activity',
                    'Request Type',
                    'Your Action',
                    'Amount',
                    'Date Requested',
                    'Status',
                    'Actions',
                ]}
                empty={false}
            >
                {rows.length === 0 ? (
                    <TableRow>
                        <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                ) : (
                    rows.map((item, index) => (
                        <TableRow key={item.id}>
                            <TableCell className="w-12">{pageStart + index + 1}</TableCell>
                            <TruncateCell className="max-w-[10rem]" title={item.user?.name ?? 'N/A'}>
                                {item.user?.name ?? 'N/A'}
                            </TruncateCell>
                            <TruncateCell className="max-w-[18rem]" title={item.activityTitle ?? item.requestFor}>
                                {item.activityTitle ?? item.requestFor}
                            </TruncateCell>
                            <TableCell className="whitespace-nowrap">
                                <Badge variant="outline">{item.requestType}</Badge>
                            </TableCell>
                            <TableCell>
                                {item.canAct ? (
                                    <Badge variant="warning">Pending</Badge>
                                ) : item.closedAtStage ? (
                                    <Badge variant={item.status === 'resubmission' ? 'warning' : 'destructive'}>
                                        {item.status === 'resubmission' ? 'Resubmission' : 'Rejected'}
                                    </Badge>
                                ) : item.stageApproved ? (
                                    <Badge variant="success">Completed</Badge>
                                ) : (
                                    '—'
                                )}
                            </TableCell>
                            <TableCell>{money(item.amount)}</TableCell>
                            <TableCell>{shortDate(item.createdAt)}</TableCell>
                            <TableCell>
                                <StatusBadge status={item.status} />
                            </TableCell>
                            <TableCell className="text-right">
                                <RowActions
                                    items={[
                                        {
                                            label: 'View',
                                            href: route('petty.details', {
                                                petty: item.id,
                                                ...(stage ? { stage } : {}),
                                                ...(typeFilter ? { type: typeFilter } : {}),
                                            }),
                                            icon: <EyeIcon />,
                                        },
                                    ]}
                                />
                            </TableCell>
                        </TableRow>
                    ))
                )}
            </FinanceTable>
            <FinancePagination links={requests.links} />
        </PageShell>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Approvals">{page}</AppLayout>;
