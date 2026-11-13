import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

const emptyEntry = { key_activity: '', output: '', outcome: '', impact: '', indicators: '', baseline: '', target: '', means_of_verification: '', year_plan_activity_ids: [] as string[] };
type Props = { mode: 'create' | 'edit'; period: { weekNumber: number; year: number }; availableSubDepartments?: Array<{ id: string; name: string }>; selectedDepartment?: { id: string } | null; department?: { id: string; name: string }; entry?: typeof emptyEntry; entryDefaults?: Array<typeof emptyEntry>; viewMode?: boolean; submitUrl: string; backUrl: string };

export default function WeeklyMatrixForm({ mode, period, availableSubDepartments = [], selectedDepartment, department, entry, entryDefaults, viewMode = false, submitUrl, backUrl }: Props) {
    const form = useForm({ department_id: selectedDepartment?.id ?? '', entry: { ...emptyEntry, ...entry }, entries: [{ ...emptyEntry, ...(entryDefaults?.[0] ?? {}) }] });
    const value = mode === 'create' ? form.data.entry : form.data.entries[0];
    const setValue = (key: keyof typeof emptyEntry, next: string) => {
        if (mode === 'create') form.setData('entry', { ...form.data.entry, [key]: next });
        else form.setData('entries', [{ ...form.data.entries[0], [key]: next }]);
    };
    const submit = () => {
        const options = { onSuccess: () => notify('success', `Weekly matrix ${mode === 'create' ? 'created' : 'updated'}.`) };
        if (mode === 'create') form.post(submitUrl, options); else form.put(submitUrl, options);
    };
    return <div className="flex flex-col gap-6"><div><h1 className="text-2xl font-semibold">{viewMode ? 'View' : mode === 'create' ? 'Create' : 'Edit'} Weekly Matrix</h1><p className="text-muted-foreground">Week {period.weekNumber} · {period.year}</p></div>
        <Card><CardHeader><CardTitle>{department?.name ?? 'Matrix details'}</CardTitle></CardHeader><CardContent>
            <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                {mode === 'create' && <Field><FieldLabel>Sub-department</FieldLabel><select name="department_id" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.department_id} disabled={viewMode} onChange={(event) => form.setData('department_id', event.target.value)}>{availableSubDepartments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><FieldError>{form.errors.department_id}</FieldError></Field>}
                {(['key_activity', 'output', 'outcome', 'impact', 'indicators', 'baseline', 'target', 'means_of_verification'] as const).map((key) =>
                    <Field key={key}><FieldLabel htmlFor={key}>{key.replaceAll('_', ' ')}</FieldLabel><Textarea id={key} name={`${mode === 'create' ? 'entry' : 'entries[0]'}[${key}]`} required={key === 'key_activity'} rows={key === 'key_activity' ? 5 : 3} value={String(value[key] ?? '')} disabled={viewMode} style={viewMode ? { whiteSpace: 'pre-wrap' } : undefined} onChange={(event) => setValue(key, event.target.value)} /></Field>)}
                <div className="flex gap-2"><Button type="button" variant="outline" render={<a href={backUrl} />}>Back</Button>{!viewMode && <Button type="submit" disabled={form.processing} loading={form.processing}>Save matrix</Button>}</div>
            </form>
        </CardContent></Card>
    </div>;
}

WeeklyMatrixForm.layout = (page: ReactNode) => <AppLayout title="Weekly Matrix">{page}</AppLayout>;
