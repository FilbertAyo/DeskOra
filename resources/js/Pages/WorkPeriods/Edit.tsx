import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';

export default function WorkPeriodsEdit({
    period,
    updateUrl,
    indexUrl,
}: {
    period: {
        weekNumber: number;
        year: number;
        weekStartDate: string;
        planDeadlineDate: string;
        reportDeadlineDate: string;
        status: string;
    };
    updateUrl: string;
    indexUrl: string;
}) {
    const form = useForm({
        week_start_date: period.weekStartDate,
        plan_deadline: period.planDeadlineDate,
        report_deadline: period.reportDeadlineDate,
        status: period.status,
    });

    return (
        <div className="mx-auto flex max-w-xl flex-col gap-6">
            <PageHeader
                title={`Edit Week ${period.weekNumber}, ${period.year}`}
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardContent className="space-y-3 pt-6">
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(updateUrl);
                        }}
                    >
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Week start date</span>
                            <Input type="date" value={form.data.week_start_date} onChange={(e) => form.setData('week_start_date', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Plan deadline</span>
                            <Input type="date" value={form.data.plan_deadline} onChange={(e) => form.setData('plan_deadline', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Report deadline</span>
                            <Input type="date" value={form.data.report_deadline} onChange={(e) => form.setData('report_deadline', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Status</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-2"
                                value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}
                            >
                                <option value="open">open</option>
                                <option value="closed">closed</option>
                                <option value="archived">archived</option>
                            </select>
                        </label>
                        <Button type="submit" disabled={form.processing} loading={form.processing}>Save
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

WorkPeriodsEdit.layout = (page: ReactNode) => <AppLayout title="Edit Work Period">{page}</AppLayout>;
