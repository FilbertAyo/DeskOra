import { router } from '@inertiajs/react';
import { RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, type FinanceItem, money, shortDate, StatusBadge } from '@/Pages/Finance/components';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Index({ requests }: { requests: FinanceItem[] }) {
    return (
        <>
            <PageHeader title="Petty Cash Requests" actions={<><Button variant="outline" size="icon" onClick={() => router.reload()}><RefreshCwIcon /></Button><Button render={<a href={route('petty.create')} />}>New Request</Button></>} />
            <FinanceTable headers={['No.', 'Request for', 'Request Type', 'Amount', 'Date Requested', 'Status', 'Actions']} empty={requests.length === 0}>
                {requests.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell>{index + 1}</TableCell><TableCell>{item.requestFor}</TableCell><TableCell>{item.requestType}</TableCell>
                        <TableCell>{money(item.amount)}</TableCell><TableCell>{shortDate(item.createdAt)}</TableCell><TableCell><StatusBadge status={item.status} /></TableCell>
                        <TableCell><Button variant="outline" size="sm" render={<a href={route('petty.show', { petty: item.id })} />}>View</Button></TableCell>
                    </TableRow>
                ))}
            </FinanceTable>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Petty Cash Requests">{page}</AppLayout>;
