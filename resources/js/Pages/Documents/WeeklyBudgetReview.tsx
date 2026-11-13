import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader } from '@/lib/page-kit';

type Period = { id: string; weekNumber?: number; year?: number; dateRange?: string };
type BudgetItem = {
    item?: string;
    description_purpose?: string;
    amount?: string | number;
    budget_uid?: string;
    status_label?: string;
    action_label?: string;
    document_id?: string;
    owner_name?: string;
};

type StaffGroup = {
    staff_name?: string;
    user?: { name?: string } | null;
    pending_count?: number;
    summary?: { pending?: number; approved?: number; rejected?: number };
    items?: BudgetItem[] | Record<string, BudgetItem>;
    requested_total?: number;
    provided_total?: number;
};

type DeptGroup = {
    department_name?: string;
    requested_total?: number;
    provided_total?: number;
    staff?: StaffGroup[] | Record<string, StaffGroup>;
};

type PeriodGroup = {
    period_label?: string;
    period_range?: string | null;
    departments?: DeptGroup[] | Record<string, DeptGroup>;
    requested_total?: number;
    provided_total?: number;
};

function asList<T>(value: T[] | Record<string, T> | undefined | null): T[] {
    if (!value) {
        return [];
    }
    return Array.isArray(value) ? value : Object.values(value);
}

export default function WeeklyBudgetReview({
    grouped = {},
    stats,
    showingAllPendingWeeks,
    statusFilter,
    periodOptions = [],
    usersWithBudgets = [],
    years = [],
    filters = {},
    activePeriodId,
    indexUrl,
    exportPdfUrl,
    exportExcelUrl,
    addMidweekUrl,
}: {
    grouped?: Record<string, PeriodGroup> | PeriodGroup[];
    stats?: { pending?: number; approved?: number; rejected?: number; requested_total?: number; provided_total?: number };
    showingAllPendingWeeks?: boolean;
    statusFilter?: string | null;
    periodOptions?: Period[];
    usersWithBudgets?: Array<{ id: string; name: string }>;
    years?: Array<string | number>;
    filters?: { period_id?: string; status?: string; user_id?: string; year?: string };
    activePeriodId?: string | null;
    indexUrl: string;
    exportPdfUrl?: string;
    exportExcelUrl?: string;
    addMidweekUrl?: string;
    [key: string]: unknown;
}) {
    const groups = Array.isArray(grouped) ? grouped : Object.values(grouped);
    const apply = (patch: Record<string, string | undefined>) =>
        router.get(indexUrl, { ...filters, ...patch }, { preserveState: true });

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Weekly Budget"
                description={showingAllPendingWeeks ? 'Pending budgets across weeks' : 'Review weekly budget submissions.'}
                actions={
                    <>
                        {addMidweekUrl && (
                            <Button render={<a href={addMidweekUrl} />}>+ Add Mid-Week Budget</Button>
                        )}
                        {exportPdfUrl && (
                            <Button variant="outline" render={<a href={exportPdfUrl} />}>
                                Download PDF
                            </Button>
                        )}
                        {exportExcelUrl && (
                            <Button variant="outline" render={<a href={exportExcelUrl} />}>
                                Download Excel
                            </Button>
                        )}
                    </>
                }
            />

            <Card>
                <CardContent className="grid gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-5">
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.user_id ?? ''}
                        onChange={(event) => apply({ user_id: event.target.value || undefined })}
                    >
                        <option value="">All Staff</option>
                        {usersWithBudgets.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={activePeriodId ?? filters.period_id ?? ''}
                        onChange={(event) => apply({ period_id: event.target.value || undefined })}
                    >
                        <option value="">All Weeks</option>
                        {periodOptions.map((period) => (
                            <option key={period.id} value={period.id}>
                                Week {period.weekNumber} · {period.year}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.year ?? ''}
                        onChange={(event) => apply({ year: event.target.value || undefined })}
                    >
                        <option value="">All Years</option>
                        {years.map((year) => (
                            <option key={year} value={String(year)}>
                                {year}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={statusFilter ?? filters.status ?? ''}
                        onChange={(event) => apply({ status: event.target.value || undefined })}
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <Button variant="outline" onClick={() => router.get(indexUrl, {}, { preserveState: true })}>
                        Clear
                    </Button>
                </CardContent>
            </Card>

            {showingAllPendingWeeks && (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Showing all pending weekly budgets across weeks, oldest first. Finish older items before the current week piles up again.
                </div>
            )}

            {stats && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Card><CardContent className="pt-6 text-sm"><div className="text-muted-foreground">Requested</div><div className="text-xl font-semibold">{Number(stats.requested_total ?? 0).toLocaleString()}</div></CardContent></Card>
                    <Card><CardContent className="pt-6 text-sm"><div className="text-muted-foreground">Provided</div><div className="text-xl font-semibold">{Number(stats.provided_total ?? 0).toLocaleString()}</div></CardContent></Card>
                    <Card><CardContent className="pt-6 text-sm"><div className="text-muted-foreground">Pending</div><div className="text-xl font-semibold">{stats.pending ?? 0}</div></CardContent></Card>
                    <Card><CardContent className="pt-6 text-sm"><div className="text-muted-foreground">Approved</div><div className="text-xl font-semibold">{stats.approved ?? 0}</div></CardContent></Card>
                </div>
            )}

            {groups.length === 0 ? (
                <Card>
                    <CardContent className="text-muted-foreground py-12 text-center text-sm">No budget items found.</CardContent>
                </Card>
            ) : (
                groups.map((periodGroup, periodIndex) => (
                    <Card key={periodIndex}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {periodGroup.period_label ?? `Budget group ${periodIndex + 1}`}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                Requested: {Number(periodGroup.requested_total ?? 0).toLocaleString()} · Provided:{' '}
                                {Number(periodGroup.provided_total ?? 0).toLocaleString()}
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {asList(periodGroup.departments).map((department, deptIndex) => (
                                <div key={deptIndex} className="space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h3 className="font-medium">{department.department_name ?? 'Department'}</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Requested: {Number(department.requested_total ?? 0).toLocaleString()} · Provided:{' '}
                                            {Number(department.provided_total ?? 0).toLocaleString()}
                                        </p>
                                    </div>
                                    {asList(department.staff).map((staff, staffIndex) => {
                                        const pending = staff.pending_count ?? staff.summary?.pending ?? 0;
                                        const staffName = staff.staff_name ?? staff.user?.name ?? 'Staff';
                                        return (
                                        <div key={staffIndex} className="rounded-lg border">
                                            <div className="flex items-center justify-between gap-2 border-b px-3 py-2 text-sm">
                                                <span className="font-medium">{staffName}</span>
                                                {pending > 0 && (
                                                    <Badge variant="outline">{pending} pending</Badge>
                                                )}
                                            </div>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>#</TableHead>
                                                        <TableHead>Item</TableHead>
                                                        <TableHead>Description / Purpose</TableHead>
                                                        <TableHead>Requested</TableHead>
                                                        <TableHead>Status</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {asList(staff.items).map((item, itemIndex) => (
                                                        <TableRow key={item.budget_uid ?? itemIndex}>
                                                            <TableCell>{String(itemIndex + 1).padStart(2, '0')}</TableCell>
                                                            <TableCell>{item.item ?? '—'}</TableCell>
                                                            <TableCell>{item.description_purpose ?? '—'}</TableCell>
                                                            <TableCell>{Number(item.amount ?? 0).toLocaleString()}</TableCell>
                                                            <TableCell>
                                                                <Badge variant="outline">{item.status_label ?? item.action_label ?? 'Pending'}</Badge>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))
            )}
        </div>
    );
}

WeeklyBudgetReview.layout = (page: ReactNode) => <AppLayout title="Weekly Budget">{page}</AppLayout>;
