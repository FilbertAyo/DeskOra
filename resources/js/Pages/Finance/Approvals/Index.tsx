import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, type FinanceItem, money, shortDate, StatusBadge } from '@/Pages/Finance/components';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Index({ requests, stage, typeFilter, title, emptyMessage }: { requests: FinanceItem[]; stage: string | null; stageLabel: string | null; typeFilter: string | null; title: string; emptyMessage: string }) {
    return (
        <>
            <PageHeader title={title} actions={<Button variant="outline" onClick={() => router.reload()}>Refresh</Button>} />
            <FinanceTable headers={['No.', 'Request By', 'Request For / Activity', 'Request Type', 'Your Action', 'Amount', 'Date Requested', 'Status', 'Actions']} empty={false}>
                {requests.length === 0 ? <TableRow><TableCell colSpan={9} className="h-24 text-center text-muted-foreground">{emptyMessage}</TableCell></TableRow> : requests.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell>{index + 1}</TableCell><TableCell>{item.user?.name ?? 'N/A'}</TableCell><TableCell>{item.activityTitle ?? item.requestFor}</TableCell><TableCell><Badge variant="outline">{item.requestType}</Badge></TableCell>
                        <TableCell>{item.canAct ? <Badge variant="secondary">Pending</Badge> : item.closedAtStage ? <Badge variant="destructive">{item.status === 'resubmission' ? 'Resubmission' : 'Rejected'}</Badge> : item.stageApproved ? <Badge>Completed</Badge> : '—'}</TableCell>
                        <TableCell>{money(item.amount)}</TableCell><TableCell>{shortDate(item.createdAt)}</TableCell><TableCell><StatusBadge status={item.status} /></TableCell>
                        <TableCell><Button variant="outline" size="sm" render={<a href={route('petty.details', { petty: item.id, ...(stage ? { stage } : {}), ...(typeFilter ? { type: typeFilter } : {}) })} />}>View</Button></TableCell>
                    </TableRow>
                ))}
            </FinanceTable>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Approvals">{page}</AppLayout>;
