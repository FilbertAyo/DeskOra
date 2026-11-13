import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

export type Letter = {
    id: string;
    department_code: string;
    letter_date: string;
    name?: string | null;
    recipient: string;
    subject: string;
    salutation: string;
    body: string;
    closing: string;
    signatory_id: string;
    signature_mode: string;
};

export type LetterOptions = {
    departments: Record<string, string>;
    salutations: string[];
    closings: string[];
    signatories: Array<{ id: string; name: string; title?: string | null }>;
};

export default function LetterForm({ letter, options, actionUrl, method }: { letter?: Letter; options: LetterOptions; actionUrl: string; method: 'post' | 'put' }) {
    const form = useForm({
        department_code: letter?.department_code ?? '',
        letter_date: letter?.letter_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
        name: letter?.name ?? '',
        recipient: letter?.recipient ?? '',
        subject: letter?.subject ?? '',
        salutation: letter?.salutation ?? options.salutations[0] ?? '',
        body: letter?.body ?? '',
        closing: letter?.closing ?? options.closings[0] ?? '',
        signatory_id: letter?.signatory_id ?? '',
        signature_mode: letter?.signature_mode ?? 'digital',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form[method](actionUrl, { onSuccess: () => notify('success', letter ? 'Official letter updated.' : 'Official letter created.') });
    };
    return <Card><CardHeader><CardTitle>Letter information</CardTitle></CardHeader><CardContent>
        <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
            <Field label="Department" error={form.errors.department_code}><select name="department_code" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.department_code} onChange={(e) => form.setData('department_code', e.target.value)} required><option value="">Select department</option>{Object.entries(options.departments).map(([code, label]) => <option key={code} value={code}>{code} — {label}</option>)}</select></Field>
            <Field label="Letter date" error={form.errors.letter_date}><Input name="letter_date" type="date" value={form.data.letter_date} onChange={(e) => form.setData('letter_date', e.target.value)} required /></Field>
            <Field label="Letter name (portal only)" error={form.errors.name}><Input name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
            <Field label="Subject" error={form.errors.subject}><Input name="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} required /></Field>
            <Field label="Recipient" error={form.errors.recipient} wide><Textarea name="recipient" rows={4} value={form.data.recipient} onChange={(e) => form.setData('recipient', e.target.value)} required /></Field>
            <Field label="Salutation" error={form.errors.salutation}><select name="salutation" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.salutation} onChange={(e) => form.setData('salutation', e.target.value)}>{options.salutations.map((item) => <option key={item}>{item}</option>)}</select></Field>
            <Field label="Closing" error={form.errors.closing}><select name="closing" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.closing} onChange={(e) => form.setData('closing', e.target.value)}>{options.closings.map((item) => <option key={item}>{item}</option>)}</select></Field>
            <Field label="Body" error={form.errors.body} wide><Textarea name="body" rows={12} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required /></Field>
            <Field label="Signatory" error={form.errors.signatory_id}><select name="signatory_id" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.signatory_id} onChange={(e) => form.setData('signatory_id', e.target.value)} required><option value="">Select signatory</option>{options.signatories.map((user) => <option key={user.id} value={user.id}>{user.name}{user.title ? ` — ${user.title}` : ''}</option>)}</select></Field>
            <Field label="Signature mode" error={form.errors.signature_mode}><select name="signature_mode" className="border-input bg-background h-9 w-full rounded-md border px-3" value={form.data.signature_mode} onChange={(e) => form.setData('signature_mode', e.target.value)}><option value="digital">Digital signature</option><option value="manual">Manual signature</option></select></Field>
            <div className="md:col-span-2"><Button type="submit" disabled={form.processing} loading={form.processing}>{letter ? 'Save changes' : 'Create letter'}</Button></div>
        </form>
    </CardContent></Card>;
}

function Field({ label, error, wide, children }: { label: string; error?: string; wide?: boolean; children: React.ReactNode }) {
    return <label className={`space-y-1.5 ${wide ? 'md:col-span-2' : ''}`}><span className="text-sm font-medium">{label}</span>{children}{error && <span className="text-destructive block text-sm">{error}</span>}</label>;
}
