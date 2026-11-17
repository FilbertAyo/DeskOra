import { useForm } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import AppLink from '@/components/shared/AppLink';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Month = {
    number: number;
    label: string;
    activities: Array<{ code: string; department: string; description: string; total: string }>;
};
type Plan = { id: string; year: number; activitiesCount: number; showUrl: string; months: Month[] };
type Props = { plans: Plan[]; storeUrl: string; currentYear: number };

export default function YearPlanIndex({ plans, storeUrl, currentYear }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm({ year: currentYear });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeUrl, {
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
                form.setData('year', currentYear);
            },
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-lg font-semibold">Year plans</h1>
                    <p className="text-muted-foreground text-sm">Annual activities, schedules and budgets.</p>
                </div>
                <Button type="button" onClick={() => setCreateOpen(true)}>
                    <PlusIcon />
                    Create year plan
                </Button>
            </div>

            {plans.map((plan) => (
                <Card key={plan.id}>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <AppLink href={plan.showUrl}>Year Plan {plan.year}</AppLink>
                            <span className="text-muted-foreground text-sm font-normal">
                                {plan.activitiesCount} activities
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                            {plan.months.map((month) => (
                                <div key={month.number} className="rounded-lg border p-3">
                                    <p className="font-medium">{month.label}</p>
                                    <p className="text-muted-foreground text-sm">{month.activities.length} planned</p>
                                    {month.activities.slice(0, 2).map((activity) => (
                                        <p
                                            key={`${activity.code}-${activity.description}`}
                                            className="mt-2 truncate text-xs"
                                            title={activity.description}
                                        >
                                            {activity.code} · {activity.description}
                                        </p>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            ))}

            {plans.length === 0 && (
                <p className="text-muted-foreground py-10 text-center">No year plans have been created.</p>
            )}

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create year plan</DialogTitle>
                        <DialogDescription>Start a new annual plan for a specific year.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        <div className="grid gap-2">
                            <Label htmlFor="year-plan-year">Year</Label>
                            <Input
                                id="year-plan-year"
                                type="number"
                                min={2000}
                                max={2100}
                                value={form.data.year}
                                onChange={(event) => form.setData('year', Number(event.target.value))}
                                required
                            />
                            {form.errors.year ? <p className="text-destructive text-sm">{form.errors.year}</p> : null}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Create
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

YearPlanIndex.layout = (page: ReactNode) => <AppLayout title="Year Plans">{page}</AppLayout>;
