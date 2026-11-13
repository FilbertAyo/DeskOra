import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { type FinanceItem, money } from '@/Pages/Finance/components';
import { notify } from '@/lib/notify';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Activity = { id: string; code: string; description: string; department_id: string; year: number | string | null };
type LineItem = { description: string; unit: string; cost: string | number };

export default function Form({
    mode,
    planActivities,
    hasSignature,
    formContext,
    request,
}: {
    mode: 'create' | 'edit';
    planActivities: Activity[];
    hasSignature: boolean;
    formContext: { userId: string; departmentId: string };
    request: FinanceItem | null;
}) {
    const savedActivity = request?.isOffPlan ? '__off_plan__' : (request?.activityCode ?? '');
    const form = useForm({
        user_id: formContext.userId,
        department_id: request?.departmentId ?? formContext.departmentId,
        request_type: 'Staff Requisition',
        action: request?.status === 'draft' ? 'draft' : 'submit',
        activity_selection: savedActivity,
        is_off_plan: request?.isOffPlan ? '1' : '0',
        project_program: request?.projectProgram ?? '',
        activity_code: request?.activityCode ?? '',
        activity_title: request?.activityTitle ?? '',
        budget_code: request?.budgetCode ?? '',
        date_of_request: request?.dateOfRequest ?? '',
        date_of_requirement: request?.dateOfRequirement ?? '',
        reason: request?.reason ?? '',
        items: request?.items.length ? request.items.map((item) => ({ description: item.description, unit: item.unit ?? '', cost: item.cost })) : [{ description: '', unit: '', cost: '' }],
    });
    const offPlan = form.data.activity_selection === '__off_plan__';
    const selectActivity = (code: string) => {
        if (code === '__off_plan__') {
            form.setData((data) => ({ ...data, activity_selection: code, is_off_plan: '1', activity_code: '', activity_title: '', budget_code: '' }));
            return;
        }
        const activity = planActivities.find((item) => item.code === code);
        form.setData((data) => ({
            ...data,
            activity_selection: code,
            is_off_plan: '0',
            activity_code: code,
            activity_title: activity?.description ?? data.activity_title,
            budget_code: activity?.year ? String(activity.year) : '',
            department_id: activity?.department_id ?? data.department_id,
        }));
    };
    const updateItem = (index: number, key: keyof LineItem, value: string) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const action = (event.nativeEvent as SubmitEvent).submitter instanceof HTMLButtonElement
            ? ((event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement).value
            : form.data.action;
        form.setData('action', action);
        const options = { onError: () => notify('error', 'Please correct the highlighted fields.') };
        if (mode === 'edit' && request) form.put(route('requisition.update', { petty: request.id }), options);
        else form.post(route('requisition.store'), options);
    };

    if (!hasSignature) {
        return <Alert><AlertTitle>Signature required</AlertTitle><AlertDescription className="space-y-3"><p>You must upload your signature in Profile Settings before you can create a requisition.</p><Button render={<a href={route('profile.edit')} />}>Upload Signature</Button></AlertDescription></Alert>;
    }

    const total = form.data.items.reduce((sum, item) => sum + Number(item.cost || 0), 0);
    const canDraft = mode === 'create' || request?.status === 'draft';
    return (
        <>
            <PageHeader title={mode === 'create' ? 'New Requisition' : 'Edit Requisition'} />
            <form onSubmit={submit}>
                <Card>
                    <CardHeader><CardTitle>Fund Requisition Details</CardTitle></CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Field label="Project / Program" error={form.errors.project_program}><Input name="project_program" value={form.data.project_program} onChange={(event) => form.setData('project_program', event.target.value)} required={!canDraft} /></Field>
                            <Field label="Activity Code" error={form.errors.activity_selection ?? form.errors.activity_code}>
                                <select name="activity_selection" className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm" value={form.data.activity_selection} onChange={(event) => selectActivity(event.target.value)} required={!canDraft}>
                                    <option value="">Select activity code</option>
                                    {planActivities.map((activity) => <option key={activity.id} value={activity.code}>{activity.code} - {activity.description}{activity.year ? ` (${activity.year})` : ''}</option>)}
                                    <option value="__off_plan__">Not on the year plan</option>
                                </select>
                                {offPlan && <Input name="activity_code" placeholder="Enter activity code e.g 19" value={form.data.activity_code} onChange={(event) => form.setData('activity_code', event.target.value)} required={!canDraft} />}
                                {planActivities.length === 0 && <p className="text-xs text-muted-foreground">No year plan activities found for your department. Select “Not on the year plan” to enter codes manually.</p>}
                            </Field>
                            <Field label="Budget Code" error={form.errors.budget_code}><Input name="budget_code" value={form.data.budget_code} readOnly={!offPlan} onChange={(event) => form.setData('budget_code', event.target.value)} required={!canDraft} /></Field>
                            <Field label="Activity Title" error={form.errors.activity_title}><Input name="activity_title" value={form.data.activity_title} readOnly={!offPlan} onChange={(event) => form.setData('activity_title', event.target.value)} required={!canDraft} /></Field>
                            <Field label="Date of Request" error={form.errors.date_of_request}><Input type="date" name="date_of_request" value={form.data.date_of_request} onChange={(event) => form.setData('date_of_request', event.target.value)} /></Field>
                            <Field label="Date of Requirement" error={form.errors.date_of_requirement}><Input type="date" name="date_of_requirement" value={form.data.date_of_requirement} onChange={(event) => form.setData('date_of_requirement', event.target.value)} /></Field>
                            <Field label="Reason" error={form.errors.reason}><Textarea name="reason" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>
                        </div>
                        <div>
                            <div className="mb-3 flex items-center justify-between"><h3 className="font-semibold">Line Items</h3><Button type="button" variant="outline" onClick={() => form.setData('items', [...form.data.items, { description: '', unit: '', cost: '' }])}>Add Row</Button></div>
                            <div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>S/N</TableHead><TableHead>Description</TableHead><TableHead>Unit</TableHead><TableHead>Cost</TableHead><TableHead /></TableRow></TableHeader><TableBody>
                                {form.data.items.map((item, index) => <TableRow key={index}><TableCell>{index + 1}</TableCell><TableCell><Textarea name={`items[${index}][description]`} value={item.description} onChange={(event) => updateItem(index, 'description', event.target.value)} required={!canDraft} /></TableCell><TableCell><Input name={`items[${index}][unit]`} value={item.unit} onChange={(event) => updateItem(index, 'unit', event.target.value)} /></TableCell><TableCell><Input type="number" min="0" step="0.01" name={`items[${index}][cost]`} value={item.cost} onChange={(event) => updateItem(index, 'cost', event.target.value)} required={!canDraft} /></TableCell><TableCell><Button type="button" variant="destructive" size="sm" onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))}>Remove</Button></TableCell></TableRow>)}
                                <TableRow><TableCell colSpan={3} className="text-right font-medium">Total</TableCell><TableCell className="font-medium">{money(total)}</TableCell><TableCell /></TableRow>
                            </TableBody></Table></div>
                            {form.errors.items && <p className="mt-2 text-sm text-destructive">{form.errors.items}</p>}
                        </div>
                    </CardContent>
                    <CardFooter className="gap-2">
                        {canDraft && (
                            <Button type="submit" name="action" value="draft" variant="outline" disabled={form.processing} data-action="draft">
                                Save as Draft
                            </Button>
                        )}
                        <Button type="submit" name="action" value="submit" disabled={form.processing} data-action="submit">
                            {mode === 'edit' && request?.status !== 'draft' ? 'Update Requisition' : 'Submit Requisition'}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <div className="space-y-2"><Label>{label}</Label>{children}{error && <p className="text-sm text-destructive">{error}</p>}</div>;
}

Form.layout = (page: ReactNode) => <AppLayout title="Requisition">{page}</AppLayout>;
