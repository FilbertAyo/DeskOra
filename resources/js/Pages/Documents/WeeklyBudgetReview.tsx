import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { notify } from '@/lib/notify';
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
    const [midweekOpen, setMidweekOpen] = useState(false);
    const midweekForm = useForm({
        user_id: '',
        period_id: activePeriodId ?? filters.period_id ?? '',
        amount: '',
        reason: '',
    });
    const apply = (patch: Record<string, string | undefined>) =>
        router.get(indexUrl, { ...filters, ...patch }, { preserveState: true });

    const submitMidweek = (event: FormEvent) => {
        event.preventDefault();
        if (!addMidweekUrl) {
            return;
        }
        midweekForm.post(addMidweekUrl, {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', 'Mid-week budget added.');
                setMidweekOpen(false);
                midweekForm.reset();
            },
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Weekly Budget"
                description={showingAllPendingWeeks ? 'Pending budgets across weeks' : 'Review weekly budget submissions.'}
                actions={
                    <>
                        {addMidweekUrl && (
                            <Button type="button" onClick={() => setMidweekOpen(true)}>
                                + Add Mid-Week Budget
                            </Button>
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

            {addMidweekUrl && (
                <Dialog open={midweekOpen} onOpenChange={setMidweekOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add mid-week budget</DialogTitle>
                            <DialogDescription>
                                Add an authorized mid-week budget line to a submitted weekly plan.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="space-y-4" onSubmit={submitMidweek}>
                            <Field>
                                <FieldLabel htmlFor="midweek-user">Staff</FieldLabel>
                                <select
                                    id="midweek-user"
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={midweekForm.data.user_id}
                                    onChange={(event) => midweekForm.setData('user_id', event.target.value)}
                                    required
                                >
                                    <option value="">Select staff</option>
                                    {usersWithBudgets.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError>{midweekForm.errors.user_id}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="midweek-period">Work period</FieldLabel>
                                <select
                                    id="midweek-period"
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={midweekForm.data.period_id}
                                    onChange={(event) => midweekForm.setData('period_id', event.target.value)}
                                    required
                                >
                                    <option value="">Select week</option>
                                    {periodOptions.map((period) => (
                                        <option key={period.id} value={period.id}>
                                            Week {period.weekNumber} · {period.year}
                                        </option>
                                    ))}
                                </select>
                                <FieldError>{midweekForm.errors.period_id}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="midweek-amount">Amount</FieldLabel>
                                <Input
                                    id="midweek-amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={midweekForm.data.amount}
                                    onChange={(event) => midweekForm.setData('amount', event.target.value)}
                                    required
                                />
                                <FieldError>{midweekForm.errors.amount}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="midweek-reason">Reason</FieldLabel>
                                <Textarea
                                    id="midweek-reason"
                                    rows={3}
                                    value={midweekForm.data.reason}
                                    onChange={(event) => midweekForm.setData('reason', event.target.value)}
                                    required
                                />
                                <FieldError>{midweekForm.errors.reason}</FieldError>
                            </Field>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setMidweekOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={midweekForm.processing} loading={midweekForm.processing}>
                                    Add budget
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

WeeklyBudgetReview.layout = (page: ReactNode) => <AppLayout title="Weekly Budget">{page}</AppLayout>;
