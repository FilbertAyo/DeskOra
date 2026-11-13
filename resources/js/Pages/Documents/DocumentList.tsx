import { router } from '@inertiajs/react';
import { EyeIcon, PencilIcon } from 'lucide-react';

import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

export type DocumentRow = {
    id: string;
    title: string;
    state: string;
    userName?: string | null;
    period?: { weekNumber: number; year: number; dateRange: string } | null;
    month?: number | null;
    year?: number | null;
    createdAt?: string | null;
    updatedAt?: string | null;
    updatedAgo?: string | null;
    canEdit: boolean;
    showUrl: string;
    editUrl: string;
};

type Props = {
    title: string;
    documents: { data: DocumentRow[]; links?: Array<{ url: string | null; label: string; active: boolean }> };
    createUrl?: string | null;
    filters?: React.ReactNode;
};

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export default function DocumentList({ title, documents, createUrl, filters }: Props) {
    return (
        <PageShell>
            <PageHeader
                title={title}
                description={`Create, review, and track your ${title.toLowerCase()}.`}
                actions={createUrl ? <Button render={<a href={createUrl} />}>New {title.replace(/s$/, '').toLowerCase()}</Button> : null}
            />
            {filters}
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">{title}</CardTitle>
                </CardHeader>
                <CardContent className="pt-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Period</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                        No {title.toLowerCase()} found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                documents.data.map((document) => (
                                    <TableRow key={document.id}>
                                        <TableCell className="font-medium">{document.title}</TableCell>
                                        <TableCell>
                                            {document.period
                                                ? `Week ${document.period.weekNumber} · ${document.period.dateRange}`
                                                : document.month && document.year
                                                  ? `${monthNames[document.month - 1]} ${document.year}`
                                                  : '—'}
                                        </TableCell>
                                        <TableCell>{document.userName ?? '—'}</TableCell>
                                        <TableCell>
                                            {document.updatedAt ?? '—'}
                                            {document.updatedAgo && (
                                                <span className="text-muted-foreground block text-xs">{document.updatedAgo}</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{document.state === 'draft' ? 'Draft' : 'Submitted'}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={[
                                                    { label: 'View', href: document.showUrl, icon: <EyeIcon /> },
                                                    {
                                                        label: 'Edit',
                                                        href: document.editUrl,
                                                        icon: <PencilIcon />,
                                                        hidden: !document.canEdit,
                                                    },
                                                ]}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                    {documents.links && documents.links.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {documents.links.map((link, index) => (
                                <Button
                                    key={`${link.label}-${index}`}
                                    size="sm"
                                    variant={link.active ? 'default' : 'outline'}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url)}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}
