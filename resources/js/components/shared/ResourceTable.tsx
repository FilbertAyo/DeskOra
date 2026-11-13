import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { notify } from '@/lib/notify';

export type ResourceColumn<T> = { label: string; render: (row: T) => ReactNode };

export default function ResourceTable<T extends { id: string | number }>({
    rows,
    columns,
    editUrl,
    destroyUrl,
}: {
    rows: T[];
    columns: ResourceColumn<T>[];
    editUrl?: (row: T) => string | null;
    destroyUrl?: (row: T) => string | null;
}) {
    return (
        <Card>
            <CardContent className="p-0">
                <Table>
                    <TableHeader><TableRow>{columns.map((column) => <TableHead key={column.label}>{column.label}</TableHead>)}{(editUrl || destroyUrl) && <TableHead className="text-right">Actions</TableHead>}</TableRow></TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {columns.map((column) => <TableCell key={column.label}>{column.render(row)}</TableCell>)}
                                {(editUrl || destroyUrl) && <TableCell className="space-x-2 text-right">
                                    {editUrl?.(row) && <Button variant="outline" size="sm" render={<Link href={editUrl(row) as string} />}>Edit</Button>}
                                    {destroyUrl?.(row) && <Button variant="destructive" size="sm" onClick={() => {
                                        if (!window.confirm('Delete this item?')) return;
                                        router.delete(destroyUrl(row) as string, { preserveScroll: true, onSuccess: () => notify('success', 'Item deleted.') });
                                    }}>Delete</Button>}
                                </TableCell>}
                            </TableRow>
                        ))}
                        {rows.length === 0 && <TableRow><TableCell colSpan={columns.length + 1} className="py-10 text-center text-muted-foreground">No records found.</TableCell></TableRow>}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
