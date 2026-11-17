import { router } from '@inertiajs/react';

import AppLink from '@/components/shared/AppLink';
import { EyeIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, type FinanceItem, money, shortDate, StatusBadge } from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Index({ requests }: { requests: FinanceItem[] }) {
    return (
        <PageShell>
            <PageHeader
                title="Petty Cash Requests"
                actions={
                    <>
                        <Button variant="outline" size="icon" onClick={() => router.reload()}>
                            <RefreshCwIcon />
                        </Button>
                        <Button render={<AppLink href={route('petty.create')} />}>New Request</Button>
                    </>
                }
            />
            <FinanceTable
                headers={['No.', 'Request for', 'Request Type', 'Amount', 'Date Requested', 'Status', 'Actions']}
                empty={requests.length === 0}
            >
                {requests.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>{item.requestFor}</TableCell>
                        <TableCell>{item.requestType}</TableCell>
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
                                        href: route('petty.show', { petty: item.id }),
                                        icon: <EyeIcon />,
                                    },
                                ]}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </FinanceTable>
        </PageShell>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Petty Cash Requests">{page}</AppLayout>;
