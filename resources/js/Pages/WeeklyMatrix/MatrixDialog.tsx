import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

export type MatrixEntry = {
    key_activity: string;
    output: string;
    outcome: string;
    impact: string;
    indicators: string;
    baseline: string;
    target: string;
    means_of_verification: string;
    year_plan_activity_ids: string[];
};

export type MatrixSubDepartment = {
    id: string;
    name: string;
    parentName?: string | null;
};

export type MatrixDialogMode = 'create' | 'edit' | 'view';

const PRIMARY_FIELD = 'key_activity' as const;

const GRID_FIELDS = [
    'output',
    'outcome',
    'impact',
    'indicators',
    'baseline',
    'target',
    'means_of_verification',
] as const;

const ENTRY_FIELDS = [PRIMARY_FIELD, ...GRID_FIELDS] as const;

const emptyEntry = (): MatrixEntry => ({
    key_activity: '',
    output: '',
    outcome: '',
    impact: '',
    indicators: '',
    baseline: '',
    target: '',
    means_of_verification: '',
    year_plan_activity_ids: [],
});

type Props = {
    open: boolean;
    mode: MatrixDialogMode;
    onOpenChange: (open: boolean) => void;
    periodLabel: string;
    availableSubDepartments?: MatrixSubDepartment[];
    department?: MatrixSubDepartment | null;
    entry?: MatrixEntry | null;
    createSubmitUrl: string;
    updateUrl?: string | null;
};

function fieldLabel(key: (typeof ENTRY_FIELDS)[number]): string {
    return key.replaceAll('_', ' ');
}

export default function MatrixDialog({
    open,
    mode,
    onOpenChange,
    periodLabel,
    availableSubDepartments = [],
    department = null,
    entry = null,
    createSubmitUrl,
    updateUrl = null,
}: Props) {
    const isCreate = mode === 'create';
    const isView = mode === 'view';
    const form = useForm({
        department_id: department?.id ?? availableSubDepartments[0]?.id ?? '',
        entry: { ...emptyEntry(), ...(entry ?? {}) },
        entries: [{ ...emptyEntry(), ...(entry ?? {}) }],
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            department_id: department?.id ?? availableSubDepartments[0]?.id ?? '',
            entry: { ...emptyEntry(), ...(entry ?? {}) },
            entries: [{ ...emptyEntry(), ...(entry ?? {}) }],
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when dialog opens with new context
    }, [open, mode, department?.id, entry, availableSubDepartments]);

    const value = isCreate ? form.data.entry : form.data.entries[0];

    const setValue = (key: (typeof ENTRY_FIELDS)[number], next: string) => {
        if (isCreate) {
            form.setData('entry', { ...form.data.entry, [key]: next });
            return;
        }
        form.setData('entries', [{ ...form.data.entries[0], [key]: next }]);
    };

    const title = isView ? 'View weekly matrix' : isCreate ? 'Create weekly matrix' : 'Edit weekly matrix';
    const departmentLabel = department
        ? [department.parentName, department.name].filter(Boolean).join(' · ')
        : 'Matrix details';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (isView) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                notify('success', isCreate ? 'Weekly matrix created.' : 'Weekly matrix updated.');
                onOpenChange(false);
            },
        };

        if (isCreate) {
            form.post(createSubmitUrl, options);
            return;
        }

        if (!updateUrl) {
            return;
        }

        form.put(updateUrl, options);
    };

    const renderField = (key: (typeof ENTRY_FIELDS)[number], rows: number) => (
        <Field key={key}>
            <FieldLabel htmlFor={key}>{fieldLabel(key)}</FieldLabel>
            <Textarea
                id={key}
                name={isCreate ? `entry[${key}]` : `entries[0][${key}]`}
                required={key === PRIMARY_FIELD}
                rows={rows}
                value={String(value[key] ?? '')}
                disabled={isView}
                className={isView ? 'whitespace-pre-wrap' : undefined}
                onChange={(event) => setValue(key, event.target.value)}
            />
            <FieldError>
                {
                    (form.errors as Record<string, string | undefined>)[
                        isCreate ? `entry.${key}` : `entries.0.${key}`
                    ]
                }
            </FieldError>
        </Field>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-5xl lg:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {periodLabel}
                        {!isCreate ? ` · ${departmentLabel}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={submit}>
                    {isCreate && (
                        <Field>
                            <FieldLabel htmlFor="department_id">Sub-department</FieldLabel>
                            <select
                                id="department_id"
                                name="department_id"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.department_id}
                                onChange={(event) => form.setData('department_id', event.target.value)}
                            >
                                {availableSubDepartments.length === 0 ? (
                                    <option value="">No open sub-departments</option>
                                ) : (
                                    availableSubDepartments.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.parentName ? `${item.parentName} · ${item.name}` : item.name}
                                        </option>
                                    ))
                                )}
                            </select>
                            <FieldError>{form.errors.department_id}</FieldError>
                        </Field>
                    )}

                    {renderField(PRIMARY_FIELD, 4)}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {GRID_FIELDS.map((key) => renderField(key, 3))}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {isView ? 'Close' : 'Cancel'}
                        </Button>
                        {!isView && (
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Save matrix
                            </Button>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
