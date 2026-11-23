import { router } from '@inertiajs/react';
import { HistoryIcon } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PaginationLinks } from '@/lib/page-kit';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';

export type PaginationLink = { url: string | null; label: string; active: boolean };

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
};

export type ListFilters = {
    search?: string;
    status?: string;
};

/** Keep list tables from growing wider than the page when activity/description text is long. */
export function TruncateCell({
    children,
    className,
    title,
    maxLength = 30,
}: {
    children: ReactNode;
    className?: string;
    title?: string | null;
    maxLength?: number;
}) {
    const raw =
        title ??
        (typeof children === 'string' || typeof children === 'number' ? String(children) : null);
    const full = raw?.trim() ? raw : null;
    const display =
        full && full.length > maxLength ? `${full.slice(0, maxLength).trimEnd()}…` : (children ?? '—');

    return (
        <TableCell className={cn('max-w-[12rem] min-w-[7rem] overflow-hidden', className)} title={full || undefined}>
            <span className="block truncate whitespace-nowrap">{display}</span>
        </TableCell>
    );
}

export type FinanceItem = {
    id: string;
    code: string;
    requestType: string;
    requestFor: string | null;
    amount: number;
    reason: string | null;
    status: string;
    createdAt: string | null;
    paidDate: string | null;
    user: { id: string; name: string } | null;
    department: { id: string; name: string } | null;
    departmentId: string;
    projectProgram: string | null;
    activityCode: string | null;
    activityTitle: string | null;
    budgetCode: string | null;
    isOffPlan: boolean;
    weeklyPlanId?: string | null;
    dateOfRequest: string | null;
    dateOfRequirement: string | null;
    items: Array<{ id: string; serialNo: number; description: string; unit: string | null; cost: number }>;
    currentStage: string;
    reviewedAt: string | null;
    approvedAt: string | null;
    authorizedAt: string | null;
    canAct: boolean;
    stageApproved: boolean;
    closedAtStage: boolean;
    canDownload: boolean;
    canPay: boolean;
};

export type ApprovalLog = {
    id: string;
    step: string;
    action: string;
    comment: string | null;
    actedAt: string | null;
    actor: { name: string } | null;
};

export const money = (value: number) =>
    new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);

export const shortDate = (value: string | null) =>
    value ? new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';

export function StatusBadge({ status }: { status: string }) {
    return <Badge variant={statusBadgeVariant(status)}>{status.charAt(0).toUpperCase() + status.slice(1)}</Badge>;
}

export function FinanceTable({
    headers,
    children,
    empty,
}: {
    headers: string[];
    children: ReactNode;
    empty?: boolean;
}) {
    return (
        <Card className="overflow-hidden">
            <CardContent className="p-0">
                {/*
                  Avoid table-fixed: on narrow screens it crushes columns and badges overlap.
                  Let content define width; the Table wrapper scrolls horizontally.
                */}
                <Table className="w-full min-w-max">
                    <TableHeader>
                        <TableRow>
                            {headers.map((header) => (
                                <TableHead key={header}>{header}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {empty ? (
                            <TableRow>
                                <TableCell colSpan={headers.length} className="text-muted-foreground h-24 text-center">
                                    No records found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            children
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

export function RequestPreview({ request }: { request: FinanceItem }) {
    return (
        <div className="space-y-4">
            <Card>
                <CardHeader><CardTitle>Request Details</CardTitle></CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Detail label="Reference" value={request.code} />
                    <Detail label="Request Type" value={request.requestType} />
                    <Detail label="Requester" value={request.user?.name ?? 'N/A'} />
                    <Detail label="Status" value={<StatusBadge status={request.status} />} />
                    {request.requestType === 'Staff Requisition' ? (
                        <>
                            <Detail label="Project / Program" value={request.projectProgram} />
                            <Detail label="Activity Code" value={`${request.activityCode ?? '—'}${request.isOffPlan ? ' (Not on the year plan)' : ''}`} />
                            <Detail label="Activity Title" value={request.activityTitle} />
                            <Detail label="Budget Code" value={request.budgetCode} />
                            <Detail label="Date of Request" value={request.dateOfRequest} />
                            <Detail label="Date of Requirement" value={request.dateOfRequirement} />
                        </>
                    ) : (
                        <>
                            <Detail label="Request For" value={request.requestFor} />
                            <Detail label="Date Issued" value={shortDate(request.createdAt)} />
                            <Detail label="Description" value={request.reason} />
                        </>
                    )}
                    <Detail label="Total Amount" value={`TZS ${money(request.amount)}`} />
                </CardContent>
            </Card>
            {request.requestType === 'Staff Requisition' && (
                <FinanceTable headers={['S/N', 'Description', 'Unit', 'Cost']} empty={request.items.length === 0}>
                    {request.items.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell>{item.serialNo}</TableCell>
                            <TableCell className="whitespace-pre-wrap">{item.description}</TableCell>
                            <TableCell>{item.unit ?? '—'}</TableCell>
                            <TableCell>{money(item.cost)}</TableCell>
                        </TableRow>
                    ))}
                </FinanceTable>
            )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: ReactNode }) {
    return <div><p className="text-xs font-medium uppercase text-muted-foreground">{label}</p><div className="mt-1">{value || '—'}</div></div>;
}

export function ApprovalTimeline({ logs, className }: { logs: ApprovalLog[]; className?: string }) {
    return (
        <div className={cn('space-y-3', className)}>
            {logs.length === 0 ? (
                <p className="text-muted-foreground text-sm">No approval activity yet.</p>
            ) : (
                logs.map((log) => (
                    <div key={log.id} className="border-l-2 pl-4">
                        <p className="font-medium capitalize">
                            {log.step}: {log.action}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {log.actor?.name ?? 'System'} · {shortDate(log.actedAt)}
                        </p>
                        {log.comment && <p className="mt-1 text-sm">{log.comment}</p>}
                    </div>
                ))
            )}
        </div>
    );
}

export function ApprovalTimelineButton({ logs }: { logs: ApprovalLog[] }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button type="button" variant="outline" onClick={() => setOpen(true)}>
                <HistoryIcon />
                Timeline
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-b px-4 py-4">
                        <SheetTitle>Approval Timeline</SheetTitle>
                        <SheetDescription>Review, approval, and authorization history for this request.</SheetDescription>
                    </SheetHeader>
                    <div className="overflow-y-auto px-4 py-4">
                        <ApprovalTimeline logs={logs} />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

export function FinanceListToolbar({
    filters,
    statusOptions,
    actionUrl,
    extraParams,
    children,
}: {
    filters: ListFilters;
    statusOptions?: Array<{ value: string; label: string }>;
    actionUrl: string;
    extraParams?: Record<string, string | undefined | null>;
    children?: ReactNode;
}) {
    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const search = String(form.get('search') ?? '').trim();
        const status = String(form.get('status') ?? '').trim();
        const params: Record<string, string> = {};
        for (const [key, value] of Object.entries(extraParams ?? {})) {
            if (value) {
                params[key] = value;
            }
        }
        if (search) {
            params.search = search;
        }
        if (status) {
            params.status = status;
        }
        router.get(actionUrl, params, { preserveState: true, replace: true });
    };

    return (
        <form onSubmit={apply} className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div className="min-w-[12rem] flex-1 space-y-1">
                <label htmlFor="finance-search" className="text-sm font-medium">
                    Search
                </label>
                <Input
                    id="finance-search"
                    name="search"
                    defaultValue={filters.search ?? ''}
                    placeholder="Search by reference, activity, requester…"
                />
            </div>
            {statusOptions && statusOptions.length > 0 && (
                <div className="w-full space-y-1 sm:w-44">
                    <label htmlFor="finance-status" className="text-sm font-medium">
                        Status
                    </label>
                    <select
                        id="finance-status"
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            )}
            <div className="flex flex-wrap gap-2">
                <Button type="submit" variant="outline">
                    Apply
                </Button>
                {children}
            </div>
        </form>
    );
}

export function FinancePagination({ links }: { links?: PaginationLink[] }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return <PaginationLinks links={links} className="mt-4" />;
}

export function PayButton({ id }: { id: string }) {
    return (
        <Button size="sm" data-action="pay" onClick={() => router.post(route('c_approve.approve', { petty: id }))}>
            Pay Now
        </Button>
    );
}

export type CashFlowForecast = {
    available: number;
    committed: number;
    projected: number;
    awaiting_payment_count: number;
    shortage: boolean;
    authorized_budget_next_two_weeks: number;
    horizon_label: string;
};

export function Forecast({ value }: { value: CashFlowForecast }) {
    const cards = [
        ['Available balance', value.available],
        ['Committed (awaiting payment)', value.committed],
        ['Projected after payments', value.projected],
        [`Authorized budgets (${value.horizon_label})`, value.authorized_budget_next_two_weeks],
    ] as const;
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map(([label, amount]) => (
                <Card key={label}><CardContent className="p-4"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-xl font-semibold">{money(amount)}/=</p>{label.startsWith('Committed') && <p className="text-xs text-muted-foreground">{value.awaiting_payment_count} request(s)</p>}</CardContent></Card>
            ))}
        </div>
    );
}
