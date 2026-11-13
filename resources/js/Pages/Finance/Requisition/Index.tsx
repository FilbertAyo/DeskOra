import { router } from '@inertiajs/react';
import { EyeIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { FinanceTable, type FinanceItem, money, shortDate, StatusBadge } from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Index({ requests }: { requests: FinanceItem[] }) {
    const remove = (id: string) =>
        window.confirm('Delete this draft requisition?') && router.delete(route('requisition.destroy', { petty: id }));

    return (
        <PageShell>
            <PageHeader
                title="Staff Requisitions"
                actions={<Button render={<a href={route('requisition.create')} />}>New Requisition</Button>}
            />
            <FinanceTable
                headers={['No.', 'Activity', 'Amount', 'Date Requested', 'Status', 'Actions']}
                empty={requests.length === 0}
            >
                {requests.map((item, index) => (
                    <TableRow key={item.id}>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>{item.activityTitle ?? item.requestFor}</TableCell>
                        <TableCell>{money(item.amount)}</TableCell>
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
        </PageShell>
    );
}

Index.layout = (page: ReactNode) => <AppLayout title="Staff Requisitions">{page}</AppLayout>;
