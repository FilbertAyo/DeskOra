import { router } from '@inertiajs/react';
import { EyeIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import type { ReactNode } from 'react';

import RowActions from '@/components/shared/RowActions';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { notify } from '@/lib/notify';

export type ResourceColumn<T> = { label: string; render: (row: T) => ReactNode };

export default function ResourceTable<T extends { id: string | number }>({
    rows,
    columns,
    showUrl,
    editUrl,
    destroyUrl,
    numbered = false,
}: {
    rows: T[];
    columns: ResourceColumn<T>[];
    showUrl?: (row: T) => string | null;
    editUrl?: (row: T) => string | null;
    destroyUrl?: (row: T) => string | null;
    numbered?: boolean;
}) {
    const hasActions = Boolean(showUrl || editUrl || destroyUrl);
    const colSpan = columns.length + (hasActions ? 1 : 0) + (numbered ? 1 : 0);

    return (
        <Card className="shadow-none">
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {numbered && <TableHead className="w-12">#</TableHead>}
                            {columns.map((column) => (
                                <TableHead key={column.label}>{column.label}</TableHead>
                            ))}
                            {hasActions && <TableHead className="w-12 text-right">Actions</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row, index) => (
                            <TableRow key={row.id}>
                                {numbered && <TableCell className="text-muted-foreground tabular-nums">{index + 1}</TableCell>}
                                {columns.map((column) => (
                                    <TableCell key={column.label}>{column.render(row)}</TableCell>
                                ))}
                                {hasActions && (
                                    <TableCell className="text-right">
                                        <RowActions
                                            items={[
                                                {
                                                    label: 'View',
                                                    href: showUrl?.(row) ?? undefined,
                                                    icon: <EyeIcon />,
                                                    hidden: !showUrl?.(row),
                                                },
                                                {
                                                    label: 'Edit',
                                                    href: editUrl?.(row) ?? undefined,
                                                    icon: <PencilIcon />,
                                                    hidden: !editUrl?.(row),
                                                },
                                                {
                                                    label: 'Delete',
                                                    icon: <Trash2Icon />,
                                                    destructive: true,
                                                    hidden: !destroyUrl?.(row),
                                                    onClick: () => {
                                                        const url = destroyUrl?.(row);
                                                        if (!url || !window.confirm('Delete this item?')) {
                                                            return;
                                                        }
                                                        router.delete(url, {
                                                            preserveScroll: true,
                                                            onSuccess: () => notify('success', 'Item deleted.'),
                                                        });
                                                    },
                                                },
                                            ]}
                                        />
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={colSpan} className="text-muted-foreground py-10 text-center">
                                    No records found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
