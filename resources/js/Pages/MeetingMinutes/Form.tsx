import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { notify } from '@/lib/notify';

type Props = { mode: 'create' | 'edit'; period: { id: string; weekNumber: number; year: number; dateRange: string }; submitUrl: string; backUrl: string };

export default function MeetingMinutesForm({ mode, period, submitUrl, backUrl }: Props) {
    const form = useForm<{ period_id: string; file: File | null }>({ period_id: period.id, file: null });
    const submit = () => {
        const options = { forceFormData: true, onSuccess: () => notify('success', `Meeting minutes ${mode === 'create' ? 'uploaded' : 'updated'}.`) };
        if (mode === 'create') form.post(submitUrl, options); else form.put(submitUrl, options);
    };
    return <div className="mx-auto flex max-w-2xl flex-col gap-6"><div><h1 className="text-2xl font-semibold">{mode === 'create' ? 'Upload' : 'Replace'} Meeting Minutes</h1><p className="text-muted-foreground">Week {period.weekNumber} of {period.year} · {period.dateRange}</p></div>
        <Card><CardHeader><CardTitle>Minutes PDF</CardTitle></CardHeader><CardContent><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); submit(); }}>
            <Field><FieldLabel htmlFor="file">PDF file</FieldLabel><Input id="file" name="file" type="file" accept="application/pdf,.pdf" required onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /><FieldError>{form.errors.file}</FieldError><p className="text-muted-foreground text-xs">PDF only, maximum 10 MB.</p></Field>
            <div className="flex gap-2"><Button type="button" variant="outline" render={<a href={backUrl} />}>Cancel</Button><Button type="submit" disabled={form.processing} loading={form.processing}>Upload minutes</Button></div>
        </form></CardContent></Card>
    </div>;
}

MeetingMinutesForm.layout = (page: ReactNode) => <AppLayout title="Meeting Minutes">{page}</AppLayout>;
