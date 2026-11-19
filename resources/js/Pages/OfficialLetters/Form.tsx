import { Link, useForm } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';

import LetterBodyEditor from './LetterBodyEditor';

export type LetterSignatory = {
    id: string | number;
    name: string;
    title?: string | null;
};

export type OfficialLetterInput = {
    reference_number?: string | null;
    department_code?: string | null;
    letter_date?: string | null;
    name?: string | null;
    recipient?: string | null;
    subject?: string | null;
    salutation?: string | null;
    body?: string | null;
    closing?: string | null;
    signatory_id?: string | number | null;
    signature_mode?: string | null;
};

type LetterFormData = {
    department_code: string;
    letter_date: string;
    name: string;
    recipient: string;
    subject: string;
    salutation: string;
    body: string;
    closing: string;
    signatory_id: string;
    signature_mode: 'digital' | 'manual';
};

const selectClassName =
    'border-input bg-background text-foreground h-9 w-full rounded-md border px-2.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-3 aria-invalid:border-destructive [color-scheme:light] dark:[color-scheme:dark]';

export default function LetterForm({
    letter,
    departments,
    salutations,
    closings,
    signatories,
    actionUrl,
    method,
    cancelUrl,
}: {
    letter?: OfficialLetterInput;
    departments: Record<string, string>;
    salutations: string[];
    closings: string[];
    signatories: LetterSignatory[];
    actionUrl: string;
    method: 'post' | 'put';
    cancelUrl: string;
}) {
    const isEdit = Boolean(letter);
    const form = useForm<LetterFormData>({
        department_code: letter?.department_code ?? '',
        letter_date: dateInputValue(letter?.letter_date, !isEdit),
        name: letter?.name ?? '',
        recipient: letter?.recipient ?? '',
        subject: letter?.subject ?? '',
        salutation: preferredOption(salutations, 'Dear Sir/Madam,', letter?.salutation),
        body: letter?.body ?? '',
        closing: preferredOption(closings, 'In service,', letter?.closing),
        signatory_id: letter?.signatory_id ? String(letter.signatory_id) : '',
        signature_mode: letter?.signature_mode === 'manual' ? 'manual' : 'digital',
    });

    const selectedSignatory = signatories.find((signatory) => String(signatory.id) === form.data.signatory_id);
    const errors = Object.values(form.errors).filter((message): message is string => Boolean(message));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form[method](actionUrl);
    };

    return (
        <Card className="w-full shadow-none">
            <CardHeader className="border-b">
                <CardTitle>{isEdit ? 'Edit Letter' : 'Letter Details'}</CardTitle>
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent className="space-y-5">
                    {errors.length > 0 && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                <p className="text-foreground font-medium">Please fix the following errors:</p>
                                <ul className="mt-2 list-disc pl-5">
                                    {errors.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}

                    {isEdit ? (
                        <Field label="Reference Number" hint="Read-only. Changing department or letter month will allocate a new reference automatically.">
                            <Input value={letter?.reference_number ?? ''} readOnly className="bg-muted" />
                        </Field>
                    ) : (
                        <Alert>
                            <InfoIcon />
                            <AlertDescription>
                                The reference number is generated automatically from the department and letter date after you save. It cannot be edited manually.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Field
                        label="Letter name"
                        error={form.errors.name}
                        hint="Used only in the portal so you can tell letters apart when they share the same subject. It does not appear on the downloaded letter."
                    >
                        <Input
                            name="name"
                            value={form.data.name}
                            maxLength={255}
                            placeholder="e.g. Mwanza follow-up, Kigoma round 2"
                            aria-invalid={Boolean(form.errors.name)}
                            onChange={(event) => form.setData('name', event.target.value)}
                        />
                    </Field>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Department" required error={form.errors.department_code}>
                            <select
                                name="department_code"
                                className={selectClassName}
                                value={form.data.department_code}
                                aria-invalid={Boolean(form.errors.department_code)}
                                required
                                onChange={(event) => form.setData('department_code', event.target.value)}
                            >
                                {!isEdit && <option value="">Select department</option>}
                                {Object.entries(departments).map(([code, label]) => (
                                    <option key={code} value={code}>
                                        {code} — {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Letter Date" required error={form.errors.letter_date}>
                            <Input
                                name="letter_date"
                                type="date"
                                value={form.data.letter_date}
                                aria-invalid={Boolean(form.errors.letter_date)}
                                required
                                onChange={(event) => form.setData('letter_date', event.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Recipient" required error={form.errors.recipient} hint="Entered in uppercase.">
                        <Textarea
                            name="recipient"
                            rows={4}
                            required
                            value={form.data.recipient}
                            placeholder={'ORGANIZATION / TITLE\nADDRESS LINE\nCITY'}
                            className="min-h-28 uppercase"
                            aria-invalid={Boolean(form.errors.recipient)}
                            onChange={(event) => applyUppercase(event, (value) => form.setData('recipient', value))}
                        />
                    </Field>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Salutation" required error={form.errors.salutation}>
                            <select
                                name="salutation"
                                className={selectClassName}
                                value={form.data.salutation}
                                aria-invalid={Boolean(form.errors.salutation)}
                                required
                                onChange={(event) => form.setData('salutation', event.target.value)}
                            >
                                {salutations.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Closing" required error={form.errors.closing}>
                            <select
                                name="closing"
                                className={selectClassName}
                                value={form.data.closing}
                                aria-invalid={Boolean(form.errors.closing)}
                                required
                                onChange={(event) => form.setData('closing', event.target.value)}
                            >
                                {closings.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <Field label="Subject (RE:)" required error={form.errors.subject} hint="Entered in uppercase.">
                        <Input
                            name="subject"
                            required
                            value={form.data.subject}
                            className="uppercase"
                            aria-invalid={Boolean(form.errors.subject)}
                            onChange={(event) => applyUppercase(event, (value) => form.setData('subject', value))}
                        />
                    </Field>

                    <Field label="Body" required error={form.errors.body} hint="Formatting is preserved as written. Font: Calibri.">
                        <LetterBodyEditor
                            value={form.data.body}
                            invalid={Boolean(form.errors.body)}
                            onChange={(html) => form.setData('body', html)}
                        />
                    </Field>

                    <Field label="Signatory" required error={form.errors.signatory_id} hint="Name and title are taken from the selected user’s profile.">
                        <select
                            name="signatory_id"
                            className={selectClassName}
                            value={form.data.signatory_id}
                            aria-invalid={Boolean(form.errors.signatory_id)}
                            required
                            onChange={(event) => form.setData('signatory_id', event.target.value)}
                        >
                            <option value="">Select signatory</option>
                            {signatories.map((signatory) => (
                                <option key={signatory.id} value={String(signatory.id)}>
                                    {signatory.name}
                                    {signatory.title ? ` — ${signatory.title}` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Signatory Name">
                            <Input value={selectedSignatory?.name ?? ''} readOnly className="bg-muted" />
                        </Field>
                        <Field label="Signatory Title">
                            <Input value={selectedSignatory?.title ?? ''} readOnly className="bg-muted" />
                        </Field>
                    </div>

                    <Field label="Signature Type" required error={form.errors.signature_mode}>
                        <RadioGroup
                            value={form.data.signature_mode}
                            onValueChange={(value) => form.setData('signature_mode', value === 'manual' ? 'manual' : 'digital')}
                            className="gap-3"
                        >
                            <div className="flex items-start gap-2">
                                <RadioGroupItem value="digital" id="signature_mode_digital" className="mt-0.5" />
                                <Label htmlFor="signature_mode_digital" className="items-start font-normal">
                                    <span>
                                        Digital signature
                                        <span className="text-muted-foreground block text-xs font-normal">
                                            Use the selected staff member’s uploaded signature on the PDF.
                                        </span>
                                    </span>
                                </Label>
                            </div>
                            <div className="flex items-start gap-2">
                                <RadioGroupItem value="manual" id="signature_mode_manual" className="mt-0.5" />
                                <Label htmlFor="signature_mode_manual" className="items-start font-normal">
                                    <span>
                                        Manual signature
                                        <span className="text-muted-foreground block text-xs font-normal">
                                            Leave a blank signature line so it can be signed by hand after printing.
                                        </span>
                                    </span>
                                </Label>
                            </div>
                        </RadioGroup>
                    </Field>
                </CardContent>
                <CardFooter className="gap-2 border-t">
                    <Button type="submit" disabled={form.processing} loading={form.processing}>
                        {isEdit ? 'Save Changes' : 'Create Letter'}
                    </Button>
                    <Button variant="outline" render={<Link href={cancelUrl} />}>
                        Cancel
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function Field({
    label,
    required,
    error,
    hint,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <span className="block text-sm font-medium">
                {label}
                {required && <span className="text-destructive"> *</span>}
            </span>
            {children}
            {hint && <span className="text-muted-foreground block text-xs">{hint}</span>}
            {error && <span className="text-destructive block text-sm">{error}</span>}
        </div>
    );
}

function preferredOption(options: string[], fallback: string, current?: string | null): string {
    if (current && options.includes(current)) {
        return current;
    }
    if (options.includes(fallback)) {
        return fallback;
    }

    return options[0] ?? '';
}

function dateInputValue(value: string | null | undefined, useToday: boolean): string {
    if (value) {
        return value.slice(0, 10);
    }
    if (!useToday) {
        return '';
    }

    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);

    return local.toISOString().slice(0, 10);
}

function applyUppercase(event: FormEvent<HTMLInputElement | HTMLTextAreaElement>, set: (value: string) => void) {
    const element = event.currentTarget;
    const start = element.selectionStart;
    const end = element.selectionEnd;
    set(element.value.toLocaleUpperCase('en-US'));
    requestAnimationFrame(() => {
        if (typeof start === 'number' && typeof end === 'number') {
            element.setSelectionRange(start, end);
        }
    });
}
