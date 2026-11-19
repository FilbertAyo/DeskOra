import { router } from '@inertiajs/react';

import AppLink from '@/components/shared/AppLink';
import { EyeIcon, PencilIcon, Trash2Icon } from 'lucide-react';
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
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'resubmission', label: 'Resubmission' },
];

export default function Index({
    requests,
    filters,
}: {
    requests: Paginated<FinanceItem>;
    filters: ListFilters;
}) {
    const remove = (id: string) =>
        window.confirm('Delete this draft requisition?') && router.delete(route('requisition.destroy', { petty: id }));

    const rows = requests.data ?? [];
    const pageStart = ((requests.current_page ?? 1) - 1) * (requests.per_page ?? rows.length);

    return (
        <PageShell>
            <PageHeader
                title="Staff Requisitions"
                actions={<Button render={<AppLink href={route('requisition.create')} />}>New Requisition</Button>}
            />
            <FinanceListToolbar filters={filters} statusOptions={statusOptions} actionUrl={route('requisition.index')} />
            <FinanceTable headers={['No.', 'Activity', 'Amount', 'Date Requested', 'Status', 'Actions']} empty={rows.length === 0}>
                {rows.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell className="w-12">{pageStart + index + 1}</TableCell>
                        <TruncateCell className="max-w-[22rem]" title={item.activityTitle ?? item.requestFor}>
                            {item.activityTitle ?? item.requestFor}
                        </TruncateCell>
                        <TableCell className="whitespace-nowrap">{money(item.amount)}</TableCell>
                        <TableCell>{shortDate(item.createdAt)}</TableCell>
                        <TableCell>
                            {item.status === 'draft' ? (
                                <Badge variant="outline">Draft</Badge>
                            ) : (
                                <StatusBadge status={item.status} />
                            )}
                        </TableCell>
                        <TableCell className="text-right">
                            <RowActions
                                items={[
                                    {
                                        label: 'View',
                                        href: route('requisition.show', { petty: item.id }),
                                        icon: <EyeIcon />,
                                    },
                                    {
                                        label: item.status === 'draft' ? 'Continue Draft' : 'Edit',
                                        href: route('requisition.edit', { petty: item.id }),
                                        icon: <PencilIcon />,
                                        hidden: !['draft', 'resubmission'].includes(item.status),
                                    },
                                    {
                                        label: 'Delete Draft',
                                        icon: <Trash2Icon />,
                                        destructive: true,
                                        hidden: item.status !== 'draft',
                                        onClick: () => remove(item.id),
                                    },
                                ]}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </FinanceTable>
            <FinancePagination links={requests.links} />
        </PageShell>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Staff Requisitions">{page}</AppLayout>;
