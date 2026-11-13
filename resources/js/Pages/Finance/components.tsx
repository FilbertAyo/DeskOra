import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { route } from '@/lib/route';

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
    const variant = status === 'approved' || status === 'paid' ? 'default' : status === 'rejected' ? 'destructive' : 'secondary';
    return <Badge variant={variant}>{status.charAt(0).toUpperCase() + status.slice(1)}</Badge>;
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
        <Card>
            <CardContent className="overflow-x-auto p-0">
                <Table>
                    <TableHeader>
                        <TableRow>{headers.map((header) => <TableHead key={header}>{header}</TableHead>)}</TableRow>
                    </TableHeader>
                    <TableBody>
                        {empty ? (
                            <TableRow><TableCell colSpan={headers.length} className="h-24 text-center text-muted-foreground">No records found.</TableCell></TableRow>
                        ) : children}
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

export function ApprovalTimeline({ logs }: { logs: ApprovalLog[] }) {
    return (
        <Card>
            <CardHeader><CardTitle>Approval Timeline</CardTitle></CardHeader>
            <CardContent className="space-y-3">
                {logs.length === 0 ? <p className="text-sm text-muted-foreground">No approval activity yet.</p> : logs.map((log) => (
                    <div key={log.id} className="border-l-2 pl-4">
                        <p className="font-medium capitalize">{log.step}: {log.action}</p>
                        <p className="text-sm text-muted-foreground">{log.actor?.name ?? 'System'} · {shortDate(log.actedAt)}</p>
                        {log.comment && <p className="mt-1 text-sm">{log.comment}</p>}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
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
