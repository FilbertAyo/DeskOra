import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';

export default function WorkPeriodsCreate({
    nextPeriod,
    storeUrl,
    indexUrl,
}: {
    nextPeriod: { year: number; weekNumber: number; weekStartDate: string };
    storeUrl: string;
    indexUrl: string;
}) {
    const form = useForm({
        year: nextPeriod.year,
        week_number: nextPeriod.weekNumber,
        week_start_date: nextPeriod.weekStartDate,
    });

    return (
        <div className="mx-auto flex max-w-xl flex-col gap-6">
            <PageHeader
                title="Create Work Period"
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardContent className="pt-6">
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeUrl);
                        }}
                    >
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Year</span>
                            <Input type="number" value={form.data.year} onChange={(e) => form.setData('year', Number(e.target.value))} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Week number</span>
                            <Input type="number" value={form.data.week_number} onChange={(e) => form.setData('week_number', Number(e.target.value))} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Week start date</span>
                            <Input type="date" value={form.data.week_start_date} onChange={(e) => form.setData('week_start_date', e.target.value)} />
                        </label>
                        <Button type="submit" disabled={form.processing} loading={form.processing}>Create
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

WorkPeriodsCreate.layout = (page: ReactNode) => <AppLayout title="Create Work Period">{page}</AppLayout>;
