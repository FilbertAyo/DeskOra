import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TableCell, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { type CashFlowForecast, FinanceTable, Forecast, money, shortDate } from '@/Pages/Finance/components';
import { notify } from '@/lib/notify';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Deposit = { id: string; deposit: number; remaining: number; createdAt: string | null; description: string | null; depositedBy: string };

export default function Deposits({ deposits, remaining, cashFlowForecast, formContext }: { deposits: Deposit[]; remaining: number; cashFlowForecast: CashFlowForecast; formContext: { userId: string; departmentId: string; canDeposit: boolean } }) {
    const [selected, setSelected] = useState<Deposit | null>(null);
    const form = useForm({ deposit: '', user_id: formContext.userId, department_id: formContext.departmentId, created_at: '', description: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('deposit.store'), { onSuccess: () => form.reset('deposit', 'created_at', 'description'), onError: () => notify('error', 'Please correct the deposit details.') });
    };
    return (
        <>
            <PageHeader title="Cash Management" actions={formContext.canDeposit && <Dialog><DialogTrigger render={<Button />}>New Deposit</DialogTrigger><DialogContent><DialogHeader><DialogTitle>New Deposit</DialogTitle><DialogDescription>Add funds to the current cash balance.</DialogDescription></DialogHeader><form onSubmit={submit} className="space-y-4"><Field label="Amount of Money"><Input type="number" min="100" name="deposit" value={form.data.deposit} onChange={(event) => form.setData('deposit', event.target.value)} required /></Field><Field label="Date Deposited"><Input type="date" name="created_at" value={form.data.created_at} onChange={(event) => form.setData('created_at', event.target.value)} required /></Field><Field label="Description (optional)"><Textarea name="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field><Button disabled={form.processing} loading={form.processing}>Add Deposit</Button></form></DialogContent></Dialog>} />
            <div className="mb-6"><Forecast value={cashFlowForecast} /></div>
            <p className={`mb-4 text-lg font-semibold ${remaining < 0 ? 'text-destructive' : ''}`}>Remaining Amount: {money(remaining)}/=</p>
            <FinanceTable headers={['No.', 'Deposit Amount', 'Remaining Balance', 'Date Deposited', 'Deposited By', 'Action']} empty={deposits.length === 0}>
                {deposits.map((deposit, index) => <TableRow key={deposit.id}><TableCell>{index + 1}</TableCell><TableCell>{money(deposit.deposit)}/=</TableCell><TableCell className={deposit.remaining < 0 ? 'text-destructive' : ''}>{money(deposit.remaining)}/=</TableCell><TableCell>{shortDate(deposit.createdAt)}</TableCell><TableCell>{deposit.depositedBy}</TableCell><TableCell><Button variant="outline" size="sm" onClick={() => setSelected(deposit)}>View</Button></TableCell></TableRow>)}
            </FinanceTable>
            <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}><DialogContent><DialogHeader><DialogTitle>Deposit Details</DialogTitle><DialogDescription>Deposit transaction information.</DialogDescription></DialogHeader>{selected && <div className="grid gap-3 sm:grid-cols-2"><p><span className="text-muted-foreground">Amount:</span> {money(selected.deposit)}/=</p><p><span className="text-muted-foreground">Remaining:</span> {money(selected.remaining)}/=</p><p><span className="text-muted-foreground">Date:</span> {shortDate(selected.createdAt)}</p><p><span className="text-muted-foreground">Deposited by:</span> {selected.depositedBy}</p><p className="sm:col-span-2"><span className="text-muted-foreground">Description:</span> {selected.description ?? 'No description provided'}</p></div>}</DialogContent></Dialog>
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return <div className="space-y-2"><Label>{label}</Label>{children}</div>;
}

Deposits.layout = (page: ReactNode) => <AppLayout title="Cash Management">{page}</AppLayout>;
