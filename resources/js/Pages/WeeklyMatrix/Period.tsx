import { EyeIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Props = {
    period: { weekNumber: number; year: number; dateRange: string; status: string };
    matrices: Array<{
        department: { name: string; parentName?: string | null } | null;
        entry: { keyActivity: string } | null;
        yearPlanLinkCount: number;
        updatedAt?: string | null;
        viewUrl?: string | null;
        editUrl?: string | null;
    }>;
    canCreate: boolean;
    indexUrl: string;
    createUrl: string;
    downloadUrl: string;
};

export default function WeeklyMatrixPeriod({ period, matrices, canCreate, indexUrl, createUrl, downloadUrl }: Props) {
    return (
        <PageShell>
            <PageHeader
                title={`Week ${period.weekNumber} · ${period.year}`}
                description={`${period.dateRange} · ${period.status}`}
                actions={
                    <>
                        <Button variant="outline" render={<a href={indexUrl} />}>
                            All weeks
                        </Button>
                        <Button variant="outline" render={<a href={downloadUrl} />}>
                            Download PDF
                        </Button>
                        {canCreate && <Button render={<a href={createUrl} />}>Create</Button>}
                    </>
                }
            />
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Weekly matrices</CardTitle>
                </CardHeader>
                <CardContent className="pt-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Parent department</TableHead>
                                <TableHead>Sub-department</TableHead>
                                <TableHead>Key activity</TableHead>
                                <TableHead>Year plan links</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {matrices.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                        No matrix present
                                    </TableCell>
                                </TableRow>
                            ) : (
                                matrices.map((row, index) => (
                                    <TableRow key={`${row.department?.name}-${index}`}>
                                        <TableCell>{row.department?.parentName ?? '—'}</TableCell>
                                        <TableCell className="font-medium">{row.department?.name ?? '—'}</TableCell>
                                        <TableCell className="max-w-md whitespace-pre-wrap">
                                            {row.entry?.keyActivity ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{row.yearPlanLinkCount || '—'}</Badge>
                                        </TableCell>
                                        <TableCell>{row.updatedAt ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={[
                                                    {
                                                        label: 'View',
                                                        href: row.viewUrl ?? undefined,
                                                        icon: <EyeIcon />,
                                                        hidden: !row.viewUrl,
                                                    },
                                                    {
                                                        label: 'Edit',
                                                        href: row.editUrl ?? undefined,
                                                        icon: <PencilIcon />,
                                                        hidden: !row.editUrl,
                                                    },
                                                ]}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </PageShell>
    );
}

WeeklyMatrixPeriod.layout = (page: ReactNode) => <AppLayout title="Weekly Matrix">{page}</AppLayout>;
