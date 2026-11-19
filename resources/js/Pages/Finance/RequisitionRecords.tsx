import { router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import {
    FinanceListToolbar,
    FinancePagination,
    FinanceTable,
    TruncateCell,
    type ListFilters,
    type Paginated,
    money,
    shortDate,
} from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

type RecordRow = {
    date: string;
    request_id: string;
    reference: string;
    requester: string;
    activity: string;
    project: string;
    budget_code: string;
    amount: number;
    cumulative: number;
};
type GroupedRow = { label: string; amount: number; cumulative: number; count: number };

export default function RequisitionRecords({
    transactions,
    isFiltered,
    filterType,
    canDownloadRequisition,
    filters,
}: {
    transactions: Paginated<RecordRow | GroupedRow>;
    isFiltered: boolean;
    filterType: string | null;
    canDownloadRequisition: boolean;
    filters: ListFilters & { filter_type?: string | null };
}) {
    const query: Record<string, string> = {};
    if (filterType) {
        query.filter_type = filterType;
    }
    if (filters.search) {
        query.search = filters.search;
    }

    const rows = transactions.data ?? [];

    return (
        <PageShell>
            <PageHeader
                title="Requisition Records"
                actions={
                    <>
                        <Button
                            variant="destructive"
                            render={<a href={route('requisition.records.download', { format: 'pdf', ...query })} />}
                        >
                            PDF
                        </Button>
                        <Button render={<a href={route('requisition.records.download', { format: 'excel', ...query })} />}>
                            Excel
                        </Button>
                    </>
                }
            />
            <FinanceListToolbar
                filters={filters}
                actionUrl={route('requisition.records')}
                extraParams={{ filter_type: filterType ?? undefined }}
            >
                <select
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    value={filterType ?? ''}
                    onChange={(event) =>
                        router.get(route('requisition.records'), {
                            ...(event.target.value ? { filter_type: event.target.value } : {}),
                            ...(filters.search ? { search: filters.search } : {}),
                        })
                    }
                >
                    <option value="">Show All</option>
                    <option value="daily">Daily</option>
                    <option value="monthly">Monthly</option>
                </select>
            </FinanceListToolbar>
            {isFiltered ? (
                <FinanceTable headers={['Period', 'Requisitions', 'Amount', 'Cumulative Total']} empty={rows.length === 0}>
                    {(rows as GroupedRow[]).map((tx) => (
                        <TableRow key={tx.label}>
                            <TableCell>{tx.label}</TableCell>
                            <TableCell>{tx.count}</TableCell>
                            <TableCell>{money(tx.amount)}</TableCell>
                            <TableCell>{money(tx.cumulative)}</TableCell>
                        </TableRow>
                    ))}
                </FinanceTable>
            ) : (
                <FinanceTable
                    headers={[
                        'Date Authorized',
                        'Reference',
                        'Requester',
                        'Activity',
                        'Project',
                        'Budget Code',
                        'Amount',
                        'Cumulative Total',
                        'Actions',
                    ]}
                    empty={rows.length === 0}
                >
                    {(rows as RecordRow[]).map((tx) => (
                        <TableRow key={tx.request_id}>
                            <TableCell className="whitespace-nowrap">{shortDate(tx.date)}</TableCell>
                            <TableCell className="font-mono whitespace-nowrap">{tx.reference}</TableCell>
                            <TruncateCell className="max-w-[10rem]" title={tx.requester}>
                                {tx.requester}
                            </TruncateCell>
                            <TruncateCell className="max-w-[16rem]" title={tx.activity}>
                                {tx.activity}
                            </TruncateCell>
                            <TruncateCell className="max-w-[10rem]" title={tx.project}>
                                {tx.project}
                            </TruncateCell>
                            <TableCell className="whitespace-nowrap">{tx.budget_code}</TableCell>
                            <TableCell>{money(tx.amount)}</TableCell>
                            <TableCell>{money(tx.cumulative)}</TableCell>
                            <TableCell className="text-right">
                                <RowActions
                                    items={[
                                        {
                                            label: 'View',
                                            href: route('petty.details', { petty: tx.request_id }),
                                            icon: <EyeIcon />,
                                        },
                                        {
                                            label: 'Download PDF',
                                            href: route('petty.requisition.download', { petty: tx.request_id }),
                                            icon: <DownloadIcon />,
                                            hidden: !canDownloadRequisition,
                                        },
                                    ]}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </FinanceTable>
            )}
            <FinancePagination links={transactions.links} />
        </PageShell>
    );
}

RequisitionRecords.layout = (page: ReactNode) => <AppLayout title="Requisition Records">{page}</AppLayout>;
