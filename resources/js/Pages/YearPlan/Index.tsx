import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Month = { number: number; label: string; activities: Array<{ code: string; department: string; description: string; total: string }> };
type Plan = { id: string; year: number; activitiesCount: number; showUrl: string; months: Month[] };
type Props = { plans: Plan[]; storeUrl: string; currentYear: number };

export default function YearPlanIndex({ plans, storeUrl, currentYear }: Props) {
    const form = useForm({ year: currentYear });
    return <div className="flex flex-col gap-6">
        <div><h1 className="text-2xl font-semibold">Year plans</h1><p className="text-muted-foreground text-sm">Annual activities, schedules and budgets.</p></div>
        <Card><CardHeader><CardTitle>Create year plan</CardTitle></CardHeader><CardContent><form className="flex items-end gap-3" onSubmit={(event) => { event.preventDefault(); form.post(storeUrl); }}><label className="grid gap-1 text-sm">Year<Input type="number" min={2000} max={2100} value={form.data.year} onChange={(e) => form.setData('year', Number(e.target.value))} /></label><Button type="submit" disabled={form.processing} loading={form.processing}>Create</Button><span className="text-destructive text-sm">{form.errors.year}</span></form></CardContent></Card>
        {plans.map((plan) => <Card key={plan.id}><CardHeader><CardTitle className="flex items-center justify-between"><a href={plan.showUrl}>Year Plan {plan.year}</a><span className="text-muted-foreground text-sm font-normal">{plan.activitiesCount} activities</span></CardTitle></CardHeader><CardContent>
            <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">{plan.months.map((month) => <div key={month.number} className="rounded-lg border p-3"><p className="font-medium">{month.label}</p><p className="text-muted-foreground text-sm">{month.activities.length} planned</p>{month.activities.slice(0, 2).map((activity) => <p key={`${activity.code}-${activity.description}`} className="mt-2 truncate text-xs" title={activity.description}>{activity.code} · {activity.description}</p>)}</div>)}</div>
        </CardContent></Card>)}
        {plans.length === 0 && <p className="text-muted-foreground py-10 text-center">No year plans have been created.</p>}
    </div>;
}

YearPlanIndex.layout = (page: ReactNode) => <AppLayout title="Year Plans">{page}</AppLayout>;
