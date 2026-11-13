import { EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, type FinanceItem, money, PayButton, shortDate } from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Payments({ requests }: { requests: FinanceItem[] }) {
    return (
        <PageShell>
            <PageHeader title="Petty Cash Payments" />
            <FinanceTable
                headers={['No.', 'Request By', 'Request For', 'Request Type', 'Amount', 'Date Requested', 'Status', 'Actions']}
                empty={requests.length === 0}
            >
                {requests.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>{item.user?.name ?? 'N/A'}</TableCell>
                        <TableCell>{item.requestFor}</TableCell>
                        <TableCell>{item.requestType}</TableCell>
                        <TableCell>{money(item.amount)}</TableCell>
                        <TableCell>{shortDate(item.createdAt)}</TableCell>
                        <TableCell>{item.status === 'paid' ? 'Paid' : 'Awaiting Payment'}</TableCell>
                        <TableCell className="text-right">
                            <div className="inline-flex items-center gap-1">
                                <RowActions
                                    items={[
                                        {
                                            label: 'View',
                                            href: route('petty.details', { petty: item.id }),
                                            icon: <EyeIcon />,
                                        },
                                    ]}
                                />
                                {item.canPay && <PayButton id={item.id} />}
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
            </FinanceTable>
        </PageShell>
    );
}

Payments.layout = (page: ReactNode) => <AppLayout title="Petty Cash Payments">{page}</AppLayout>;
