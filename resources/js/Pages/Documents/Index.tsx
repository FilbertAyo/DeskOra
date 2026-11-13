import { router } from '@inertiajs/react';
import { EyeIcon, FileText } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageShell } from '@/lib/page-kit';

type Period = { id?: string; weekNumber?: number | string; year?: number | string; dateRange?: string };
type DocLink = { id?: string; title?: string; showUrl: string; userName?: string | null };

type Row = {
    period: Period | null;
    participants?: string[];
    plan: DocLink | null;
    report: DocLink | null;
    updatedAt?: string | null;
};

type Props = {
    weeklyRows: { data: Row[]; links?: Array<{ url: string | null; label: string; active: boolean }>; current_page?: number; per_page?: number } | Row[];
    periods?: Array<{ id: string; weekNumber: number; year: number; dateRange: string }>;
    years?: Array<number | string>;
    filters?: { period_id?: string; year?: string };
    indexUrl?: string;
};

export default function DocumentsIndex({
    weeklyRows,
    periods = [],
    years = [],
    filters = {},
    indexUrl = '/documents',
}: Props) {
    const rows = Array.isArray(weeklyRows) ? weeklyRows : (weeklyRows.data ?? []);
    const links = Array.isArray(weeklyRows) ? undefined : weeklyRows.links;
    const currentPage = Array.isArray(weeklyRows) ? 1 : (weeklyRows.current_page ?? 1);
    const perPage = Array.isArray(weeklyRows) ? 15 : (weeklyRows.per_page ?? 15);

    const update = (key: string, value: string) =>
        router.get(indexUrl, { ...filters, [key]: value || undefined }, { preserveState: true });

    const hasFilters = Boolean(filters.period_id || filters.year);

    return (
        <PageShell>
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">Documents</h1>
                <p className="text-muted-foreground text-sm">Home › Documents</p>
            </div>

            <Card>
                <CardContent className="pt-6">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label className="block text-sm">
                            <span className="text-muted-foreground mb-1 block text-xs">Work Period</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={filters.period_id ?? ''}
                                onChange={(event) => update('period_id', event.target.value)}
                            >
                                <option value="">Last 2 work periods</option>
                                {periods.map((period) => (
                                    <option key={period.id} value={period.id}>
                                        Week {period.weekNumber}, {period.year} ({period.dateRange})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm">
                            <span className="text-muted-foreground mb-1 block text-xs">Year</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={filters.year ?? ''}
                                onChange={(event) => update('year', event.target.value)}
                            >
                                <option value="">All Years</option>
                                {years.map((year) => (
                                    <option key={year} value={String(year)}>
                                        {year}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="flex items-end gap-2">
                            <Button className="w-full" onClick={() => router.get(indexUrl, filters, { preserveState: true })}>
                                Filter
                            </Button>
                            {hasFilters && (
                                <Button variant="outline" onClick={() => router.get(indexUrl, {}, { preserveState: true })}>
                                    Clear
                                </Button>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <FileText className="size-4" />
                        Submitted documents (all staff)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {rows.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center py-12 text-center">
                            <FileText className="mb-3 size-10 opacity-40" />
                            <p>No documents found</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center">No.</TableHead>
                                        <TableHead>Week Period</TableHead>
                                        <TableHead>Staff</TableHead>
                                        <TableHead>Weekly Plan</TableHead>
                                        <TableHead>Weekly Report</TableHead>
                                        <TableHead>Last Updated</TableHead>
                                        <TableHead className="w-12 text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row, index) => (
                                        <TableRow key={`${row.period?.id ?? 'row'}-${index}`}>
                                            <TableCell className="text-center">{(currentPage - 1) * perPage + index + 1}</TableCell>
                                            <TableCell>
                                                {row.period ? (
                                                    <>
                                                        <div className="font-medium">
                                                            Week {row.period.weekNumber} of {row.period.year}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">{row.period.dateRange}</div>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {row.participants && row.participants.length > 0
                                                    ? row.participants.join(', ')
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {row.plan ? (
                                                    <a href={row.plan.showUrl} className="text-sky-700 hover:underline">
                                                        {row.plan.title}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">Not created</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {row.report ? (
                                                    <a href={row.report.showUrl} className="text-sky-700 hover:underline">
                                                        {row.report.title}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">Not created</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{row.updatedAt ?? '—'}</TableCell>
                                            <TableCell className="text-right">
                                                <RowActions
                                                    items={[
                                                        {
                                                            label: 'View Plan',
                                                            href: row.plan?.showUrl,
                                                            icon: <EyeIcon />,
                                                            hidden: !row.plan,
                                                        },
                                                        {
                                                            label: 'View Report',
                                                            href: row.report?.showUrl,
                                                            icon: <EyeIcon />,
                                                            hidden: !row.report,
                                                        },
                                                    ]}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            {links && links.length > 3 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {links.map((link, index) => (
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
                        </>
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}

DocumentsIndex.layout = (page: ReactNode) => <AppLayout title="Documents">{page}</AppLayout>;
