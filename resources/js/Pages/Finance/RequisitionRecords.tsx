import { router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, money, shortDate } from '@/Pages/Finance/components';
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
}: {
    transactions: Array<RecordRow | GroupedRow>;
    isFiltered: boolean;
    filterType: string | null;
    canDownloadRequisition: boolean;
}) {
    const query = filterType ? { filter_type: filterType } : {};

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
            <select
                className="border-input bg-background h-9 max-w-xs rounded-md border px-3 text-sm"
                value={filterType ?? ''}
                onChange={(event) =>
                    router.get(
                        route('requisition.records'),
                        event.target.value ? { filter_type: event.target.value } : {},
                    )
                }
            >
                <option value="">Show All</option>
                <option value="daily">Daily</option>
                <option value="monthly">Monthly</option>
            </select>
            {isFiltered ? (
                <FinanceTable headers={['Period', 'Requisitions', 'Amount', 'Cumulative Total']} empty={transactions.length === 0}>
                    {(transactions as GroupedRow[]).map((tx) => (
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
                    empty={transactions.length === 0}
                >
                    {(transactions as RecordRow[]).map((tx) => (
                        <TableRow key={tx.request_id}>
                            <TableCell>{shortDate(tx.date)}</TableCell>
                            <TableCell className="font-mono">{tx.reference}</TableCell>
                            <TableCell>{tx.requester}</TableCell>
                            <TableCell>{tx.activity}</TableCell>
                            <TableCell>{tx.project}</TableCell>
                            <TableCell>{tx.budget_code}</TableCell>
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
        </PageShell>
    );
}

RequisitionRecords.layout = (page: ReactNode) => <AppLayout title="Requisition Records">{page}</AppLayout>;
