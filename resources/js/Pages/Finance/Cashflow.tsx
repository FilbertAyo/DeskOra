import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { type CashFlowForecast, FinanceTable, Forecast, money } from '@/Pages/Finance/components';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Transaction = { date?: string; label?: string; requested_by?: string; deposit?: number; deduction: number; remaining: number };

export default function Cashflow({ transactions, isFiltered, filterType, cashFlowForecast }: { transactions: Transaction[]; isFiltered: boolean; filterType: string | null; cashFlowForecast: CashFlowForecast }) {
    const query = filterType ? { filter_type: filterType } : {};
    return (
        <>
            <PageHeader title="Petty Cash Flow" actions={<><Button variant="destructive" render={<a href={route('cashflow.download', { format: 'pdf', ...query })} />}>PDF</Button><Button render={<a href={route('cashflow.download', { format: 'excel', ...query })} />}>Excel</Button></>} />
            <div className="mb-6"><Forecast value={cashFlowForecast} /></div>
            <select className="border-input bg-background mb-4 h-9 rounded-md border px-3 text-sm" value={filterType ?? ''} onChange={(event) => router.get(route('cashflow.index'), event.target.value ? { filter_type: event.target.value } : {})}><option value="">Show All</option><option value="daily">Daily</option><option value="monthly">Monthly</option></select>
            <FinanceTable headers={isFiltered ? ['Date', 'Deduction', 'Running Balance'] : ['Transaction Date', 'Name', 'Deposit', 'Deduction', 'Running Balance']} empty={transactions.length === 0}>
                {transactions.map((tx, index) => <TableRow key={`${tx.label ?? tx.date}-${index}`}><TableCell>{tx.label ?? (tx.date ? tx.date.slice(0, 10) : '—')}</TableCell>{!isFiltered && <><TableCell>{tx.requested_by}</TableCell><TableCell>{tx.deposit ? money(tx.deposit) : '—'}</TableCell></>}<TableCell>{tx.deduction ? money(tx.deduction) : '—'}</TableCell><TableCell className={tx.remaining < 0 ? 'text-destructive' : ''}>{money(tx.remaining)}</TableCell></TableRow>)}
            </FinanceTable>
        </>
    );
}

Cashflow.layout = (page: ReactNode) => <AppLayout title="Petty Cash Flow">{page}</AppLayout>;
