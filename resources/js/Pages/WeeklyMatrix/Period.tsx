import { EyeIcon, PencilIcon } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

import AppLink from '@/components/shared/AppLink';
import AppLayout from '@/Layouts/AppLayout';
import DownloadButton from '@/components/shared/DownloadButton';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';
import MatrixDialog, {
    type MatrixDialogMode,
    type MatrixEntry,
    type MatrixSubDepartment,
} from './MatrixDialog';

type MatrixRow = {
    department: MatrixSubDepartment | null;
    entry: MatrixEntry;
    yearPlanLinkCount: number;
    updatedAt?: string | null;
    updateUrl?: string | null;
    destroyUrl?: string | null;
};

type Props = {
    period: { id: string; weekNumber: number; year: number; dateRange: string; status: string };
    matrices: MatrixRow[];
    availableSubDepartments: MatrixSubDepartment[];
    canCreate: boolean;
    modal?: MatrixDialogMode | null;
    modalDepartmentId?: string | null;
    indexUrl: string;
    createSubmitUrl: string;
    downloadUrl: string;
};

export default function WeeklyMatrixPeriod({
    period,
    matrices,
    availableSubDepartments,
    canCreate,
    modal = null,
    modalDepartmentId = null,
    indexUrl,
    createSubmitUrl,
    downloadUrl,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(Boolean(modal));
    const [dialogMode, setDialogMode] = useState<MatrixDialogMode>(modal ?? 'create');
    const [activeDepartmentId, setActiveDepartmentId] = useState<string | null>(modalDepartmentId);

    useEffect(() => {
        if (!modal) {
            return;
        }
        setDialogMode(modal);
        setActiveDepartmentId(modalDepartmentId);
        setDialogOpen(true);
    }, [modal, modalDepartmentId]);

    const activeRow = useMemo(
        () => matrices.find((row) => row.department?.id === activeDepartmentId) ?? null,
        [matrices, activeDepartmentId],
    );

    const periodLabel = `Week ${period.weekNumber} · ${period.year} · ${period.dateRange}`;

    const clearModalQuery = () => {
        router.get(window.location.pathname, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openDialog = (mode: MatrixDialogMode, departmentId: string | null = null) => {
        setDialogMode(mode);
        setActiveDepartmentId(departmentId);
        setDialogOpen(true);
    };

    const handleOpenChange = (open: boolean) => {
        setDialogOpen(open);
        if (!open && modal) {
            clearModalQuery();
        }
    };

    return (
        <PageShell>
            <PageHeader
                title={`Week ${period.weekNumber} · ${period.year}`}
                description={`${period.dateRange} · ${period.status}`}
                actions={
                    <>
                        <Button variant="outline" render={<AppLink href={indexUrl} />}>
                            All weeks
                        </Button>
                        <DownloadButton href={downloadUrl} />
                        {canCreate && (
                            <Button type="button" onClick={() => openDialog('create')}>
                                Create
                            </Button>
                        )}
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
                                matrices.map((row) => (
                                    <TableRow key={row.department?.id ?? row.updatedAt}>
                                        <TableCell>{row.department?.parentName ?? '—'}</TableCell>
                                        <TableCell className="font-medium">{row.department?.name ?? '—'}</TableCell>
                                        <TableCell className="max-w-md whitespace-pre-wrap">
                                            {row.entry.key_activity || '—'}
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
                                                        icon: <EyeIcon />,
                                                        hidden: !row.department,
                                                        onClick: () => openDialog('view', row.department?.id ?? null),
                                                    },
                                                    {
                                                        label: 'Edit',
                                                        icon: <PencilIcon />,
                                                        hidden: !row.department || !row.updateUrl,
                                                        onClick: () => openDialog('edit', row.department?.id ?? null),
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

            <MatrixDialog
                open={dialogOpen}
                mode={dialogMode}
                onOpenChange={handleOpenChange}
                periodLabel={periodLabel}
                availableSubDepartments={availableSubDepartments}
                department={
                    dialogMode === 'create'
                        ? availableSubDepartments.find((item) => item.id === activeDepartmentId) ?? null
                        : activeRow?.department ?? null
                }
                entry={dialogMode === 'create' ? null : activeRow?.entry ?? null}
                createSubmitUrl={createSubmitUrl}
                updateUrl={activeRow?.updateUrl ?? null}
            />
        </PageShell>
    );
}

WeeklyMatrixPeriod.layout = (page: ReactNode) => <AppLayout title="Weekly Matrix">{page}</AppLayout>;
