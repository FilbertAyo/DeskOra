import { Link, useForm } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';

const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
] as const;

type Props = {
    mode: 'create' | 'edit';
    submitUrl: string;
    indexUrl: string;
    yearChoices: number[];
    existingReportMonthsByYear: Record<string, number[]>;
    defaultYear: number;
    defaultMonth: number | null;
    document?: {
        id?: string;
        title?: string;
        month?: number | null;
        year?: number | null;
        fileName?: string | null;
        downloadUrl?: string | null;
        canEdit?: boolean;
    } | null;
};

export default function MonthlyReportForm({
    mode,
    submitUrl,
    indexUrl,
    yearChoices,
    existingReportMonthsByYear,
    defaultYear,
    defaultMonth,
    document = null,
}: Props) {
    const lockedMonth = mode === 'edit' ? Number(document?.month ?? defaultMonth ?? 0) : null;
    const lockedYear = mode === 'edit' ? Number(document?.year ?? defaultYear) : null;

    const form = useForm<{
        type: string;
        year: string;
        month: string;
        file: File | null;
        action: string;
    }>({
        type: 'monthly_report',
        year: String(lockedYear ?? defaultYear),
        month: lockedMonth ? String(lockedMonth) : defaultMonth ? String(defaultMonth) : '',
        file: null,
        action: 'draft',
    });

    const availableMonths = useMemo(() => {
        if (mode === 'edit' && lockedMonth) {
            return [{ value: lockedMonth, label: MONTH_NAMES[lockedMonth - 1] ?? `Month ${lockedMonth}` }];
        }

        const yearKey = String(form.data.year);
        const taken = new Set((existingReportMonthsByYear[yearKey] ?? []).map(Number));

        return MONTH_NAMES.map((label, index) => {
            const value = index + 1;
            return { value, label, taken: taken.has(value) };
        }).filter((month) => !month.taken);
    }, [mode, lockedMonth, form.data.year, existingReportMonthsByYear]);

    const monthSelected = form.data.month !== '';
    const pageTitle =
        mode === 'edit'
            ? document?.title ?? 'Edit monthly report'
            : 'Create monthly report';

    const submit = (action: string) => {
        form.transform((data) => ({ ...data, action }));
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        };
        if (mode === 'edit') {
            form.put(submitUrl, options);
            return;
        }
        form.post(submitUrl, options);
    };

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6">
            <PageHeader
                title={pageTitle}
                description="Pick a month that has no report yet for the year plan, then upload the PDF."
                actions={
                    <BackButton href={indexUrl} />
                }
            />

            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Monthly report</CardTitle>
                    <CardDescription>
                        Only months without an existing report for this year plan are listed.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5 pt-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block space-y-1.5 text-sm">
                            <span className="font-medium">Year plan</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm disabled:opacity-70"
                                value={form.data.year}
                                disabled={mode === 'edit'}
                                onChange={(event) => {
                                    const nextYear = event.target.value;
                                    const taken = new Set(
                                        (existingReportMonthsByYear[nextYear] ?? []).map(Number),
                                    );
                                    const firstOpen = MONTH_NAMES.map((_, index) => index + 1).find(
                                        (month) => !taken.has(month),
                                    );
                                    form.setData((data) => ({
                                        ...data,
                                        year: nextYear,
                                        month: firstOpen ? String(firstOpen) : '',
                                        file: null,
                                    }));
                                }}
                            >
                                {yearChoices.length === 0 ? (
                                    <option value="">No year plans found</option>
                                ) : (
                                    yearChoices.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))
                                )}
                            </select>
                            {form.errors.year && <p className="text-destructive text-sm">{form.errors.year}</p>}
                        </label>

                        <label className="block space-y-1.5 text-sm">
                            <span className="font-medium">Month</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm disabled:opacity-70"
                                value={form.data.month}
                                disabled={mode === 'edit' || availableMonths.length === 0}
                                onChange={(event) => {
                                    form.setData((data) => ({
                                        ...data,
                                        month: event.target.value,
                                        file: null,
                                    }));
                                }}
                            >
                                <option value="">Select month</option>
                                {availableMonths.map((month) => (
                                    <option key={month.value} value={month.value}>
                                        {month.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.month && <p className="text-destructive text-sm">{form.errors.month}</p>}
                            {mode === 'create' && availableMonths.length === 0 && (
                                <p className="text-muted-foreground text-xs">
                                    Every month for this year already has a report.
                                </p>
                            )}
                        </label>
                    </div>

                    {monthSelected && (
                        <label className="block space-y-1.5 text-sm">
                            <span className="font-medium">Monthly report PDF</span>
                            <Input
                                type="file"
                                accept="application/pdf,.pdf"
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.file && <p className="text-destructive text-sm">{form.errors.file}</p>}
                            <p className="text-muted-foreground text-xs">PDF only, maximum 10 MB.</p>
                            {mode === 'edit' && document?.fileName && (
                                <p className="text-muted-foreground text-xs">
                                    Current file:{' '}
                                    {document.downloadUrl ? (
                                        <a
                                            href={document.downloadUrl}
                                            className="text-foreground underline underline-offset-2"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {document.fileName}
                                        </a>
                                    ) : (
                                        document.fileName
                                    )}
                                    . Upload a new PDF to replace it.
                                </p>
                            )}
                        </label>
                    )}
                </CardContent>
                <CardFooter className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-t">
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Cancel
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing || !monthSelected}
                            onClick={() => submit('draft')}
                        >
                            Save Draft
                        </Button>
                        <Button
                            type="button"
                            disabled={form.processing || !monthSelected}
                            onClick={() => submit('submit')}
                        >
                            Submit
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    );
}

MonthlyReportForm.layout = (page: ReactNode) => <AppLayout title="Monthly Report">{page}</AppLayout>;
