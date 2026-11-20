import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader, PageShell } from '@/lib/page-kit';

type DocumentPayload = {
    id: string;
    title?: string;
    period?: { weekNumber?: number; year?: number; dateRange?: string } | null;
    targetAccomplished?: boolean | null;
    targetExplanation?: string | null;
    content?: Record<string, unknown>;
};

type Props = {
    document: DocumentPayload;
    relatedPlan?: { mainTarget?: string | null; state?: string | null } | null;
    content?: Record<string, unknown>;
    submitUrl: string;
    editUrl: string;
    indexUrl: string;
};

function resolveInitialStatus(document: DocumentPayload, content: Record<string, unknown>): string {
    const fromContent = String(content.target_accomplished_status ?? '').trim();
    if (['completed', 'partially_completed', 'not_completed'].includes(fromContent)) {
        return fromContent;
    }
    if (document.targetAccomplished === true) {
        return 'completed';
    }
    if (document.targetAccomplished === false) {
        return 'not_completed';
    }
    return '';
}

export default function WeeklyReportReview({
    document,
    relatedPlan = null,
    content = {},
    submitUrl,
    editUrl,
    indexUrl,
}: Props) {
    const mergedContent = { ...(document.content ?? {}), ...content };
    const form = useForm({
        action: 'submit',
        wizard_review: '1',
        target_accomplished_status: resolveInitialStatus(document, mergedContent),
        target_explanation: String(document.targetExplanation ?? ''),
    });

    const needsExplanation = ['partially_completed', 'not_completed'].includes(form.data.target_accomplished_status);

    const submit = () => {
        form.put(submitUrl);
    };

    return (
        <PageShell>
            <PageHeader
                title="Review & submit"
                description={
                    document.period
                        ? `Week ${document.period.weekNumber} of ${document.period.year} · ${document.period.dateRange ?? ''}`
                        : 'Confirm target status, then submit your weekly report.'
                }
            />

            <Card className="mx-auto max-w-2xl shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Main target status</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 pt-6">
                    <div className="bg-muted rounded-md border p-3 text-sm">
                        <p className="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wide">From weekly plan</p>
                        {relatedPlan?.mainTarget ? (
                            <p className="whitespace-pre-wrap font-medium">{relatedPlan.mainTarget}</p>
                        ) : (
                            <p className="text-muted-foreground">No main target was set on the related weekly plan.</p>
                        )}
                    </div>

                    <Field>
                        <FieldLabel>Was the main target accomplished?</FieldLabel>
                        <div className="grid gap-2">
                            {[
                                { value: 'completed', label: 'Completed' },
                                { value: 'partially_completed', label: 'Partially completed' },
                                { value: 'not_completed', label: 'Not completed' },
                            ].map((option) => (
                                <label
                                    key={option.value}
                                    className="hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="target_accomplished_status"
                                        value={option.value}
                                        checked={form.data.target_accomplished_status === option.value}
                                        onChange={() => form.setData('target_accomplished_status', option.value)}
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                        <FieldError>{form.errors.target_accomplished_status}</FieldError>
                    </Field>

                    {needsExplanation && (
                        <Field>
                            <FieldLabel htmlFor="target_explanation">Explanation</FieldLabel>
                            <Textarea
                                id="target_explanation"
                                rows={4}
                                value={form.data.target_explanation}
                                onChange={(event) => form.setData('target_explanation', event.target.value)}
                                placeholder="Explain why the target was not fully accomplished…"
                            />
                            <FieldError>{form.errors.target_explanation}</FieldError>
                        </Field>
                    )}
                    {form.errors.action && <FieldError>{form.errors.action}</FieldError>}
                </CardContent>
                <CardFooter className="flex flex-wrap justify-between gap-2 border-t">
                    <div className="flex gap-2">
                        <BackButton href={editUrl} />
                        <Button variant="ghost" render={<Link href={indexUrl} />}>
                            Cancel
                        </Button>
                    </div>
                    <Button
                        type="button"
                        disabled={form.processing || !form.data.target_accomplished_status}
                        loading={form.processing}
                        onClick={submit}
                    >
                        Submit report
                    </Button>
                </CardFooter>
            </Card>
        </PageShell>
    );
}

WeeklyReportReview.layout = (page: ReactNode) => <AppLayout title="Review weekly report">{page}</AppLayout>;
