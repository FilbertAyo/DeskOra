import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Create({ formContext }: { formContext: { userId: string; departmentId: string } }) {
    const form = useForm({
        user_id: formContext.userId,
        department_id: formContext.departmentId,
        request_type: 'Petty Cash',
        request_for: 'Office Supplies',
        amount: '',
        reason: '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('petty.store'), {
            onError: () => notify('error', 'Please correct the highlighted fields.'),
        });
    };
    return (
        <>
            <PageHeader title="New Petty Cash Request" />
            <form onSubmit={submit}>
                <Card>
                    <CardHeader><CardTitle>Request Info</CardTitle></CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        <Field label="Request Type" error={form.errors.request_type}>
                            <Select value={form.data.request_type} onValueChange={(value) => form.setData('request_type', value ?? 'Petty Cash')}>
                                <SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="Petty Cash">Petty Cash</SelectItem><SelectItem value="Reimbursement">Reimbursement</SelectItem></SelectContent>
                            </Select>
                        </Field>
                        <Field label="Request For" error={form.errors.request_for}>
                            <Select value={form.data.request_for} onValueChange={(value) => form.setData('request_for', value ?? 'Office Supplies')}>
                                <SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{['Sales Delivery', 'Transport', 'Office Supplies'].map((value) => <SelectItem key={value} value={value}>{value}</SelectItem>)}</SelectContent>
                            </Select>
                        </Field>
                        <Field label="Amount" error={form.errors.amount}><Input type="number" min="0" step="0.01" name="amount" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} required /></Field>
                        <Field label="Reason" error={form.errors.reason}><Textarea name="reason" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} required /></Field>
                    </CardContent>
                    <CardFooter><Button disabled={form.processing} loading={form.processing}>Submit Request</Button></CardFooter>
                </Card>
            </form>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <div className="space-y-2"><Label>{label}</Label>{children}{error && <p className="text-sm text-destructive">{error}</p>}</div>;
}

Create.layout = (page: ReactNode) => <AppLayout title="New Petty Cash Request">{page}</AppLayout>;
