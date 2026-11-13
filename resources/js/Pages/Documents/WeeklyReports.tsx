import { router } from '@inertiajs/react';
import { Activity, EyeIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { DocumentRow } from '@/Pages/Documents/DocumentList';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Period = { id: string; weekNumber: number; year: number; dateRange: string };

type DeadlineStatuses = {
    report_due?: string;
    report_text?: string;
    report_badge?: string;
};

type WeeklyReportSlot = {
    period?: Period | null;
    graceActive?: boolean;
    graceEndsAt?: string | null;
    showAction?: boolean;
    actionUrl?: string | null;
    actionLabel?: string | null;
};

type Props = {
    documents: { data: DocumentRow[]; links?: Array<{ url: string | null; label: string; active: boolean }>; current_page?: number; per_page?: number };
    periods: Period[];
    years: number[];
    currentPeriod?: Period | null;
    deadlineStatuses?: DeadlineStatuses | null;
    weeklyReportSlot?: WeeklyReportSlot | null;
    weeklyReportAlreadySubmitted?: boolean;
    availableTypes?: Record<string, string>;
    returnedDocumentMap?: Record<string, unknown>;
    filters: { period_id?: string; year?: string };
    indexUrl: string;
};

function formatDeadline(value?: string): string {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

export default function WeeklyReports({
    documents,
    periods,
    years,
    currentPeriod,
    deadlineStatuses,
    weeklyReportSlot,
    weeklyReportAlreadySubmitted = false,
    availableTypes = {},
    returnedDocumentMap = {},
    filters,
    indexUrl,
}: Props) {
    const update = (key: string, value: string) =>
        router.get(indexUrl, { ...filters, [key]: value || undefined }, { preserveState: true });

    const slotPeriod = weeklyReportSlot?.period ?? currentPeriod;
    const showDeadline = Boolean(deadlineStatuses?.report_due) && !weeklyReportAlreadySubmitted;
    const canCreateType = Object.prototype.hasOwnProperty.call(availableTypes, 'weekly_report');
    const currentPage = documents.current_page ?? 1;
    const perPage = documents.per_page ?? 15;

    return (
        <PageShell>
            <PageHeader title="Weekly Reports" description="Home › Documents › Weekly Reports" />

            <Card className="shadow-none">
                <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Activity className="text-sky-600 size-4" />
                        Weekly Reports
                    </CardTitle>
                    <div className="flex flex-wrap gap-2">
                        <select
                            className="border-input bg-background h-9 min-w-[10rem] rounded-md border px-3 text-sm"
                            value={filters.period_id ?? ''}
                            onChange={(event) => update('period_id', event.target.value)}
                        >
                            <option value="">All Weeks</option>
                            {currentPeriod && (
                                <option value={currentPeriod.id}>Current Week (Week {currentPeriod.weekNumber})</option>
                            )}
                            {periods
                                .filter((period) => period.id !== currentPeriod?.id)
                                .map((period) => (
                                    <option key={period.id} value={period.id}>
                                        Week {period.weekNumber} • {period.dateRange}
                                    </option>
                                ))}
                        </select>
                        <select
                            className="border-input bg-background h-9 min-w-[8rem] rounded-md border px-3 text-sm"
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
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {showDeadline && (
                        <div
                            className={`flex flex-col gap-3 rounded-md border px-4 py-3 sm:flex-row sm:items-center sm:justify-between ${
                                weeklyReportSlot?.graceActive
                                    ? 'border-amber-200 bg-amber-50 text-amber-950'
                                    : 'border-sky-200 bg-sky-50 text-sky-950'
                            }`}
                        >
                            <div>
                                <div className="font-medium">
                                    Weekly Report Deadline
                                    {slotPeriod && (
                                        <span className="text-muted-foreground font-normal">
                                            {' '}
                                            (for Week {slotPeriod.weekNumber}
                                            {slotPeriod.year ? ` of ${slotPeriod.year}` : ''})
                                        </span>
                                    )}
                                </div>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                    <strong>{formatDeadline(deadlineStatuses?.report_due)}</strong>
                                    {deadlineStatuses?.report_text && (
                                        <Badge variant="destructive">{deadlineStatuses.report_text}</Badge>
                                    )}
                                </div>
                                {weeklyReportSlot?.graceActive && weeklyReportSlot.graceEndsAt && (
                                    <p className="mt-2 text-xs">
                                        Grace period: you can still create or submit until {formatDeadline(weeklyReportSlot.graceEndsAt)}.
                                    </p>
                                )}
                            </div>
                            {canCreateType && weeklyReportSlot?.showAction && weeklyReportSlot.actionUrl && (
                                <Button render={<a href={weeklyReportSlot.actionUrl} />}>
                                    {weeklyReportSlot.actionLabel ?? 'Create weekly report'}
                                </Button>
                            )}
                        </div>
                    )}

                    {documents.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center py-12 text-center">
                            <Activity className="mb-3 size-10 opacity-40" />
                            <p>No weekly reports found.</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center">No.</TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Week Period</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead>Last Updated</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                        <TableHead className="w-12 text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.data.map((document, index) => (
                                        <TableRow key={document.id}>
                                            <TableCell className="text-center">{(currentPage - 1) * perPage + index + 1}</TableCell>
                                            <TableCell className="font-medium">{document.title}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {document.period
                                                    ? `Week ${document.period.weekNumber} • ${document.period.dateRange}`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>{document.userName ?? '—'}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{document.createdAt ?? '—'}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {document.updatedAt ?? '—'}
                                                {document.updatedAgo && (
                                                    <span className="block text-xs">{document.updatedAgo}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant={document.state === 'draft' ? 'outline' : 'default'}>
                                                    {document.state === 'draft' ? 'Draft' : 'Submitted'}
                                                </Badge>
                                                {returnedDocumentMap[document.id] != null && (
                                                    <Badge variant="destructive" className="mt-1">
                                                        Returned
                                                    </Badge>
                                                )}
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
                                    ))}
                                </TableBody>
                            </Table>
                            {documents.links && documents.links.length > 3 && (
                                <div className="flex flex-wrap gap-2">
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
                        </>
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}

WeeklyReports.layout = (page: ReactNode) => <AppLayout title="Weekly Reports">{page}</AppLayout>;
